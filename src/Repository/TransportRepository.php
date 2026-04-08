<?php

namespace App\Repository;

use App\Entity\Transport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transport::class);
    }

    public function findByFilters(string $search, string $type, string $tri, string $ordre): array
    {
        $allowed = ['t.id', 't.type_transport', 'v.id'];
        if (!in_array($tri, $allowed)) $tri = 't.id';
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.voyage', 'v')
            ->addSelect('v');

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('t.id = :idT OR v.id = :idT')
                   ->setParameter('idT', (int) $search);
            } else {
                $qb->andWhere('t.type_transport LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
        }

        if ($type) {
            $qb->andWhere('t.type_transport = :type')
               ->setParameter('type', $type);
        }

        $qb->orderBy($tri, $ordre);

        return $qb->getQuery()->getResult();
    }
}