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

use App\Entity\Cluster;
use App\Entity\Node;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ClusterRepository extends ServiceEntityRepository
{
    private $_connection;
    private $_nodeRepository;

    public function __construct(
        ManagerRegistry $registry
    )
    {
        parent::__construct($registry, Cluster::class);
        $this->_connection = $this->getEntityManager()->getConnection();
        $this->_nodeRepository = $this->getEntityManager()->getRepository(Node::class);
    }

    public function findAllConfig()
    {
        $clusters = $this->createQueryBuilder('c', 'c.name')
                         ->getQuery()
                         ->getResult();

        foreach ($clusters as $cluster ) {
            $cluster->setNodes($this->_nodeRepository->findBy(
                ['cluster' => $cluster->getId()])
            );
        }

        return $clusters;
    }

    public function addNodes(Cluster $cluster)
    {
        $cluster->setNodes($this->_nodeRepository->findBy(
            ['cluster' => $cluster->getId()])
        );

        return $cluster;
    }

}
