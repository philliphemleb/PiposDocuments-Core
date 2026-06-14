<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum TokenType: string
{
    case Registration = 'registration';
    case Login = 'login';
}
