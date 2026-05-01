<?php

declare(strict_types=1);

namespace App\Authentication\Service;

use App\Authentication\Entity\EmailVerificationToken;
use App\Authentication\Entity\User;
use App\Authentication\Enum\FailedRegistrationReason;
use App\Authentication\Enum\UserStatus;
use App\Authentication\Exception\FailedRegistrationException;
use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Repository\BannedIdentifierRepository;
use App\Authentication\Repository\UserRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private UserRepository $userRepository,
        private BannedIdentifierRepository $bannedIdentifierRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function register(string $email): void
    {
        if ($this->bannedIdentifierRepository->findOneByEmail($email) instanceof \App\Authentication\Entity\BannedIdentifier) {
            throw new FailedRegistrationException(FailedRegistrationReason::EmailBanned);
        }

        if ($this->userRepository->findOneByEmail($email) instanceof User) {
            throw new FailedRegistrationException(FailedRegistrationReason::EmailAlreadyRegistered);
        }

        $user = new User(email: $email, status: UserStatus::UNVERIFIED_EMAIL);
        $token = new EmailVerificationToken(
            user: $user,
            token: bin2hex(random_bytes(32)),
            expiresAt: CarbonImmutable::now()->addHours(24),
        );

        $this->em->persist($user);
        $this->em->persist($token);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new FailedRegistrationException(FailedRegistrationReason::EmailAlreadyRegistered);
        }

        try {
            $this->bus->dispatch(new SendVerificationEmailMessage(
                email: $user->email,
                token: $token->token,
            ));
            $token->markAsDispatched();
            $this->em->flush();
        } catch (TransportException $transportException) {
            $this->logger->error('Failed to dispatch verification email after registration; scheduler will retry', [
                'email' => $email,
                'exception' => $transportException->getMessage(),
            ]);
        }
    }
}
