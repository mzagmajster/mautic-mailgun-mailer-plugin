<?php

require_once MAUTIC_ROOT_DIR.'/plugins/MauticMailgunMailerBundle/.plugin-env.php';

return [
    'name'        => 'MailgunMailer',
    'description' => 'Integrate PHP Mailer transport for Mailgun API',
    'author'      => 'Stanislav Denysenko',
    'version'     => '1.0.0',

    'services' => [
        'forms' => [
            'mautic.form.type.mailgun.account' => [
                'class'     => \MauticPlugin\MauticMailgunMailerBundle\Form\Type\MailgunAccountType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],

            'mautic.form.type.mailgun.config' => [
                'class'     => \MauticPlugin\MauticMailgunMailerBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        /*'events' => [
            'mautic.mailgun.subscriber.config' => [
                'class'     => \MauticPlugin\MauticMailgunMailerBundle\EventListener\ConfigSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],*/

        'other' => [
            'mautic.omnivery.transport_factory' => [
                'class'        => \MauticPlugin\MauticMailgunMailerBundle\Mailer\Factory\MauticMailgunTransportFactory::class,
                'arguments'    => [
                    'event_dispatcher',
                    'mautic.omnivery.http.client',
                    'monolog.logger.mautic',
                    'mautic.helper.core_parameters',
                ],
                'tag'          => 'mailer.transport_factory',
            ],

            'mautic.omnivery.http.client' => [
                'class' => Symfony\Component\HttpClient\NativeHttpClient::class,
            ],
        ],
    ],

    'parameters' => [
        'mailer_mailgun_max_batch_limit'       => \MauticPlugin\MauticMailgunMailerBundle\Env\MAX_BATCH_LIMIT,
        'mailer_mailgun_batch_recipient_count' => \MauticPlugin\MauticMailgunMailerBundle\Env\BATCH_RECIPIENT_COUNT,
        'mailer_mailgun_region'                => \MauticPlugin\MauticMailgunMailerBundle\Env\REGION,
        'mailer_mailgun_webhook_signing_key'   => \MauticPlugin\MauticMailgunMailerBundle\Env\WEBHOOK_SIGNING_KEY,
    ],
];
