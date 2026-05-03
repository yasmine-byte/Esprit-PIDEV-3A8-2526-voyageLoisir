<?php

namespace App\Repository;

use App\Entity\Users;
use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voyage>
 */
class VoyageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voyage::class);
    }

    /**
     * @return array<int, Voyage>
     */
    public function findByFilters(string $search, string $tri, string $ordre): array
    {
        $allowed = ['id', 'date_depart', 'date_arrivee', 'point_depart', 'point_arrivee', 'prix'];
        if (!in_array($tri, $allowed)) $tri = 'id';
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        // Étape 1 — récupérer les IDs avec LIMIT
        $qb = $this->createQueryBuilder('v')->select('v.id');

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

        $qb->orderBy('v.' . $tri, $ordre)->setMaxResults(50);
        $ids = array_column($qb->getQuery()->getArrayResult(), 'id');

        if (empty($ids)) return [];

        // Étape 2 — charger les voyages avec transports en eager loading (évite N+1)
        return $this->createQueryBuilder('v')
            ->leftJoin('v.transports', 't')
            ->addSelect('t')
            ->leftJoin('v.destination', 'd')
            ->addSelect('d')
            ->where('v.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('v.' . $tri, $ordre)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Voyage>
     */
    public function findByReservedUser(Users $user): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.transports', 't')
            ->addSelect('t')
            ->join('v.reservedByUsers', 'u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }
}