<?php

namespace App\Repository;

use App\Entity\Konzert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Konzert>
 */
class KonzertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Konzert::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.songs', 's')
            ->addSelect('s')
            ->orderBy('k.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
