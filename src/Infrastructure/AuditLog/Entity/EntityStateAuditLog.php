<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog\Entity;

use App\Infrastructure\AuditLog\Repository\EntityStateAuditLogRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EntityStateAuditLogRepository::class)]
#[ORM\Table(name: 'entity_state_audit_logs')]
#[ORM\Index(name: 'idx_entity_state_audit_log_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\HasLifecycleCallbacks]
class EntityStateAuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[ORM\Column(length: 255)]
    public private(set) string $entityType;

    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $entityId;

    #[ORM\Column(length: 255)]
    public private(set) string $oldState;

    #[ORM\Column(length: 255)]
    public private(set) string $newState;

    #[ORM\Column(length: 255)]
    public private(set) string $changedBy;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $changedAt;

    #[ORM\Column(length: 255)]
    public private(set) string $reason;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $createdAt;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $updatedAt;

    public function __construct(
        string $entityType,
        Uuid $entityId,
        string $oldState,
        string $newState,
        string $changedBy,
        string $reason,
        ?CarbonImmutable $changedAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->oldState = $oldState;
        $this->newState = $newState;
        $this->changedBy = $changedBy;
        $this->reason = $reason;
        $this->changedAt = $changedAt ?? CarbonImmutable::now();
        $this->createdAt = CarbonImmutable::now();
        $this->updatedAt = CarbonImmutable::now();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = CarbonImmutable::now();
    }
}
