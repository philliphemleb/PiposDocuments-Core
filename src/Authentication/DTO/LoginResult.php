<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use App\Authentication\Enum\FailedLoginReason;

final readonly class LoginResult
{
    private function __construct(
        public bool $success,
        public ?FailedLoginReason $reason = null,
        public ?string $token = null,
    ) {
    }

    public static function success(string $token): self
    {
        return new self(success: true, token: $token);
    }

    public static function failed(FailedLoginReason $reason): self
    {
        return new self(success: false, reason: $reason);
    }
}
