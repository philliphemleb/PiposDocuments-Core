<?php

declare(strict_types=1);

namespace App\Authentication\Task;

use App\Authentication\Message\SendVerificationEmailMessage;
use App\Authentication\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class DispatchPendingVerificationEmailsHandler
{
    public function __construct(
        private EmailVerificationTokenRepository $tokenRepository,
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchPendingVerificationEmailsTask $task): void
    {
        $tokens = $this->tokenRepository->findPendingDispatch();

        foreach ($tokens as $token) {
            try {
                $this->bus->dispatch(new SendVerificationEmailMessage(
                    email: $token->user->email,
                    token: $token->token,
                ));
                $token->markAsDispatched();
            } catch (TransportException $e) {
                $this->logger->error('Scheduler failed to dispatch pending verification email', [
                    'email' => $token->user->email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->em->flush();
    }
}
