<?php

namespace App\Repository;

use App\Entity\SongKeyword;
use App\Entity\Style;
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
     * Full-text search across song name, composer and etikett.
     */
    public function search(string $term): array
    {
        $like = '%' . $term . '%';
        return $this->createQueryBuilder('s')
            ->where('s.songName LIKE :q OR s.composer LIKE :q OR s.etikett LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('s.songName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByStylePaginated(Style $style, int $page, int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.styles', 'st')
            ->where('st = :style')
            ->setParameter('style', $style)
            ->orderBy('s.songName', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStyle(Style $style): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.styles', 'st')
            ->where('st = :style')
            ->setParameter('style', $style)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPaginated(int $page, int $limit, string $sort = 'songName', string $dir = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.parent IS NULL');
        $this->applySongSort($qb, $sort, $dir);
        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    private function applySongSort(\Doctrine\ORM\QueryBuilder $qb, string $sort, string $dir): void
    {
        if ($sort === 'latestKonzert') {
            $qb->addSelect('(SELECT MAX(k.date) FROM App\Entity\Konzert k JOIN k.songs ks WHERE ks = s) AS HIDDEN latestKonzert')
               ->orderBy('latestKonzert', $dir);
        } else {
            $qb->orderBy('s.' . $sort, $dir);
        }
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.parent IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAllIncludingMovements(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function searchPaginated(string $term, int $page, int $limit, string $sort = 'songName', string $dir = 'ASC'): array
    {
        $like = '%' . $term . '%';
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.styles', 'st')
            ->leftJoin('s.children', 'c')
            ->leftJoin('c.styles', 'cst')
            ->where('s.parent IS NULL')
            ->andWhere('
                s.songName LIKE :q OR s.composer LIKE :q OR s.etikett LIKE :q OR st.name LIKE :q
                OR c.songName LIKE :q OR c.composer LIKE :q OR c.etikett LIKE :q OR cst.name LIKE :q
            ')
            ->setParameter('q', $like)
            ->groupBy('s.id');
        $this->applySongSort($qb, $sort, $dir);
        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    public function countSearch(string $term): int
    {
        $like = '%' . $term . '%';
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->leftJoin('s.styles', 'st')
            ->leftJoin('s.children', 'c')
            ->leftJoin('c.styles', 'cst')
            ->where('s.parent IS NULL')
            ->andWhere('
                s.songName LIKE :q OR s.composer LIKE :q OR s.etikett LIKE :q OR st.name LIKE :q
                OR c.songName LIKE :q OR c.composer LIKE :q OR c.etikett LIKE :q OR cst.name LIKE :q
            ')
            ->setParameter('q', $like)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns {childId: parentId} map for all songs that have a parent.
     * Used to build grouping in the concert form.
     */
    public function findParentIdMap(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.id', 'IDENTITY(s.parent) AS parentId')
            ->where('s.parent IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = (int)$row['parentId'];
        }
        return $map;
    }

    /**
     * Unified filtered query supporting optional text search and optional style filter.
     */
    public function findFilteredPaginated(
        string $search,
        ?Style $style,
        int $page,
        int $limit,
        string $sort = 'songName',
        string $dir = 'ASC'
    ): array {
        $qb = $this->createQueryBuilder('s');
        if ($style !== null) {
            $qb->join('s.styles', 'st')->andWhere('st = :style')->setParameter('style', $style);
        }
        if ($search !== '') {
            $qb->andWhere('s.songName LIKE :q OR s.composer LIKE :q OR s.etikett LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }
        return $qb->orderBy('s.' . $sort, $dir)
                  ->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    public function countFiltered(string $search, ?Style $style): int
    {
        $qb = $this->createQueryBuilder('s')->select('COUNT(s.id)');
        if ($style !== null) {
            $qb->join('s.styles', 'st')->andWhere('st = :style')->setParameter('style', $style);
        }
        if ($search !== '') {
            $qb->andWhere('s.songName LIKE :q OR s.composer LIKE :q OR s.etikett LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Return top-level songs (no parent) in a given folder.
     */
    public function findByFolderTopLevel(string $folder): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.folder = :folder')
            ->andWhere('s.parent IS NULL')
            ->setParameter('folder', $folder)
            ->orderBy('s.songName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Return all top-level songs except those in the given folder, sorted by song name.
     */
    public function findAllExcept(string $excludeFolder): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.folder != :folder')
            ->andWhere('s.parent IS NULL')
            ->setParameter('folder', $excludeFolder)
            ->orderBy('s.songName', 'ASC')
            ->getQuery()
            ->getResult();
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
     * Get cleaned song titles (strips "Composer - " prefix) for word cloud
     */
    public function getSongTitles(string $folder = 'Noten'): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('s.songName')
            ->where('s.folder = :folder')
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getResult();

        $titles = [];
        foreach ($results as $row) {
            $name = $row['songName'];
            // Strip "Composer - " prefix if present
            if (preg_match('/^[^-]+ - (.+)$/', $name, $m)) {
                $name = trim($m[1]);
            }
            // Strip parenthetical subtitles to keep titles concise
            $name = trim(preg_replace('/\s*\(.*?\)/', '', $name));
            if ($name !== '') {
                $titles[] = $name;
            }
        }

        return $titles;
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
