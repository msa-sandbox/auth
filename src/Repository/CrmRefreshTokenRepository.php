<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CrmRefreshToken;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CrmRefreshToken>
 */
class CrmRefreshTokenRepository extends ServiceEntityRepository implements CrmRefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmRefreshToken::class);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CrmRefreshToken $token): void
    {
        $em = $this->getEntityManager();

        $em->persist($token);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function findValidByJti(string $jti): ?CrmRefreshToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :jti')
            ->andWhere('t.expires_at > :now')
            ->andWhere('t.revoked = :revoked')
            ->setParameter('jti', $jti)
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('revoked', false)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function revokeByJti(string $jti): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.revoked', ':revoked')
            ->where('t.id = :jti')
            ->setParameter('revoked', true)
            ->setParameter('jti', $jti)
            ->getQuery()
            ->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupExpired(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expires_at < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
