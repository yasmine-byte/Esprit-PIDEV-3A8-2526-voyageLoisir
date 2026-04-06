<?php

namespace App\Repository;

use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Users>
 */
class UsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    }

    /**
     * Compte les inscriptions par mois sur les 6 derniers mois
     */
    public function countByMonth(): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(id) as total
        FROM users
        WHERE created_at >= :date
        GROUP BY month
        ORDER BY month ASC
    ";

    $results = $conn->executeQuery($sql, [
        'date' => (new \DateTime('-6 months'))->format('Y-m-d'),
    ])->fetchAllAssociative();

    // Remplir les mois manquants avec 0
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $key = (new \DateTime("-$i months"))->format('Y-m');
        $months[$key] = 0;
    }

    foreach ($results as $row) {
        $months[$row['month']] = (int) $row['total'];
    }

    return $months;
}

    /**
     * Trouve les N derniers inscrits
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Compte les utilisateurs actifs
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les utilisateurs inactifs
     */
    public function countInactive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche par nom, prénom ou email
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.nom LIKE :term')
            ->orWhere('u.prenom LIKE :term')
            ->orWhere('u.email LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs par rôle
     */
    public function findByRoleName(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.roles', 'r')
            ->where('r.name = :role')
            ->setParameter('role', $roleName)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
    /**
 * Méthode utilisée par Symfony Security pour recharger l'utilisateur
 * avec ses rôles à chaque requête
 */
public function findOneByEmailWithRoles(string $email): ?Users
{
    return $this->createQueryBuilder('u')
        ->leftJoin('u.roles', 'r')
        ->addSelect('r')
        ->where('u.email = :email')
        ->setParameter('email', $email)
        ->getQuery()
        ->getOneOrNullResult();
}
}
