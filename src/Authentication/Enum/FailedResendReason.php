<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum FailedResendReason
{
    case UserNotEligible;
    case TokenExpired;
    case MaxAttemptsReached;
}
