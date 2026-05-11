<?php

declare(strict_types=1);

namespace App\Authentication\Task;

use App\Authentication\Enum\FailedResendReason;
use App\Authentication\Service\RegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DispatchPendingRegistrationVerificationsHandler
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private RegistrationService $registrationService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchPendingRegistrationVerificationsTask $task): void
    {
        do {
            $results = $this->registrationService->dispatchPendingVerifications(self::BATCH_SIZE);
            $count = \count($results);

            foreach ($results as $email => $result) {
                if (!$result->success && $result->reason instanceof FailedResendReason) {
                    $this->logger->warning('Scheduler: pending registration verification dispatch failed', [
                        'email' => $email,
                        'reason' => $result->reason->name,
                    ]);
                }
            }
        } while (self::BATCH_SIZE === $count);

        if ($count > 0) {
            $this->logger->info('Scheduler: processed pending registration verifications', [
                'count' => $count,
            ]);
        }
    }
}
