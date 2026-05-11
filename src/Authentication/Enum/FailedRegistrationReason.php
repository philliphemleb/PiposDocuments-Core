<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum FailedRegistrationReason
{
    case EmailBanned;
    case EmailAlreadyRegistered;
}
