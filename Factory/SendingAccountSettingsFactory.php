<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Factory;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticMailgunMailerBundle\DTO\SendingAccountSettings;

class SendingAccountSettingsFactory
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper
    ) {
    }

    public function create(): array
    {
        $accounts = $this->coreParametersHelper
            ->get('mailer_mailgun_accounts');

        $loadedAccounts = [];
        foreach ($accounts as $account) {
            $accountObject = new SendingAccountSettings();
            $accountObject->setSendingDomain($account['host'])
                ->setRegion($account['region'])
                ->setApiKey($account['api_key'])
                ->setIsDefault(false);
            $loadedAccounts[] = $accountObject;
        }

        return $loadedAccounts;
    }
}
