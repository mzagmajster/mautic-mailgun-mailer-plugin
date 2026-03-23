<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticMailgunMailerBundle\Entity\TransLog;
use Psr\Log\LoggerInterface;

class ApiLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function logRequest(string $endpoint, array $payload, int $statusCode, string $responseContent): void
    {
        try {
            $log = new TransLog();
            $log->setEndpoint($endpoint);
            $log->setRequest(json_encode($payload, JSON_THROW_ON_ERROR));
            $log->setStatusCode($statusCode);
            $log->setResponse($responseContent);
            $log->setDateAdded(new \DateTime('UTC'));

            $this->entityManager->persist($log);
            $this->entityManager->flush($log);
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to log Mailgun API request', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
