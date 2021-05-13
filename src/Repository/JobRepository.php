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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Job;
use App\Entity\User;

class JobRepository extends ServiceEntityRepository
{
    private $_timing;
    private $_logger;
    private $_connection;
    private $_userRepository;

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

    private function addStringCondition($qb, $field, $i, $cond)
    {
        if (isset($cond['eq']))
            $qb->andWhere("j.$field = :{$field}_$i")
               ->setParameter("{$field}_$i", $cond['eq']);

        if (isset($cond['contains']))
            $qb->andWhere("j.$field LIKE :{$field}_c_$i")
               ->setParameter("{$field}_c_$i", '%'.$cond['contains'].'%');

        if (isset($cond['startsWith']))
            $qb->andWhere("j.$field LIKE :{$field}_sw_$i")
               ->setParameter("{$field}_sw_$i", $cond['startsWith'].'%');

        if (isset($cond['endsWith']))
            $qb->andWhere("j.$field LIKE :{$field}_ew_$i")
               ->setParameter("{$field}_ew_$i", '%'.$cond['endsWith']);
    }

    private function buildJobFilter($qb, $filter, $sorting)
    {
        if ($filter && isset($filter['list'])) {
            $filterList = $filter['list'];
            foreach ($filterList as $i => $filter) {
                if (isset($filter['jobId']))
                    $this->addStringCondition($qb, 'jobId', $i, $filter['jobId']);
                if (isset($filter['userId']))
                    $this->addStringCondition($qb, 'userId', $i, $filter['userId']);
                if (isset($filter['projectId']))
                    $this->addStringCondition($qb, 'projectId', $i, $filter['projectId']);
                if (isset($filter['clusterId']))
                    $this->addStringCondition($qb, 'clusterId', $i, $filter['clusterId']);

                if (isset($filter['duration']))
                    $qb->andWhere("j.duration BETWEEN :duration_from_$i AND :duration_to_$i")
                       ->setParameter("duration_from_$i", $filter['duration']['from'])
                       ->setParameter("duration_to_$i", $filter['duration']['to']);

                if (isset($filter['numNodes']))
                    $qb->andWhere("j.numNodes BETWEEN :numNodes_from_$i AND :numNodes_to_$i")
                       ->setParameter("numNodes_from_$i", $filter['numNodes']['from'])
                       ->setParameter("numNodes_to_$i", $filter['numNodes']['to']);

                if (isset($filter['startTime']))
                    $qb->andWhere("j.startTime BETWEEN :startTime_from_$i AND :startTime_to_$i")
                       ->setParameter("startTime_from_$i", $filter['startTime']['from'])
                       ->setParameter("startTime_to_$i", $filter['startTime']['to']);

                if (isset($filter['hasProfile']))
                    $qb->andWhere('j.hasProfile = '.$filter['hasProfile']);

                if (isset($filter['tags']))
                    $qb->join('j.tags', 't')
                       ->andWhere($qb->expr()->in('t.id', $filter['tags']));
            }
        }

        if ($sorting)
            $qb->orderBy('j.'.$sorting['field'], $sorting['order']);
    }

    public function countJobs($filter, $sorting)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('count(j)');
        $this->buildJobFilter($qb, $filter, $sorting);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFilteredJobs($page, $filter, $sorting)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('j');
        $this->buildJobFilter($qb, $filter, $sorting);

        if ($page) {
            $qb->setFirstResult(($page['page'] - 1) * $page['itemsPerPage']);
            $qb->setMaxResults($page['itemsPerPage']);
        } else {
            $qb->setMaxResults(50);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    /*
     * Filters are expected in the same format as for
     * findFilteredJobs() and countJobs() (therefore,
     * the GraphQL JobFilterList type).
     */
    public function findFilteredStatistics($filter)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select([
            'COUNT(j.id)',
            'SUM(j.duration) / 3600',
            'SUM(j.duration * j.numNodes) / 3600'
        ]);
        $this->buildJobFilter($qb, $filter, null);
        $res = $qb->getQuery()->getSingleResult();
        $stats = [
            'totalJobs' => $res[1],
            'totalWalltime' => intval($res[2]),
            'totalCoreHours' => intval($res[3])
        ];

        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j.id)')
           ->andWhere('j.duration < 120');
        $this->buildJobFilter($qb, $filter, null);
        $stats['shortJobs'] = $qb->getQuery()->getSingleResult()[1];

        // histWalltime
        // TODO/FIXME: No int division in standard SQL?
        $qb = $this->createQueryBuilder('j');
        $qb->select([
            '(j.duration / 3600) as value',
            'count(j.id) as count'
        ]);
        $this->buildJobFilter($qb, $filter, null);
        $qb->groupBy('value');
        $rows = $qb->getQuery()->getResult();
        $histo = [];
        // The problem: value is a float, grouping is broken
        foreach ($rows as $row) {
            $value = intval($row['value']);
            if (isset($histo[$value]))
                $histo[$value] += $row['count'];
            else
                $histo[$value] = $row['count'];
        }
        $histWalltime = [];
        foreach ($histo as $value => $count) {
            $histWalltime[] = ['count' => $count, 'value' => $value];
        }


        // histNumNodes
        $histNumNodes = [];
        $qb = $this->createQueryBuilder('j');
        $qb->select(['j.numNodes as value', 'count(j.id) as count']);
        $this->buildJobFilter($qb, $filter, null);
        $qb->groupBy('value')->orderBy('value');
        $rows = $qb->getQuery()->getResult();
        foreach ($rows as $row) {
            $histNumNodes[] = $row;
        }

        $stats['histWalltime'] = $histWalltime;
        $stats['histNumNodes'] = $histNumNodes;
        return $stats;
    }

    public function getFilterRanges($cluster = null)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select([
            'MIN(j.duration)', 'MAX(j.duration)',
            'MIN(j.numNodes)', 'MAX(j.numNodes)',
            'MIN(j.startTime)', 'MAX(j.startTime)'
        ]);

        if ($cluster != null)
            $qb->where('j.clusterId = :cluster')
               ->setParameter('cluster', $cluster);

        $res = $qb->getQuery()->getSingleResult();
        return [
            'duration' => [ 'from' => $res[1], 'to' => $res[2] ],
            'numNodes' => [ 'from' => $res[3], 'to' => $res[4] ],
            'startTime' => [ 'from' => intval($res[5]), 'to' => intval($res[6]) ]
        ];
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

    public function findBatchJob($jobId, $clusterId, $startTime)
    {
        $this->_logger->info("Find BatchJobs: " . $jobId);

        $qb = $this->createQueryBuilder('j');
        $qb->select('j')->where("j.jobId = :jobId");
        $qb->setParameter('jobId', $jobId);

        if ( $clusterId ){
            $qb->andWhere("j.clusterId = :clusterId");
            $qb->setParameter('clusterId', $clusterId);
        }
/*         if ( $startTime ){ */
/*             $qb->andWhere("j.startTime = $startTime"); */
/*         } */

        return $qb
            ->getQuery()
            ->getSingleResult();
    }

    public function findJobById($jobId, $userId)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('j')
           ->andWhere("j.id = :jobId")
           ->setParameter('jobId', $jobId);

        if ( $userId ){
            $qb->andWhere("j.user = :userId")
               ->setParameter('userId', $userId);
        }

        return $qb
            ->getQuery()
            ->getSingleResult();
    }
}
