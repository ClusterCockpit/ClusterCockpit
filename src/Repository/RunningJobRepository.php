<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Repository;

use App\Entity\RunningJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class RunningJobRepository extends ServiceEntityRepository
{
    private $_connection;

    public function __construct(
        RegistryInterface $registry
    )
    {
        parent::__construct($registry, RunningJob::class);
        $this->_connection = $this->getEntityManager()->getConnection();
    }

    public function countFilteredJobs( $userId, $filter )
    {
        $qb = $this->createQueryBuilder('j');

        if( $filter == 'false' ){
            $qb->select('count(j.id)');
        } else {
            $qb->select('count(j.id)')
               ->innerJoin('j.user', 'u', 'WITH', "u.userId LIKE :word")
               ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
        }

        if ( $userId ){
            $qb->andWhere("j.user = $userId");
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFilteredJobs(
        $userId,
        $offset, $limit,
        $sorting,
        $filter
    )
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('j')
           ->orderBy('j.'.$sorting['col'], $sorting['order'])
           ->setFirstResult( $offset )
           ->setMaxResults( $limit );

        if( $filter != 'false' ){
            $qb->innerJoin('j.user', 'u', 'WITH', "u.userId LIKE :word")
               ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
        }

        if ( $userId ){
            $qb->andWhere("j.user = $userId");
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findAvgTodo()
    {
        $qb = $this->createQueryBuilder('j');

        return $qb
            ->select('j')
            ->getQuery()
            ->getResult();
    }
}
