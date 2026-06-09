<?php

declare(strict_types=1);

namespace App\Authentication\Repository;

use App\Authentication\Entity\User;
use App\Authentication\Entity\VerificationToken;
use App\Authentication\Enum\TokenType;
use Carbon\CarbonImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerificationToken>
 */
class VerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationToken::class);
    }

    public function findOneByToken(string $token): ?VerificationToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findValidTokenForUser(User $user): ?VerificationToken
    {
        /** @var VerificationToken|null $result */
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

    public function findValidTokenForUserByType(User $user, TokenType $type): ?VerificationToken
    {
        /** @var VerificationToken|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('now', CarbonImmutable::now())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * @return list<VerificationToken>
     */
    public function findPendingDispatch(int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dispatchedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('now', CarbonImmutable::now())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSentTokensForUserSince(User $user, CarbonImmutable $since): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.sentAt IS NOT NULL')
            ->andWhere('t.sentAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function deleteExpiredBatch(int $limit): int
    {
        /** @var list<VerificationToken> $tokens */
        $tokens = $this->createQueryBuilder('t')
            ->where('t.expiresAt <= :now')
            ->setParameter('now', CarbonImmutable::now())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($tokens as $token) {
            $this->getEntityManager()->remove($token);
        }

        $this->getEntityManager()->flush();

        return \count($tokens);
    }
}
