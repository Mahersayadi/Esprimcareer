<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findByParticipant(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('p.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findConversationBetweenUsers(User $user1, User $user2): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p1')
            ->innerJoin('c.participants', 'p2')
            ->where('p1.id = :user1Id')
            ->andWhere('p2.id = :user2Id')
            ->andWhere('SIZE(c.participants) = 2')
            ->setParameter('user1Id', $user1->getId())
            ->setParameter('user2Id', $user2->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }
public function getUnreadCount(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(m.id)')
            ->join('c.messages', 'm')
            ->where('m.sender != :user')
            ->andWhere('m.isRead = false')
            ->andWhere(':user MEMBER OF c.participants')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

}