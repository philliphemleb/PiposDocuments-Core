<?php

declare(strict_types=1);

namespace App\Authentication\Message;

use App\Authentication\Entity\VerificationToken;
use App\Authentication\Repository\VerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;

#[AsMessageHandler]
final readonly class SendLoginTokenHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private VerificationTokenRepository $tokenRepository,
        private EntityManagerInterface $em,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendLoginTokenMessage $message): void
    {
        $token = $this->tokenRepository->findOneByToken($message->code);

        if (!$token instanceof VerificationToken
            || $token->sentAt instanceof CarbonImmutable
            || $token->expiresAt->isPast()
        ) {
            return;
        }

        $this->logger->info('Sending login code email', [
            'email' => $message->email,
            'token_id' => $token->id->toString(),
        ]);

        $email = $this->getEmailTemplate($message);

        try {
            $this->mailer->send($email);
            $this->logger->info('Login code email sent successfully', [
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
            throw new UnrecoverableMessageHandlingException(\sprintf('Failed to send login code to %s: %s', $message->email, $throwable->getMessage()), (int) $throwable->getCode(), previous: $throwable);
        }

        $token->markAsSent();
        $this->em->flush();
    }

    private function getEmailTemplate(SendLoginTokenMessage $message): Email
    {
        $html = $this->twig->render('emails/login_verification/body.html.twig', [
            'code' => $message->code,
            'expires_in_minutes' => $message->expiresInMinutes,
        ]);

        return new Email()
            ->to($message->email)
            ->subject('Your PiposDocuments login code')
            ->html($html)
            ->text(\sprintf(
                "Your login code is: %s\n\nThis code will expire in %d minutes.",
                $message->code,
                $message->expiresInMinutes,
            ));
    }
}
