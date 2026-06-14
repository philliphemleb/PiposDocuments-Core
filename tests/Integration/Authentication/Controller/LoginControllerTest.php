<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Controller;

use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendLoginTokenMessage;
use App\Authentication\Story\UserStory;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class LoginControllerTest extends WebTestCase
{
    use InteractsWithMessenger;

    #[Test]
    public function loginWithValidActiveUserReturns202AndQueuesMessage(): void
    {
        $client = self::createClient();

        UserStory::createOne([
            'email' => 'login@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'login@example.com']),
        );

        self::assertResponseStatusCodeSame(202);

        $this->transport('async')
            ->queue()
            ->assertContains(SendLoginTokenMessage::class, 1);
    }

    #[Test]
    public function loginWithNonexistentUserReturns401(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'nobody@example.com']),
        );

        self::assertResponseStatusCodeSame(401);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function loginWithUnverifiedUserReturns401(): void
    {
        $client = self::createClient();

        UserStory::createOne([
            'email' => 'unverified@example.com',
            'status' => UserStatus::UNVERIFIED_EMAIL,
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'unverified@example.com']),
        );

        self::assertResponseStatusCodeSame(401);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function loginWithLockedUserReturns401(): void
    {
        $client = self::createClient();

        UserStory::createOne([
            'email' => 'locked@example.com',
            'status' => UserStatus::LOCKED,
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'locked@example.com']),
        );

        self::assertResponseStatusCodeSame(401);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function loginWithInvalidEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'not-an-email']),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function loginWithMissingEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function loginWithMaxRateLimitReturns429(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserStory::createOne([
            'email' => 'ratelimited@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        for ($i = 0; 2 > $i; ++$i) {
            $token = new VerificationToken(
                user: $user,
                type: TokenType::Login,
                token: \sprintf('%06d', random_int(0, 999999)),
                expiresAt: CarbonImmutable::now()->addMinutes(15),
            );
            $token->markAsSent();
            $em->persist($token);
        }

        $em->flush();

        $this->transport('async')->reset();

        $client->request(
            method: 'POST',
            uri: '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'ratelimited@example.com']),
        );
        self::assertResponseStatusCodeSame(429);
        $this->transport('async')->queue()->assertEmpty();
    }
}
