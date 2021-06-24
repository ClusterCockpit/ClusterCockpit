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

                if (isset($filter['flopsAnyAvg']))
                    $qb->andWhere('j.flopsAnyAvg BETWEEN '
                        .$filter['flopsAnyAvg']['from'].' AND '.$filter['flopsAnyAvg']['to']);

                if (isset($filter['memBwAvg']))
                    $qb->andWhere('j.memBwAvg BETWEEN '
                        .$filter['memBwAvg']['from'].' AND '.$filter['memBwAvg']['to']);

                if (isset($filter['loadAvg']))
                    $qb->andWhere('j.loadAvg BETWEEN '
                        .$filter['loadAvg']['from'].' AND '.$filter['loadAvg']['to']);

                if (isset($filter['memUsedMax']))
                    $qb->andWhere('j.memUsedMax BETWEEN '
                        .$filter['memUsedMax']['from'].' AND '.$filter['memUsedMax']['to']);
            }
        }

        if ($sorting)
            $qb->andWhere('j.'.$sorting['field'].' IS NOT NULL')
               ->orderBy('j.'.$sorting['field'], $sorting['order']);
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
        } else if ($page === null) {
            $qb->setMaxResults(50);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    private function filteredStatisticsPerCluster($filter, $cluster)
    {
        $coresPerNode = $cluster['socketsPerNode'] * $cluster['coresPerSocket'];
        $filter['clusterId'] = ['eq' => $cluster['clusterID']];
        $qb = $this->createQueryBuilder('j');
        $qb->select([
            'COUNT(j.id)',
            'SUM(j.duration) / 3600',
            'SUM(j.duration * j.numNodes * '.$coresPerNode.') / 3600'
        ]);
        $this->buildJobFilter($qb, $filter, null);
        $res = $qb->getQuery()->getSingleResult();
        return [
            'totalJobs' => $res[1],
            'totalWalltime' => intval($res[2]),
            'totalCoreHours' => intval($res[3])
        ];
    }

    /*
     * Filters are expected in the same format as for
     * findFilteredJobs() and countJobs() (therefore,
     * the GraphQL JobFilterList type).
     */
    public function findFilteredStatistics($filter, $clusterCfg)
    {
        $stats = null;
        if (isset($filter['clusterId'])) {
            $stats = $this->filteredStatisticsPerCluster($filter,
                $clusterCfg->getClusterConfiguration($filter['clusterId']));
        } else {
            $stats = ['totalJobs' => 0, 'totalWalltime' => 0, 'totalCoreHours' => 0];
            foreach ($clusterCfg->getConfigurations() as $cluster) {
                $res = $this->filteredStatisticsPerCluster($filter, $cluster);
                $stats['totalJobs'] += $res['totalJobs'];
                $stats['totalWalltime'] += $res['totalWalltime'];
                $stats['totalCoreHours'] += $res['totalCoreHours'];
            }
        }

        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j.id)')
           ->andWhere('j.duration < 120');
        $this->buildJobFilter($qb, $filter, null);
        $stats['shortJobs'] = $qb->getQuery()->getSingleResult()[1];

        // histWalltime
        $histWalltime = [];
        $qb = $this->createQueryBuilder('j');
        $qb->select([
            'ROUND(j.duration / 3600) as value',
            'count(j.id) as count'
        ]);
        $this->buildJobFilter($qb, $filter, null);
        $qb->groupBy('value')->orderBy('value');
        $rows = $qb->getQuery()->getResult();
        foreach ($rows as $row) {
            $histWalltime[] = $row;
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

    public function statUsers($startTime, $stopTime, $clusterId, $clusters)
    {
        $users = array();
        $lookup = array();

        foreach ( $clusters as $cluster ){
            if ($clusterId != null && $cluster['clusterID'] != $clusterId)
                continue;

            $sql = "
            SELECT user_id as userId,
                   SUM(duration)/3600 as totalWalltime,
                   COUNT(*) as totalJobs,
                   SUM(duration*num_nodes*".$cluster['socketsPerNode']."*".$cluster['coresPerSocket'].")/3600 as coreHours
            FROM job
            WHERE duration>0
            AND job.cluster_id='".$cluster['clusterID']."'
            ".($startTime != null && $stopTime != null ? "AND job.start_time BETWEEN $startTime AND $stopTime" : "")."
            GROUP BY 1
            ";

            $tmpUsers = $this->_connection->fetchAll($sql);

            foreach ( $tmpUsers as $user ){
                $users[ $user['userId'] ][ $cluster['clusterID'] ]['totalWalltime'] = $user['totalWalltime'];
                $users[ $user['userId'] ][ $cluster['clusterID'] ]['totalJobs']     = $user['totalJobs'];
                $users[ $user['userId'] ][ $cluster['clusterID'] ]['coreHours']     = $user['coreHours'];
            }
        }

        // TODO/FIXME: Change Table Layout?
        $sql = "
        SELECT DISTINCT job.user_id, user.id FROM job
        JOIN user ON user.username = job.user_id
        ";
        $rows = $this->_connection->fetchAll($sql);
        $mapping = array();
        foreach ($rows as $row) {
            $mapping[$row['user_id']] = $row['id'];
        }

        foreach ( $users as $id => &$user ){
            /* TODO Remove workaround */
            if ( is_null($id) ){
                $id = 1;
            }
            $user['id'] = $mapping[$id];
            $user['userId'] = $id;
            $user['totalWalltime'] = 0;
            $user['totalJobs'] = 0;
            $user['totalCoreHours'] = 0;

            foreach ( $clusters as $cluster ){
                if (isset($user[ $cluster['clusterID'] ])) {
                    $user['totalWalltime'] += $user[ $cluster['clusterID'] ]['totalWalltime'];
                    $user['totalCoreHours'] += $user[ $cluster['clusterID'] ]['coreHours'];
                    $user['totalJobs'] += $user[ $cluster['clusterID'] ]['totalJobs'];
                }
            }
        }

        foreach ( $users as $id => &$user ){

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

    public function findJobById($id)
    {
        $qb = $this->createQueryBuilder('j');
        return $qb->select('j')
                  ->andWhere("j.id = :id")
                  ->setParameter('id', $id)
                  ->getQuery()
                  ->getSingleResult();
    }

    public function persistJob($job)
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($job);
        $entityManager->flush();
    }
}
