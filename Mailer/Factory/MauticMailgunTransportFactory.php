<?php

namespace MauticPlugin\MauticMailgunMailerBundle\Mailer\Factory;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticMailgunMailerBundle\Factory\SendingAccountSettingsFactory;
use MauticPlugin\MauticMailgunMailerBundle\Mailer\Transport\MailgunApiTransport;
use MauticPlugin\MauticMailgunMailerBundle\Service\AccountProviderService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Matic Zagmajster <maticzagmajster@gmail.com>
 */
final class MauticMailgunTransportFactory extends AbstractTransportFactory
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var SendingAccountSettingsFactory
     */
    private $sendingAccountSettingsFactory;

    public function __construct(
        ContainerInterface $container,
        SendingAccountSettingsFactory $sendingAccountSettingsFactory,
        EventDispatcherInterface $dispatcher = null,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null,
        CoreParametersHelper $coreParametersHelper = null,
    ) {
        if (\MAUTIC_ENV === 'dev') {
            $plgDevLogger = $container->get('monolog.logger.plgdev');
            $logger       = $plgDevLogger ?? $logger;
        }

        $this->coreParametersHelper          = $coreParametersHelper;
        $this->sendingAccountSettingsFactory = $sendingAccountSettingsFactory;

        parent::__construct(
            $dispatcher,
            $client,
            $logger
        );
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('mautic+mailgun+api' === $scheme) {
            $host              = ('default' === $dsn->getHost()) ? MailgunApiTransport::HOST : $dsn->getHost();
            $key               =  $dsn->getPassword();
            $domain            = $dsn->getOption('domain');
            $maxBatchLimit     = $dsn->getOption('maxBatchLimit') ?? 5000;
            $webhookSigningKey = $this->coreParametersHelper->get(
                'mautic.mailer_mailgun_webhook_signing_key',
                null
            );

            $rootUrl       = $this->coreParametersHelper->get('site_url');
            $rootUrl       = rtrim($rootUrl, '/');
            $callbackUrl   = $rootUrl.'/mailer/callback';

            if (null === $webhookSigningKey) {
                throw new InvalidArgumentException('Configure singing key in global configuration.');
            }

            if (null === $key || null === $domain) {
                throw new InvalidArgumentException('Key or domain not set, cannot create MailgunApiTransport object!');
            }

            $accountProviderService = new AccountProviderService(
                $this->sendingAccountSettingsFactory->create()
            );

            return new MailgunApiTransport(
                $host,
                $key,
                $domain,
                $maxBatchLimit,
                $callbackUrl,
                $webhookSigningKey,
                $accountProviderService,
                $this->dispatcher,
                $this->client,
                $this->logger
            );
        }

        throw new UnsupportedSchemeException($dsn, 'mailgun', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mautic+mailgun+api'];
    }
}
