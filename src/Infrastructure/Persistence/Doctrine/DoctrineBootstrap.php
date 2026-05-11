<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Carbon\Doctrine\DateTimeDefaultPrecision;

final readonly class DoctrineBootstrap
{
    public static function configureCarbonPrecision(): void
    {
        DateTimeDefaultPrecision::set(0);
    }
}
