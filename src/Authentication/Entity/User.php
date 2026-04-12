<?php

declare(strict_types=1);

namespace App\Authentication\Entity;

use App\Authentication\Enum\UserRole;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Repository\UserRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public private(set) Uuid $id;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255, unique: true)]
    public private(set) string $email;

    #[ORM\Column(type: 'string', enumType: UserRole::class)]
    public private(set) UserRole $role;

    #[ORM\Column(type: 'string', enumType: UserStatus::class)]
    public private(set) UserStatus $status;

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
}
