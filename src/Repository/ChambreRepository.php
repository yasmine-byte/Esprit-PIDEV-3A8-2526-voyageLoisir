<?php
namespace App\Repository;

use App\Entity\Chambre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChambreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chambre::class);
    }

    public function search(?string $query = null, ?string $typeChambre = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.hebergement', 'h')
            ->addSelect('h');

        if ($query) {
            $qb->andWhere('c.numero LIKE :q OR c.equipements LIKE :q OR h.description LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        if ($typeChambre) {
            $qb->andWhere('c.typeChambre = :type')
               ->setParameter('type', $typeChambre);
        }

        return $qb->orderBy('c.id', 'DESC')->getQuery()->getResult();
    }
}