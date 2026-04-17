<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use App\Authentication\Repository\UserLockRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserLockRepository::class)]
#[ORM\Table(name: 'user_locks')]
#[ORM\HasLifecycleCallbacks]
class UserLock
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public private(set) User $user;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $lockedAt;

    #[ORM\Column(type: 'carbon_immutable', nullable: true)]
    public private(set) ?CarbonImmutable $validUntil;

    #[ORM\Column(length: 255)]
    public private(set) string $reason;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public private(set) User $lockedBy;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $createdAt;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $updatedAt;

    public function __construct(
        User $user,
        CarbonImmutable $lockedAt,
        string $reason,
        User $lockedBy,
        ?CarbonImmutable $validUntil = null,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->lockedAt = $lockedAt;
        $this->reason = $reason;
        $this->lockedBy = $lockedBy;
        $this->validUntil = $validUntil;
        $this->createdAt = CarbonImmutable::now();
        $this->updatedAt = CarbonImmutable::now();
    }

    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        $this->createdAt = CarbonImmutable::now();
        $this->updatedAt = CarbonImmutable::now();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = CarbonImmutable::now();
    }
}
