<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\EmailVerificationToken;
use App\Authentication\Entity\User;
use Carbon\CarbonImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailVerificationToken>
 */
class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationToken::class);
    }

    public function findOneByToken(string $token): ?EmailVerificationToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findValidTokenForUser(User $user): ?EmailVerificationToken
    {
        /** @var EmailVerificationToken|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', CarbonImmutable::now())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * @return list<EmailVerificationToken>
     */
    public function findPendingDispatch(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dispatchedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('now', CarbonImmutable::now())
            ->getQuery()
            ->getResult();
    }
}
