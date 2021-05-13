<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2021 Jan Eitzinger
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

class InfluxDBMetricDataRepository implements MetricDataRepository
{
    private $_timing;
    private $_database;
    private $_logger;

    public function __construct(
        LoggerInterface $logger
    )
    {
        $this->_timer = new Stopwatch();
        $this->_logger = $logger;
        $influxdbURL = getenv('INFLUXDB_URL');
	    $this->_logger->info("Scheme: $influxdbURL");
        $this->_database = \InfluxDB\Client::fromDSN("influxdb://symfony:mashup@127.0.0.1:8086/ClusterCockpit");
    }

    public function getJobRoofline($job, $metrics)
    {
        $nodes = $job->getNodeNameArray();

        $flopsAny = $metrics['flops_any'];
        $memBw = $metrics['mem_bw'];
        $nodes = implode('|', $nodes);
        $stopTime = $job->startTime + $job->duration;

        $query = "SELECT {$flopsAny['name']}*{$flopsAny['scale']}
            FROM {$flopsAny['measurement']}
            WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
            AND host =~ /$nodes/";

        $result = $this->_database->query($query, ['epoch' => 's']);
        $points[0] = $result->getPoints();

        $query = "SELECT {$memBw['name']}*{$memBw['scale']}
            FROM {$memBw['measurement']}
            WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
            AND host =~ /$nodes/";

        $this->_timer->start('InfluxDB');
        $result = $this->_database->query($query, ['epoch' => 's']);
        $points[1] = $result->getPoints();
        $this->_timer->stop( 'InfluxDB');

        foreach ( $points[0] as $index => $point ){
            $memBw = $points[1][$index]['mem_bw'];

            if ( $memBw != 0 ){
                $intensity = $point['flops_any']/$memBw;

                $roofline[] = array(
                    'x' => round($intensity,2),
                    'y' => round($point['flops_any'],2)
                );
            }
        }

        return $roofline;
    }

    public function hasProfile($job, $metric)
    {
        $nodes = $job->getNodeArray();

        if ( count($nodes) < 1 ){
            $job->hasProfile = false;
            return false;
        }
        $stopTime = $job->startTime + $job->duration;

        /* TODO add measurement */
        $query = "SELECT COUNT({$metric['name']})
            FROM {$metric['measurement']}
            WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
            AND host = '{$nodes[0]}'";

        $this->_logger->info("InfluxDB QUERY: $query");
        $result = $this->_database->query($query);
        $points = $result->getPoints();

        if ( count($points) == 0 || $points[0]['count'] < 4 ){
            $job->hasProfile = false;
            return false;
        } else {
            $job->hasProfile = true;
            return true;
        }
    }

    public function getJobStats($job, $metrics)
    {
        $nodes = $job->getNodes('|');
        $stopTime = $job->startTime + $job->duration;

        foreach ( $metrics as $metric ){
            $name = $metric['name'];
            $scale = sprintf("%f",$metric['scale']);

            $query = "SELECT
                MEAN($name) * $scale AS {$name}_avg
                ,MIN($name)  * $scale AS {$name}_min
                ,MAX($name)  * $scale AS {$name}_max
                FROM {$metric['measurement']}
                WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
                AND host =~ /$nodes/ GROUP BY host";
	    $this->_logger->info("InfluxDB QUERY: $query");

            $this->_timer->start( 'InfluxDB');
            $result = $this->_database->query($query);
            $queries[] = $result->getSeries();
            $this->_timer->stop( 'InfluxDB');

            $query = "SELECT
                MEAN($name) * $scale AS {$name}_avg
                ,MIN($name)  * $scale AS {$name}_min
                ,MAX($name)  * $scale AS {$name}_max
                FROM {$metric['measurement']}
                WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
                AND host =~ /$nodes/";

	    $this->_logger->info("InfluxDB JobStat QUERY: $query");
            $this->_timer->start( 'InfluxDB');
            $result = $this->_database->query($query);
            $points = $result->getPoints();
            $this->_timer->stop( 'InfluxDB');

            foreach ( $points[0] as $index => $value ){
                if ($index != 'time'){
                    $stats[$index] = round($value,2);
                }
            }
        }

        foreach ($queries as $queryresult) {
            foreach ($queryresult as $data) {
                $nodeId = $data['tags']['host'];
                $nodeData[$nodeId]['nodeId'] = $nodeId;

                foreach ( $data['columns'] as $index => $metric ){
                    if ($metric != 'time'){
                        $nodeData[$nodeId][$metric] = round($data['values'][0][$index],2);
                    }
                }
            }
        }

        foreach ($nodeData as $node) {
            $nodeStat[] = $node;
        }

        $stats['nodeStats'] = $nodeStat;

        return $stats;
    }

    public function getMetricData($job, $metrics, $options)
    {
        $nodes = $job->getNodes('|');
        $stopTime = $job->startTime + $job->duration;

        if ( $options['sample'] > 0 ){
            $sampletime = intdiv($job->duration, $options['sample']);
        }

        foreach ( $metrics as $metric ){
            $query = "SELECT
                MEAN({$metric['name']}) * $scale AS {$metric['name']}
                FROM {$metric['measurement']}
                WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
                AND host =~ /$nodes/ GROUP BY time({$sampletime}s), host";

            $this->_timer->start( 'InfluxDB');
            $result = $this->_database->query($query, ['epoch' => 's']);
            $queries[] = $result->getSeries();
            $this->_timer->stop( 'InfluxDB');
        }

        $data = array();

        foreach ($queries as $queryresult) {
            foreach ($queryresult as $seriesdata) {
                $nodeId = $seriesdata['tags']['host'];
                $start = $seriesdata['values'][0][0];

                foreach ( $seriesdata['columns'] as $index => $metric ){
                    foreach ( $seriesdata['values'] as $row ){
                        $data[$metric][$nodeId][] = $row[$index];
                    }
                }
            }
        }

        return $data;
    }

    public function getMetricCount($job, $metrics)
    {
        $nodes = $job->getNodeArray();
        $id = $nodes[0];
        $metric = reset($metrics);
        $stopTime = $job->startTime + $job->duration;

        $query = "SELECT COUNT({$metric['name']})
            FROM {$metric['measurement']}
            WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
            AND host = '$id'";

        $this->_logger->info("InfluxDB QUERY: $query");
        $result = $this->_database->query($query, ['epoch' => 's']);
        $count =  $result->getPoints();

        if ( array_key_exists(0, $count) ) {
            return $count[0]['count'] * count($nodes) * count($metrics);
        } else {
            return 0;
        }
    }
}
