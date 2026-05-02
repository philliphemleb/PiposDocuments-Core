<?php

declare(strict_types=1);

namespace App\Authentication\Message;

use App\Authentication\Entity\EmailVerificationToken;
use App\Authentication\Repository\EmailVerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;

#[AsMessageHandler]
final readonly class SendVerificationEmailHandler
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
        private EmailVerificationTokenRepository $tokenRepository,
        private EntityManagerInterface $em,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendVerificationEmailMessage $message): void
    {
        $token = $this->tokenRepository->findOneByToken($message->token);

        if (!$token instanceof EmailVerificationToken || $token->sentAt instanceof CarbonImmutable) {
            return;
        }

        $this->logger->info('Sending verification email', [
            'email' => $message->email,
            'token' => $message->token,
        ]);

        $email = $this->getEmailTemplate($message);

        try {
            $this->mailer->send($email);
            $this->logger->info('Verification email sent successfully', [
                'email' => $message->email,
                'token' => $message->token,
            ]);
        } catch (Throwable $throwable) {
            $this->logger->error('Failed to send verification email', [
                'email' => $message->email,
                'token' => $message->token,
                'error' => $throwable->getMessage(),
            ]);
            throw new UnrecoverableMessageHandlingException(\sprintf('Failed to send verification email to %s: %s', $message->email, $throwable->getMessage()), (int) $throwable->getCode(), previous: $throwable);
        }

        $token->markAsSent();
        $this->em->flush();
    }

    private function getEmailTemplate(SendVerificationEmailMessage $message): Email
    {
        $expiryText = match (true) {
            $message->expiresInMinutes >= 60 && 0 === $message->expiresInMinutes % 60 => \sprintf('%d hour(s)', $message->expiresInMinutes / 60),
            default => \sprintf('%d minutes', $message->expiresInMinutes),
        };

        $html = $this->twig->render('emails/verification/body.html.twig', [
            'token' => $message->token,
            'app_url' => $this->appUrl,
            'expires_in_minutes' => $message->expiresInMinutes,
        ]);

        return new Email()
            ->to($message->email)
            ->subject('Verify your PiposDocuments email')
            ->html($html)
            ->text(\sprintf(
                "Click the link below to verify your email address:\n\n%s/verify-email?token=%s\n\nThis link will expire in %s.",
                $this->appUrl,
                $message->token,
                $expiryText,
            ));
    }
}
