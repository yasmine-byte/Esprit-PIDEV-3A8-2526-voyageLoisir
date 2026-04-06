<?php

namespace App\Repository;

use App\Entity\Destination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    public function findByFilters(string $search, string $saison, string $statut, string $tri, string $ordre): array
    {
        $allowed = ['id', 'nom', 'pays', 'meilleure_saison', 'nb_visites'];
        if (!in_array($tri, $allowed)) $tri = 'id';
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('d');

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('d.nom LIKE :search OR d.pays LIKE :search OR d.id = :id')
                   ->setParameter('search', '%' . $search . '%')
                   ->setParameter('id', (int) $search);
            } else {
                $qb->andWhere('d.nom LIKE :search OR d.pays LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
        }

        if ($saison) {
            $qb->andWhere('d.meilleure_saison = :saison')
               ->setParameter('saison', $saison);
        }

        if ($statut !== '') {
            $qb->andWhere('d.statut = :statut')
               ->setParameter('statut', (bool) $statut);
        }

        $qb->orderBy('d.' . $tri, $ordre);

        return $qb->getQuery()->getResult();
    }
}