<?php

namespace App\Repository;

use App\Entity\Style;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Style>
 */
class StyleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Style::class);
    }

    /**
     * Eagerly loads songKeywords to avoid N+1 on the repertoire page.
     */
    public function findAllWithSongs(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.songKeywords', 'sk')
            ->addSelect('sk')
            ->leftJoin('s.repertoireSongs', 'rs')
            ->addSelect('rs')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
