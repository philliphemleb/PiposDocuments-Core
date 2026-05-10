<?php

declare(strict_types=1);

namespace App\Authentication\Story;

use App\Authentication\Entity\EmailVerificationToken;
use Carbon\CarbonImmutable;
use Override;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<EmailVerificationToken>
 */
final class EmailVerificationTokenStory extends PersistentObjectFactory
{
    #[Override]
    public static function class(): string
    {
        return EmailVerificationToken::class;
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
