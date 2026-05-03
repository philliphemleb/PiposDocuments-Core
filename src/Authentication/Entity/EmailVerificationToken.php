<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use App\Authentication\Repository\EmailVerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EmailVerificationTokenRepository::class)]
#[ORM\Table(name: 'email_verification_tokens')]
class EmailVerificationToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) User $user;

    #[ORM\Column(length: 64, unique: true)]
    public private(set) string $token;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $expiresAt;

    #[ORM\Column(type: 'carbon_immutable', nullable: true)]
    public private(set) ?CarbonImmutable $dispatchedAt = null;

    #[ORM\Column(type: 'carbon_immutable', nullable: true)]
    public private(set) ?CarbonImmutable $sentAt = null;

    #[ORM\Column(options: ['default' => 0])]
    public private(set) int $sendAttempts = 0;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $createdAt;

    public function __construct(
        User $user,
        string $token,
        CarbonImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
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

    public function incrementSendAttempts(): void
    {
        ++$this->sendAttempts;
    }
}
