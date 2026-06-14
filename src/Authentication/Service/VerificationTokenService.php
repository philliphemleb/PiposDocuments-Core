<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use App\Authentication\Repository\VerificationTokenRepository;
use Carbon\CarbonImmutable;

final readonly class VerificationTokenService
{
    public const int REGISTRATION_TOKEN_EXPIRY_MINUTES = 60;

    public const int LOGIN_TOKEN_EXPIRY_MINUTES = 15;

    public function __construct(
        private VerificationTokenRepository $tokenRepository,
    ) {
    }

    public function createRegistrationToken(User $user): VerificationToken
    {
        return new VerificationToken(
            user: $user,
            type: TokenType::Registration,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addMinutes(self::REGISTRATION_TOKEN_EXPIRY_MINUTES),
        );
    }

    public function createLoginToken(User $user): VerificationToken
    {
        return new VerificationToken(
            user: $user,
            type: TokenType::Login,
            token: \sprintf('%06d', random_int(0, 999999)),
            expiresAt: CarbonImmutable::now()->addMinutes(self::LOGIN_TOKEN_EXPIRY_MINUTES),
        );
    }

    public function invalidateExistingForUser(User $user, TokenType $type): void
    {
        $existingToken = $this->tokenRepository->findValidTokenForUserByType($user, $type);

        if ($existingToken instanceof VerificationToken) {
            $existingToken->invalidate();
        }
    }
}
