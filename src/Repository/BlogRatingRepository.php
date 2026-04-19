<?php

namespace App\Repository;

use App\Entity\Blog;
use App\Entity\BlogRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogRating>
 */
class BlogRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogRating::class);
    }

    public function getGlobalAverageRating(): float
    {
        $value = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) ($value ?? 0), 2);
    }

    public function getBlogRatingSummary(Blog $blog): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS rating_count', 'AVG(r.rating) AS rating_average')
            ->andWhere('r.blog = :blog')
            ->setParameter('blog', $blog)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'count' => (int) ($result['rating_count'] ?? 0),
            'average' => round((float) ($result['rating_average'] ?? 0), 1),
        ];
    }

    public function getAggregatedStatsByBlogIds(array $blogIds): array
    {
        $blogIds = array_values(array_unique(array_filter(array_map('intval', $blogIds))));
        if ([] === $blogIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.blog) AS blog_id', 'COUNT(r.id) AS rating_count', 'AVG(r.rating) AS rating_average')
            ->andWhere('r.blog IN (:blogIds)')
            ->setParameter('blogIds', $blogIds)
            ->groupBy('r.blog')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $blogId = (int) ($row['blog_id'] ?? 0);
            if ($blogId <= 0) {
                continue;
            }

            $stats[$blogId] = [
                'count' => (int) ($row['rating_count'] ?? 0),
                'average' => round((float) ($row['rating_average'] ?? 0), 1),
            ];
        }

        return $stats;
    }

    //    /**
    //     * @return BlogRating[] Returns an array of BlogRating objects
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

    //    public function findOneBySomeField($value): ?BlogRating
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
