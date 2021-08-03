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

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

use App\Service\JobData;
use App\Service\JobArchive;
use App\Service\ClusterConfiguration;
use App\Entity\Job;

class JobStats
{
    private $_jobData;
    private $_jobArchive;
    private $_cacheDir;
    private $_logger;
    private $_clusterCfg;

    public function __construct(
        JobData $jobData,
        JobArchive $jobArchive,
        ParameterBagInterface $params,
        ClusterConfiguration $clusterCfg,
        LoggerInterface $logger
    )
    {
        $this->_jobData = $jobData;
        $this->_jobArchive = $jobArchive;
        $this->_cacheDir = $params->get('kernel.cache_dir').'/job-statistics';
        $this->_clusterCfg = $clusterCfg;
        $this->_logger = $logger;
    }

    /*
     * TODO: Implement something that creates the files asked for here...
     */
    private function getCachePath($name, $filter)
    {
        return $this->_cacheDir."/".$name."-".md5(serialize($filter)).".json";
    }

    public function getAverages($jobs, $filter, $metrics)
    {
        $filename = $this->getCachePath("averages", [$filter, $metrics]);
        if (file_exists($filename))
            return json_decode(file_get_contents($filename), true);

        $res = [];
        foreach ($metrics as $idx => $metric) {
            $res[$idx] = [];
        }

        foreach ($jobs as $job) {
            if ($this->_jobArchive->isArchived($job)) {
                $stats = $this->_jobArchive->getMeta($job)['statistics'];
                foreach ($metrics as $idx => $metric) {
                    if (isset($stats[$metric]))
                        $res[$idx][] = $stats[$metric]['avg'];
                    else
                        $res[$idx][] = null;
                }
                continue;
            }

            $metricConfig = $this->_clusterCfg->getMetricConfiguration($job->getClusterId(), $metrics);
            $stats = $this->_jobData->getMetricRepo()->getJobStats($job, $metricConfig);
            foreach ($metrics as $idx => $metric) {
                if (isset($stats[$metric.'_avg']))
                    $res[$idx][] = $stats[$metric.'_avg'];
                else
                    $res[$idx][] = null;
            }
        }

        return $res;
    }

    public function rooflineHeatmap($jobs, $filter, $rows, $cols, $minX, $minY, $maxX, $maxY)
    {
        $filename = $this->getCachePath("roofline", [$filter, $rows, $cols, $minX, $minY, $maxX, $maxY]);
        if (file_exists($filename))
            return json_decode(file_get_contents($filename), true);

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
