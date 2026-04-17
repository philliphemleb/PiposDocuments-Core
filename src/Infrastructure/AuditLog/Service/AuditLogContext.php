<?php

declare(strict_types=1);

namespace App\Infrastructure\AuditLog\Service;

class AuditLogContext
{
    public ?string $actor = null {
        get {
            return $this->actor;
        }
    }

    public ?string $reason = null {
        get {
            return $this->reason;
        }
    }

    public function setContext(string $actor, string $reason): void
    {
        $this->actor = $actor;
        $this->reason = $reason;
    }

    public function clearContext(): void
    {
        $this->actor = null;
        $this->reason = null;
    }
}
