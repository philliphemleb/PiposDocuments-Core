<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog\Repository;

use App\Infrastructure\AuditLog\Entity\EntityStateAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntityStateAuditLog>
 */
class EntityStateAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityStateAuditLog::class);
    }
}
