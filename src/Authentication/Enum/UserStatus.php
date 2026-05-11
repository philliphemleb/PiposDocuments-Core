<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum UserStatus: string
{
    case UNVERIFIED_EMAIL = 'unverified_email';
    case TO_BE_VERIFIED_EMAIL = 'to_be_verified_email';
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case BANNED = 'banned';
    case TO_BE_UNLOCKED = 'to_be_unlocked';
    case TO_BE_DELETED = 'to_be_deleted';
}
