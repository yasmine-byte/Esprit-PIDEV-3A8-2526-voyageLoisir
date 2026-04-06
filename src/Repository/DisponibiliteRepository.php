<?php
namespace App\Repository;

use App\Entity\Disponibilite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DisponibiliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Disponibilite::class);
    }

    public function search(?string $query = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.hebergement', 'h')
            ->addSelect('h');

        if ($query) {
            $qb->andWhere('h.description LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        if ($statut === 'disponible') {
            $qb->andWhere('d.disponible = true');
        } elseif ($statut === 'indisponible') {
            $qb->andWhere('d.disponible = false');
        }

        return $qb->orderBy('d.id', 'DESC')->getQuery()->getResult();
    }
}