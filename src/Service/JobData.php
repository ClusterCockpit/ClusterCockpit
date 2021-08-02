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
use App\Service\JobArchive;
use App\Service\ClusterConfiguration;
use App\Repository\InfluxDBMetricDataRepository;
#use App\Repository\InfluxDBv2MetricDataRepository;
#use Psr\Log\LoggerInterface;

class JobData
{
    private $_metricDataRepository;
    #private $_metricDataRepositoryV2;
    private $_clusterCfg;
    private $_jobArchive;
    #private $_logger;

    public function __construct(
        InfluxDBMetricDataRepository $metricRepo,
        #InfluxDBv2MetricDataRepository $metricRepoV2,
        ClusterConfiguration $clusterCfg,
        JobArchive $jobArchive
        #LoggerInterface $logger,
    )
    {
        $this->_metricDataRepository = $metricRepo;
        #$this->_metricDataRepositoryV2 = $metricRepoV2;
        $this->_clusterCfg = $clusterCfg;
        $this->_jobArchive = $jobArchive;
        #$this->_logger = $logger;
    }



    public function hasData($job)
    {
        // For actually running jobs, resetting the duration here is fine.
        // For jobs from the development testing data where 'duration' and 'isRunning'
        // can both be > 0, this messes things up.
        if ($job->isRunning && ($job->duration == null || $job->duration == 0)) {
            $job->duration = time() - $job->startTime;
        }

        $job->hasProfile = $this->_jobArchive->isArchived($job);

        # V1 Repository-Code for InfluxDB 1.*
        if (!$job->hasProfile){
            $this->_metricDataRepository->hasProfile($job,
            $this->_clusterCfg->getSingleMetric($job->getClusterId()));
        }

        # V2 Repository-Code for InfluxDB 2.*
        #if (!$job->hasProfile){
        #    $this->_metricDataRepositoryV2->hasProfile($job,
        #    $this->_clusterCfg->getSingleMetric($job->getClusterId()));
        #}

        return $job->hasProfile;
    }

    public function getData($job, $metrics)
    {
        if (! $this->hasData($job) ) {
            return false;
        }

        if ($metrics == null) {
            $cluster = $this->_clusterCfg->getClusterConfiguration($job->getClusterId());
            $metrics = array_keys($cluster['metricConfig']);
        }

        if ($job->isRunning()) {
            $metricConfig = $this->_clusterCfg->getMetricConfiguration($job->getClusterId(), $metrics);

            # V1 Repository-Code for InfluxDB 1.*
            $stats = $this->_metricDataRepository->getJobStats($job, $metricConfig);
            $data = $this->_metricDataRepository->getMetricData($job, $metricConfig);

            # V2 Repository-Code for InfluxDB 2.*
            #$stats = $this->_metricDataRepositoryV2->getJobStats($job, $metricConfig);
            #$data = $this->_metricDataRepositoryV2->getMetricData($job, $metricConfig);

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
            $data = $this->_jobArchive->getData($job);
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
