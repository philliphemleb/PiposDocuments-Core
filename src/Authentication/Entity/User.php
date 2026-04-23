<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use App\Authentication\Enum\UserRole;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Repository\UserRepository;
use App\Infrastructure\AuditLog\Entity\AuditableEntityStateInterface;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, AuditableEntityStateInterface
{
    #[Override]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255, unique: true)]
    public private(set) string $email {
        set(string $value) => $this->email = mb_strtolower(trim($value));
    }

    #[ORM\Column(type: 'string', enumType: UserRole::class)]
    public private(set) UserRole $role;

    #[ORM\Column(type: 'string', enumType: UserStatus::class)]
    public private(set) UserStatus $status {
        set(string|UserStatus $value) => $this->status = $value instanceof UserStatus
            ? $value
            : UserStatus::from($value);
    }

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $createdAt;

    #[ORM\Column(type: 'carbon_immutable')]
    public private(set) CarbonImmutable $updatedAt;

    public function __construct(
        string $email,
        UserRole $role = UserRole::USER,
        UserStatus $status = UserStatus::ACTIVE,
    ) {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->role = $role;
        $this->status = $status;
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

    #[Override]
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return ['ROLE_' . $this->role->name];
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getAuditableFields(): array
    {
        return ['status'];
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function changeEmail(string $email): void
    {
        $this->email = $email;
    }
}
