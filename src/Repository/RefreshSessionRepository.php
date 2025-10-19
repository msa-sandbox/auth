<?php

namespace App\Repository;

use App\Entity\RefreshSession;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshSession>
 */
class RefreshSessionRepository extends ServiceEntityRepository implements RefreshSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshSession::class);
    }

    public function save(RefreshSession $refreshToken): void
    {
        $em = $this->getEntityManager();

        $em->persist($refreshToken);
        $em->flush();
    }

    public function findValid(string $refreshId): ?RefreshSession
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
