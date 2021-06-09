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

namespace App\Service;

use App\Entity\Job;
use App\Service\ClusterConfiguration;
use App\Repository\InfluxDBMetricDataRepository;

class JobData
{
    private $_metricDataRepository;
    private $_clusterCfg;
    private $projectDir;

    public function __construct(
        InfluxDBMetricDataRepository $metricRepo,
        ClusterConfiguration $clusterCfg,
        $projectDir
    )
    {
        $this->_metricDataRepository = $metricRepo;
        $this->_clusterCfg = $clusterCfg;
        $this->_rootdir = "$projectDir/var/job-archive";
    }

    private function _getJobDataPath($jobId, $clusterId)
    {
        $jobId = intval(explode('.', $jobId)[0]);
        $lvl1 = intdiv($jobId, 1000);
        $lvl2 = $jobId % 1000;
        $path = sprintf('%s/%s/%d/%03d/data.json',
            $this->_rootdir, $clusterId, $lvl1, $lvl2);
        return $path;
    }

    public function hasData($job)
    {
        if ( $job->isRunning()) {
            $job->duration = time() - $job->startTime;
        }

        $job->hasProfile = file_exists(
            $this->_getJobDataPath($job->getJobId(),
            $job->getClusterId()));

        if (!$job->hasProfile){
            $this->_metricDataRepository->hasProfile($job,
            $this->_clusterCfg->getSingleMetric($job->getClusterId()));
        }

        return $job->hasProfile;
    }

    public function getData($job, $metrics)
    {
        if (! $this->hasData($job) ) {
            return false;
        }

        if ( $job->isRunning()) {
            $metricConfig = $this->_clusterCfg->getMetricConfiguration($job->getClusterId(), $metrics);
            $stats = $this->_metricDataRepository->getJobStats($job, $metricConfig);
            $data = $this->_metricDataRepository->getMetricData($job, $metricConfig);
            $res = [];

            foreach ( $metrics as $metricName => $metric) {

                $series = [];
                foreach ( $data[$metricName] as $nodeId => $nodedata) {
                    $series[] = [
                        'node_id' => $nodeId,
                        'statistics' => [
                            'avg' => $stats['nodeStats'][$nodeId][$metricName.'_avg'],
                            'min' => $stats['nodeStats'][$nodeId][$metricName.'_min'],
                            'max' => $stats['nodeStats'][$nodeId][$metricName.'_max']
                        ],
                        'data' => $data[$metricName][$nodeId]
                    ];
                }

                $res[] = [
                    'name' => $metricName,
                    'metric' => [
                        'unit' => $metric['unit'],
                        'scope' => $metric['scope'],
                        'timestep' => $metric['sampletime'],
                        'series' => $series
                    ]
                ];
            }
        } else {
            $path = $this->_getJobDataPath($job->getJobId(), $job->getClusterId());
            $data = @file_get_contents($path);

            $data = json_decode($data);
            $res = [];
            foreach ($data as $metricName => $metricData) {
                if ($metrics && !in_array($metricName, $metrics))
                    continue;

                $res[] = [
                    'name' => $metricName,
                    'metric' => $metricData
                ];
            }
        }
        return $res;
    }
}
