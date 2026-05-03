<?php

declare(strict_types=1);

namespace App\Authentication\Story;

use App\Authentication\Entity\User;
use App\Authentication\Enum\UserRole;
use App\Authentication\Enum\UserStatus;
use Override;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserStory extends PersistentObjectFactory
{
    #[Override]
    public static function class(): string
    {
        return User::class;
    }

    #[Override]
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->email(),
            'role' => UserRole::USER,
            'status' => UserStatus::ACTIVE,
        ];
    }
}
