<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2019 Jan Eitzinger
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

use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Job;
use App\Entity\User;
use App\Entity\Cluster;
use App\Entity\JobSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JobRepository extends ServiceEntityRepository
{
    private $_timing;
    private $_logger;
    private $_connection;
    private $_userRepository;
    private $_clusters;

    public function __construct(
        ManagerRegistry $registry,
        LoggerInterface $logger,
        Stopwatch $stopwatch
    )
    {
        parent::__construct($registry, Job::class);
        $this->_logger = $logger;
        $this->_timing = $stopwatch;
        $this->_connection = $this->getEntityManager()->getConnection();
        $this->_userRepository = $this->getEntityManager()->getRepository(User::class);
        $cluster_rep = $this->getEntityManager()->getRepository(Cluster::class);
        $clusters = $cluster_rep->findAll();
        $this->_clusters = array();

        foreach ( $clusters as $cluster ){
            $this->_clusters[$cluster->getId()] = array(
                'name' => $cluster->getName(),
                'coresPerNode' => $cluster->getCoresPerNode()
            );
        }
    }

    private function getSettings($control): array
    {
        $single = true;
        $clusterId = $control->getCluster();
        $clusters = array();

        if ( $clusterId == 0 ){
            foreach ( $this->_clusters as $id => $cluster ){
                $clusters[] = array(
                    'id' => $id,
                    'coresPerNode' => $cluster['coresPerNode'],
                    'name' => $cluster['name']
                );
            }
            $single = false;
	} else {
	    if(isset($this->_clusters[$clusterId]))
            {
                $clusters[] =  array(
                    'id' => $clusterId,
                    'coresPerNode' => $this->_clusters[$clusterId]['coresPerNode'],
                    'name' => $this->_clusters[$clusterId]['name']
                );
	    }
        }

        $month = $control->getMonth();
        $year = $control->getYear();

        if (isset($month)){
            $datestring = sprintf("%04d%02d01",$year,$month);
            $startTime = strtotime($datestring);
            $days = date(' t ', $startTime );
            $datestring = sprintf("%04d%02d%02d",$year, $month, $days);
            $stopTime = strtotime($datestring);
        } else {
            $datestring = sprintf("%04d0101",$year);
            $startTime = strtotime($datestring);
            $datestring = sprintf("%04d1231",$year);
            $stopTime = strtotime($datestring);
        }
        $settings = array(
            'startTime' => $startTime,
            'stopTime' => $stopTime,
            'clusters' => $clusters,
            'oneSystem' => $single
        );

        $this->_logger->info("Settings: ", $settings);

        return  $settings;
    }

    private function getHisto($settings, $target, $constraint = '', $join = ''): array
    {
        $startTime = $settings['startTime'];
        $stopTime = $settings['stopTime'];
        $histo = array();

        foreach ( $settings['clusters'] as $cluster ){
            $sql = "
            SELECT $target AS bin, count(*) AS count
            FROM job
            $join
            WHERE job.cluster_id=".$cluster['id']."
            AND job.start_time BETWEEN $startTime AND $stopTime
            $constraint
            GROUP BY 1
            ORDER BY 1
            ";

            $stat = $this->_connection->fetchAll($sql);

            foreach ( $stat as $item ){
                if (isset($histo[ $item['bin'] ] )){
                    $histo[ $item['bin'] ] += $item['count'];
                } else {
                    $histo[ $item['bin'] ] = $item['count'];
                }
            }
        }

        return $histo;
    }

    private function getStats($settings, $constraint = '', $join = ''): array
    {
        $startTime = $settings['startTime'];
        $stopTime = $settings['stopTime'];
        $stat = array(
            'totalWalltime' => 0,
            'jobCount' => 0,
            'totalCoreHours' => 0,
        );

        foreach ( $settings['clusters'] as $cluster ){
            $sql = "
            SELECT ROUND(SUM(job.duration)/3600,2) AS totalWalltime,
                   COUNT(*) AS jobCount,
                   ROUND(SUM(job.duration*job.num_nodes*".$cluster['coresPerNode'].")/3600,2) as totalCoreHours
            FROM job
            $join
            WHERE job.cluster_id=".$cluster['id']."
            AND job.start_time BETWEEN $startTime AND $stopTime
            $constraint
            ";

            $tmp = $this->_connection->fetchAssoc($sql);
            $stat['totalWalltime'] += $tmp['totalWalltime'];
            $stat['jobCount'] += $tmp['jobCount'];
            $stat['totalCoreHours'] += $tmp['totalCoreHours'];
        }

        return $stat;
    }

    public function countJobs(
        $userId,
        $filter,
        $jobQuery
    )
    {
        $qb = $this->createQueryBuilder('j');

        $qb->select('count(j)')
           ->where("j.duration > 300");

        if ( $userId ){
            $qb->andWhere("j.user = $userId");
        } else {
            if( $filter ){
                $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                   ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
            }
        }

        if (array_key_exists ( 'runningJobs', $jobQuery )) {
            $qb->andWhere("j.isRunning = true");
        } elseif (array_key_exists ( 'clusterId', $jobQuery )) {

            $qb->andWhere($qb->expr()->between('j.numNodes',$jobQuery['numNodesFrom'],$jobQuery['numNodesTo']));
            $qb->andWhere($qb->expr()->between( 'j.duration', $jobQuery['durationFrom'], $jobQuery['durationTo']));
            $qb->andWhere($qb->expr()->between( 'j.startTime', $jobQuery['dateFrom'], $jobQuery['dateTo']));

            if ( $jobQuery['clusterId'] != 0 ){  /* 0 means all Clusters */
                $qb->andWhere("j.cluster = $jobQuery[clusterId]");
            }

            if ( ! $userId and  $jobQuery['userId'] != 0 ){
                $qb->andWhere("j.user = $jobQuery[userId]");
            }

        } elseif (array_key_exists ( 'jobTag', $jobQuery )) {
            $qb->innerJoin('j.tags', 't', 'WITH', 't.id = :tagId')
               ->setParameter('tagId', $jobQuery['jobTag']);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFilteredJobs(
        $userId,
        $offset, $limit,
        $sorting,
        $filter,
        $jobQuery)
    {
        $qb = $this->createQueryBuilder('j');

        $qb->select('j')
           ->where("j.duration > 300")
           ->orderBy('j.'.$sorting['col'], $sorting['order'])
           ->setFirstResult( $offset )
           ->setMaxResults( $limit );

        if ( $userId ){
            $qb->andWhere("j.user = $userId");
        } else {
            if( $filter ){
                $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                   ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
            }
        }

        if (array_key_exists ( 'runningJobs', $jobQuery )) {
            $qb->andWhere("j.isRunning = true");
        } elseif (array_key_exists ( 'clusterId', $jobQuery )) {

            $qb->andWhere($qb->expr()->between('j.numNodes',$jobQuery['numNodesFrom'],$jobQuery['numNodesTo']));
            $qb->andWhere($qb->expr()->between( 'j.duration', $jobQuery['durationFrom'], $jobQuery['durationTo']));
            $qb->andWhere($qb->expr()->between( 'j.startTime', $jobQuery['dateFrom'], $jobQuery['dateTo']));

            if ( $jobQuery['clusterId'] != 0 ) { /* 0 means all Clusters */
                $qb->andWhere("j.cluster = $jobQuery[clusterId]");
            }

            if ( ! $userId and  $jobQuery['userId'] != 0 ){
                $qb->andWhere("j.user = $jobQuery[userId]");
            }

        } elseif (array_key_exists ( 'jobTag', $jobQuery )) {
            $qb->innerJoin('j.tags', 't', 'WITH', 't.id = :tagId')
               ->setParameter('tagId', $jobQuery['jobTag']);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findRunningJobs()
    {
        $qb = $this->createQueryBuilder('j');

        return $qb
            ->where("j.isRunning = true")
            ->orderBy('j.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCachedJobs()
    {
        $qb = $this->createQueryBuilder('j');

        return $qb
            ->where("j.isRunning = false")
            ->where("j.isCached = true")
            ->getQuery()
            ->getResult();
    }

    public function findJobsToClean($timestamp)
    {
        $qb = $this->createQueryBuilder('j');

        return $qb
            ->where("j.isCached = true")
            ->andWhere($qb->expr()->lt( 'j.startTime', $timestamp))
            ->getQuery()
            ->getResult();
    }

    public function findJobsToBuild($timestamp)
    {
        $qb = $this->createQueryBuilder('j');

        return $qb
            ->where("j.isRunning = false")
            ->andWhere("j.isCached = false")
            ->andWhere($qb->expr()->gt( 'j.startTime', $timestamp))
            ->getQuery()
            ->getResult();
    }

    public function findByStartTime($startFrom, $startTo)
    {
        $qb = $this->createQueryBuilder('j');

        return $qb
            ->where($qb->expr()->between( 'j.startTime', $startFrom, $startTo))
            ->getQuery()
            ->getResult();
    }

    public function findStatByUser($userId, $control, $full=true)
    {
        $stat = array();
        $settings = $this->getSettings($control);
        $constraint = "AND job.user_id=$userId";
        $stat['stat'] = $this->getStats($settings,$constraint);

        if( $full ){
            $stat['histo_runtime'] = $this->getHisto($settings,
                'floor(duration/3600)+1',"AND job.user_id=$userId");
            $stat['histo_numnodes'] = $this->getHisto($settings,
                'num_nodes-1',"AND job.user_id=$userId");
        }
        return $stat;
    }

    public function statUsers($control)
    {
        $settings = $this->getSettings($control);
        $startTime = $settings['startTime'];
        $stopTime = $settings['stopTime'];
        $users = array();
        $lookup;

        if ( $settings['oneSystem'] && isset($settings['clusters'][0])){
            $cluster = $settings['clusters'][0];

            $sql = "
            SELECT user_id as userId,
                   ROUND(SUM(duration)/3600,2) as totalWalltime,
                   COUNT(*) as jobCount,
                   ROUND(SUM(duration*num_nodes*".$cluster['coresPerNode'].")/3600,2) as totalCoreHours
            FROM job
            WHERE duration>0
            AND job.cluster_id=".$cluster['id']."
            AND job.start_time BETWEEN $startTime AND $stopTime
            GROUP BY 1
            ";

            $users = $this->_connection->fetchAll($sql);

            $sql = "
            SELECT user_id as userId,
                   COUNT(*) as count
            FROM job
            WHERE duration<120
            AND job.cluster_id=".$cluster['id']."
            AND job.start_time BETWEEN $startTime AND $stopTime
            GROUP BY 1
            ";

            $shortJobs = $this->_connection->fetchAll($sql);

            foreach ( $shortJobs as $id => &$user ){
                $index = $user['userId'];
                $lookup[$index] = $user['count'];
            }

        } else {
            foreach ( $settings['clusters'] as $cluster ){
                $sql = "
                SELECT user_id as userId,
                       SUM(duration)/3600 as totalWalltime,
                       COUNT(*) as totalJobs,
                       SUM(duration*num_nodes*".$cluster['coresPerNode'].")/3600 as coreHours
                FROM job
                WHERE duration>0
                AND job.cluster_id=".$cluster['id']."
                AND job.start_time BETWEEN $startTime AND $stopTime
                GROUP BY 1
                ";

                $tmpUsers = $this->_connection->fetchAll($sql);

                foreach ( $tmpUsers as $user ){
                    $users[ $user['userId'] ][ $cluster['id'] ]['totalWalltime'] = $user['totalWalltime'];
                    $users[ $user['userId'] ][ $cluster['id'] ]['totalJobs']     = $user['totalJobs'];
                    $users[ $user['userId'] ][ $cluster['id'] ]['coreHours']     = $user['coreHours'];
                }
            }

            foreach ( $users as $id => &$user ){
                /* TODO Remove workaround */
                if ( is_null($id) ){
                    $id = 1;
                }
                $user['userId'] = $id;
                $user['totalWalltime'] = 0;
                $user['totalJobs'] = 0;
                $user['coreHours'] = 0;

                foreach ( $settings['clusters'] as $cluster ){
                    $user['totalWalltime'] += $user[ $cluster['id'] ]['totalWalltime'];
                    $user['totalJobs'] += $user[ $cluster['id'] ]['totalJobs'];
                    $user['coreHours'] += $user[ $cluster['id'] ]['coreHours'];
                }
            }
        }

        foreach ( $users as $id => &$user ){
            $userObject = $this->_userRepository->find($user['userId']);
            $user['userName'] = $userObject->getUserId();
            $this->_logger->info("SHORT: ", $user);
            $index = $user['userId'];
            if ( isset($lookup[$index]) ){
                $user['shortJobs'] = $lookup[$index];
            } else {
                $user['shortJobs'] = 0;
            }
        }

        return $users;
    }


    public function findJobById($jobId, $userId)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('j')
           ->andWhere("j.id = $jobId");

        if ( $userId ){
            $qb->andWhere("j.user = $userId");
        }

        return $qb
            ->getQuery()
            ->getSingleResult();
    }
}
