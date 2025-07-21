<?php

namespace MauticPlugin\MauticMailgunMailerBundle\Factory;

use MauticPlugin\MauticMailgunMailerBundle\Service\AccountProviderService;

class AccountProviderServiceFactory
{
    /**
     * @var SendingAccountSettingsFactory
     */
    private $sendingAccountSettingsFactory;

    public function __construct(SendingAccountSettingsFactory $sendingAccountSettingsFactory)
    {
        $this->sendingAccountSettingsFactory = $sendingAccountSettingsFactory;
    }

    /**
     * @return AccountProviderService
     */
    public function create()
    {
        return new AccountProviderService($this->sendingAccountSettingsFactory->create());
    }
}
