<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\DTO\LoginResult;
use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\FailedLoginReason;
use App\Authentication\Enum\TokenType;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Message\SendLoginTokenMessage;
use App\Authentication\Repository\UserRepository;
use App\Authentication\Repository\VerificationTokenRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class LoginService
{
    private const int MAX_LOGIN_CODES = 2;

    private const int RATE_LIMIT_MINUTES = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private UserRepository $userRepository,
        private VerificationTokenRepository $tokenRepository,
        private VerificationTokenService $tokenService,
        private JWTTokenManagerInterface $jwtManager,
        private LoggerInterface $logger,
    ) {
    }

    public function requestLoginCode(string $email): LoginResult
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user instanceof User) {
            $this->logger->warning('Login code rejected: user not found', [
                'email' => $email,
            ]);

            return LoginResult::failed(FailedLoginReason::UserNotFound);
        }

        if (UserStatus::ACTIVE !== $user->status) {
            $this->logger->warning('Login code rejected: user not active', [
                'email' => $email,
                'status' => $user->status->name,
            ]);

            return LoginResult::failed(FailedLoginReason::UserNotActive);
        }

        $sentInLastWindow = $this->tokenRepository->countSentTokensForUserSince(
            $user,
            CarbonImmutable::now()->subMinutes(self::RATE_LIMIT_MINUTES),
        );

        if ($sentInLastWindow >= self::MAX_LOGIN_CODES) {
            $this->logger->warning('Login code rejected: max login codes reached', [
                'email' => $email,
                'sent_count' => $sentInLastWindow,
            ]);

            return LoginResult::failed(FailedLoginReason::MaxAttemptsReached);
        }

        $this->tokenService->invalidateExistingForUser($user, TokenType::Login);

        $token = $this->tokenService->createLoginToken($user);
        $this->em->persist($token);
        $this->em->flush();

        $this->dispatchLoginToken($user, $token);

        $this->logger->info('Login code requested', [
            'email' => $email,
        ]);

        return LoginResult::success(token: '');
    }

    public function verifyLoginCode(string $email, string $code): LoginResult
    {
        $result = null;

        $this->em->wrapInTransaction(function () use ($email, $code, &$result): void {
            $user = $this->userRepository->findOneByEmailForUpdate($email);

            if (!$user instanceof User || UserStatus::ACTIVE !== $user->status) {
                $this->logger->warning('Login verify rejected: user not eligible', [
                    'email' => $email,
                ]);

                $result = LoginResult::failed(
                    !$user instanceof User ? FailedLoginReason::UserNotFound : FailedLoginReason::UserNotActive,
                );

                return;
            }

            $token = $this->tokenRepository->findValidTokenForUserByType($user, TokenType::Login);

            if (!$token instanceof VerificationToken) {
                $this->logger->warning('Login verify rejected: no valid code', [
                    'email' => $email,
                ]);

                $result = LoginResult::failed(FailedLoginReason::InvalidCode);

                return;
            }

            if ($token->expiresAt->isPast()) {
                $this->logger->warning('Login verify rejected: code expired', [
                    'email' => $email,
                ]);

                $result = LoginResult::failed(FailedLoginReason::CodeExpired);

                return;
            }

            if ($token->token !== $code) {
                $this->logger->warning('Login verify rejected: invalid code', [
                    'email' => $email,
                ]);

                $result = LoginResult::failed(FailedLoginReason::InvalidCode);

                return;
            }

            $token->invalidate();
            $this->em->flush();

            $jwt = $this->jwtManager->create($user);

            $this->logger->info('Login successful', [
                'email' => $email,
                'user_id' => $user->id->toRfc4122(),
            ]);

            $result = LoginResult::success(token: $jwt);
        });

        return $result ?? LoginResult::failed(FailedLoginReason::InvalidCode);
    }

    private function dispatchLoginToken(User $user, VerificationToken $token): void
    {
        try {
            $token->markAsDispatched();
            $this->em->flush();

            $this->bus->dispatch(new SendLoginTokenMessage(
                email: $user->email,
                code: $token->token,
                expiresInMinutes: VerificationTokenService::LOGIN_TOKEN_EXPIRY_MINUTES,
            ));

            $this->logger->info('Login token dispatched', [
                'email' => $user->email,
            ]);
        } catch (TransportException $transportException) {
            $this->logger->error('Failed to dispatch login token; scheduler will retry', [
                'email' => $user->email,
                'exception' => $transportException->getMessage(),
            ]);
        }
    }
}
