<?php

declare(strict_types=1);

namespace App\Authentication\Task;

use App\Authentication\Service\RegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CleanupExpiredVerificationTokensHandler
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private RegistrationService $registrationService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupExpiredVerificationTokensTask $task): void
    {
        $deleted = $this->registrationService->cleanupExpiredTokens(self::BATCH_SIZE);

        if ($deleted > 0) {
            $this->logger->info('Cleaned up expired verification tokens', [
                'deleted' => $deleted,
            ]);
        }
    }
}
