<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum UserRole: string
{
    case USER = 'user';
    case ADMIN = 'admin';
}
