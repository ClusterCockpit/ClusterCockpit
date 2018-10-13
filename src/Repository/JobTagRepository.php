<?php

namespace App\Repository;

use App\Entity\JobTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class JobTagRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, JobTag::class);
    }

    /* public function findByExampleField($value) */
    /* { */
    /*     return $this->createQueryBuilder('j') */
    /*         ->andWhere('j.exampleField = :val') */
    /*         ->setParameter('val', $value) */
    /*         ->orderBy('j.id', 'ASC') */
    /*         ->setMaxResults(10) */
    /*         ->getQuery() */
    /*         ->getResult() */
    /*     ; */
    /* } */
}
