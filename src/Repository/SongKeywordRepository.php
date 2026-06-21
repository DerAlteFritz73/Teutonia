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
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.styles', 'st')
            ->leftJoin('s.children', 'c')
            ->leftJoin('c.styles', 'cst')
            ->where('s.parent IS NULL')
            ->groupBy('s.id');
        $this->applyWordSearch($qb, $term);
        $this->applySongSort($qb, $sort, $dir);
        return $qb->setFirstResult(($page - 1) * $limit)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    public function countSearch(string $term): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->leftJoin('s.styles', 'st')
            ->leftJoin('s.children', 'c')
            ->leftJoin('c.styles', 'cst')
            ->where('s.parent IS NULL');
        $this->applyWordSearch($qb, $term);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyWordSearch(\Doctrine\ORM\QueryBuilder $qb, string $term): void
    {
        $words = array_values(array_filter(array_map('trim', explode(' ', $term))));
        if (empty($words)) { return; }
        foreach ($words as $i => $word) {
            $p = 'q' . $i;
            $qb->andWhere("
                s.songName LIKE :$p OR s.composer LIKE :$p OR s.arrangeur LIKE :$p OR s.etikett LIKE :$p OR st.name LIKE :$p
                OR c.songName LIKE :$p OR c.composer LIKE :$p OR c.arrangeur LIKE :$p OR c.etikett LIKE :$p OR cst.name LIKE :$p
            ")->setParameter($p, '%' . $word . '%');
        }
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
     * Return top-level songs (no parent) in a given folder.
     */
    public function findByFolderTopLevel(string $folder): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isAktuelleProben = true')
            ->andWhere('s.parent IS NULL')
            ->orderBy('s.songName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Return all top-level songs for the Noten section, sorted by song name.
     * Includes songs that are also in Aktuelle Proben.
     * When multiple DB rows share the same name (duplicates before dedup-sync runs),
     * keep the one with the highest score: children > isAktuelleProben > dropbox link.
     */
    public function findAllExcept(string $excludeFolder): array
    {
        $all = $this->createQueryBuilder('s')
            ->andWhere('s.parent IS NULL')
            ->orderBy('s.songName', 'ASC')
            ->getQuery()
            ->getResult();

        $byName = [];
        foreach ($all as $song) {
            $name = $song->getSongName();
            if (!isset($byName[$name])) {
                $byName[$name] = $song;
                continue;
            }
            $score = fn($s) => ($s->getChildren()->count() > 0 ? 4 : 0)
                             + ($s->isAktuelleProben()          ? 2 : 0)
                             + ($s->getDropboxlink()            ? 1 : 0);
            if ($score($song) > $score($byName[$name])) {
                $byName[$name] = $song;
            }
        }

        return array_values($byName);
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
}
