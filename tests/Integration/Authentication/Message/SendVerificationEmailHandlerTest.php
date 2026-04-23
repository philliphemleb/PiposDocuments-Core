<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Message;

use App\Authentication\Message\SendVerificationEmailHandler;
use App\Authentication\Message\SendVerificationEmailMessage;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class SendVerificationEmailHandlerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private SendVerificationEmailHandler $handler;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->handler = self::getContainer()->get(SendVerificationEmailHandler::class);
    }

    #[Test]
    public function handlerSendsEmailToRecipient(): void
    {
        ($this->handler)(new SendVerificationEmailMessage(
            email: 'verify@example.com',
            token: 'abc123testtoken',
        ));

        self::assertEmailCount(1);

        $email = self::getMailerMessage() ?? self::fail('Expected a mailer message');
        self::assertEmailAddressContains($email, 'to', 'verify@example.com');
        self::assertEmailSubjectContains($email, 'Verify your PiposDocuments email');
        self::assertEmailTextBodyContains($email, 'abc123testtoken');
        self::assertEmailTextBodyContains($email, '/verify-email?token=');
    }
}
