<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\MauticMailgunMailerBundle\Entity\TransLog;

class Version_1_0_1 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $schema->getTable($this->concatPrefix(TransLog::TABLE_NAME));

            return false;
        } catch (SchemaException) {
            return true;
        }
    }

    protected function up(): void
    {
        $table = $this->concatPrefix(TransLog::TABLE_NAME);

        $this->addSql("
            CREATE TABLE `{$table}` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `request` longtext DEFAULT NULL,
                `endpoint` varchar(255) DEFAULT NULL,
                `response` longtext DEFAULT NULL,
                `status_code` int(11) DEFAULT NULL,
                `date_added` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_mailgun_trans_log_date_added` (`date_added`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB ROW_FORMAT = DYNAMIC
        ");
    }
}
