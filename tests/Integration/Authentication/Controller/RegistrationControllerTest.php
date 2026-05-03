<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Controller;

use App\Authentication\Entity\BannedIdentifier;
use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Story\UserStory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class RegistrationControllerTest extends WebTestCase
{
    use InteractsWithMessenger;

    #[Test]
    public function registerWithValidEmailReturns201AndQueuesMessage(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'newuser@example.com']),
        );

        self::assertResponseStatusCodeSame(201);

        $this->transport('async')
            ->queue()
            ->assertContains(SendVerificationEmailMessage::class, 1);

        $messages = $this->transport('async')->queue()->messages(SendVerificationEmailMessage::class);
        $message = $messages[0] ?? self::fail('Expected a SendVerificationEmailMessage in the queue');
        self::assertSame('newuser@example.com', $message->email);
        self::assertNotEmpty($message->token);
    }

    #[Test]
    public function registerWithDuplicateEmailReturns422(): void
    {
        $client = self::createClient();

        UserStory::createOne(['email' => 'existing@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'existing@example.com']),
        );

        self::assertResponseStatusCodeSame(422);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function registerWithBannedEmailReturns422(): void
    {
        $client = self::createClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $banned = new BannedIdentifier(email: 'banned@example.com', reason: 'test');
        $em->persist($banned);
        $em->flush();

        $client->request(
            method: 'POST',
            uri: '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'banned@example.com']),
        );

        self::assertResponseStatusCodeSame(422);
        $this->transport('async')->queue()->assertEmpty();
    }

    #[Test]
    public function registerWithInvalidEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'not-an-email']),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function registerWithMissingEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([]),
        );

        self::assertResponseStatusCodeSame(422);
    }
}
