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

use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Job;
use App\Entity\User;
use App\Entity\Cluster;
use App\Entity\JobSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

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

    public function getSettings($control): array
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
            $clusters[] =  array(
                'id' => $clusterId,
                'coresPerNode' => $this->_clusters[$clusterId]['coresPerNode'],
                'name' => $this->_clusters[$clusterId]['name']
            );
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

    public function countFilteredJobs($userId,  $filter, $search = NULL )
    {
        $qb = $this->createQueryBuilder('j');

        $qb->select('count(j.id)')
           ->where("j.duration > 300");  /* TODO: Make this configurable */

        if ( ! is_null($search) ){
            $qb->andWhere($qb->expr()->between('j.numNodes',$search['numNodesFrom'],$search['numNodesTo']));
            $qb->andWhere($qb->expr()->between( 'j.duration', $search['durationFrom'], $search['durationTo']));
            $qb->andWhere($qb->expr()->between( 'j.startTime', $search['dateFrom'], $search['dateTo']));

            if ( $search['clusterId'] != 0 ){  /* 0 means all Clusters */
                $qb->andWhere("j.cluster = $search[clusterId]");
            }

            /* regular user is not allowed to search or filter for users */
            if ( $userId ){
                $qb->andWhere("j.user = $userId");
            } else {

                if( $filter ){
                    $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                       ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
                }

                if ( isset($search['userId']) ){

                    $userId = $search['userId'];

                    if ( is_numeric($userId) ){
                        $qb->andWhere("j.user = $userId");
                    } else {
                        $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                           ->setParameter('word', '%'.addcslashes($userId, '%_').'%');
                    }
                }
            }
        } else {
            $qb->andWhere("j.isRunning = true");

            /* regular user is not allowed to filter for users */
            if ( $userId ){
                $qb->andWhere("j.user = $userId");
            } else {
                if( $filter ){
                    $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                       ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
                }
            }
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
        $search = NULL )
    {
        $qb = $this->createQueryBuilder('j');

        $qb->select('j')
           ->where("j.duration > 300")
           ->orderBy('j.'.$sorting['col'], $sorting['order'])
           ->setFirstResult( $offset )
           ->setMaxResults( $limit );


        if ( ! is_null($search) ){
            $qb->andWhere($qb->expr()->between('j.numNodes',$search['numNodesFrom'],$search['numNodesTo']));
            $qb->andWhere($qb->expr()->between( 'j.duration', $search['durationFrom'], $search['durationTo']));
            $qb->andWhere($qb->expr()->between( 'j.startTime', $search['dateFrom'], $search['dateTo']));

            if ( $search['clusterId'] != 0 ) { /* 0 means all Clusters */
                $qb->andWhere("j.cluster = $search[clusterId]");
            }

            /* regular user is not allowed to search or filter for users */
            if ( $userId ){
                $qb->andWhere("j.user = $userId");
            } else {

                if( $filter ){
                    $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                       ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
                }

                if ( isset($search['userId']) ){

                    $userId = $search['userId'];

                    if ( is_numeric($userId) ){
                        $qb->andWhere("j.user = $userId");
                    } else {
                        $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                           ->setParameter('word', '%'.addcslashes($userId, '%_').'%');
                    }
                }
            }
        } else {
            $qb->andWhere("j.isRunning = true");

            /* regular user is not allowed to filter for users */
            if ( $userId ){
                $qb->andWhere("j.user = $userId");
            } else {
                if( $filter ){
                    $qb->innerJoin('j.user', 'u', 'WITH', "u.username LIKE :word")
                       ->setParameter('word', '%'.addcslashes($filter, '%_').'%');
                }
            }

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

    public function findByJobSearch(JobSearch $search)
    {
        $qb = $this->createQueryBuilder('j');
        $userId = $search->getUserId();

        if (isset($userId)){
            $user_rep = $this->getEntityManager()->getRepository(User::class);
            $user = $user_rep->findOneByUserId($search->getUserId());

            $qb->andWhere("j.user =".$user->getId());
        }

        /* Convert to seconds */
        $durationFrom = $search->getDurationFrom()->h*3600+$search->getDurationFrom()->m*60;
        $durationTo = $search->getDurationTo()->h*3600+$search->getDurationFrom()->m*60;
        $startFrom = $search->getDateFrom();
        $startTo = $search->getDateTo();

        $qb->andWhere($qb->expr()->between('j.numNodes',$search->getnumNodesFrom(),$search->getnumNodesTo()));
        $qb->andWhere($qb->expr()->between( 'j.duration', $durationFrom, $durationTo));
        $qb->andWhere($qb->expr()->between( 'j.startTime', $startFrom, $startTo));
        $qb->andWhere('j.cluster =1');

        return $qb
            ->orderBy('j.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAvgTodo($startTime, $stopTime)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('j')
           ->where($qb->expr()->between( 'j.startTime', $startTime, $stopTime))
           ->andWhere("j.duration > 300");

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findByUser($userId, $limit, $control )
    {
        $settings = $this->getSettings($control);
        $qb = $this->createQueryBuilder('j');

        $startTime = $settings['startTime'];
        $stopTime = $settings['stopTime'];

        $qb->select('j')
           ->where('j.user = ?1')
           ->orderBy('j.startTime', 'DESC')
           ->setMaxResults( $limit )
           ->setParameter(1, $userId);
        $qb->andWhere($qb->expr()->between( 'j.startTime', $startTime, $stopTime));

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findBySystem($control)
    {
        $qb = $this->createQueryBuilder('j');

        $qb->select('j')
           ->where('j.cluster = ?1')
           ->orderBy('j.numNodes', 'DESC')
           ->setMaxResults( 20 )
           ->setParameter(1, $control->getCluster());

        return $qb
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

    public function statClusters($control)
    {
        $stat = array();
        $settings = $this->getSettings($control);
        $stat['stat'] = $this->getStats($settings);
        $stat['histo_runtime'] = $this->getHisto($settings,'floor(duration/3600)+1');
        $stat['histo_numnodes'] = $this->getHisto($settings,'num_nodes');
        return $stat;
    }

    public function statUsers($control)
    {
        $settings = $this->getSettings($control);
        $startTime = $settings['startTime'];
        $stopTime = $settings['stopTime'];
        $users = array();
        $lookup;

        if ( $settings['oneSystem'] ){
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

    public function statGroups($control)
    {
        $settings = $this->getSettings($control);
        $stat = array();

        $sql = "SELECT id, group_id FROM unix_group";
        $groups = $this->_connection->fetchAll($sql);

        foreach ( $groups as $group ){
            $stat[] = array(
                'groupId' => $group['id'],
                'groupName' => $group['group_id'],
                'stat' => $this->findStatByGroup((int) $group['id'], $settings)
            );
        }

        return $stat;
    }

    public function findStatByGroup($groupId, $settings)
    {
        $join = "INNER JOIN users_groups ON job.user_id = users_groups.user_id ";
        $constraint = "AND users_groups.group_id=$groupId";
        $stat['stat'] = $this->getStats($settings,$constraint, $join);
        $stat['histo_runtime'] = $this->getHisto($settings,'floor(duration/3600)+1',$constraint, $join);
        $stat['histo_numnodes'] = $this->getHisto($settings,'num_nodes',$constraint, $join);
        return $stat;
    }

    public function getNumUsers()
    {
        $sql = "SELECT COUNT(DISTINCT(user_id)) AS count FROM job WHERE status='running'";
        $stmt = $this->_connection->prepare($sql);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function persistJobSeverity($job){
        $id = $job->getId();
        $severity = $job->severity;

        $sql = "UPDATE job SET severity=$severity WHERE id=$id";
        $stmt = $this->_connection->prepare($sql);
        $stmt->execute();
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
