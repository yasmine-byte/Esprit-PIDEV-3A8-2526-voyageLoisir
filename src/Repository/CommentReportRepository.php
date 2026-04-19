<?php

namespace App\Repository;

use App\Entity\CommentReport;
use App\Entity\Commentaire;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentReport>
 */
class CommentReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentReport::class);
    }

    public function findOneByCommentAndUser(Commentaire $commentaire, Users $user): ?CommentReport
    {
        return $this->findOneBy([
            'commentaire' => $commentaire,
            'user' => $user,
        ]);
    }

    public function getUserReportedCommentIds(Users $user, array $commentIds): array
    {
        $commentIds = array_values(array_unique(array_filter(array_map('intval', $commentIds))));
        if ([] === $commentIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('cr')
            ->select('IDENTITY(cr.commentaire) AS comment_id')
            ->andWhere('cr.user = :user')
            ->andWhere('cr.commentaire IN (:commentIds)')
            ->setParameter('user', $user)
            ->setParameter('commentIds', $commentIds)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['comment_id'] ?? 0), $rows)));
    }

    public function createAdminListQueryBuilder(string $status = 'all', string $reason = 'all'): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.commentaire', 'c')->addSelect('c')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->leftJoin('c.blog', 'b')->addSelect('b')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC');

        if ('all' !== $status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        if ('all' !== $reason) {
            $qb->andWhere('r.reason = :reason')->setParameter('reason', $reason);
        }

        return $qb;
    }

    public function deleteByComment(Commentaire $commentaire): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.commentaire = :commentaire')
            ->setParameter('commentaire', $commentaire)
            ->getQuery()
            ->execute();
    }
}
