<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Controller;

use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Story\UserStory;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginVerifyControllerTest extends WebTestCase
{
    #[Test]
    public function verifyWithValidCodeReturns200WithJwt(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserStory::createOne([
            'email' => 'verify-valid@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Login,
            token: '123456',
            expiresAt: CarbonImmutable::now()->addMinutes(15),
        );
        $em->persist($token);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'verify-valid@example.com',
                'code' => '123456',
            ]),
        );

        self::assertResponseStatusCodeSame(200);

        /** @var array{token: string} $decoded */
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $decoded);
        self::assertNotEmpty($decoded['token']);
    }

    #[Test]
    public function verifyWithWrongCodeReturns401(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserStory::createOne([
            'email' => 'verify-wrong@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Login,
            token: '123456',
            expiresAt: CarbonImmutable::now()->addMinutes(15),
        );
        $em->persist($token);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'verify-wrong@example.com',
                'code' => '654321',
            ]),
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function verifyWithExpiredCodeReturns401(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserStory::createOne([
            'email' => 'verify-expired@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Login,
            token: '123456',
            expiresAt: CarbonImmutable::now()->subMinute(),
        );
        $em->persist($token);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'verify-expired@example.com',
                'code' => '123456',
            ]),
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function verifyWithNonexistentUserReturns401(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'nobody@example.com',
                'code' => '123456',
            ]),
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function verifyWithInvalidEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'not-an-email',
                'code' => '123456',
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function verifyWithInvalidCodeFormatReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'test@example.com',
                'code' => '12345',
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function verifyWithNonNumericCodeReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'test@example.com',
                'code' => 'abcdef',
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function verifyCodeIsOneTimeUseOnly(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserStory::createOne([
            'email' => 'verify-onetime@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Login,
            token: '123456',
            expiresAt: CarbonImmutable::now()->addMinutes(15),
        );
        $em->persist($token);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'verify-onetime@example.com',
                'code' => '123456',
            ]),
        );
        self::assertResponseStatusCodeSame(200);

        $client->request(
            method: 'POST',
            uri: '/api/login/verify',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'email' => 'verify-onetime@example.com',
                'code' => '123456',
            ]),
        );
        self::assertResponseStatusCodeSame(401);
    }
}
