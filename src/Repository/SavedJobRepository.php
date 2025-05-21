<?php

namespace App\Repository;

use App\Entity\SavedJob;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavedJob>
 */
class SavedJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedJob::class);
    }

    public function save(SavedJob $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SavedJob $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findSavedJob(User $user, int $jobId): ?SavedJob
    {
        return $this->createQueryBuilder('sj')
            ->andWhere('sj.user = :user')
            ->andWhere('sj.job = :jobId')
            ->setParameter('user', $user)
            ->setParameter('jobId', $jobId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUserSavedJobs(User $user): array
    {
        return $this->createQueryBuilder('sj')
            ->leftJoin('sj.job', 'j')
            ->addSelect('j')
            ->andWhere('sj.user = :user')
            ->setParameter('user', $user)
            ->orderBy('sj.savedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}