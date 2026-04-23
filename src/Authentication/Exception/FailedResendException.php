<?php

declare(strict_types=1);

namespace App\Authentication\Exception;

use App\Authentication\Enum\FailedResendReason;
use RuntimeException;

final class FailedResendException extends RuntimeException
{
    public function __construct(public readonly FailedResendReason $reason)
    {
        parent::__construct();
    }
}
