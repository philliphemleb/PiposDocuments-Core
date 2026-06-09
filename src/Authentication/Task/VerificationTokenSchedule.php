<?php

declare(strict_types=1);

namespace App\Authentication\Task;

use Override;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('verification_tokens')]
readonly class VerificationTokenSchedule implements ScheduleProviderInterface
{
    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(
            RecurringMessage::every('30 minutes', new DispatchPendingVerificationTokensTask()),
            RecurringMessage::cron('0 4 * * *', new CleanupExpiredVerificationTokensTask()),
        );
    }
}
