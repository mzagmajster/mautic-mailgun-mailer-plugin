<?php

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
        'mailer_mailgun_max_batch_limit'       => getenv('MAUTIC_MAILER_MAILGUN_MAX_BATCH_LIMIT') ?: 3000,
        'mailer_mailgun_batch_recipient_count' => getenv('MAUTIC_MAILER_MAILGUN_BATCH_RECIPIENT_COUNT') ?: 200,
        'mailer_mailgun_region'                => getenv('MAUTIC_MAILER_MAILGUN_REGION') ?: 'eu',
        'mailer_mailgun_webhook_signing_key'   => getenv('MAUTIC_MAILER_MAILGUN_WEBHOOK_SIGNING_KEY') ?: '',
    ],
];
