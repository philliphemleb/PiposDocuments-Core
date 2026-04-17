<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\BannedIdentifier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BannedIdentifier>
 */
class BannedIdentifierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BannedIdentifier::class);
    }
}
