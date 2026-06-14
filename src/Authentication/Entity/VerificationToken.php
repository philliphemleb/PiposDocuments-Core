<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use App\Authentication\Enum\TokenType;
use App\Authentication\Repository\VerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VerificationTokenRepository::class)]
#[ORM\Table(name: 'verification_tokens')]
class VerificationToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) User $user;

    #[ORM\Column(type: 'string', enumType: TokenType::class)]
    public private(set) TokenType $type;

    #[ORM\Column(length: 64, unique: true)]
    public private(set) string $token;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $expiresAt;

    #[ORM\Column(type: 'carbon_immutable', nullable: true)]
    public private(set) ?CarbonImmutable $dispatchedAt = null;

    #[ORM\Column(type: 'carbon_immutable', nullable: true)]
    public private(set) ?CarbonImmutable $sentAt = null;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $createdAt;

    public function __construct(
        User $user,
        TokenType $type,
        string $token,
        CarbonImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = $type;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->createdAt = CarbonImmutable::now();
    }

    public function markAsDispatched(): void
    {
        $this->dispatchedAt = CarbonImmutable::now();
    }

    public function markAsSent(): void
    {
        $this->sentAt = CarbonImmutable::now();
    }

    public function invalidate(): void
    {
        $this->expiresAt = CarbonImmutable::now();
    }
}
