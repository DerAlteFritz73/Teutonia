<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countMembers(): int
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where("u.roles NOT LIKE '%ROLE_GUEST%'")
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function sumLoginCountExcluding(string $username): int
    {
        $result = $this->createQueryBuilder('u')
            ->select('SUM(u.loginCount)')
            ->where('u.username != :username')
            ->andWhere("u.roles NOT LIKE '%ROLE_GUEST%'")
            ->setParameter('username', $username)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function countUsersWithLoginsExcluding(string $username): int
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.username != :username')
            ->andWhere('u.loginCount > 0')
            ->andWhere("u.roles NOT LIKE '%ROLE_GUEST%'")
            ->setParameter('username', $username)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function lastLoginAtExcluding(string $username): ?\DateTimeInterface
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.username != :username')
            ->andWhere('u.lastLoginAt IS NOT NULL')
            ->andWhere("u.roles NOT LIKE '%ROLE_GUEST%'")
            ->setParameter('username', $username)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $user?->getLastLoginAt();
    }

    public function lastSeenAtExcluding(string $username): ?\DateTimeInterface
    {
        $user = $this->createQueryBuilder('u')
            ->where('u.username != :username')
            ->andWhere('u.lastSeenAt IS NOT NULL')
            ->andWhere("u.roles NOT LIKE '%ROLE_GUEST%'")
            ->setParameter('username', $username)
            ->orderBy('u.lastSeenAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $user?->getLastSeenAt();
    }
}
