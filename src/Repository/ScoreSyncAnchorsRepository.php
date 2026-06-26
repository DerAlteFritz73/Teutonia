<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScoreSyncAnchors;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScoreSyncAnchors>
 */
class ScoreSyncAnchorsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScoreSyncAnchors::class);
    }

    public function findOneByPaths(int $songId, string $pdfPath, string $audioPath): ?ScoreSyncAnchors
    {
        return $this->createQueryBuilder('s')
            ->where('IDENTITY(s.song) = :songId')
            ->andWhere('s.pdfPath = :pdf')
            ->andWhere('s.audioPath = :audio')
            ->setParameter('songId', $songId)
            ->setParameter('pdf', $pdfPath)
            ->setParameter('audio', $audioPath)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByPaths(string $pdfPath, string $audioPath): ?ScoreSyncAnchors
    {
        return $this->createQueryBuilder('s')
            ->where('s.pdfPath = :pdf')
            ->andWhere('s.audioPath = :audio')
            ->setParameter('pdf', $pdfPath)
            ->setParameter('audio', $audioPath)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
