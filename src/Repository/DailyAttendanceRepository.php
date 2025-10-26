<?php

namespace App\Repository;

use App\Entity\DailyAttendance;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\DateType;

/**
 * @extends ServiceEntityRepository<DailyAttendance>
 */
class DailyAttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyAttendance::class);
    }

    public function findAllByDate(): array
    {
        return $this->createQueryBuilder("l")
            ->addOrderBy('l.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

public function canteenDay(\DateTimeImmutable $date)
{
    return (int) $this->createQueryBuilder('a')
        ->select('count(k.id)')
        ->innerJoin('a.kid', 'k')
        ->where('a.date = :d')
        ->andWhere('a.canteen = :yes')
        ->setParameter('d', $date)
        ->setParameter('yes', true)
        ->getQuery()
        ->getSingleScalarResult();
}
public function morningDay(\DateTimeImmutable $date)
{
    return (int) $this->createQueryBuilder('a')
        ->select('count(k.id)')
        ->innerJoin('a.kid', 'k')
        ->where('a.date = :d')
        ->andWhere('a.morning = :yes')
        ->setParameter('d', $date)
        ->setParameter('yes', true)
        ->getQuery()
        ->getSingleScalarResult();
}

    //    /**
    //     * @return DailyAttendance[] Returns an array of DailyAttendance objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DailyAttendance
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
