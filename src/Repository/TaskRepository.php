<?php

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
         * @return Task[] Returns an array of Task objects
    */
    public function tasksOfDay(\DateTime $date): array
    {
        return $this->createQueryBuilder("t")
            ->where('t.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findAllByDate(): array
    {
        return $this->createQueryBuilder('t')
            ->addOrderBy('t.date', 'DESC')
            ->addOrderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
