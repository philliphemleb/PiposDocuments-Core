<?php

declare(strict_types=1);

namespace App\Authentication\DTO;

use App\Authentication\Enum\FailedResendReason;

final readonly class ResendResult
{
    private function __construct(
        public bool $success,
        public ?FailedResendReason $reason = null,
    ) {
    }

    public static function success(): self
    {
        return new self(success: true);
    }

    public static function failed(FailedResendReason $reason): self
    {
        return new self(success: false, reason: $reason);
    }
}
