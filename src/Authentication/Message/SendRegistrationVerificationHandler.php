<?php

declare(strict_types=1);

namespace App\Authentication\Message;

use App\Authentication\Entity\RegistrationVerificationToken;
use App\Authentication\Repository\RegistrationVerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;

#[AsMessageHandler]
final readonly class SendRegistrationVerificationHandler
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
        private RegistrationVerificationTokenRepository $tokenRepository,
        private EntityManagerInterface $em,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendRegistrationVerificationMessage $message): void
    {
        $token = $this->tokenRepository->findOneByToken($message->token);

        if (!$token instanceof RegistrationVerificationToken
            || $token->sentAt instanceof CarbonImmutable
            || $token->expiresAt->isPast()
        ) {
            return;
        }

        $this->logger->info('Sending registration verification email', [
            'email' => $message->email,
            'token_id' => $token->id->toString(),
        ]);

        $email = $this->getEmailTemplate($message);

        try {
            $this->mailer->send($email);
            $this->logger->info('Registration verification email sent successfully', [
                'email' => $message->email,
                'token_id' => $token->id->toString(),
            ]);
        } catch (TransportException $transportException) {
            $this->logger->warning('Transient mailer failure, will retry', [
                'email' => $message->email,
                'token_id' => $token->id->toString(),
                'error' => $transportException->getMessage(),
            ]);
            throw $transportException;
        } catch (Throwable $throwable) {
            $this->logger->error('Permanent mailer failure, not retrying', [
                'email' => $message->email,
                'token_id' => $token->id->toString(),
                'error' => $throwable->getMessage(),
            ]);
            throw new UnrecoverableMessageHandlingException(\sprintf('Failed to send registration verification email to %s: %s', $message->email, $throwable->getMessage()), (int) $throwable->getCode(), previous: $throwable);
        }

        $token->markAsSent();
        $this->em->flush();
    }

    private function getEmailTemplate(SendRegistrationVerificationMessage $message): Email
    {
        $expiryText = match (true) {
            $message->expiresInMinutes >= 60 && 0 === $message->expiresInMinutes % 60 => \sprintf('%d hour(s)', $message->expiresInMinutes / 60),
            default => \sprintf('%d minutes', $message->expiresInMinutes),
        };

        $html = $this->twig->render('emails/registration_verification/body.html.twig', [
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
