<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Task;

use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendVerificationTokenMessage;
use App\Authentication\Task\DispatchPendingVerificationTokensHandler;
use App\Authentication\Task\DispatchPendingVerificationTokensTask;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class DispatchPendingVerificationTokensHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private DispatchPendingVerificationTokensHandler $handler;

    private EntityManagerInterface $em;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->handler = self::getContainer()->get(DispatchPendingVerificationTokensHandler::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function pendingTokensAreDispatched(): void
    {
        $user = new User(email: 'pending@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Registration,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingVerificationTokensTask());

        $this->transport('async')
            ->queue()
            ->assertContains(SendVerificationTokenMessage::class, 1);

        $this->em->clear();
        $refreshed = $this->em->find(VerificationToken::class, $token->id);
        self::assertNotNull($refreshed?->dispatchedAt);
    }

    #[Test]
    public function alreadyDispatchedTokensAreSkipped(): void
    {
        $user = new User(email: 'dispatched@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Registration,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $token->markAsDispatched();

        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingVerificationTokensTask());

        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function expiredTokensAreSkipped(): void
    {
        $user = new User(email: 'expired@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Registration,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->subHour(),
        );
        $this->em->persist($token);
        $this->em->flush();

        ($this->handler)(new DispatchPendingVerificationTokensTask());

        $this->transport('async')->queue()->assertEmpty();
    }
}
