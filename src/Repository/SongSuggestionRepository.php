<?php

namespace App\Repository;

use App\Entity\SongSuggestion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SongSuggestion>
 */
class SongSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongSuggestion::class);
    }

    /**
     * Find all suggestions with like counts, ordered by date or popularity
     *
     * @param string $orderBy 'newest' for date DESC or 'popular' for like count DESC
     * @return SongSuggestion[]
     */
    public function findAllWithLikeCounts(?string $orderBy = 'newest'): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.likes', 'l')
            ->addSelect('COUNT(l.id) as HIDDEN likeCount')
            ->groupBy('s.id');

        if ($orderBy === 'popular') {
            $qb->orderBy('likeCount', 'DESC')
               ->addOrderBy('s.createdAt', 'DESC');
        } else {
            $qb->orderBy('s.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all suggestions submitted by a specific user
     *
     * @return SongSuggestion[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.submittedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
