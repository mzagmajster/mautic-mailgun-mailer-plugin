<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailgunMailerBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<TransLog>
 */
class TransLogRepository extends CommonRepository
{
    public function getTableAlias(): string
    {
        return 'tl';
    }
}
