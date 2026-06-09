<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Message;

use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use App\Authentication\Message\SendVerificationTokenHandler;
use App\Authentication\Message\SendVerificationTokenMessage;
use Carbon\CarbonImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class SendVerificationTokenHandlerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private SendVerificationTokenHandler $handler;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->handler = self::getContainer()->get(SendVerificationTokenHandler::class);
    }

    #[Test]
    public function handlerSendsEmailToRecipient(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = new User(email: 'verify@example.com');
        $em->persist($user);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Registration,
            token: 'abc123testtoken',
            expiresAt: CarbonImmutable::now()->addDay(),
        );
        $em->persist($token);
        $em->flush();

        ($this->handler)(new SendVerificationTokenMessage(
            email: 'verify@example.com',
            token: 'abc123testtoken',
            expiresInMinutes: 60,
        ));

        self::assertEmailCount(1);

        $email = self::getMailerMessage() ?? self::fail('Expected a mailer message');
        self::assertEmailAddressContains($email, 'to', 'verify@example.com');
        self::assertEmailSubjectContains($email, 'Verify your PiposDocuments email');
        self::assertEmailTextBodyContains($email, 'abc123testtoken');
        self::assertEmailTextBodyContains($email, '/verify-email?token=');
    }
}
