<?php

namespace MauticPlugin\MauticMailgunMailerBundle\Mailer\Transport;

use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
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
     * @var LoggerInterface
     */
    private $logger;

    // Configuration

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $region;

    /**
     * @var int
     */
    private $maxBatchLimit;

    /**
     * @var string
     */
    private $callbackUrl;

    /**
     * @var string
     */
    private $webhookSigningKey;

    /**
     * @var array
     */
    private $accounts;

    /**
     * This is actually selected account domain.
     *
     * @var array
     * */
    private $selectedAccount;

    /**
     * @var string|null
     * */
    private $accountDomain;

    /**
     * @var array
     */
    private $mauticTransportOptions;

    private function selectAccount($email)
    {
        $email = strtolower($email);
        $parts = explode('@', $email);

        // $parts[1] should contain top level domain.
        $this->accountDomain = $parts[1];
        if (isset($this->accounts[$this->accountDomain])) {
            $this->selectedAccount = $this->accounts[$this->accountDomain];
        } else {
            // Config not found, which means we will use default config.
            $this->accountDomain   = null;
            $this->selectedAccount = [];
        }

        return $this;
    }

    private function isAccountSelected()
    {
        return null !== $this->accountDomain;
    }

    public function __construct(
        string $host = '',
        string $key = '',
        string $domain = '',
        int $maxBatchLimit = 0,
        string $callbackUrl = '',
        string $webhookSigningKey = '',
        array $accounts = [],
        EventDispatcherInterface $dispatcher = null,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null,
    ) {
        $this->host                   = $host;
        $this->key                    = $key;
        $this->domain                 = $domain;
        $this->maxBatchLimit          = $maxBatchLimit;
        $this->callbackUrl            = $callbackUrl;
        $this->webhookSigningKey      = $webhookSigningKey;
        $this->accounts               = $accounts;

        $this->selectedAccount          = [];
        $this->accountDomain            = null;
        $this->mauticTransportOptions   = [
            'o:testmode' => 'no',
            'o:tracking' => 'yes',
        ];

        $this->logger          = $logger;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf(
            'mautic+mailgun+api://%s?domain=%s',
            $this->getEndpoint(),
            $this->domain
        );
    }

    public function getKey(): string
    {
        if (null !== $this->accountDomain) {
            return $this->selectedAccount['api_key'];
        }

        // Use value from Email Settings.
        return $this->key;
    }

    public function getDomain(): string
    {
        if (null !== $this->accountDomain) {
            return $this->selectedAccount['host'];
        }

        // Use value from Email Settings.
        return $this->domain;
    }

    public function getRegion(): string
    {
        if (null !== $this->accountDomain) {
            return $this->selectedAccount['region'];
        }

        return $this->region;
    }

    private function getEndpoint(): ?string
    {
        switch ($this->getRegion()) {
            case 'eu':
                return 'api.eu.mailgun.net';
                break;
            case 'us':
                return 'api.mailgun.net';
                break;
            default:
                break;
        }

        return $this->host;
    }

    public function getMaxBatchLimit(): int
    {
        return $this->maxBatchLimit;
    }

    /**
     * @todo Make it work.
     *
     * @param MauticMessage $email [description]
     * @param string        $html  [description]
     *
     * @return [type]               [description]
     */
    private function mauticGetAttachments(MauticMessage $email, ?string $html): array
    {
        $attachments = $inlines = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                // replace the cid with just a file name (the only supported way by Mailgun)
                if ($html) {
                    $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
                    $new      = basename($filename);
                    $html     = str_replace('cid:'.$filename, 'cid:'.$new, $html);
                    $p        = new \ReflectionProperty($attachment, 'filename');
                    $p->setAccessible(true);
                    $p->setValue($attachment, $new);
                }
                $inlines[] = $attachment;
            } else {
                $attachments[] = $attachment;
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
        foreach ($metadata as $email => $meta) {
            yield [
                'emailTo' => $email,
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

        // When we are sending test email message $metadata and most of $sentMessage is empty
        $metadata = $email->getMetadata();

        return !(bool) count($metadata);
    }

    private function mauticGetTestMessagePayload(SentMessage $sentMessage): array
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $toList     = null;
        $fromList   = null;
        $subject    = null;

        $text    = $email->getTextBody();
        $html    = $email->getHtmlBody();
        $headers = $email->getHeaders();

        foreach ($headers->all() as $name => $header) {
            $headerKey = strtolower($name);

            switch ($headerKey) {
                case 'from':
                    $fromList = $header->getAddresses();
                    break;
                case 'to':
                    $toList = $header->getAddresses();
                    break;
                case 'subject':
                    $subject = $header->getValue();
                    break;

                default:
                    break;
            }

            if (null !== $toList && null !== $fromList && null !== $subject) {
                break;
            }
        }

        // Details on how to behave with message - these headers can be overwritten if they are specified with the email.
        $oHeaders = [
            'o:testmode' => $this->mauticTransportOptions['o:testmode'],
            'o:tracking' => $this->mauticTransportOptions['o:tracking'],
        ];

        // Attach custom JSON data.
        $vHeaders = [];

        // Template variables.
        $tHeaders = [];

        // Other headers.
        $hHeaders = [];

        foreach ($headers->all() as $name => $header) {
            if (\in_array(strtolower($name), self::MAUTIC_HEADERS_TO_BYPASS, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $oHeaders['o:tag'] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $vHeaderKey            ='v:'.$header->getKey();
                $vHeaders[$vHeaderKey] = $header->getValue();
                continue;
            }

            // Check if it is a valid prefix or header name according to Mailgun API
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

        $substitutions = $recipientMeta['meta']['tokens'] ?? [];

        return array_merge(
            [
                'from'          => $this->mauticStringifyAddresses($fromList),
                'to'            => $this->mauticStringifyAddresses($toList),
                'reply_to'      => $this->mauticStringifyAddresses($toList),
                'cc'            => [],
                'bcc'           => [],
                'subject'       => $subject,
                'text'          => $text,
                'html'          => $html,
                'callback_url'  => $this->callbackUrl,
            ],
            $oHeaders,
            $vHeaders,
            $tHeaders,
            $hHeaders,
        );
    }

    private function mauticGetFromEmail(SentMessage $sentMessage)
    {
        $email = $sentMessage->getOriginalMessage();

        return $this->mauticStringifyAddresses($email->getFrom());
    }

    private function mauticGetPayload(SentMessage $sentMessage, array $recipientMeta): array
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        // Work with objects so we can use mauticStringifyAddresses to properly format.
        $recipientName = $recipientMeta['meta']['name'] ?? '';
        $addressTo     = new Address(
            $recipientMeta['emailTo'],
            $recipientName
        );
        $text    = $email->getTextBody();
        $html    = $email->getHtmlBody();
        $headers = $email->getHeaders();

        // Details on how to behave with message - these headers can be overwritten if they are specified with the email.
        $oHeaders = [
            'o:testmode' => $this->mauticTransportOptions['o:testmode'],
            'o:tracking' => $this->mauticTransportOptions['o:tracking'],
        ];

        // Attach custom JSON data.
        $vHeaders = [];

        // Template variables.
        $tHeaders = [];

        // Other headers.
        $hHeaders = [];

        /**
         * @todo Make attachments work.
         */
        [$attachments, $inlines, $html] = $this->mauticGetAttachments($email, $html);

        foreach ($headers->all() as $name => $header) {
            if (\in_array(strtolower($name), self::MAUTIC_HEADERS_TO_BYPASS, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $oHeaders['o:tag'] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $vHeaderKey            ='v:'.$header->getKey();
                $vHeaders[$vHeaderKey] = $header->getValue();
                continue;
            }

            // Check if it is a valid prefix or header name according to Mailgun API
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

        $substitutions = $recipientMeta['meta']['tokens'] ?? [];

        return array_merge(
            [
                'from'          => $this->mauticStringifyAddresses($email->getFrom()),
                'to'            => $this->mauticStringifyAddresses([$addressTo]),
                'reply_to'      => $this->mauticStringifyAddresses($email->getReplyTo()),
                'cc'            => $this->mauticStringifyAddresses($email->getCc()),
                'bcc'           => $this->mauticStringifyAddresses($email->getBcc()),
                'subject'       => $email->getSubject(),
                'text'          => $text,
                'html'          => $html,

                /**
                 * @todo Support for attachments.
                 */
                /* 'attachment'    => $attachments, */

                'substitutions' => json_encode($substitutions),
                'callback_url'  => $this->callbackUrl,
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
            urlencode($this->domain)
        );

        return $this->client->request(
            'POST',
            'https://'.$endpoint,
            [
                'auth_basic'   => 'api:'.$this->key,
                'headers'      => ['Content-Type: application/x-www-form-urlencoded'],
                'body'         => $payload,
            ]
        );
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
                $payload = $this->mauticGetTestMessagePayload($sentMessage);

                $response = $this->mauticGetApiResponse($payload);
                $this->mauticHandleError($response);

                return $response;
            }

            // For sending all other emails (segments, example emails, direct emails, etc.)
            $fromEmail = $this->mauticGetFromEmail($sentMessage);
            $this->selectAccount($fromEmail);
            if (!$this->isAccountSelected()) {
                /**
                 * @todo Handle the case where account is not selected
                 * (do not use coreParameters helper here).
                 */
            }
            $recipientsMeta = $this->mauticGetRecipientData($sentMessage);
            foreach ($recipientsMeta as $recipientMeta) {
                $payload = $this->mauticGetPayload(
                    $sentMessage,
                    $recipientMeta
                );

                $response = $this->mauticGetApiResponse($payload);
                $this->mauticHandleError($response);
            }

            return $response;
        } catch (\Exception $e) {
            throw new TransportException($e->getMessage());
        }
    }
}
