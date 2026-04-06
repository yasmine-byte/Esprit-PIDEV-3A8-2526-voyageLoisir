<?php
namespace App\Repository;

use App\Entity\Type;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Type::class);
    }

    public function search(?string $query = null): array
    {
        $qb = $this->createQueryBuilder('t');

        if ($query) {
            $qb->andWhere('t.nom LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        return $qb->orderBy('t.id', 'DESC')->getQuery()->getResult();
    }
}