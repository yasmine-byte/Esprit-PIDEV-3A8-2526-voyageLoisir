<?php

namespace App\Repository;

use App\Entity\Commentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commentaire>
 */
class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    public function countByBlogIds(array $blogIds): array
    {
        $blogIds = array_values(array_unique(array_filter(array_map('intval', $blogIds))));
        if ([] === $blogIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.blog) AS blog_id', 'COUNT(c.id) AS total_comments')
            ->andWhere('c.blog IN (:blogIds)')
            ->setParameter('blogIds', $blogIds)
            ->groupBy('c.blog')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $blogId = (int) ($row['blog_id'] ?? 0);
            if ($blogId <= 0) {
                continue;
            }

            $counts[$blogId] = (int) ($row['total_comments'] ?? 0);
        }

        return $counts;
    }

    //    /**
    //     * @return Commentaire[] Returns an array of Commentaire objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Commentaire
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
