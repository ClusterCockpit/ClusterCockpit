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

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;

use App\Entity\Job;
use App\Service\JobArchive;
use App\Service\ClusterConfiguration;
use App\Repository\MetricDataRepository;
use App\Repository\InfluxDBMetricDataRepository;
use App\Repository\InfluxDBv2MetricDataRepository;
use Psr\Log\LoggerInterface;

class JobData
{
    const CACHE_EXPIRES_AFTER_RUNNING = 60; // 1min
    const CACHE_EXPIRES_AFTER_ARCHIVED = 60 * 60; // 1h

    private $_metricDataRepository;
    private $_metricDataRepositoryV2;
    private $_clusterCfg;
    private $_jobArchive;
    private $_logger;

    public function __construct(
        InfluxDBMetricDataRepository $metricRepo,
        InfluxDBv2MetricDataRepository $metricRepoV2,
        ClusterConfiguration $clusterCfg,
        JobArchive $jobArchive,
        LoggerInterface $logger,
        CacheInterface $cache
    )
    {
        $this->_metricDataRepository = $metricRepo;
        $this->_metricDataRepositoryV2 = $metricRepoV2;
        $this->_clusterCfg = $clusterCfg;
        $this->_jobArchive = $jobArchive;
        $this->_logger = $logger;
        $this->_cache = $cache;
    }

    /*
     * This function is used by the JobStats Service to access a MetricDataRepository.
     * Having this function here makes it simpler to switch repos.
     *
     * For the future, I would like to suggest using the MetricDataRepository interface together with
     * symfony's autowiring to automatically inject one or the other reporsitory depending on a setting in config/.
     */
    public function getMetricRepo(): MetricDataRepository
    {
        // return $this->_metricDataRepository;
        return $this->_metricDataRepositoryV2;
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

        if (!$job->hasProfile){
            $this->getMetricRepo()->hasProfile($job,
                $this->_clusterCfg->getSingleMetric($job->getClusterId()));
        }

        return $job->hasProfile;
    }

    public function getData($job, $metrics)
    {
        $key = $job->getClusterId()."-".$job->getJobId()."-".$job->getStartTime()."-".md5(serialize($metrics));
        return $this->_cache->get($key, function (ItemInterface $item) use ($job, $metrics) {
            if ($job->isRunning)
                $item->expiresAfter(self::CACHE_EXPIRES_AFTER_RUNNING);
            else
                $item->expiresAfter(self::CACHE_EXPIRES_AFTER_ARCHIVED);

            return $this->_getData($job, $metrics);
        });
    }

    private function _getData($job, $metrics)
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

            $stats = $this->getMetricRepo()->getJobStats($job, $metricConfig);
            $data = $this->getMetricRepo()->getMetricData($job, $metricConfig);

            $res = [];

            foreach ( $metricConfig as $metricName => $metric) {
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
                        'scope' => 'node', // TODO: Add scope to cluster.json/metricConfig? // $metric['scope'],
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
