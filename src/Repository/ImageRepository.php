<?php

namespace App\Repository;

use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function findByFilters(string $search, string $tri, string $ordre): array
    {
        $allowed = ['i.id', 'd.id', 'd.nom', 'd.pays'];
        if (!in_array($tri, $allowed)) $tri = 'i.id';
        $ordre = strtoupper($ordre) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.destination', 'd')
            ->addSelect('d');

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('i.id = :idImg OR d.id = :idImg')
                   ->setParameter('idImg', (int) $search);
            } else {
                $qb->andWhere('d.nom LIKE :search OR d.pays LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
        }

        $qb->orderBy($tri, $ordre);

        return $qb->getQuery()->getResult();
    }
}