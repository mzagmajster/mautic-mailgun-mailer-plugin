<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Service;

class AccountProviderService
{
    /**
     * @var int
     */
    private $selectedIndex;

    public function __construct(
        private array $accounts = []
    ) {
    }

    private function parseEmail($email)
    {
        $email = strtolower($email);
        $parts = explode('@', $email);

        return [
            'recipient' => $parts[0] ?? null,
            'domain'    => $parts[1] ?? null,
        ];
    }

    public function getAccount()
    {
        if (null === $this->selectedIndex) {
            return null;
        }

        return $this->accounts[$this->selectedIndex];
    }

    public function selectedAccount($email)
    {
        $emailInfo = $this->parseEmail($email);
        $domain    = $emailInfo['domain'];

        return $domain;
    }
}
