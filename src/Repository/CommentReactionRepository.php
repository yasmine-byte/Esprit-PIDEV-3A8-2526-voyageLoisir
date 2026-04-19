<?php

namespace App\Repository;

use App\Entity\CommentReaction;
use App\Entity\Commentaire;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentReaction>
 */
class CommentReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentReaction::class);
    }

    public function findOneByCommentAndUser(Commentaire $commentaire, Users $user): ?CommentReaction
    {
        return $this->findOneBy([
            'commentaire' => $commentaire,
            'user' => $user,
        ]);
    }

    public function aggregateCountsForComment(Commentaire $commentaire): array
    {
        $rows = $this->createQueryBuilder('cr')
            ->select('cr.reactionType AS reaction_type', 'COUNT(cr.id) AS total')
            ->andWhere('cr.commentaire = :commentaire')
            ->setParameter('commentaire', $commentaire)
            ->groupBy('cr.reactionType')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $reactionType = (string) ($row['reaction_type'] ?? '');
            if ('' === $reactionType) {
                continue;
            }

            $counts[$reactionType] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    public function getUserReactionsByCommentIds(Users $user, array $commentIds): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        if ([] === $commentIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('cr')
            ->select('IDENTITY(cr.commentaire) AS comment_id', 'cr.reactionType AS reaction_type')
            ->andWhere('cr.user = :user')
            ->andWhere('cr.commentaire IN (:commentIds)')
            ->setParameter('user', $user)
            ->setParameter('commentIds', $commentIds)
            ->getQuery()
            ->getArrayResult();

        $userReactions = [];
        foreach ($rows as $row) {
            $commentId = (int) ($row['comment_id'] ?? 0);
            if ($commentId <= 0) {
                continue;
            }

            $userReactions[$commentId] = (string) ($row['reaction_type'] ?? '');
        }

        return $userReactions;
    }

    public function deleteByComment(Commentaire $commentaire): int
    {
        return $this->createQueryBuilder('cr')
            ->delete()
            ->andWhere('cr.commentaire = :commentaire')
            ->setParameter('commentaire', $commentaire)
            ->getQuery()
            ->execute();
    }
}
