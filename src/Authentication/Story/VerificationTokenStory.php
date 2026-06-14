<?php

declare(strict_types=1);

namespace App\Authentication\Story;

use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use Carbon\CarbonImmutable;
use Override;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<VerificationToken>
 */
final class VerificationTokenStory extends PersistentObjectFactory
{
    #[Override]
    public static function class(): string
    {
        return VerificationToken::class;
    }

    #[Override]
    protected function defaults(): array
    {
        return [
            'user' => UserStory::new(),
            'type' => TokenType::Registration,
            'token' => bin2hex(random_bytes(32)),
            'expiresAt' => CarbonImmutable::now()->addHour(),
        ];
    }
}
