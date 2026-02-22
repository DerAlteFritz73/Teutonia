<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findAllOrderedWithMainFirst(int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.isMain', 'DESC')
            ->addOrderBy('p.position', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findMainPost(): ?Post
    {
        return $this->createQueryBuilder('p')
            ->where('p.isMain = :main')
            ->setParameter('main', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByPage(string $page, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.page = :page')
            ->setParameter('page', $page)
            ->orderBy('p.isMain', 'DESC')
            ->addOrderBy('p.position', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByPageOrderedByDate(string $page): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.page = :page')
            ->setParameter('page', $page)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPagePaginated(string $page, int $pageNum = 1, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.page = :page')
            ->setParameter('page', $page)
            ->orderBy('p.isMain', 'DESC')
            ->addOrderBy('p.position', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setFirstResult(($pageNum - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByPage(string $page): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.page = :page')
            ->setParameter('page', $page)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
