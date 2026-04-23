<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\AuditLog\EventSubscriber;

use App\Authentication\Enum\UserStatus;
use App\Authentication\Story\UserStory;
use App\Infrastructure\AuditLog\Entity\EntityStateAuditLog;
use App\Infrastructure\AuditLog\Repository\EntityStateAuditLogRepository;
use App\Infrastructure\AuditLog\Service\AuditLogContext;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

final class EntityStateAuditLogSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private EntityStateAuditLogRepository $auditLogRepository;

    private AuditLogContext $auditLogContext;

    private WorkflowInterface $userStatusWorkflow;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->auditLogRepository = self::getContainer()->get(EntityStateAuditLogRepository::class);
        $this->auditLogContext = self::getContainer()->get(AuditLogContext::class);
        $this->userStatusWorkflow = self::getContainer()->get('state_machine.user_status');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->auditLogContext->clearContext();
        parent::tearDown();
    }

    #[Test]
    public function statusChangeCreatesAuditLogRow(): void
    {
        $user = UserStory::createOne([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->auditLogContext->setContext('TestWorker', 'automated test');

        $this->userStatusWorkflow->apply($user, 'lock');
        $this->em->persist($user);
        $this->em->flush();

        $auditLogs = $this->auditLogRepository->findAll();

        self::assertCount(1, $auditLogs);
        $log = $auditLogs[0] ?? self::fail('Expected audit log entry');

        self::assertSame('User', $log->entityType);
        self::assertSame($user->id->toRfc4122(), $log->entityId->toRfc4122());
        self::assertSame('active', $log->oldState);
        self::assertSame('locked', $log->newState);
        self::assertSame('TestWorker', $log->changedBy);
        self::assertSame('automated test', $log->reason);
    }

    #[Test]
    public function statusChangeWithoutContextDefaultsToManualChange(): void
    {
        $user = UserStory::createOne([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->userStatusWorkflow->apply($user, 'lock');
        $this->em->persist($user);
        $this->em->flush();

        $auditLogs = $this->auditLogRepository->findAll();

        self::assertCount(1, $auditLogs);
        $log = $auditLogs[0] ?? self::fail('Expected audit log entry');

        self::assertSame('manual_change', $log->changedBy);
        self::assertSame('', $log->reason);
    }

    #[Test]
    public function nonStatusChangeDoesNotCreateAuditLog(): void
    {
        $user = UserStory::createOne([
            'email' => 'original@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $user->changeEmail('changed@example.com');
        $this->em->persist($user);
        $this->em->flush();

        $auditLogs = $this->auditLogRepository->findAll();

        self::assertCount(0, $auditLogs);
    }

    #[Test]
    public function nonAuditableEntityDoesNotCreateAuditLog(): void
    {
        $auditLog = new EntityStateAuditLog(
            entityType: 'TestEntity',
            entityId: Uuid::v7(),
            oldState: 'old',
            newState: 'new',
            changedBy: 'test',
            reason: 'test',
        );

        $this->em->persist($auditLog);
        $this->em->flush();

        $auditLogs = $this->auditLogRepository->findAll();

        self::assertCount(1, $auditLogs);
    }

    #[Test]
    public function multipleStatusChangesInOneFlush(): void
    {
        $user1 = UserStory::createOne(['status' => UserStatus::ACTIVE]);
        $user2 = UserStory::createOne(['status' => UserStatus::ACTIVE]);

        $this->auditLogContext->setContext('BatchWorker', 'batch processing');

        $this->userStatusWorkflow->apply($user1, 'lock');
        $this->userStatusWorkflow->apply($user2, 'ban');
        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->flush();

        $auditLogs = $this->auditLogRepository->findAll();

        self::assertCount(2, $auditLogs);
    }
}
