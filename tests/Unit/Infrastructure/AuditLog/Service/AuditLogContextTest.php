<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\AuditLog\Service;

use App\Infrastructure\AuditLog\Service\AuditLogContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditLogContextTest extends TestCase
{
    #[Test]
    public function setContextSetsActorAndReason(): void
    {
        $context = new AuditLogContext();
        $context->setContext('ProcessLockedUsersWorker', 'Automated unlock');

        self::assertSame('ProcessLockedUsersWorker', $context->actor);
        self::assertSame('Automated unlock', $context->reason);
    }

    #[Test]
    public function clearContextResetsActorAndReason(): void
    {
        $context = new AuditLogContext();
        $context->setContext('worker', 'reason');
        $context->clearContext();

        self::assertNull($context->actor);
        self::assertNull($context->reason);
    }

    #[Test]
    public function defaultStateIsNull(): void
    {
        $context = new AuditLogContext();

        self::assertNull($context->actor);
        self::assertNull($context->reason);
    }

    #[Test]
    public function overwriteContextUsesLatestValues(): void
    {
        $context = new AuditLogContext();
        $context->setContext('FirstWorker', 'first reason');
        $context->setContext('SecondWorker', 'second reason');

        self::assertSame('SecondWorker', $context->actor);
        self::assertSame('second reason', $context->reason);
    }
}
