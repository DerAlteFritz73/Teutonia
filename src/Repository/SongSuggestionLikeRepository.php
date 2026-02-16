<?php

namespace App\Repository;

use App\Entity\SongSuggestion;
use App\Entity\SongSuggestionLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SongSuggestionLike>
 */
class SongSuggestionLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongSuggestionLike::class);
    }

    /**
     * Find a specific like by user and suggestion
     */
    public function findLikeByUserAndSuggestion(User $user, SongSuggestion $suggestion): ?SongSuggestionLike
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.suggestion = :suggestion')
            ->setParameter('user', $user)
            ->setParameter('suggestion', $suggestion)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all likes by a specific user
     *
     * @return SongSuggestionLike[]
     */
    public function findLikesByUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
