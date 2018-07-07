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

use App\Entity\Configuration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Configuration|null find($id, $lockMode = null, $lockVersion = null)
 * @method Configuration|null findOneBy(array $criteria, array $orderBy = null)
 * @method Configuration[]    findAll()
 * @method Configuration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConfigurationRepository extends ServiceEntityRepository
{
    private $_connection;

    public function __construct(
        RegistryInterface $registry
    )
    {
        parent::__construct($registry, Configuration::class);
        $this->_connection = $this->getEntityManager()->getConnection();
    }

    public function isInit()
    {
        $sql = "SELECT COUNT(*) AS count FROM configuration";
        $stmt = $this->_connection->prepare($sql);
        $stmt->execute();
        $count = $stmt->fetch();

        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function findAllDefault()
    {
        return $this->createQueryBuilder('c', 'c.name')
            ->andWhere("c.scope = 'default'")
            ->getQuery()
            ->getResult();
    }

    public function findAllScope($scopes)
    {
        $config = $this->createQueryBuilder('c', 'c.name')
            ->andWhere("c.scope = 'default'")
            ->getQuery()
            ->getResult();

        foreach ($scopes as $scope) {
            $configScope = $this->createQueryBuilder('c', 'c.name')
                                ->andWhere('c.scope = :val')
                                ->setParameter('val', $scope)
                                ->getQuery()
                                ->getResult();

            foreach ($configScope as $key => $value) {
                $config[$key] = $value;
            }
        }

        return $config;
    }
}
