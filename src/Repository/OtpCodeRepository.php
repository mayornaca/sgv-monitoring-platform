<?php

namespace App\Repository;

use App\Entity\OtpCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OtpCode>
 */
class OtpCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OtpCode::class);
    }

    /**
     * Find valid OTP codes for email and channel
     */
    public function findValidCodes(string $email, string $channel): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.email = :email')
            ->andWhere('o.channel = :channel')
            ->andWhere('o.expiresAt > :now')
            ->andWhere('o.used = false')
            ->setParameter('email', $email)
            ->setParameter('channel', $channel)
            ->setParameter('now', new \DateTime())
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired codes for cleanup
     */
    public function findExpiredCodes(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up expired OTP codes
     */
    public function cleanupExpired(): int
    {
        return $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}