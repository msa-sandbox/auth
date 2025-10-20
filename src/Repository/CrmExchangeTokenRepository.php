<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CrmExchangeToken;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CrmExchangeToken>
 */
class CrmExchangeTokenRepository extends ServiceEntityRepository implements CrmExchangeTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmExchangeToken::class);
    }

    public function save(CrmExchangeToken $token): void
    {
        $em = $this->getEntityManager();

        $em->persist($token);
        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function findValidByHash(string $tokenHash): ?CrmExchangeToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.token_hash = :hash')
            ->andWhere('t.expires_at > :now')
            ->andWhere('t.used_at IS NULL')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
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
