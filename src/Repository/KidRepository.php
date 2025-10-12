<?php

namespace App\Repository;

use App\Entity\Kid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Kid>
 */
class KidRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kid::class);
    }
    /**
     * Récupère tous les enfants de 3 à 5 ans
     * 
     * @return Kid[]
     */
    public function listKids3To5YearsOld(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.age >= :minAge')
            ->andWhere('k.age <= :maxAge')
            ->setParameter('minAge', 3)
            ->setParameter('maxAge', 5)
            ->orderBy('k.lastname', 'ASC')
            ->addOrderBy('k.firstname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère tous les enfants de 6 à 12 ans
     * 
     * @return Kid[]
     */
    public function listKids6To12YearsOld(): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.age >= :minAge')
            ->andWhere('k.age <= :maxAge')
            ->setParameter('minAge', 6)
            ->setParameter('maxAge', 12)
            ->orderBy('k.lastname', 'ASC')
            ->addOrderBy('k.firstname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Kid[] Returns an array of Kid objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('k')
    //            ->andWhere('k.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('k.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Kid
    //    {
    //        return $this->createQueryBuilder('k')
    //            ->andWhere('k.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
