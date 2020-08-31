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

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository  implements UserLoaderInterface
{
    private $_connection;

    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $em
    )
    {
        parent::__construct($registry, User::class);
        $this->_connection = $em->getConnection();
    }

    public function findAll()
    {
        return $this->createQueryBuilder('u','u.username')
                    ->where('u.password IS NULL')
                    ->getQuery()
                    ->getResult();
    }

    public function findLocalUsers()
    {
        return $this->createQueryBuilder('u')
                    ->where('u.password IS NOT NULL')
                    ->getQuery()
                    ->getResult();
    }

    public function loadUserByUsername($username)
    {
        return $this->createQueryBuilder('u')
                    ->where('u.username = :username')
                    ->setParameter('username', $username)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    public function resetActiveUsers($activeUsers)
    {
        $sql = "UPDATE user SET is_active = 0";
        $stmt = $this->_connection->executeUpdate($sql);

        foreach ($activeUsers as $key => $value){
            if ( $value ){
                $sql = "UPDATE user SET is_active = 1 WHERE username = ?";
                $stmt = $this->_connection->executeUpdate($sql, array($key));
            }
        }
    }
}
