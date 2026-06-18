<?php

namespace App\Repository;

use App\Entity\BlacklistedIp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlacklistedIpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlacklistedIp::class);
    }

    public function isBlacklisted(string $ipAddress): bool
    {
        $entry = $this->createQueryBuilder('b')
            ->where('b.ipAddress = :ip')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('ip', $ipAddress)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        return $entry !== null;
    }

    public function addIp(string $ipAddress, ?string $reason = null, ?\DateTimeImmutable $expiresAt = null): BlacklistedIp
    {
        $ip = new BlacklistedIp();
        $ip->setIpAddress($ipAddress);
        $ip->setReason($reason);
        $ip->setBlacklistedAt(new \DateTimeImmutable());
        $ip->setExpiresAt($expiresAt);

        $this->getEntityManager()->persist($ip);
        $this->getEntityManager()->flush();

        return $ip;
    }
}
