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

    private function _initJob($job)
    {
        if ( $job->isRunning()) {
            $job->duration = time() - $job->startTime;
        }
        if (!$job->hasProfile){
            $this->_metricDataRepository->hasProfile($job);
        }
    }

    private function _computeAverages($job, $sortMetrics)
    {
        $stats = $this->_metricDataRepository->getJobStats(
            $job, $sortMetrics['metrics']);

        foreach ( $sortMetrics['info'] as $sortInfo ){
            $name = $sortInfo->getAccessKey();
            $slot = 'slot_'.$sortInfo->getSlot();
            $job->{$slot} = $stats[$name.'_avg'];
        }
    }

    private function _generatePlots(
        $job,
        $mode,
        $options,
        $live = false
    )
    {
        $job->jobCache = new \App\Entity\JobCache();

        if ( $mode === 'view' or $live == false ){
            if ( $options['plot_view_showPolarplot'] == 'true' or
                $options['plot_view_showStatTable'] == 'true' or
                $options['plot_general_colorBackground'] == 'true'
            ) {
                /* collect all metrics required for node table and job table sorting */
                $metrics = $job->getCluster()->getMetricList('stat');
                $stats = $this->_metricDataRepository->getJobStats($job, $metrics);
            }
        }

        if ( $mode === 'view' ) { /* Single Job View */
            $options['mode'] = 'view';
            $options['autotick'] = true;
            $options['sample'] = 0;
            $options['legend'] = false;

            if ( $options['plot_view_showRoofline'] == 'true' ) {
                $this->_plotGenerator->generateJobRoofline(
                    $job, $this->_metricDataRepository->getJobRoofline($job, $metrics)
                );
            }

            if ( $options['plot_view_showPolarplot'] == 'true' ) {
                $this->_plotGenerator->generateJobPolarPlot(
                    $job, $metrics, $stats
                );
            }

            if ( $options['plot_view_showStatTable'] == 'true' ) {
                $job->jobCache->nodeStat = $stats['nodeStats'];
            } else {
                $job->jobCache->nodeStat = false;
            }
        } else if ( $mode === 'list' ) { /* Job list  */
            $options['mode'] = 'list';
            $options['sample'] = $options['plot_list_samples'];
            $options['legend'] = false;
        }

        $options['lineWidth'] =  $options['plot_general_lineWidth'];

        $metrics = $job->getCluster()->getMetricList($mode);
        $data = $this->_metricDataRepository->getMetricData( $job, $metrics, $options);

        if ( $data == false ) {
            $job->hasProfile = false;
            return;
        }

        foreach ($metrics as $metric){

            if ( $mode === 'view' or $live == false ) {
                if ( $options['plot_general_colorBackground'] === 'true' ) {
                    $this->_colorBackground($options, $metric, $stats);
                }
            }

            $this->_plotGenerator->generateMetricPlot(
                $job,
                $metric,
                $options,
                $data
            );
        }
    }

    public function getArchive($job)
    {
        if ( ! $job->isRunning()) {
            if ( $this->_metricDataRepository->hasProfile($job) ) {
                $job->jobCache = new \App\Entity\JobCache();
                $metrics = $job->getCluster()->getMetricList('view');
                $data = $this->_metricDataRepository->getMetricData(
                    $job, $metrics, array('sample' => 0));
                $nodes = $job->getNodes();

                foreach ($metrics as $metric) {
                    $metricName = $metric->getName();
                    $plot = new  Plot();
                    $plot->name = $metricName;
                    $lineData  = array();

                    foreach ($nodes as $node) {
                        $nodeId = $node->getNodeId();
                        $lineData[] = array(
                            'x'     => $data[$metricName][$nodeId]['x'],
                            'y'     => $data[$metricName][$nodeId]['y'],
                            'name'  => $node->getNodeId()
                        );
                    }

                    $plot->setOptions(array(
                        'unit' => $metric->getUnit(),
                        'timestep' => $metric->sampletime
                    ));
                    $plot->setData($lineData);
                    $job->jobCache->addPlot($plot);
                }

            } else {
                return false;
            }
        } else {
            return false;
        }
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
        /* Get MetricData from Database */
                     /* $data = $this->_metricDataRepository->getMetricData( */
                    /* $job, $metrics, array('sample' => 0)); */
            /* if ( $this->_metricDataRepository->hasProfile($job)){ */
            /*     $this->_generatePlots($job, $mode, $options, true); */
            /* } */
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
