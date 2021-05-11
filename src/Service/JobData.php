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

use Psr\Log\LoggerInterface;
use App\Entity\Job;
use App\Repository\InfluxDBMetricDataRepository;
use Doctrine\ORM\EntityManagerInterface;

class JobData
{
    private $_em;
    private $_metricDataRepository;
    private $logger;
    private $projectDir;

    public function __construct(
        EntityManagerInterface $em,
        InfluxDBMetricDataRepository $metricRepo,
        LoggerInterface $logger,
        $projectDir
    )
    {
        $this->_em = $em;
        $this->_metricDataRepository = $metricRepo;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    private function _getJobDataPath($jobId, $clusterId)
    {
        $jobId = intval(explode('.', $jobId)[0]);
        $lvl1 = intdiv($jobId, 1000);
        $lvl2 = $jobId % 1000;
        $path = sprintf('%s/job-data/%s/%d/%03d/data.json',
            $this->projectDir, $clusterId, $lvl1, $lvl2);
        $this->logger->info("PATH $path");
        return $path;
    }

    public function hasData($job)
    {
        if ( $job->isRunning()) {
            $job->duration = time() - $job->startTime;
        }

        $job->hasProfile = file_exists( $this->_getJobDataPath($job->getJobId(), $job->getClusterId()));

        if (!$job->hasProfile){
            $this->_metricDataRepository->hasProfile($job);
        }

        return $job->hasProfile;
    }

    public function getData($job, $metrics)
    {
        if (! $this->hasData($job) ) {
            return false;
        }

        if ( $job->isRunning()) {
            $metricConfig = $this->_clusterConfig->getMetrics($metrics);
            $res = $this->_metricDataRepository->getMetricData( $job, $metricConfig);
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
