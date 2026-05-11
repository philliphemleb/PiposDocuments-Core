<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\UserLock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLock>
 */
class UserLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLock::class);
    }
}
