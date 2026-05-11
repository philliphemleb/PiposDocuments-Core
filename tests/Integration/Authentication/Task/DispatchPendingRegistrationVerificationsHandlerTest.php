<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Task;

use App\Authentication\Entity\RegistrationVerificationToken;
use App\Authentication\Entity\User;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendRegistrationVerificationMessage;
use App\Authentication\Task\DispatchPendingRegistrationVerificationsHandler;
use App\Authentication\Task\DispatchPendingRegistrationVerificationsTask;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class DispatchPendingRegistrationVerificationsHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private DispatchPendingRegistrationVerificationsHandler $handler;

    private EntityManagerInterface $em;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->handler = self::getContainer()->get(DispatchPendingRegistrationVerificationsHandler::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function pendingTokensAreDispatched(): void
    {
        $user = new User(email: 'pending@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new RegistrationVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingRegistrationVerificationsTask());

        $this->transport('async')
            ->queue()
            ->assertContains(SendRegistrationVerificationMessage::class, 1);

        $this->em->clear();
        $refreshed = $this->em->find(RegistrationVerificationToken::class, $token->id);
        self::assertNotNull($refreshed?->dispatchedAt);
    }

    #[Test]
    public function alreadyDispatchedTokensAreSkipped(): void
    {
        $user = new User(email: 'dispatched@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new RegistrationVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $token->markAsDispatched();

        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingRegistrationVerificationsTask());

        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function expiredTokensAreSkipped(): void
    {
        $user = new User(email: 'expired@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new RegistrationVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->subHour(),
        );
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingRegistrationVerificationsTask());

        $this->transport('async')->queue()->assertEmpty();
    }
}
