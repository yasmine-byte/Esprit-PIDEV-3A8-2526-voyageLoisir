<?php
namespace App\Repository;

use App\Entity\Hebergement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Reservation;
class HebergementRepository extends ServiceEntityRepository
{
    /**
 * Historique des réservations d'un client par email
 */
public function findHistoriqueClient(string $email): array
{
    return $this->createQueryBuilder('r')
        ->join('r.hebergement', 'h')
        ->where('r.clientEmail = :email')
        ->andWhere('r.statut = :statut')
        ->setParameter('email', $email)
        ->setParameter('statut', 'confirmée')
        ->orderBy('r.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
    
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
    /**
 * Hébergements similaires : même type, prix proche, pas déjà vus
 */
public function findSimilaires(int $typeId, float $prixMoyen, array $exclusIds, int $limit = 4): array
{
    $qb = $this->createQueryBuilder('h')
        ->join('h.type', 't')
        ->where('t.id = :typeId')
        ->andWhere('h.prix BETWEEN :prixMin AND :prixMax')
        ->setParameter('typeId', $typeId)
        ->setParameter('prixMin', $prixMoyen * 0.6)
        ->setParameter('prixMax', $prixMoyen * 1.4)
        ->setMaxResults($limit);

    if (!empty($exclusIds)) {
        $qb->andWhere('h.id NOT IN (:exclus)')
           ->setParameter('exclus', $exclusIds);
    }

    $results = $qb->getQuery()->getResult();

    // Compléter si pas assez de résultats
    if (count($results) < $limit) {
        $manquants = $limit - count($results);
        $autresIds = array_merge($exclusIds, array_map(fn($h) => $h->getId(), $results));

        $autres = $this->createQueryBuilder('h')
            ->where('h.id NOT IN (:exclus)')
            ->setParameter('exclus', $autresIds)
            ->setMaxResults($manquants)
            ->getQuery()
            ->getResult();

        $results = array_merge($results, $autres);
    }

    return $results;
}

/**
 * Les hébergements les plus réservés
 */
public function findLesReserves(int $limit = 4): array
{
    return $this->createQueryBuilder('h')
        ->leftJoin(Reservation::class, 'r', 'WITH', 'r.hebergement = h.id')
        ->groupBy('h.id')
        ->orderBy('COUNT(r.id)', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
}