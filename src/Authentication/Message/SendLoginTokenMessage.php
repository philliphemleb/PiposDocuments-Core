<?php

declare(strict_types=1);

namespace App\Authentication\Message;

readonly class SendLoginTokenMessage
{
    public function __construct(
        public string $email,
        public string $code,
        public int $expiresInMinutes,
    ) {
    }
}
