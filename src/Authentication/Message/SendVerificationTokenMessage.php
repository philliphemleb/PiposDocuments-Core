<?php

declare(strict_types=1);

namespace App\Authentication\Message;

readonly class SendVerificationTokenMessage
{
    public function __construct(
        public string $email,
        public string $token,
        public int $expiresInMinutes,
    ) {
    }
}
