<?php

declare(strict_types=1);

namespace App\Authentication\Enum;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case BANNED = 'banned';
    case SHOULD_BE_UNLOCKED = 'should_be_unlocked';
    case SHOULD_BE_DELETED = 'should_be_deleted';
}
