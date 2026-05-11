<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use App\Authentication\Enum\FailedRegistrationReason;

final readonly class RegistrationResult
{
    private function __construct(
        public bool $success,
        public ?FailedRegistrationReason $reason = null,
    ) {
    }

    public static function success(): self
    {
        return new self(success: true);
    }

    public static function failed(FailedRegistrationReason $reason): self
    {
        return new self(success: false, reason: $reason);
    }
}
