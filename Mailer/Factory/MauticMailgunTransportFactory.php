<?php

namespace MauticPlugin\MauticMailgunMailerBundle\Mailer\Factory;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticMailgunMailerBundle\Mailer\Transport\MailgunApiTransport;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Matic Zagmajster <maticzagmajster@gmail.com>
 */
final class MauticMailgunTransportFactory extends AbstractTransportFactory
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    public function __construct(
        ContainerInterface $container,
        EventDispatcherInterface $dispatcher = null,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null,
        CoreParametersHelper $coreParametersHelper = null,
    ) {

        if (\MAUTIC_ENV === 'dev') {
            $plgDevLogger = $container->get('monolog.logger.plgdev');
            $logger = $plgDevLogger ?? $logger;
        }
        
        $this->coreParametersHelper = $coreParametersHelper;

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
            $accounts = $this->coreParametersHelper->get('mailer_mailgun_accounts');

            $rootUrl       = $this->coreParametersHelper->get('site_url');
            $rootUrl       = rtrim($rootUrl, '/');
            $callbackUrl   = $rootUrl.'/mailer/callback';

            if (null === $webhookSigningKey) {
                throw new InvalidArgumentException('Configure singing key in global configuration.');
            }

            if (null === $key || null === $domain) {
                throw new InvalidArgumentException('Key or domain not set, cannot create MailgunApiTransport object!');
            }

            return new MailgunApiTransport(
                $host,
                $key,
                $domain,
                $maxBatchLimit,
                $callbackUrl,
                $webhookSigningKey,
                $accounts,
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
