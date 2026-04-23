<?php

declare(strict_types=1);

namespace App\Authentication\Exception;

use App\Authentication\Enum\FailedRegistrationReason;
use RuntimeException;

final class FailedRegistrationException extends RuntimeException
{
    public function __construct(public readonly FailedRegistrationReason $reason)
    {
        parent::__construct();
    }
}
