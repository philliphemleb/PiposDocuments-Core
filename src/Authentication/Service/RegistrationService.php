<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\DTO\RegistrationResult;
use App\Authentication\DTO\ResendResult;
use App\Authentication\Entity\BannedIdentifier;
use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\FailedRegistrationReason;
use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Enum\TokenType;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendVerificationTokenMessage;
use App\Authentication\Repository\BannedIdentifierRepository;
use App\Authentication\Repository\UserRepository;
use App\Authentication\Repository\VerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RegistrationService
{
    private const int MAX_DAILY_TOKENS = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private UserRepository $userRepository,
        private BannedIdentifierRepository $bannedIdentifierRepository,
        private VerificationTokenRepository $tokenRepository,
        private VerificationTokenService $tokenService,
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

        $token = $this->tokenService->createRegistrationToken($user);
        $this->em->persist($token);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->logger->warning('Registration rejected: concurrent duplicate email', [
                'email' => $email,
                'reason' => FailedRegistrationReason::EmailAlreadyRegistered->name,
            ]);

            return RegistrationResult::failed(FailedRegistrationReason::EmailAlreadyRegistered);
        }

        $this->dispatchVerificationToken($user->email, $token);

        $this->logger->info('User registered successfully', [
            'email' => $email,
            'user_id' => $user->id->toRfc4122(),
        ]);

        return RegistrationResult::success();
    }

    public function resendRegistrationVerification(string $email): ResendResult
    {
        $newToken = null;

        $result = $this->em->wrapInTransaction(function () use ($email, &$newToken): ResendResult {
            $user = $this->userRepository->findOneByEmailForUpdate($email);

            if (!$user instanceof User || UserStatus::UNVERIFIED_EMAIL !== $user->status) {
                $this->logger->warning('Resend rejected: user not eligible', [
                    'email' => $email,
                    'reason' => FailedResendReason::UserNotEligible->name,
                ]);

                return ResendResult::failed(FailedResendReason::UserNotEligible);
            }

            $sentInLast24h = $this->tokenRepository->countSentTokensForUserSince(
                $user,
                CarbonImmutable::now()->subDay(),
            );

            if ($sentInLast24h >= self::MAX_DAILY_TOKENS) {
                $this->logger->warning('Resend rejected: max daily tokens reached', [
                    'email' => $email,
                    'reason' => FailedResendReason::MaxAttemptsReached->name,
                    'sent_count' => $sentInLast24h,
                ]);

                return ResendResult::failed(FailedResendReason::MaxAttemptsReached);
            }

            $this->tokenService->invalidateExistingForUser($user, TokenType::Registration);

            $newToken = $this->tokenService->createRegistrationToken($user);
            $this->em->persist($newToken);
            $this->em->flush();

            return ResendResult::success();
        });

        if ($result->success && $newToken instanceof VerificationToken) {
            $this->dispatchVerificationToken($newToken->user->email, $newToken);
        }

        return $result;
    }

    /**
     * @return array<string, ResendResult>
     */
    public function dispatchPendingVerifications(int $limit = 50): array
    {
        $tokens = $this->tokenRepository->findPendingDispatch($limit);
        $results = [];

        foreach ($tokens as $token) {
            $this->dispatchVerificationToken($token->user->email, $token);
            $results[$token->user->email] = ResendResult::success();
        }

        return $results;
    }

    /**
     * @return int Number of expired tokens deleted
     */
    public function cleanupExpiredTokens(int $batchSize = 50): int
    {
        $totalDeleted = 0;

        do {
            $deleted = $this->tokenRepository->deleteExpiredBatch($batchSize);
            $totalDeleted += $deleted;
        } while ($deleted === $batchSize);

        return $totalDeleted;
    }

    private function dispatchVerificationToken(string $userEmail, VerificationToken $token): void
    {
        try {
            $token->markAsDispatched();
            $this->em->flush();

            $this->bus->dispatch(new SendVerificationTokenMessage(
                email: $userEmail,
                token: $token->token,
                expiresInMinutes: VerificationTokenService::REGISTRATION_TOKEN_EXPIRY_MINUTES,
            ));

            $this->logger->info('Verification token dispatched', [
                'email' => $userEmail,
                'token_type' => $token->type->name,
            ]);
        } catch (TransportException $transportException) {
            $this->logger->error('Failed to dispatch verification token; scheduler will retry', [
                'email' => $userEmail,
                'exception' => $transportException->getMessage(),
            ]);
        }
    }
}
