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
use Psr\Log\LoggerInterface;

use App\Service\JobData;
use App\Service\JobArchive;
use App\Service\ClusterConfiguration;
use App\Repository\MetricDataRepository;
use App\Entity\Job;

class JobStats
{
    const CACHE_EXPIRES_AFTER = 60 * 60; // 1h

    private $_jobData;
    private $_jobArchive;
    private $_metricDataRepository;
    private $_logger;
    private $_clusterCfg;
    private $_cache;

    public function __construct(
        JobData $jobData,
        JobArchive $jobArchive,
        MetricDataRepository $metricRepo,
        ClusterConfiguration $clusterCfg,
        LoggerInterface $logger,
        CacheInterface $cache
    )
    {
        $this->_jobData = $jobData;
        $this->_jobArchive = $jobArchive;
        $this->_metricDataRepository = $metricRepo;
        $this->_clusterCfg = $clusterCfg;
        $this->_logger = $logger;
        $this->_cache = $cache;
    }

    private function getCacheKey($name, $filter)
    {
        return $name."_".md5(serialize($filter));
    }

    public function getFootprints($jobs, $filter, $metrics)
    {
        $key = $this->getCacheKey("analysisview-averages", [$filter, $metrics]);
        return $this->_cache->get($key, function (ItemInterface $item) use ($jobs, $metrics) {
            $item->expiresAfter(self::CACHE_EXPIRES_AFTER);
            return $this->_fetchFootprints($jobs, $metrics);
        });
    }

    private function _fetchFootprints($jobs, $metrics)
    {
        $res = [];
        foreach ($metrics as $idx => $metric)
            $res[$idx] = [ 'name' => $metric, 'footprints' => [] ];

        foreach ($jobs as $job) {
            if ($this->_jobArchive->isArchived($job) || $this->_jobArchive->isLegacyArchived($job)) {
                $stats = $this->_jobArchive->getMeta($job)['statistics'];
                foreach ($metrics as $idx => $metric) {
                    if (isset($stats[$metric]))
                        $res[$idx]['footprints'][] = $stats[$metric]['avg'];
                    else
                        $res[$idx]['footprints'][] = null;
                }
                continue;
            }

            $metricConfig = $this->_clusterCfg->getMetricConfiguration($job->getClusterId(), $metrics);
            $stats = $this->_metricDataRepository->getJobStats($job, $metricConfig);
            foreach ($metrics as $idx => $metric) {
                if (isset($stats[$metric.'_avg']))
                    $res[$idx]['footprints'][] = $stats[$metric.'_avg'];
                else
                    $res[$idx]['footprints'][] = null;
            }
        }

        return $res;
    }

    public function rooflineHeatmap($jobs, $filter, $rows, $cols, $minX, $minY, $maxX, $maxY)
    {
        $key = $this->getCacheKey("analysisview-roofline", [$filter, $rows, $cols, $minX, $minY, $maxX, $maxY]);
        return $this->_cache->get($key, function (ItemInterface $item) use ($jobs, $rows, $cols, $minX, $minY, $maxX, $maxY) {
            $item->expiresAfter(self::CACHE_EXPIRES_AFTER);
            return $this->_calcRooflineHeatmap($jobs, $rows, $cols, $minX, $minY, $maxX, $maxY);
        });
    }

    private function _calcRooflineHeatmap($jobs, $rows, $cols, $minX, $minY, $maxX, $maxY)
    {
        $tiles = [];
        for ($i = 0; $i < $rows; $i++) {
            $tiles[$i] = [];
            for ($j = 0; $j < $cols; $j++) {
                $tiles[$i][$j] = 0;
            }
        }

        // All jobs should be from the same cluster!
        $minX = log10($minX);
        $minY = log10($minY);
        $maxX = log10($maxX);
        $maxY = log10($maxY);

        foreach ($jobs as $job) {
            $data = $this->_jobData->getData($job, ['flops_any', 'mem_bw']);
            if ($data === false)
                continue;

            $flopsAny = null;
            $memBw = null;
            foreach ($data as $entry) {
                if ($entry['name'] == 'flops_any')
                    $flopsAny = $entry['metric'];
                if ($entry['name'] == 'mem_bw')
                    $memBw = $entry['metric'];
            }

            for ($n = 0; $n < $job->getNumNodes(); $n++) {
                $flopsAnyData = $flopsAny['series'][$n]['data'];
                $memBwData = $memBw['series'][$n]['data'];
                $count = count($flopsAnyData);
                for ($i = 0; $i < $count; $i++) {
                    $f = $flopsAnyData[$i];
                    $m = $memBwData[$i];
                    if ($m <= 0 || $f == null || $m == null)
                        continue;

                    $x = log10($f / $m);
                    $y = log10($f);
                    if ($x < $minX || $x > $maxX  || $y < $minY || $y > $maxY)
                        continue;

                    $x = floor((($x - $minX) / ($maxX - $minX)) * $cols);
                    $y = floor((($y - $minY) / ($maxY - $minY)) * $rows);
                    if ($y >= $rows || $x >= $cols)
                        throw new Exception("Error Processing Request");

                    $tiles[$y][$x] += 1;
                }
            }
        }

        return $tiles;
    }
}
