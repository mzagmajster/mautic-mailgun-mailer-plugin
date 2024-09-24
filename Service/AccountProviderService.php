<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Service;

use MauticPlugin\MauticMailgunMailerBundle\DTO\SendingAccountSettings;

class AccountProviderService
{
    /**
     * @var int
     */
    private $selectedIndex;

    public function __construct(
        private array $accounts = []
    ) {
        $this->selectedIndex = null;
    }

    public function __toString()
    {
        $sendingDomain = '<none>';
        if (null !== $this->selectedIndex) {
            $sendingDomain = $this->accounts[$this->selectedIndex]->getSendingDomain();
        }

        return sprintf(
            'SelectedAccountIndex %d - SelectedSendingDomain %s',
            $this->selectedIndex,
            $sendingDomain
        );
    }

    private function parseEmail($email): array
    {
        $email = strtolower($email);
        $parts = explode('@', $email);

        return [
            'recipient' => $parts[0] ?? null,
            'domain'    => $parts[1] ?? null,
        ];
    }

    /**
     * Parse TLD.
     *
     * Note: Its not perfect, but should cover most cases,
     *
     * @param string $domain
     */
    private function parseTld($domain): string
    {
        $parts = explode('.', $domain);
        if (count($parts) <= 2) {
            return $domain;
        } else {
            unset($parts[0]);

            return implode('.', $parts);
        }
    }

    public function getAccount(): ?SendingAccountSettings
    {
        if (null === $this->selectedIndex) {
            return null;
        }

        return $this->accounts[$this->selectedIndex];
    }

    public function selectAccount($email): self
    {
        $this->selectedIndex = null;
        $emailInfo           = $this->parseEmail($email);
        $domain              = $emailInfo['domain'];

        if (null === $domain) {
            return $this;
        }

        $tldDomain       = $this->parseTld($domain);
        $exactMatchIndex = null;
        $tldMatchIndex   = null;
        $i               = 0;
        $size            = count($this->accounts);
        while ($i < $size
                && (null === $exactMatchIndex || null === $tldMatchIndex)
        ) {
            $isExactMatch = $this->accounts[$i]->getSendingDomain() === $domain;
            $isTldMatch   = $this->parseTld(
                $this->accounts[$i]->getSendingDomain()
            )
                === $tldDomain;

            if ($isExactMatch) {
                $exactMatchIndex = $i;
            }

            if ($isTldMatch) {
                $tldMatchIndex = $i;
            }

            ++$i;
        }

        if (null !== $exactMatchIndex) {
            $this->selectedIndex = $exactMatchIndex;
        } elseif (null !== $tldMatchIndex) {
            $this->selectedIndex = $tldMatchIndex;
        }

        return $this;
    }
}
