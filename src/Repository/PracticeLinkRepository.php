<?php

namespace App\Repository;

use App\Entity\PracticeLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PracticeLink>
 */
class PracticeLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PracticeLink::class);
    }

    /**
     * @return PracticeLink[]
     */
    public function findAllOrderedBySortOrder(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.song', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PracticeLink[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->setParameter('type', $type)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, PracticeLink[]>
     */
    public function findAllGroupedBySong(): array
    {
        $links = $this->createQueryBuilder('p')
            ->orderBy('p.song', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($links as $link) {
            $song = $link->getSong() ?: 'Sonstige';
            if (!isset($grouped[$song])) {
                $grouped[$song] = [];
            }
            $grouped[$song][] = $link;
        }

        return $grouped;
    }
}
