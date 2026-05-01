<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Exception\FailedResendException;
use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Repository\EmailVerificationTokenRepository;
use App\Authentication\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class ResendVerificationEmailService
{
    private const int MAX_RESEND_ATTEMPTS = 5;

    public function __construct(
        private UserRepository $userRepository,
        private EmailVerificationTokenRepository $tokenRepository,
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function resend(string $email): void
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user instanceof \App\Authentication\Entity\User || UserStatus::UNVERIFIED_EMAIL !== $user->status) {
            throw new FailedResendException(FailedResendReason::UserNotEligible);
        }

        $token = $this->tokenRepository->findValidTokenForUser($user);

        if (!$token instanceof \App\Authentication\Entity\EmailVerificationToken) {
            throw new FailedResendException(FailedResendReason::TokenExpired);
        }

        if ($token->sendAttempts >= self::MAX_RESEND_ATTEMPTS) {
            throw new FailedResendException(FailedResendReason::MaxAttemptsReached);
        }

        try {
            $this->bus->dispatch(new SendVerificationEmailMessage(
                email: $user->email,
                token: $token->token,
            ));
        } catch (TransportException $transportException) {
            $this->logger->error('Failed to dispatch resend verification email', [
                'email' => $email,
                'exception' => $transportException->getMessage(),
            ]);

            return;
        }

        $token->incrementSendAttempts();
        $token->markAsDispatched();

        $this->em->flush();
    }
}
