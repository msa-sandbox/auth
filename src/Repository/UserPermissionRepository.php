<?php

namespace App\Repository;

use App\Entity\UserPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPermission>
 */
class UserPermissionRepository extends ServiceEntityRepository implements UserPermissionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPermission::class);
    }

    public function save(UserPermission $userPermission): void
    {
        $em = $this->getEntityManager();

        $em->persist($userPermission);
        $em->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteBy(array $criteria): int
    {
        $qb = $this->createQueryBuilder('q')
            ->delete();

        foreach ($criteria as $field => $value) {
            $qb->andWhere("q.$field = :$field")
                ->setParameter($field, $value);
        }

        return $qb->getQuery()->execute();
    }
}
