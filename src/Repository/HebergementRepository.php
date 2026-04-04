<?php
namespace App\Repository;

use App\Entity\Hebergement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HebergementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hebergement::class);
    }

    public function search(
        ?string $description = null,
        ?int $typeId = null,
        ?float $prixMin = null,
        ?float $prixMax = null,
        ?string $tri = null,
        ?\DateTime $dateDebut = null,
        ?\DateTime $dateFin = null
    ): array {
        $qb = $this->createQueryBuilder('h')
            ->leftJoin('h.type', 't')
            ->addSelect('t');

        // Recherche par description
        if ($description) {
            $qb->andWhere('h.description LIKE :description')
               ->setParameter('description', '%' . $description . '%');
        }

        // Filtre par type
        if ($typeId) {
            $qb->andWhere('t.id = :typeId')
               ->setParameter('typeId', $typeId);
        }

        // Filtre par prix min
        if ($prixMin !== null) {
            $qb->andWhere('h.prix >= :prixMin')
               ->setParameter('prixMin', $prixMin);
        }

        // Filtre par prix max
        if ($prixMax !== null) {
            $qb->andWhere('h.prix <= :prixMax')
               ->setParameter('prixMax', $prixMax);
        }

        // Filtre par disponibilité
        if ($dateDebut && $dateFin) {
            $qb->innerJoin('App\Entity\Disponibilite', 'd', 'WITH',
                'd.hebergement = h AND d.disponible = true AND d.dateDebut <= :dateDebut AND d.dateFin >= :dateFin'
            )
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin);
        }

        // Tri
        switch ($tri) {
            case 'prix_asc':
                $qb->orderBy('h.prix', 'ASC');
                break;
            case 'prix_desc':
                $qb->orderBy('h.prix', 'DESC');
                break;
            default:
                $qb->orderBy('h.id', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}