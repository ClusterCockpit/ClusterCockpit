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
    private $_logger;

    public function __construct(
        LoggerInterface $logger
    )
    {
        $this->_timer = new Stopwatch();
        $this->_logger = $logger;
        $influxdbURL = getenv('INFLUXDB_URL');
        $influxdbSsl = filter_var(getenv('INFLUXDB_SSL'), FILTER_VALIDATE_BOOLEAN); # make env string boolean
        $influxdbToken = getenv('INFLUXDB_TOKEN');
        $influxdbBucket = getenv('INFLUXDB_BUCKET');
        $influxdbOrg = getenv('INFLUXDB_ORG');
        $this->_logger->info("Scheme: $influxdbURL");
        $this->_client  = new InfluxDB2\Client([
            "url" => $influxdbURL,
            "token" => $influxdbToken,
            "bucket" => $influxdbBucket,
            "org" => $influxdbOrg,
            "verifySSL" => $influxdbSsl,
            "timeout" => 60,
            "precision" => InfluxDB2\Model\WritePrecision::S
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

        $influxdbBucket = getenv('INFLUXDB_BUCKET');
        $startTime = date("Y-m-d\TH:i:s\Z",$job->startTime);
        $stopTime = date("Y-m-d\TH:i:s\Z",$job->startTime + $job->duration);

        $query = "from(bucket:\"{$influxdbBucket}\")
            |> range(start: {$startTime}, stop: {$stopTime})
            |> filter(fn: (r) =>
            r._measurement == \"{$metric['measurement']}\" and
            r._field == \"{$metric['name']}\" and
            r.host == \"{$nodes[0]}\")
            |> count()";

        $result = $this->_queryApi->query($query);

        if (!isset($result[0])) {
            $job->hasProfile = false;
            return false;
        }

        $points = $result[0]->records[0]->values['_value'];

        #$resultJson = json_encode($result);
        #$this->_logger->info(">>>> RESULTJSON: $resultJson");

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

        $influxdbBucket = getenv('INFLUXDB_BUCKET');
        $startTime = date("Y-m-d\TH:i:s\Z",$job->startTime);
        $stopTime = date("Y-m-d\TH:i:s\Z",$job->startTime + $job->duration);

        foreach ( $metrics as $metric ) {

            $name = $metric['name'];

            $query = "data = from(bucket:\"{$influxdbBucket}\")
                |> range(start: {$startTime}, stop: {$stopTime})
                |> filter(fn: (r) =>
                r._measurement == \"{$metric['measurement']}\" and
                r._field == \"{$name}\" and
                r.host =~ /{$nodes}/
                )

            {$name}_avg = data |> mean(column: \"_value\") |> set(key: \"_field\", value: \"{$name}_avg\")
            {$name}_min = data |> min(column:  \"_value\") |> set(key: \"_field\", value: \"{$name}_min\")
            {$name}_max = data |> max(column:  \"_value\") |> set(key: \"_field\", value: \"{$name}_max\")

            union(tables: [{$name}_avg, {$name}_min, {$name}_max])
            |> pivot(rowKey:[\"host\"], columnKey: [\"_field\"], valueColumn: \"_value\")
            |> group()";

            $this->_timer->start( 'InfluxDBv2');
            $result = $this->_queryApi->query($query);
            $records = $result[0]->records;
            $recordsCount = count($records);
            $this->_timer->stop( 'InfluxDBv2');

            #$resultJson  = json_encode($result);
            #$this->_logger->info(">>>> RESULTJSON:  $resultJson");

            foreach ( $records as $index => $record ){
                // Collect stats from nodes in arrays //
                $jobStats['avg']["{$name}_avg"][] =  $record->values["{$name}_avg"];
                $jobStats['min']["{$name}_min"][] =  $record->values["{$name}_min"];
                $jobStats['max']["{$name}_max"][] =  $record->values["{$name}_max"];
                // Collect stats per node //
                $nodeId = $record->values["host"];
                $stats['nodeStats'][$nodeId]['nodeId'] = $nodeId;
                $stats['nodeStats'][$nodeId]["{$name}_avg"] = round($record->values["{$name}_avg"], 2);
                $stats['nodeStats'][$nodeId]["{$name}_min"] = round($record->values["{$name}_min"], 2);
                $stats['nodeStats'][$nodeId]["{$name}_max"] = round($record->values["{$name}_max"], 2);
            }
        }

        // Find JobStats from Node-Value-Arrays //
        foreach ($jobStats as $type => $stat) {
            switch ($type) {
            case 'avg':
                foreach ($stat as $name => $values) {
                    $stats[$name] = round((array_sum($values) / $recordsCount), 2);
                }
                break;
            case 'min':
                foreach ($stat as $name => $values) {
                    $stats[$name] = round(min($values), 2);
                }
                break;
            case 'max':
                foreach ($stat as $name => $values) {
                    $stats[$name] = round(max($values), 2);
                }
                break;
            }
        }

        #$statsJson = json_encode($stats);
        #$this->_logger->info(">>>> STATSJSON:  $statsJson");

        return $stats;
    }

    public function getMetricData($job, $metrics)
    {
        set_time_limit(60); // Long Load Buffer for method directly instead of global php setting
        $nodes = $job->getNodes('|');
        $measurement = $metrics[array_key_first($metrics)]['measurement'];

        $influxdbBucket = getenv('INFLUXDB_BUCKET');
        $startTime = date("Y-m-d\TH:i:s\Z",$job->startTime);
        $stopTime  = date("Y-m-d\TH:i:s\Z",$job->startTime + $job->duration);

        $query = "from(bucket:\"{$influxdbBucket}\")
            |> range(start: {$startTime}, stop: {$stopTime})
            |> filter(fn: (r) =>
            r._measurement == \"{$measurement}\" and
            r.host =~ /{$nodes}/
            )
            |> truncateTimeColumn(unit: 1m)
";

        #$this->_logger->info(">>>> QUERY:  $query");

        $this->_timer->start( 'InfluxDBv2');
        $result = $this->_queryApi->query($query);
        $this->_timer->stop( 'InfluxDBv2');

        $data = array();

        foreach ( $result as $table ){
            $tableRecords = $table->records;
            $nodeId = $tableRecords[0]->values["host"];
            $metric = $tableRecords[0]->values["_field"];

            foreach ( $tableRecords as $record) {
                $data[$metric][$nodeId][] = $record->values["_value"];
            }
        }

        #$dataJson  = json_encode($data);
        #$this->_logger->info(">>>> DATAJSON:  $dataJson");

        #$this->_logger->info(">>>> COMPLETED METRICS");

        return $data;
    }
}
