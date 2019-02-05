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


class InfluxDBMetricDataRepository implements MetricDataRepository
{
    private $_database;

    public function __construct()
    {
        $client = new \InfluxDB\Client('localhost', '8086');
        $this->_database = $client->selectDB('ClusterCockpit');
    }

    public function getJobRoofline($job, $metrics)
    {
        $nodes = $job->getNodeNameArray();

        $flopsAny = $metrics['flops_any'];
        $memBw = $metrics['mem_bw'];
        $nodes = implode('|', $nodes);

        $query = "SELECT {$flopsAny->name}*{$flopsAny->scale} as x,
           {$flopsAny->name}/{$memBw->name} as y FROM data
            WHERE  time >= {$job->startTime}s AND time <= {$job->stopTime}s
            AND host =~ /$nodes/";

        $result = $this->_database->query($query);
        $points = $result->getPoints();

        return $points;
    }

    public function hasProfile($job)
    {
        $nodes = $job->getNodes();

        if ( count($nodes) < 1 ){
            return false;
        }

        $query = "SELECT COUNT(flops_any) FROM data
            WHERE  time >= {$job->startTime}s AND time <= {$job->stopTime}s
            AND host = '{$nodes->first()->getNodeId()}'";

        $result = $this->_database->query($query);
        $points = $result->getPoints();
        $count = $points[0]['count'];

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

        foreach ( $metrics as $metric ){
            $name = $metric->name;
            $scale = sprintf("%f",$metric->scale);

            $metricString .= ",MEAN($name) * $scale AS {$name}_avg";
            $metricString .= ",MIN($name)  * $scale AS {$name}_min";
            $metricString .= ",MAX($name)  * $scale AS {$name}_max";
        }
        $metricString = substr($metricString,1);
        $nodes = $job->getNodeNameArray();
        $nodes = implode('|', $nodes);

        $query = "SELECT
            $metricString
            FROM data
            WHERE  time >= {$job->startTime}s AND time <= {$job->stopTime}s
            AND host =~ /$nodes/ GROUP BY host";

        $result = $this->_database->query($query);
        $series = $result->getSeries();

        foreach ($series as $data) {
            $nodeData['nodeId'] = $data['tags']['host'];

            foreach ( $data['columns'] as $index => $metric ){
                if ($metric != 'time'){
                    $nodeData[$metric] = $data['values'][0][$index];
                }
            }

            $nodeStat[] = $nodeData;
        }

        $query = "SELECT
            $metricString
            FROM data
            WHERE  time >= {$job->startTime}s AND time <= {$job->stopTime}s
            AND host =~ /$nodes/";

        $result = $this->_database->query($query);
        $points = $result->getPoints();
        $stats = $points[0];
        $stats['nodeStats'] = $nodeStat;

        return $stats;
    }

    public function getMetricData($job, $metrics)
    {
        $metricString = '';

        foreach ( $metrics as $metric ){
            $name = $metric->name;
            $scale = sprintf("%f",$metric->scale);
            $metricString .= ",MEAN($name) * $scale AS {$name}";
        }
        $metricString = substr($metricString,1);
        $nodes = $job->getNodeNameArray();
        $nodes = implode('|', $nodes);

        $query = "SELECT $metricString FROM data
            WHERE  time >= {$job->startTime}s AND time <= {$job->stopTime}s
            AND host =~ /$nodes/ GROUP BY time(1m), host FILL(linear)";

        $result = $this->_database->query($query);
        $series = $result->getSeries();

        foreach ($series as $seriesdata) {
            $nodeId = $seriesdata['tags']['host'];

            foreach ( $data['columns'] as $index => $metric ){
                if ($metric != 'time'){
                    $data[$metric][$nodeId]['y'][] = floatval($row[$metricName]);
                    $nodeData[$metric] = $data['values'][0][$index];
                }
            }

            $nodeStat[] = $nodeData;
        }

        return $points;
    }
}
