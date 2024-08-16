<?php

require_once MAUTIC_ROOT_DIR.'/plugins/MauticMailgunMailerBundle/.plugin-env.php';

return [
    'name'        => 'MailgunMailer',
    'description' => 'Integrate PHP Mailer transport for Mailgun API',
    'author'      => 'Stanislav Denysenko',
    'version'     => '1.0.0',

    'services' => [
        'other' => [
            'mautic.mailgun.transport_factory' => [
                'class'        => MauticPlugin\MauticMailgunMailerBundle\Mailer\Factory\MauticMailgunTransportFactory::class,
                'arguments'    => [
                    'service_container',
                    'mautic.mailgun.factory.sending_account_settings',
                    'event_dispatcher',
                    'mautic.mailgun.http.client',
                    'monolog.logger.mautic',
                    'mautic.helper.core_parameters',
                ],
                'tag'          => 'mailer.transport_factory',
            ],

            'mautic.mailgun.http.client' => [
                'class' => Symfony\Component\HttpClient\NativeHttpClient::class,
            ],
        ],  // end: other
    ],

    'parameters' => [
        'mailer_mailgun_max_batch_limit'       => \MauticPlugin\MauticMailgunMailerBundle\Env\MAX_BATCH_LIMIT,
        'mailer_mailgun_batch_recipient_count' => \MauticPlugin\MauticMailgunMailerBundle\Env\BATCH_RECIPIENT_COUNT,
        'mailer_mailgun_region'                => \MauticPlugin\MauticMailgunMailerBundle\Env\REGION,
        'mailer_mailgun_webhook_signing_key'   => \MauticPlugin\MauticMailgunMailerBundle\Env\WEBHOOK_SIGNING_KEY,
    ],
];
