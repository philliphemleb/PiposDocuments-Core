<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication\Message;

use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use App\Authentication\Message\SendLoginTokenHandler;
use App\Authentication\Message\SendLoginTokenMessage;
use Carbon\CarbonImmutable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class SendLoginTokenHandlerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private SendLoginTokenHandler $handler;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->handler = self::getContainer()->get(SendLoginTokenHandler::class);
    }

    #[Test]
    public function handlerSendsEmailWithCodeToRecipient(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = new User(email: 'login-code@example.com');
        $em->persist($user);

        $token = new VerificationToken(
            user: $user,
            type: TokenType::Login,
            token: '123456',
            expiresAt: CarbonImmutable::now()->addMinutes(15),
        );
        $em->persist($token);
        $em->flush();

        ($this->handler)(new SendLoginTokenMessage(
            email: 'login-code@example.com',
            code: '123456',
            expiresInMinutes: 15,
        ));

        self::assertEmailCount(1);

        $email = self::getMailerMessage() ?? self::fail('Expected a mailer message');
        self::assertEmailAddressContains($email, 'to', 'login-code@example.com');
        self::assertEmailSubjectContains($email, 'Your PiposDocuments login code');
        self::assertEmailTextBodyContains($email, '123456');
    }
}
