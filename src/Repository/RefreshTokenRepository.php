<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function save(RefreshToken $refreshToken): void
    {
        $em = $this->getEntityManager();

        $em->persist($refreshToken);
        $em->flush();
    }

    public function findValid(string $refreshId): ?RefreshToken
    {
        return $this->createQueryBuilder('r')
            ->where('r.id = :refreshId')
            ->andWhere('r.expires_at > :now')
            ->andWhere('r.revoked = :revoked')
            ->setParameter('refreshId', $refreshId)
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('revoked', false)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
