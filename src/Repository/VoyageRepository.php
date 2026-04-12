<?php

namespace App\Repository;
use App\Entity\Users;

use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VoyageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voyage::class);
    }

    public function findByFilters(string $search, string $tri, string $ordre): array
    {
        $allowed = ['id', 'date_depart', 'date_arrivee', 'point_depart', 'point_arrivee', 'prix'];
        if (!in_array($tri, $allowed)) $tri = 'id';
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('v');

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('v.point_depart LIKE :search OR v.point_arrivee LIKE :search OR v.id = :id')
                   ->setParameter('search', '%' . $search . '%')
                   ->setParameter('id', (int) $search);
            } else {
                $qb->andWhere('v.point_depart LIKE :search OR v.point_arrivee LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
        }

        $qb->orderBy('v.' . $tri, $ordre);

        return $qb->getQuery()->getResult();
    }
    public function findByReservedUser(Users $user): array
{
    return $this->createQueryBuilder('v')
        ->join('v.reservedByUsers', 'u')
        ->andWhere('u = :user')
        ->setParameter('user', $user)
        ->getQuery()
        ->getResult();
}
}