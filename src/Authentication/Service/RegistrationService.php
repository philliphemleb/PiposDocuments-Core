<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\DTO\RegistrationResult;
use App\Authentication\DTO\ResendResult;
use App\Authentication\Entity\BannedIdentifier;
use App\Authentication\Entity\EmailVerificationToken;
use App\Authentication\Entity\User;
use App\Authentication\Enum\FailedRegistrationReason;
use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Repository\BannedIdentifierRepository;
use App\Authentication\Repository\EmailVerificationTokenRepository;
use App\Authentication\Repository\UserRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RegistrationService
{
    public const int VERIFICATION_TOKEN_EXPIRY_MINUTES = 60;
    private const int MAX_RESEND_ATTEMPTS = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private UserRepository $userRepository,
        private BannedIdentifierRepository $bannedIdentifierRepository,
        private EmailVerificationTokenRepository $tokenRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function register(string $email): RegistrationResult
    {
        if ($this->bannedIdentifierRepository->findOneByEmail($email) instanceof BannedIdentifier) {
            $this->logger->warning('Registration rejected: email is banned', [
                'email' => $email,
                'reason' => FailedRegistrationReason::EmailBanned->name,
            ]);

            return RegistrationResult::failed(FailedRegistrationReason::EmailBanned);
        }

        if ($this->userRepository->findOneByEmail($email) instanceof User) {
            $this->logger->warning('Registration rejected: email already registered', [
                'email' => $email,
                'reason' => FailedRegistrationReason::EmailAlreadyRegistered->name,
            ]);

            return RegistrationResult::failed(FailedRegistrationReason::EmailAlreadyRegistered);
        }

        $user = new User(email: $email, status: UserStatus::UNVERIFIED_EMAIL);
        $this->em->persist($user);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->logger->warning('Registration rejected: concurrent duplicate email', [
                'email' => $email,
                'reason' => FailedRegistrationReason::EmailAlreadyRegistered->name,
            ]);

            return RegistrationResult::failed(FailedRegistrationReason::EmailAlreadyRegistered);
        }

        $token = new EmailVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addMinutes(self::VERIFICATION_TOKEN_EXPIRY_MINUTES),
        );

        $this->em->persist($token);
        $this->em->flush();

        $this->dispatchVerificationEmail($user->email, $token);

        $this->logger->info('User registered successfully', [
            'email' => $email,
            'user_id' => $user->id->toRfc4122(),
        ]);

        return RegistrationResult::success();
    }

    public function resendVerificationMail(string $email): ResendResult
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user instanceof User || UserStatus::UNVERIFIED_EMAIL !== $user->status) {
            $this->logger->warning('Resend rejected: user not eligible', [
                'email' => $email,
                'reason' => FailedResendReason::UserNotEligible->name,
            ]);

            return ResendResult::failed(FailedResendReason::UserNotEligible);
        }

        $token = $this->tokenRepository->findValidTokenForUser($user);

        if (!$token instanceof EmailVerificationToken) {
            $this->logger->warning('Resend rejected: no valid token', [
                'email' => $email,
                'reason' => FailedResendReason::TokenExpired->name,
            ]);

            return ResendResult::failed(FailedResendReason::TokenExpired);
        }

        if ($token->sendAttempts >= self::MAX_RESEND_ATTEMPTS) {
            $this->logger->warning('Resend rejected: max attempts reached', [
                'email' => $email,
                'reason' => FailedResendReason::MaxAttemptsReached->name,
            ]);

            return ResendResult::failed(FailedResendReason::MaxAttemptsReached);
        }

        $this->dispatchVerificationEmail($user->email, $token);

        return ResendResult::success();
    }

    /**
     * @return array<string, ResendResult>
     */
    public function dispatchPendingEmails(int $limit = 50): array
    {
        $tokens = $this->tokenRepository->findPendingDispatch($limit);
        $results = [];

        foreach ($tokens as $token) {
            $this->dispatchVerificationEmail($token->user->email, $token);
            $results[$token->user->email] = ResendResult::success();
        }

        return $results;
    }

    private function dispatchVerificationEmail(string $userEmail, EmailVerificationToken $token): void
    {
        try {
            $this->bus->dispatch(new SendVerificationEmailMessage(
                email: $userEmail,
                token: $token->token,
                expiresInMinutes: self::VERIFICATION_TOKEN_EXPIRY_MINUTES,
            ));

            $token->markAsDispatched();
            $token->incrementSendAttempts();

            $this->em->flush();

            $this->logger->info('Verification email dispatched', [
                'email' => $userEmail,
            ]);
        } catch (TransportException $transportException) {
            $this->logger->error('Failed to dispatch verification email; scheduler will retry', [
                'email' => $userEmail,
                'exception' => $transportException->getMessage(),
            ]);
        }
    }
}
