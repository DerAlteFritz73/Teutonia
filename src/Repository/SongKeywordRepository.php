<?php

namespace App\Repository;

use App\Entity\SongKeyword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SongKeyword>
 */
class SongKeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongKeyword::class);
    }

    /**
     * Get all composers for word cloud
     *
     * @return array Array of composers with their frequency
     */
    public function getComposerFrequency(string $folder = 'Noten'): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.composer, COUNT(s.composer) as frequency')
            ->where('s.folder = :folder')
            ->setParameter('folder', $folder)
            ->groupBy('s.composer')
            ->orderBy('frequency', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all keywords for word cloud
     */
    public function getAllKeywords(string $folder = 'Noten'): array
    {
        $keywords = [];

        $results = $this->createQueryBuilder('s')
            ->select('s.keywords')
            ->where('s.folder = :folder')
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getResult();

        foreach ($results as $result) {
            if (!empty($result['keywords'])) {
                foreach ($result['keywords'] as $keyword) {
                    if (isset($keywords[$keyword])) {
                        $keywords[$keyword]++;
                    } else {
                        $keywords[$keyword] = 1;
                    }
                }
            }
        }

        return $keywords;
    }
}
