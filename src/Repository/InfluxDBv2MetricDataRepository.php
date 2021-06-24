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
use InfluxDB2;

class InfluxDBv2MetricDataRepository implements MetricDataRepository
{
    private $_timing;
    private $_client;
    private $_queryAPI;
    #private $_logger;

    public function __construct(
        #LoggerInterface $logger
    )
    {
        $this->_timer = new Stopwatch();
        #$this->_logger = $logger;
        $influxdbURL = getenv('INFLUXDB_URL');
        $influxdbToken = getenv('INFLUXDB_TOKEN');
        #$this->_logger->info("Scheme: $influxdbURL");
        $this->_client  = new InfluxDB2\Client([
            "url" => "http://cc-influxdb:8086",
            "token" => $influxdbToken,
            "bucket" => "ClusterCockpit/data",
            "org" => "ClusterCockpit",
            "precision" => InfluxDB2\Model\WritePrecision::S,
            "debug" => true
        ]);

        $this->_queryApi = $this->_client->createQueryApi();
    }

    public function hasProfile($job, $metric)
    {
        $nodes = $job->getNodeArray();

        if ( count($nodes) < 1 ){
            $job->hasProfile = false;
            return false;
        }

        $startTime = date("Y-m-d\TH:i:s\Z",$job->startTime);
        $stopTime = date("Y-m-d\TH:i:s\Z",$job->startTime + $job->duration);

        $query = "from(bucket:\"ClusterCockpit/data\")
            |> range(start: {$startTime}, stop: {$stopTime})
            |> filter(fn: (r) =>
                          r._measurement == \"{$metric['measurement']}\" and
                          r._field == \"{$metric['name']}\" and
                          r.host == \"{$nodes[0]}\")#
            |> count()";

        $result = $this->_queryApi->query($query);

        $points = $result[0]->records[0]->values['_value'];

        #$resultJson = json_encode($result);
        #$resultType = gettype($result);
        #$pointsJson = json_encode($points);
        #$pointsType = gettype($points);

        #$this->_logger->info(">>>> QUERY: $query");
        #$this->_logger->info(">>>> RESULTJSON: $resultJson");
        #$this->_logger->info(">>>> RESULTTYPE: $resultType");
        #$this->_logger->info(">>>> POINTSJSON: $pointsJson");
        #$this->_logger->info(">>>> POINTSTYPE: $pointsType");

        #Original: if ( count($points) == 0 || $points[0]['count'] < 4 )
        if ( $points == 0 || $points < 4 ){
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
        $startTime = date("Y-m-d\TH:i:s\Z",$job->startTime);
        $stopTime = date("Y-m-d\TH:i:s\Z",$job->startTime + $job->duration);

        foreach ( $metrics as $metric ) {
            $name = $metric['name'];

            $query = "from(bucket:\"ClusterCockpit\")
                |> range(start: {$startTime}, stop: {$stopTime})
                |> filter(fn: (r) =>
                r._measurement == \"{$metric['measurement']}\" and
                r._field == \"{$metric['name']}\" and
                r.host == \"{$nodes[0]}\"
                |> count()";

            $result = $this->_queryApi->query($query);



            $query = "SELECT
                MEAN($name)  AS {$name}_avg
                ,MIN($name)  AS {$name}_min
                ,MAX($name)  AS {$name}_max
                FROM {$metric['measurement']}
                WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
                AND host =~ /$nodes/ GROUP BY host";

            $this->_timer->start( 'InfluxDB');
            $result = $this->_queryApi->query($query);
            $queries[] = $result->getSeries();
            $this->_timer->stop( 'InfluxDB');

            $query = "SELECT
                MEAN($name)  AS {$name}_avg
                ,MIN($name)  AS {$name}_min
                ,MAX($name)  AS {$name}_max
                FROM {$metric['measurement']}
                WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
                AND host =~ /$nodes/";

	    /* $this->_logger->info("InfluxDB JobStat QUERY: $query"); */
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

/*         foreach ($nodeData as $node) { */
/*             $nodeStat[] = $node; */
/*         } */

        $stats['nodeStats'] = $nodeData;

        return $stats;
    }

    public function getMetricData($job, $metrics)
    {
        $nodes = $job->getNodes('|');
        $stopTime = $job->startTime + $job->duration;

        foreach ( $metrics as $metric ) {
            $query = "SELECT
                MEAN({$metric['name']})  AS {$metric['name']}
                FROM {$metric['measurement']}
                WHERE  time >= {$job->startTime}s AND time <= {$stopTime}s
                AND host =~ /$nodes/ GROUP BY time({$metric['sampletime']}s), host";

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
}
