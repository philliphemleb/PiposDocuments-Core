<?php

declare(strict_types=1);

namespace App\Authentication\Message;

use App\Authentication\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;
use Throwable;

#[AsMessageHandler]
readonly class SendVerificationEmailHandler
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
        private EmailVerificationTokenRepository $tokenRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SendVerificationEmailMessage $message): void
    {
        $token = $this->tokenRepository->findOneByToken($message->token);

        if (!$token instanceof \App\Authentication\Entity\EmailVerificationToken || $token->sentAt instanceof \Carbon\CarbonImmutable) {
            return;
        }

        $email = new Email()
            ->to($message->email)
            ->subject('Verify your PiposDocuments email')
            ->text(\sprintf(
                "Click the link below to verify your email address:\n\n%s/verify-email?token=%s",
                $this->appUrl,
                $message->token,
            ));

        try {
            $this->mailer->send($email);
        } catch (Throwable $throwable) {
            throw new UnrecoverableMessageHandlingException(\sprintf('Failed to send verification email to %s: %s', $message->email, $throwable->getMessage()), (int) $throwable->getCode(), previous: $throwable);
        }

        $token->markAsSent();
        $this->em->flush();
    }
}
