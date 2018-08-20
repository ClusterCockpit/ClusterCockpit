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

class DoctrineMetricDataRepository implements MetricDataRepository
{
    private $_connection;

    public function __construct(
        EntityManagerInterface $em
    )
    {
        $this->_connection = $em->getConnection();
    }

    public function getJobRoofline($job, $metrics)
    {
        $id = $job->getId();
        $startTime = $job->getStartTime();
        $stopTime = $job->getStopTime();

        $flopsAny = $metrics['flops_any'];
        $memBw = $metrics['mem_bw'];

        if ($job->isRunning()) {
            $joinTable = 'Rjobs_nodes';
        } else {
            $joinTable = 'jobs_nodes';
        }

        $bwSlot = sprintf("slot_%d", $memBw->slot);
        $flopsSlot = sprintf("slot_%d", $flopsAny->slot);

        $sql = "
        SELECT ROUND($flopsSlot*0.001,2) AS y, case when $bwSlot=0 then 0 else ROUND($flopsSlot/$bwSlot,2) end as x
        FROM data
        INNER JOIN $joinTable ON data.node_id = $joinTable.node_id
        WHERE $joinTable.job_id=$id
        AND data.epoch BETWEEN $startTime AND $stopTime
        ORDER BY data.epoch ASC
        ";

        return $this->_connection->fetchAll($sql);
    }

    public function hasProfile($job)
    {
        $nodes = $job->getNodes();
        $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();

        if ( count($nodes) < 1 ){
            return false;
        }

        $sql = "SELECT count(*) FROM data
                WHERE node_id = {$nodes->first()->getId()}
                AND epoch BETWEEN {$job->startTime} AND {$job->stopTime}";

        $stmt = $this->_connection->prepare($sql);
        $stmt->execute();
        $count = $stmt->fetch();

        if ( $count < 4 ){
            $job->hasProfile = false;
            return false;
        } else {
            $job->hasProfile = true;
            return true;
        }
    }

    public function getJobStats($job, $metrics)
    {
        $metricString = '';
        $first = true;

        if ($job->isRunning()) {
            $joinTable = 'Rjobs_nodes';
        } else {
            $joinTable = 'jobs_nodes';
        }

        foreach ( $metrics as $metric ){
            $name = $metric->name;
            $slot = sprintf("slot_%d", $metric->slot);
            $scale = $metric->scale;

            if ( $first ) {
                $first = false;
                $metricString .= "ROUND(AVG($slot * $scale),2) AS {$name}_avg";
                $metricString .= ",ROUND(MIN($slot * $scale),2) AS {$name}_min";
                $metricString .= ",ROUND(MAX($slot * $scale),2) AS {$name}_max";
            } else {
                $metricString .= ",ROUND(AVG($slot * $scale),2) AS {$name}_avg";
                $metricString .= ",ROUND(MIN($slot * $scale),2) AS {$name}_min";
                $metricString .= ",ROUND(MAX($slot * $scale),2) AS {$name}_max";
            }
        }

        $sql = "SELECT data.node_id as nodeId,
            $metricString
            FROM data
            WHERE data.node_id IN (
                SELECT node.id
                FROM node
                INNER JOIN $joinTable ON node.id = $joinTable.node_id
                WHERE $joinTable.job_id = {$job->id})
                AND epoch BETWEEN {$job->startTime} AND {$job->stopTime}
                GROUP BY data.node_id";

        $stmt = $this->_connection->prepare($sql);
        $stmt->execute();
        $nodeStats = $stmt->fetchAll();
        $stats['nodeStats'] = $nodeStats;
        $sums = array();

        foreach ( $metrics as $metric ){
            $metricName = $metric->name;
            $stats["{$metricName}_min"] = 999999999999.0;
            $stats["{$metricName}_max"] = 0;
            $sums[$metricName] = 0;
        }

        foreach ($nodeStats as $node){
            foreach ( $metrics as $metric ){
                $metricName = $metric->name;
                $sums[$metricName] += $node["{$metricName}_avg"];
                $stats["{$metricName}_min"] = min($stats["{$metricName}_min"], $node["{$metricName}_min"]);
                $stats["{$metricName}_max"] = max($stats["{$metricName}_max"], $node["{$metricName}_max"]);
            }
        }

        foreach ( $metrics as $metric ){
            $metricName = $metric->name;
            $stats["{$metricName}_avg"] = round($sums[$metricName]/count($nodeStats), 2);
        }

        return $stats;
    }

    public function getMetricData($job, $metrics)
    {
        $metricString = '';
        $first = true;
        $nodes = $job->getNodes();
        $startTime = $job->getStartTime();
        $stopTime = $job->getStopTime();

        if ( count($nodes) < 1 ){
            return false;
        }

        foreach ( $metrics as $metric ){
            $name = $metric->name;
            $slot = sprintf("slot_%d", $metric->slot);
            $scale = $metric->scale;

            if ( $first ) {
                $first = false;
                $metricString = "epoch, ROUND($slot * $scale,1) AS $name";
            } else {
                $metricString .= ", ROUND($slot * $scale,1) AS $name";
            }
        }

        foreach ($nodes as $node){

            $id = $node->getId();
            $nodeId = $node->getNodeId();

            $sql = "
                   SELECT $metricString FROM data d
                   WHERE d.node_id = $id
                   AND epoch BETWEEN $startTime AND $stopTime
                   ORDER BY d.epoch ASC
                   ";

            $stmt = $this->_connection->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch();
            $start =  $row['epoch'];

            foreach ($metrics as $metric) {
                $metricName = $metric->getName();
                $data[$metricName][$nodeId]['x'][] =  0;
                $data[$metricName][$nodeId]['y'][] =  $row[$metricName];
            }

            while ($row = $stmt->fetch()) {
                $time = $row['epoch'] - $start;

                foreach ($metrics as $metric) {
                    $metricName = $metric->getName();
                    $data[$metricName][$nodeId]['x'][] = $time;
                    $data[$metricName][$nodeId]['y'][] = $row[$metricName];
                }
            }
        }

        $nodeId = $nodes->first()->getNodeId();
        $metricName = $metrics->first()->getName();

        if ( count($data[$metricName][$nodeId]['x']) < 4 ){
            return false;
        } else {
            return $data;
        }
    }
}

    /* public function fetchClusterRoofline($clusterId):array */
    /* { */
    /*     $sql = " */
    /*     SELECT ROUND(data.flops_any*0.001,2) AS y, ROUND(data.flops_any/data.mem_bw,2) as x */
    /*     FROM data */
    /*     WHERE data.node_id IN */
    /*     (SELECT id */
    /*     FROM node */
    /*     WHERE node.cluster=1) */
    /*     AND data.epoch IN ( */
    /*         SELECT MAX(data.epoch) */
    /*         FROM data */
    /*         GROUP BY data.node_id */
    /*     ) */
    /*     AND data.mem_bw > 0; */
    /*     "; */

    /*     return $this->_connection->fetchAll($sql); */
    /* } */
    /* public function fetchJobCloudData($userId, $control) */
    /* { */
    /*     $stat = array(); */
    /*     $settings = $this->getSettings($control); */
    /*     $startTime = $settings['startTime']; */
    /*     $stopTime = $settings['stopTime']; */
    /*     $cluster = $settings['clusters'][0]['id']; */
    /*     $qb = $this->createQueryBuilder('j'); */

    /*     /1* fetch job list *1/ */
    /*     $jobs = $qb->select('j') */
    /*         ->where('j.user = ?1') */
    /*         ->andWhere('j.startTime between ?2 and ?3') */
    /*         ->andWhere('j.cluster = ?4') */
    /*         ->setParameter(1, $userId) */
    /*         ->setParameter(2, $startTime) */
    /*         ->setParameter(3, $stopTime) */
    /*         ->setParameter(4, $cluster) */
    /*         ->getQuery() */
    /*         ->getResult(); */

    /*     $x; $y; $color; */
    /*     $hasData = false; */

    /*     foreach ($jobs as $job){ */
    /*         $jobId = $job->getId(); */
    /*         $startTime = $job->getStartTime(); */
    /*         $stopTime = $job->getStopTime(); */

    /*         $sql = " */
    /*         SELECT ROUND(AVG(mem_bw),2) as mem_bw, ROUND(AVG(flops_any),2) as flops_any */
    /*         FROM data */
    /*         INNER JOIN jobs_nodes ON data.node_id = jobs_nodes.node_id */
    /*         WHERE jobs_nodes.job_id=$jobId */
    /*         AND data.epoch BETWEEN $startTime AND $stopTime */
    /*         "; */

    /*         $result = $this->_connection->fetchAll($sql); */

    /*         if (isset($result[0]['flops_any'])){ */
    /*             $x[] = $result[0]['flops_any']/$result[0]['mem_bw']; */
    /*             $y[] = $result[0]['flops_any']*0.001; */
    /*             $color[] = $job->getNumNodes(); */
    /*             $hasData = true; */
    /*         } */
    /*     } */


    /*     if (! $hasData ){ */
    /*         return false; */
    /*     } else { */
    /*         return array( */
    /*             'x' => $x, */
    /*             'y' => $y, */
    /*             'color' => $color */
    /*         ); */
    /*     } */
    /* } */

