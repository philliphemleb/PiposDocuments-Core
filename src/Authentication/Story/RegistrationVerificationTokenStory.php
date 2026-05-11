<?php

declare(strict_types=1);

namespace App\Authentication\Story;

use App\Authentication\Entity\RegistrationVerificationToken;
use Carbon\CarbonImmutable;
use Override;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<RegistrationVerificationToken>
 */
final class RegistrationVerificationTokenStory extends PersistentObjectFactory
{
    #[Override]
    public static function class(): string
    {
        return RegistrationVerificationToken::class;
    }

    #[Override]
    protected function defaults(): array
    {
        return [
            'user' => UserStory::new(),
            'token' => bin2hex(random_bytes(32)),
            'expiresAt' => CarbonImmutable::now()->addHour(),
        ];
    }
}
