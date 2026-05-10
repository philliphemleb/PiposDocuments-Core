<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Controller;

use App\Authentication\Entity\EmailVerificationToken;
use App\Authentication\Entity\User;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Story\EmailVerificationTokenStory;
use App\Authentication\Story\UserStory;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class ResendControllerTest extends WebTestCase
{
    use InteractsWithMessenger;

    #[Test]
    public function resendWithValidUnverifiedUserReturns202AndQueuesMessage(): void
    {
        $client = self::createClient();

        $user = UserStory::createOne([
            'status' => UserStatus::UNVERIFIED_EMAIL,
        ]);

        EmailVerificationTokenStory::createOne([
            'user' => $user,
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => $user->email]),
        );

        self::assertResponseStatusCodeSame(202);

        $this->transport('async')
            ->queue()
            ->assertContains(SendVerificationEmailMessage::class, 1);
    }

    #[Test]
    public function resendForNonexistentUserReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'nobody@example.com']),
        );

        self::assertResponseStatusCodeSame(422);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function resendForAlreadyVerifiedUserReturns422(): void
    {
        $client = self::createClient();

        $user = UserStory::createOne([
            'status' => UserStatus::ACTIVE,
        ]);

        EmailVerificationTokenStory::createOne([
            'user' => $user,
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => $user->email]),
        );

        self::assertResponseStatusCodeSame(422);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function resendWithExpiredTokenReturns422(): void
    {
        $client = self::createClient();

        $user = UserStory::createOne([
            'status' => UserStatus::UNVERIFIED_EMAIL,
        ]);

        EmailVerificationTokenStory::createOne([
            'user' => $user,
            'expiresAt' => CarbonImmutable::now()->subHour(),
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => $user->email]),
        );

        self::assertResponseStatusCodeSame(422);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function resendWithMaxAttemptsReachedReturns429(): void
    {
        $client = self::createClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User(
            email: 'maxed@example.com',
            status: UserStatus::UNVERIFIED_EMAIL,
        );
        $em->persist($user);
        $em->flush();

        $token = new EmailVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $token->markAsDispatched();
        $token->incrementSendAttempts();
        $token->incrementSendAttempts();
        $token->incrementSendAttempts();
        $em->persist($token);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => $user->email]),
        );

        self::assertResponseStatusCodeSame(429);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function resendWithInvalidEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'not-an-email']),
        );

        self::assertResponseStatusCodeSame(422);
    }
}
