<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use App\Authentication\Repository\BannedIdentifierRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BannedIdentifierRepository::class)]
#[ORM\Table(name: 'banned_identifiers')]
#[ORM\HasLifecycleCallbacks]
class BannedIdentifier
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    public private(set) string $email;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $bannedAt;

    #[ORM\Column(length: 255)]
    public private(set) string $reason;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $createdAt;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $updatedAt;

    public function __construct(
        string $email,
        string $reason,
        ?CarbonImmutable $bannedAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->reason = $reason;
        $this->bannedAt = $bannedAt ?? CarbonImmutable::now();
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
