<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\DTO;

class SendingAccountSettings
{
    /**
     * @var string
     */
    private $sendingDomain;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $region;

    /**
     * @var bool
     */
    private $isDefault;

    public function __construct()
    {
        $this->isDefault = false;
    }

    /**
     * Get the value of sendingDomain.
     */
    public function getSendingDomain(): string
    {
        return $this->sendingDomain;
    }

    /**
     * Set the value of sendingDomain.
     *
     * @param string $sendingDomain
     *
     * @return $this
     */
    public function setSendingDomain($sendingDomain): self
    {
        $this->sendingDomain = $sendingDomain;

        return $this;
    }

    /**
     * Get the value of apiKey.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Set the value of apiKey.
     *
     * @param string $apiKey
     *
     * @return $this
     */
    public function setApiKey($apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Get the value of region.
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Set the value of region.
     *
     * @param string $region
     *
     * @return $this
     */
    public function setRegion($region): self
    {
        $this->region = $region;

        return $this;
    }

    /**
     * Get the value of isDefault.
     */
    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Set the value of isDefault.
     *
     * @param bool $isDefault
     *
     * @return $this
     */
    public function setIsDefault($isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
