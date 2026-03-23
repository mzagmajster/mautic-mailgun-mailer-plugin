<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

class TransLog
{
    public const TABLE_NAME = 'mailgun_trans_log';

    private ?int $id = null;

    private ?string $request = null;

    private ?string $endpoint = null;

    private ?string $response = null;

    private ?int $statusCode = null;

    private ?\DateTimeInterface $dateAdded = null;

    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(TransLogRepository::class)
            ->addIndex(['date_added'], 'idx_mailgun_trans_log_date_added')
            ->addId();

        $builder->addNullableField('request', Types::TEXT, 'request');
        $builder->addNullableField('endpoint', Types::STRING, 'endpoint');
        $builder->addNullableField('response', Types::TEXT, 'response');

        $builder->createField('statusCode', Types::INTEGER)
            ->columnName('status_code')
            ->nullable()
            ->build();

        $builder->addNullableField('dateAdded', Types::DATETIME_MUTABLE, 'date_added');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequest(): ?string
    {
        return $this->request;
    }

    public function setRequest(?string $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getDateAdded(): ?\DateTimeInterface
    {
        return $this->dateAdded;
    }

    public function setDateAdded(?\DateTimeInterface $dateAdded): self
    {
        $this->dateAdded = $dateAdded;

        return $this;
    }
}
