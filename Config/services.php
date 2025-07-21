<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [];

    $services->load(
        'MauticPlugin\\MauticMailgunMailerBundle\\',
        '../'
    )
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->alias(
        'mautic.mailgun.model.transport_callback',
        Mautic\EmailBundle\Model\TransportCallback::class
    );

    $services->alias(
        'mautic.mailgun.factory.sending_account_settings',
        MauticPlugin\MauticMailgunMailerBundle\Factory\SendingAccountSettingsFactory::class,
    );
};
