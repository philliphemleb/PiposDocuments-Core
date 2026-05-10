<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Controller;

use App\Authentication\Entity\RegistrationVerificationToken;
use App\Authentication\Entity\User;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendRegistrationVerificationMessage;
use App\Authentication\Story\RegistrationVerificationTokenStory;
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

        RegistrationVerificationTokenStory::createOne([
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
            ->assertContains(SendRegistrationVerificationMessage::class, 1);
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

        RegistrationVerificationTokenStory::createOne([
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
    public function resendInvalidatesOldTokenAndCreatesNewOne(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User(email: 'invalidate@example.com', status: UserStatus::UNVERIFIED_EMAIL);
        $em->persist($user);

        $oldToken = new RegistrationVerificationToken(
            user: $user,
            token: 'old-test-token',
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $em->persist($oldToken);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'invalidate@example.com']),
        );

        self::assertResponseStatusCodeSame(202);

        $this->transport('async')
            ->queue()
            ->assertContains(SendRegistrationVerificationMessage::class, 1);

        $messages = $this->transport('async')->queue()->messages(SendRegistrationVerificationMessage::class);
        $message = $messages[0] ?? self::fail('Expected a message');

        self::assertNotSame('old-test-token', $message->token, 'New token should be different from old');

        $em->clear();
        $refreshedOld = $em->find(RegistrationVerificationToken::class, $oldToken->id);
        self::assertNotNull($refreshedOld?->expiresAt);
        self::assertTrue($refreshedOld->expiresAt <= CarbonImmutable::now(), 'Old token should be invalidated');
    }

    #[Test]
    public function resendWithMaxDailyTokensReachedReturns429(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User(
            email: 'maxed@example.com',
            status: UserStatus::UNVERIFIED_EMAIL,
        );
        $em->persist($user);

        for ($i = 0; 5 > $i; ++$i) {
            $token = new RegistrationVerificationToken(
                user: $user,
                token: bin2hex(random_bytes(16)),
                expiresAt: CarbonImmutable::now()->addHour(),
            );
            $token->markAsSent();
            $em->persist($token);
        }

        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/resend-verification-email',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'maxed@example.com']),
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
