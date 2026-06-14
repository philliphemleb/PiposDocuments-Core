<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum FailedLoginReason
{
    case UserNotFound;
    case UserNotActive;
    case InvalidCode;
    case CodeExpired;
    case MaxAttemptsReached;
}
