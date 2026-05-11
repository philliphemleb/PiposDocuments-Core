<?php

declare(strict_types=1);

namespace App;

use App\Infrastructure\Persistence\Doctrine\DoctrineBootstrap;
use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    #[Override]
    public function boot(): void
    {
        DoctrineBootstrap::configureCarbonPrecision();
        parent::boot();
    }
}
