<?php

namespace App\Repository;

use App\Entity\JobTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method JobTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobTag|null findOneBy(array $criteria, array $orderBy = null)
 * @method JobTag[]    findAll()
 * @method JobTag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobTagRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, JobTag::class);
    }

//    /**
//     * @return JobTag[] Returns an array of JobTag objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('j.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?JobTag
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
