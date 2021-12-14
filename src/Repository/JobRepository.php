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

    private function buildJobFilter($qb, $filterList, $sorting)
    {
        if ($filterList) {
            foreach ($filterList as $i => $filter) {
                if (isset($filter['jobId']))
                    $this->addStringCondition($qb, 'jobId', $i, $filter['jobId']);
                if (isset($filter['userId']))
                    $this->addStringCondition($qb, 'user', $i, $filter['userId']);
                if (isset($filter['projectId']))
                    $this->addStringCondition($qb, 'project', $i, $filter['projectId']);
                if (isset($filter['cluster']))
                    $this->addStringCondition($qb, 'cluster', $i, $filter['cluster']);

                if (isset($filter['duration']))
                    $qb->andWhere("j.duration BETWEEN :duration_from_$i AND :duration_to_$i")
                       ->setParameter("duration_from_$i", $filter['duration']['from'])
                       ->setParameter("duration_to_$i", $filter['duration']['to']);

                if (isset($filter['numNodes']))
                    $qb->andWhere("j.numNodes BETWEEN :numNodes_from_$i AND :numNodes_to_$i")
                       ->setParameter("numNodes_from_$i", $filter['numNodes']['from'])
                       ->setParameter("numNodes_to_$i", $filter['numNodes']['to']);

                if (isset($filter['startTime'])) {
                    if (isset($filter['startTime']['from']))
                        $qb->andWhere(":starttime_from <= j.startTime")->setParameter("starttime_from", $filter['startTime']['from']);
                    if (isset($filter['startTime']['to']))
                        $qb->andWhere("j.startTime <= :starttime_to")->setParameter("starttime_to", $filter['startTime']['to']);
                }

                if (isset($filter['isRunning']))
                    $qb->andWhere('j.isRunning = '.($filter['isRunning'] ? 'true' : 'false'));

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

    public function findStatistics($filters, $clusters, $groupBy, $hist1, $hist2)
    {
        $stats = [];
        foreach ($clusters as &$cluster) {
            $filter = $filters; // PHP is strange
            $filter[] = ['cluster' => ['eq' => $cluster['clusterID']]];
            $coresPerNode = $cluster['socketsPerNode'] * $cluster['coresPerSocket'];
            $qb = $this->createQueryBuilder('j');
            $this->buildJobFilter($qb, $filter, null);
            if ($groupBy == null) {
                $qb->select([
                    'COUNT(j.id)', 'SUM(j.duration) / 3600',
                    'SUM(j.duration * j.numNodes * '.$coresPerNode.') / 3600']);
                $res = $qb->getQuery()->getSingleResult();
                if (!isset($stats[0]))
                    $stats[0] = [ 'totalJobs' => 0, 'totalWalltime' => 0, 'totalCoreHours' => 0 ];

                $stats[0]['totalJobs'] += intval($res[1]);
                $stats[0]['totalWalltime'] += intval($res[2]);
                $stats[0]['totalCoreHours'] += intval($res[3]);
            } else {
                $qb->select([
                    'j.'.strtolower($groupBy).'Id AS gid', 'COUNT(j.id) AS jobs', 'SUM(j.duration) / 3600 AS walltime',
                    'SUM(j.duration * j.numNodes * '.$coresPerNode.') / 3600 AS corehours'])->groupBy('gid');

                $rows = $qb->getQuery()->getResult();
                foreach ($rows as $row) {
                    $s = $stats[$row['gid']] ?? [ 'id' => $row['gid'], 'totalJobs' => 0,
                        'totalWalltime' => 0, 'totalCoreHours' => 0 ];

                    $s['totalJobs'] += intval($row['jobs']);
                    $s['totalWalltime'] += intval($row['walltime']);
                    $s['totalCoreHours'] += intval($row['corehours']);
                    $stats[$row['gid']] = $s;
                }
            }
        }

        $qb = $this->createQueryBuilder('j');
        $this->buildJobFilter($qb, $filter, null);
        if ($groupBy == null) {
            $qb->select('COUNT(j.id)')->andWhere('j.duration < 120');
            $stats[0]['shortJobs'] = $qb->getQuery()->getSingleResult()[1];
        } else {
            $qb->select(['j.'.strtolower($groupBy).'Id AS id', 'COUNT(j.'.strtolower($groupBy).'Id) AS count'])
                ->andWhere('j.duration < 120')->groupBy('id');

            $rows = $qb->getQuery()->getResult();
            foreach ($rows as $row) {
                $stats[$row['id']]['shortJobs'] = $row['count'];
            }
        }

        if (!$hist1 && !$hist2)
            return $stats;

        if ($groupBy == null) {
            $stats[0]['histWalltime'] = $this->buildHistogram('ROUND(j.duration / 3600) as value', $filters);
            $stats[0]['histNumNodes'] = $this->buildHistogram('j.numNodes as value', $filters);
        } else {
            foreach ($stats as &$stat) {
                $stat['histWalltime'] = $this->buildHistogram('ROUND(j.duration / 3600) as value',
                    $filters, $stat['id'], 'j'.strtolower($groupBy).'Id');
                $stat['histNumNodes'] = $this->buildHistogram('j.numModes as value',
                    $filters, $stat['id'], 'j'.strtolower($groupBy).'Id');
            }
        }

        return $stats;
    }

    private function buildHistogram($value, $filters, $id = null, $col = null)
    {
            $qb = $this->createQueryBuilder('j');
            $qb->select([$value, 'COUNT(j.id) AS count']);
            $this->buildJobFilter($qb, $filters, null);
            if ($id != null && $col != null)
                $qb->andWhere($col.' = :id')->setParameter('id', $id);
            $qb->groupBy('value')->orderBy('value');
            return $qb->getQuery()->getResult();
    }

    public function findBatchJob($jobId, $clusterId, $startTime)
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('j')->where("j.jobId = :jobId");
        $qb->setParameter('jobId', $jobId);

        if ( $clusterId ){
            $qb->andWhere("j.cluster = :cluster");
            $qb->setParameter('cluster', $clusterId);
        }
        // if ( $startTime ){
        //     $qb->andWhere("j.startTime = $startTime");
        // }

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
