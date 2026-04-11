<?php

namespace App\Repository;

use App\Entity\Blog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Blog>
 */
class BlogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blog::class);
    }

    /**
     * @return Blog[]
     */
    public function findPublishedOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :published')
            ->setParameter('published', true)
            ->orderBy('b.datePublication', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function createAdminListingQueryBuilder(string $search = '', string $sort = 'recent', string $statusFilter = 'all'): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('b');

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('b.titre LIKE :search OR b.slug LIKE :search OR b.extrait LIKE :search OR b.contenu LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ('draft' === $statusFilter) {
            $queryBuilder->andWhere('b.status = :draft OR b.status IS NULL')->setParameter('draft', false);
        } elseif ('published' === $statusFilter) {
            $queryBuilder->andWhere('b.status = :published')->setParameter('published', true);
        } elseif ('pending' === $statusFilter) {
            $queryBuilder->andWhere('b.publicationRequested = :pending')->setParameter('pending', true);
        }

        switch ($sort) {
            case 'oldest':
                $queryBuilder->orderBy('b.dateCreation', 'ASC')->addOrderBy('b.id', 'ASC');
                break;
            case 'title':
                $queryBuilder->orderBy('b.titre', 'ASC')->addOrderBy('b.id', 'DESC');
                break;
            case 'status':
                $queryBuilder->orderBy('b.status', 'ASC')->addOrderBy('b.publicationRequested', 'DESC')->addOrderBy('b.dateCreation', 'DESC');
                break;
            case 'published':
                $queryBuilder->orderBy('b.datePublication', 'DESC')->addOrderBy('b.id', 'DESC');
                break;
            default:
                $queryBuilder->orderBy('b.dateCreation', 'DESC')->addOrderBy('b.id', 'DESC');
                break;
        }

        return $queryBuilder;
    }

    public function fetchAdminSummaryData(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.id, b.titre, b.slug, b.imageCouverture, b.extrait, b.contenu, b.status, b.publicationRequested')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Blog[] Returns an array of Blog objects
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

    //    public function findOneBySomeField($value): ?Blog
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
