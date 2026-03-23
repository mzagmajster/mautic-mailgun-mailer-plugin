<?php

namespace MauticPlugin\MauticMailgunMailerBundle\Mailer\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email as MauticEmailEntity;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticMailgunMailerBundle\Service\AccountProviderService;
use MauticPlugin\MauticMailgunMailerBundle\Service\ApiLogService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Matic Zagmajster
 */
class MailgunApiTransport extends AbstractApiTransport implements TokenTransportInterface
{
    use TokenTransportTrait;

    public const HOST = 'api.eu.mailgun.net';

    public const MAUTIC_HEADERS_TO_BYPASS = [
        'from',
        'to',
        'reply-to',
        'cc',
        'bcc',
        'subject',
        'content-type',
    ];

    /**
     * In Mautic 7, this is not required anymore, but we keep it here so
     * we can use the same codebase for Mautic5.
     */
    public const MAUTIC_TEMP_FROM_NAME_HEADER = 'MGTR-From-Name';

    private $logger;
    private $key;
    private $domain;
    private $region;
    private $maxBatchLimit;
    private $callbackUrl;
    private $webhookSigningKey;
    private $accountProviderService;
    private $mauticTransportOptions;
    private EntityManagerInterface $entityManager;
    private bool $logApiRequests;
    private ?ApiLogService $apiLogService;

    public function __construct(
        string $host = '',
        string $key = '',
        string $domain = '',
        int $maxBatchLimit = 0,
        string $callbackUrl = '',
        string $webhookSigningKey = '',
        ?AccountProviderService $accountProviderService = null,
        ?EntityManagerInterface $entityManager = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?HttpClientInterface $client = null,
        ?LoggerInterface $logger = null,
        bool $logApiRequests = false,
        ?ApiLogService $apiLogService = null,
    ) {
        $this->host                   = $host;
        $this->key                    = $key;
        $this->domain                 = $domain;
        $this->maxBatchLimit          = $maxBatchLimit;
        $this->callbackUrl            = $callbackUrl;
        $this->webhookSigningKey      = $webhookSigningKey;
        $this->accountProviderService = $accountProviderService;
        $this->entityManager          = $entityManager;
        $this->logApiRequests         = $logApiRequests;
        $this->apiLogService          = $apiLogService;
        $this->mauticTransportOptions = [
            'o:testmode' => 'no',
            'o:tracking' => 'no',
        ];

        $this->region = 'eu';
        if ('api.mailgun.net' == $this->host) {
            $this->region = 'us';
        }

        $this->logger = $logger;
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf(
            'mautic+mailgun+api://%s?domain=%s',
            $this->getEndpoint(),
            $this->getDomain()
        );
    }

    public function getKey(): string
    {
        if (null !== $this->accountProviderService->getAccount()) {
            return $this->accountProviderService->getAccount()->getApiKey();
        }

        return $this->key;
    }

    public function getDomain(): string
    {
        if (null !== $this->accountProviderService->getAccount()) {
            return $this->accountProviderService->getAccount()->getSendingDomain();
        }

        return $this->domain;
    }

    public function getRegion(): ?string
    {
        if (null !== $this->accountProviderService->getAccount()) {
            return $this->accountProviderService->getAccount()->getRegion();
        }

        return $this->region;
    }

    private function getEndpoint(): ?string
    {
        switch ($this->getRegion()) {
            case 'eu':
                return 'api.eu.mailgun.net';
            case 'us':
                return 'api.mailgun.net';
        }

        return $this->host;
    }

    public function getMaxBatchLimit(): int
    {
        return $this->maxBatchLimit;
    }

    private function mauticGetAttachments(MauticMessage $email, ?string $html): array
    {
        $attachments = $inlines = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers  = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                if ($html) {
                    $new  = basename($filename);
                    $html = str_replace('cid:'.$filename, 'cid:'.$new, $html);

                    $reflection = new \ReflectionProperty($attachment, 'filename');
                    $reflection->setAccessible(true);
                    $reflection->setValue($attachment, $new);
                }

                $inlines[] = curl_file_create(
                    $attachment->getBody(),
                    $attachment->getContentType(),
                    $filename
                );
            } else {
                $attachments[] = curl_file_create(
                    $attachment->getBody(),
                    $attachment->getContentType(),
                    $filename
                );
            }
        }

        return [$attachments, $inlines, $html];
    }

    private function mauticStringifyAddresses(array $addresses): string
    {
        if (!count($addresses)) {
            return '';
        }

        $stringAddresses = [];
        foreach ($addresses as $address) {
            $stringAddresses[] = $address->toString();
        }

        return implode(',', $stringAddresses);
    }

    private function mauticGetRecipientData(SentMessage $sentMessage): \Generator
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $metadata = $email->getMetadata();
        foreach ($metadata as $emailAddress => $meta) {
            yield [
                'emailTo' => $emailAddress,
                'meta'    => $meta,
            ];
        }
    }

    private function mauticIsSendingTestMessage(SentMessage $sentMessage): bool
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $metadata = $email->getMetadata();

        return !(bool) count($metadata);
    }

    private function mauticComposeFromAddressObject(SentMessage $sentMessage): Address
    {
        $email          = $sentMessage->getOriginalMessage();
        $orgFromAddress = $email->getFrom()[0];

        $fromName = $email->getHeaders()->getHeaderBody(self::MAUTIC_TEMP_FROM_NAME_HEADER);
        if (null === $fromName) {
            return $orgFromAddress;
        }

        return new Address(
            $orgFromAddress->getAddress(),
            $fromName
        );
    }

    private function mauticMaskLongLog(array $payload): array
    {
        if (isset($payload['html'])) {
            $payload['html'] = '<masked>';
        }

        if (isset($payload['text'])) {
            $payload['text'] = '<masked>';
        }

        return $payload;
    }

    private function mauticGetTestMessagePayload(SentMessage $sentMessage): array
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $toList      = null;
        $fromList    = null;
        $replyToList = null;
        $ccList      = null;
        $bccList     = null;
        $subject     = null;

        $text    = $email->getTextBody();
        $html    = $email->getHtmlBody();
        $headers = $email->getHeaders();

        foreach ($headers->all() as $name => $header) {
            switch (strtolower($name)) {
                case 'from':
                    $fromList = $header->getAddresses();
                    break;
                case 'to':
                    $toList = $header->getAddresses();
                    break;
                case 'reply-to':
                    $replyToList = $header->getAddresses();
                    break;
                case 'cc':
                    $ccList = $header->getAddresses();
                    break;
                case 'bcc':
                    $bccList = $header->getAddresses();
                    break;
                case 'subject':
                    $subject = $header->getValue();
                    break;
                default:
                    break;
            }
        }

        $oHeaders = [
            'o:testmode' => $this->mauticTransportOptions['o:testmode'],
            'o:tracking' => $this->mauticTransportOptions['o:tracking'],
        ];

        $vHeaders = [];
        $tHeaders = [];
        $hHeaders = [];

        foreach ($headers->all() as $name => $header) {
            if (\in_array(strtolower($name), self::MAUTIC_HEADERS_TO_BYPASS)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $oHeaders['o:tag'] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $vHeaderKey            = 'v:'.$header->getKey();
                $vHeaders[$vHeaderKey] = $header->getValue();
                continue;
            }

            $prefix = substr($name, 0, 2);
            switch ($prefix) {
                case 'o:':
                    $oHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 'v:':
                    $vHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 't:':
                    $tHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 'h:':
                    $hHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                default:
                    $headerName            = 'h:'.$header->getName();
                    $hHeaders[$headerName] = $header->getBodyAsString();
            }
        }

        return array_merge(
            [
                'from'     => $this->mauticStringifyAddresses($fromList ?? []),
                'to'       => $this->mauticStringifyAddresses($toList ?? []),
                'reply_to' => [],
                'cc'       => [],
                'bcc'      => [],
                'subject'  => $subject,
                'text'     => $text,
                'html'     => $html,
            ],
            $oHeaders,
            $vHeaders,
            $tHeaders,
            $hHeaders,
        );
    }

    private function mauticGetFromEmail(SentMessage $sentMessage): string
    {
        $email     = $sentMessage->getOriginalMessage();
        $fromArray = $email->getFrom();

        return count($fromArray) ? current($fromArray)->getAddress() : '';
    }

    private function replaceMauticTokens(?string $messageContent, array $tokens): string
    {
        if (null === $messageContent) {
            return '';
        }

        foreach ($tokens as $token => $value) {
            $messageContent = str_replace($token, $value, $messageContent);
        }

        return $messageContent;
    }

    private function mauticReadjustHeaders(SentMessage $sentMessage, Address $fixedFromAddress): SentMessage
    {
        $sentMessage->getOriginalMessage()
            ->getHeaders()
            ->remove(self::MAUTIC_TEMP_FROM_NAME_HEADER);
        $sentMessage->getOriginalMessage()->from($fixedFromAddress);

        return $sentMessage;
    }

    /**
     * Resolve reply-to per recipient using the following priority:
     *
     * 1. Email entity's explicit reply-to address setting
     * 2. Lead owner's email (when "use owner as mailer" is enabled)
     * 3. Whatever MailHelper stamped on the shared message (global config fallback)
     */
    private function mauticGetReplyTo(MauticMessage $email, array $recipientMeta): string
    {
        $emailId = $recipientMeta['meta']['emailId'] ?? null;

        if (null !== $emailId && null !== $this->entityManager) {
            $emailEntity = $this->entityManager
                ->getRepository(MauticEmailEntity::class)
                ->find($emailId);

            if ($emailEntity) {
                // 1. Explicit reply-to set on the email entity in Mautic UI
                $entityReplyTo = $emailEntity->getReplyToAddress();
                if (!empty($entityReplyTo)) {
                    $this->logger->debug('mauticGetReplyTo: using email entity reply-to', [
                        'emailId' => $emailId,
                        'replyTo' => $entityReplyTo,
                    ]);

                    return $entityReplyTo;
                }

                // 2. Owner as mailer — use the lead owner's email as reply-to
                if ($emailEntity->getUseOwnerAsMailer()) {
                    $ownerId = $recipientMeta['meta']['tokens']['{ownerid}']
                        ?? $recipientMeta['meta']['tokens']['{leadfield=owner_id}']
                        ?? null;

                    if (null !== $ownerId && null !== $this->entityManager) {
                        $owner = $this->entityManager
                            ->getRepository(User::class)
                            ->find((int) $ownerId);

                        if ($owner && $owner->getEmail()) {
                            $ownerEmail = $owner->getName()
                                ? sprintf('%s <%s>', $owner->getName(), $owner->getEmail())
                                : $owner->getEmail();

                            $this->logger->debug('mauticGetReplyTo: using owner email as reply-to', [
                                'ownerId'  => $ownerId,
                                'replyTo'  => $ownerEmail,
                            ]);

                            return $ownerEmail;
                        }
                    }
                }
            }
        }

        // 3. Fall back to whatever MailHelper stamped on the shared message
        $replyTo = $email->getReplyTo();
        if (!empty($replyTo)) {
            $fallback = $this->mauticStringifyAddresses($replyTo);
            $this->logger->debug('mauticGetReplyTo: falling back to message reply-to', [
                'replyTo' => $fallback,
            ]);

            return $fallback;
        }

        return '';
    }

    private function mauticGetPayload(SentMessage $sentMessage, array $recipientMeta): array
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $recipientName = $recipientMeta['meta']['name'] ?? '';
        $addressTo     = new Address(
            $recipientMeta['emailTo'],
            $recipientName
        );

        $substitutions = $recipientMeta['meta']['tokens'] ?? [];

        $text    = $email->getTextBody();
        $html    = $email->getHtmlBody();
        $headers = $email->getHeaders();

        $oHeaders = [
            'o:testmode' => $this->mauticTransportOptions['o:testmode'],
            'o:tracking' => $this->mauticTransportOptions['o:tracking'],
        ];

        $vHeaders = [];
        $tHeaders = [];
        $hHeaders = [];

        [$attachments, $inlines, $html] = $this->mauticGetAttachments($email, $html);

        foreach ($headers->all() as $name => $header) {
            if (\in_array(strtolower($name), self::MAUTIC_HEADERS_TO_BYPASS)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $oHeaders['o:tag'] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $vHeaderKey            = 'v:'.$header->getKey();
                $vHeaders[$vHeaderKey] = $header->getValue();
                continue;
            }

            // Rebuild List-Unsubscribe per-recipient from token instead of
            // using the pre-resolved value stamped on the shared message.
            if ('list-unsubscribe' === strtolower($header->getName())) {
                if (!empty($substitutions['{unsubscribe_url}'])) {
                    $hHeaders['h:List-Unsubscribe']      = '<'.$substitutions['{unsubscribe_url}'].'>';
                    $hHeaders['h:List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
                }
                continue;
            }

            // Skip — already handled above via unsubscribe_url token
            if ('list-unsubscribe-post' === strtolower($header->getName())) {
                continue;
            }

            $prefix = substr($name, 0, 2);
            switch ($prefix) {
                case 'o:':
                    $oHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 'v:':
                    $vHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 't:':
                    $tHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 'h:':
                    $hHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                default:
                    $headerName            = 'h:'.$header->getName();
                    $hHeaders[$headerName] = $header->getBodyAsString();
            }
        }

        return array_merge(
            [
                'from'         => $this->mauticStringifyAddresses($email->getFrom()),
                'to'           => $this->mauticStringifyAddresses([$addressTo]),
                'h:Reply-To'   => $this->mauticGetReplyTo($email, $recipientMeta),
                'cc'           => $this->mauticStringifyAddresses($email->getCc()),
                'bcc'          => $this->mauticStringifyAddresses($email->getBcc()),
                'subject'      => $email->getSubject(),
                'text'         => $this->replaceMauticTokens($text, $substitutions),
                'html'         => $this->replaceMauticTokens($html, $substitutions),
                'attachment'   => $attachments,
                'inline'       => $inlines,
                'callback_url' => $this->callbackUrl,
            ],
            $oHeaders,
            $vHeaders,
            $tHeaders,
            $hHeaders,
        );
    }

    private function mauticGetApiResponse(array $payload): ResponseInterface
    {
        $endpoint = sprintf(
            '%s/v3/%s/messages',
            $this->getEndpoint(),
            urlencode($this->getDomain())
        );

        $this->logger->debug(
            'Sending payload',
            [
                'endpoint' => $endpoint,
                'payload'  => $this->mauticMaskLongLog($payload),
            ]
        );

        $response = $this->client->request(
            'POST',
            'https://'.$endpoint,
            [
                'auth_basic' => 'api:'.$this->getKey(),
                'headers'    => ['Content-Type: application/x-www-form-urlencoded'],
                'body'       => $payload,
            ]
        );

        if ($this->logApiRequests && $this->apiLogService) {
            $this->apiLogService->logRequest(
                'https://'.$endpoint,
                $this->mauticMaskLongLog($payload),
                $response->getStatusCode(),
                $response->getContent(false)
            );
        }

        return $response;
    }

    private function mauticHandleError(ResponseInterface $response): void
    {
        if (200 === $response->getStatusCode()) {
            return;
        }

        $data = json_decode($response->getContent(false), true);

        $this->logger->error('MailgunApiTransport error response', [
            'data'               => $data,
            'responseContent'    => $response->getContent(false),
            'responseStatusCode' => $response->getStatusCode(),
        ]);

        throw new HttpTransportException('Error returned by API', $response, $response->getStatusCode());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = null;
        try {
            $sendingTestMessage = $this->mauticIsSendingTestMessage($sentMessage);
            if ($sendingTestMessage) {
                $payload  = $this->mauticGetTestMessagePayload($sentMessage);
                $response = $this->mauticGetApiResponse($payload);
                $this->mauticHandleError($response);

                return $response;
            }

            $fromEmail = $this->mauticGetFromEmail($sentMessage);
            $this->accountProviderService->selectAccount($fromEmail);
            $this->logger->debug(
                'Account selected for sending %account%',
                ['account' => $this->accountProviderService, 'fromEmail' => $fromEmail]
            );

            $recipientsMeta   = $this->mauticGetRecipientData($sentMessage);
            $fixedFromAddress = $this->mauticComposeFromAddressObject($sentMessage);
            $sentMessage      = $this->mauticReadjustHeaders($sentMessage, $fixedFromAddress);

            foreach ($recipientsMeta as $recipientMeta) {
                $payload  = $this->mauticGetPayload($sentMessage, $recipientMeta);
                $response = $this->mauticGetApiResponse($payload);
                $this->mauticHandleError($response);
            }

            return $response;
        } catch (\Exception $e) {
            throw new TransportException($e->getMessage());
        }
    }
}
