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
use Doctrine\Persistence\ManagerRegistry;

class ConfigurationRepository extends ServiceEntityRepository
{
    private $_connection;

    public function __construct(
        ManagerRegistry $registry
    )
    {
        parent::__construct($registry, Configuration::class);
        $this->_connection = $this->getEntityManager()->getConnection();
    }


    public function findAllDefault()
    {
        return $this->createQueryBuilder('c', 'c.name')
            ->andWhere("c.scope = 'default'")
            ->getQuery()
            ->getResult();
    }

    public function findAllDefaultHierarchy()
    {
        $configHash = array();
        $config = $this->createQueryBuilder('c', 'c.name')
            ->andWhere("c.scope = 'default'")
            ->getQuery()
            ->getResult();

        foreach ($config as $key => $value) {
            $parts = preg_split( '/_/' , $key);

            if (count($parts) == 3){
                $toplevel = $parts[0];
                $sublevel = $parts[1];
                $name     = $parts[2];
            } else {
                continue;
            }

            if (array_key_exists($toplevel, $configHash)) {
                if (array_key_exists($sublevel, $configHash[$toplevel])) {
                    $configHash[$toplevel][$sublevel][$name] = $value;
                } else {
                    $configHash[$toplevel][$sublevel] = array($name => $value);
                }
            } else {
                $configHash[$toplevel] = array($sublevel => array($name => $value));
            }
        }

        return $configHash;
    }


    public function findAllScopeHierarchy($scopes)
    {
        $configHash = array();
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
                $parts = preg_split( '/_/' , $key);

                $config[$key] = $value;
            }
        }

        foreach ($config as $key => $value) {
            $parts = preg_split( '/_/' , $key);

            if (count($parts) == 3){
                $toplevel = $parts[0];
                $sublevel = $parts[1];
                $name     = $parts[2];
            } else {
                continue;
            }

            if (array_key_exists($toplevel, $configHash)) {
                if (array_key_exists($sublevel, $configHash[$toplevel])) {
                    $configHash[$toplevel][$sublevel][$name] = $value;
                } else {
                    $configHash[$toplevel][$sublevel] = array($name => $value);
                }
            } else {
                $configHash[$toplevel] = array($sublevel => array($name => $value));
            }
        }

        return $configHash;
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
