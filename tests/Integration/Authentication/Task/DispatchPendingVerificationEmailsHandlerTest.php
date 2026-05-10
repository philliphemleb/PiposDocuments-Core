<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Task;

use App\Authentication\Entity\EmailVerificationToken;
use App\Authentication\Entity\User;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Task\DispatchPendingVerificationEmailsHandler;
use App\Authentication\Task\DispatchPendingVerificationEmailsTask;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class DispatchPendingVerificationEmailsHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private DispatchPendingVerificationEmailsHandler $handler;
    private EntityManagerInterface $em;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->handler = self::getContainer()->get(DispatchPendingVerificationEmailsHandler::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function pendingTokensAreDispatched(): void
    {
        $user = new User(email: 'pending@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new EmailVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingVerificationEmailsTask());

        $this->transport('async')
            ->queue()
            ->assertContains(SendVerificationEmailMessage::class, 1);

        $this->em->clear();
        $refreshed = $this->em->find(EmailVerificationToken::class, $token->id);
        self::assertNotNull($refreshed?->dispatchedAt);
    }

    #[Test]
    public function alreadyDispatchedTokensAreSkipped(): void
    {
        $user = new User(email: 'dispatched@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new EmailVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $token->markAsDispatched();
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingVerificationEmailsTask());

        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function expiredTokensAreSkipped(): void
    {
        $user = new User(email: 'expired@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new EmailVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->subHour(),
        );
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingVerificationEmailsTask());

        $this->transport('async')->queue()->assertEmpty();
    }
}
