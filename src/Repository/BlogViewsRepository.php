<?php

namespace App\Repository;

use App\Entity\BlogViews;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogViews>
 */
class BlogViewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogViews::class);
    }

    public function countByBlogIds(array $blogIds): array
    {
        $blogIds = array_values(array_unique(array_filter(array_map('intval', $blogIds))));
        if ([] === $blogIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.blog) AS blog_id', 'COUNT(v.id) AS total_views')
            ->andWhere('v.blog IN (:blogIds)')
            ->setParameter('blogIds', $blogIds)
            ->groupBy('v.blog')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $blogId = (int) ($row['blog_id'] ?? 0);
            if ($blogId <= 0) {
                continue;
            }

            $counts[$blogId] = (int) ($row['total_views'] ?? 0);
        }

        return $counts;
    }

    //    /**
    //     * @return BlogViews[] Returns an array of BlogViews objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?BlogViews
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
