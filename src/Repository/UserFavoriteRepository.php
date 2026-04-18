<?php

namespace App\Repository;

use App\Entity\Blog;
use App\Entity\UserFavorite;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserFavorite>
 */
class UserFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFavorite::class);
    }

    /**
     * @return int[]
     */
    public function findBlogIdsByUser(Users $user): array
    {
        try {
            $rows = $this->createQueryBuilder('f')
                ->select('IDENTITY(f.blog) AS blog_id')
                ->andWhere('f.user = :user')
                ->setParameter('user', $user)
                ->orderBy('f.createdAt', 'DESC')
                ->getQuery()
                ->getArrayResult();
        } catch (TableNotFoundException) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['blog_id'] ?? 0), $rows)));
    }

    public function isFavorite(Users $user, Blog $blog): bool
    {
        try {
            return null !== $this->findOneBy(['user' => $user, 'blog' => $blog]);
        } catch (TableNotFoundException) {
            return false;
        }
    }
}

