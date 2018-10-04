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

namespace App\Service;

use App\Entity\Job;
use App\Entity\RunningJob;
use App\Entity\Plot;
use App\Entity\Data;
use App\Entity\NodeStat;
use App\Entity\StatisticCache;
use App\Repository\DoctrineMetricDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Class: JobCache
 *
 * @author Jan Eitzinger
 * @version 0.1
 */
class JobCache
{
    private $_logger;
    private $_em;
    private $_cache;
    private $_jobRepository;
    private $_plotGenerator;
    private $_tsHelper;
    private $_metricDataRepository;

    public function __construct(
        LoggerInterface $logger,
        TimeseriesHelper $tsHelper,
        EntityManagerInterface $em,
        PlotGenerator $plotGenerator,
        DoctrineMetricDataRepository $metricRepo,
        AdapterInterface $cache,
        Configuration $configuration
    )
    {
        $this->_logger = $logger;
        $this->_tsHelper = $tsHelper;
        $this->_em = $em;
        $this->_plotGenerator = $plotGenerator;
        $this->_jobRepository = $em->getRepository(\App\Entity\Job::class);
        $this->_metricDataRepository = $metricRepo;
        $this->_cache = $cache;
    }

    public function getBackend()
    {
        return $this->_plotGenerator->getBackend();
    }

    public function checkStatisticCache(
        $userId,
        $control,
        $cluster,
        &$status
    )
    {
        $stat = new StatisticCache();

        $tmp = $this->_jobRepository->findStatByUser($userId, $control);
        $stat->setYear($control->getYear());
        $stat->setMonth($control->getMonth());
        $stat->setClusterId($control->getCluster());
        $stat->setUserId($userId);

        $stat->jobCount = $tmp['stat']['jobCount'] ;
        $stat->totalWalltime = $tmp['stat']['totalWalltime'];
        $stat->totalCoreHours = $tmp['stat']['totalCoreHours'];

        $this->_plotGenerator->generateJobHistograms($stat, $tmp);
        /* $data = $repository->fetchJobCloudData($userId, $control); */
        /* $this->_plotGenerator->createJobCloud($stat,$cluster, $data, $status); */
        return $stat;
    }

    private function _initJob($job)
    {
        if ( $job->isRunning()) {
            /* $job->stopTime = time(); */
            $job->stopTime = 1521057932;
            $job->duration = $job->stopTime - $job->startTime;
        }
    }

    private function _computeSeverity($job, $sortMetrics)
    {
        $severity = 0;

        $metrics = $sortMetrics['metrics'];
        $info = $sortMetrics['info'];
        $memBwMetric = $metrics['mem_bw'];
        $memBwSlot = 'slot_'.$info['mem_bw']->getSlot();
        $flopsAnyMetric = $metrics['flops_any'];
        $flopsAnySlot = 'slot_'.$info['flops_any']->getSlot();

        if ( $job->{$memBwSlot} < $memBwMetric->alert ){
            $severity += 20;
        } else if ( $job->{$memBwSlot} < $memBwMetric->caution ){
            $severity += 10;
        } else if ( $job->{$memBwSlot} > $memBwMetric->normal ){
            $severity -= 15;
        }

        if ( $job->{$flopsAnySlot} < $flopsAnyMetric->alert ){
            $severity += 20;
        } else if ( $job->{$flopsAnySlot} < $flopsAnyMetric->caution ){
            $severity += 10;
        } else if ( $job->{$flopsAnySlot} > $flopsAnyMetric->normal ){
            $severity -= 15;
        }

        $job->severity = $severity * $job->getNumNodes();
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
        $config,
        $metrics,
        $stats
    )
    {
        $job->jobCache = new \App\Entity\JobCache();

        if ( $mode === 'view' ) { /* Single Job View */
            $options['mode'] = 'view';
            $options['autotick'] = true;
            $options['sample'] = 0;
            $options['legend'] = false;

            if ( $config['plot_view_showRoofline']->value == 'true' ) {
                $this->_plotGenerator->generateJobRoofline(
                    $job, $this->_metricDataRepository->getJobRoofline($job, $metrics)
                );
            }

            if ( $config['plot_view_showPolarplot']->value == 'true' ) {
                $this->_plotGenerator->generateJobPolarPlot(
                    $job, $metrics, $stats
                );
            }

            if ( $config['plot_view_showStatTable']->value == 'true' ) {
                $job->jobCache->nodeStat = $stats['nodeStats'];
            } else {
                $job->jobCache->nodeStat = false;
            }
        } else if ( $mode === 'list' ) { /* Job list  */
            $options['mode'] = 'list';
            $options['sample'] = $config['plot_list_samples']->value;
            $options['legend'] = false;
        }

        $metrics = $job->getCluster()->getMetricList($mode)->getMetrics();
        $data = $this->_metricDataRepository->getMetricData( $job, $metrics);

        if ( $data == false ) {
            $job->hasProfile = false;
            return;
        }

        foreach ($metrics as $metric){

            $metricName = $metric->name;

            if ( $config['plot_general_colorBackground']->value === 'true' ) {
                if ( isset($stats["{$metricName}_avg"]) ) {
                    if ( $metricName === 'mem_used' ){
                        if ( $stats["{$metricName}_avg"] > $metric->alert ){
                            $options['bgColor'] = 'rgb(255,238,230)';
                        } else if ( $stats["{$metricName}_avg"] > $metric->caution ){
                            $options['bgColor'] = 'rgb(255,255,230)';
                        } else {
                            unset($options['bgColor']);
                        }

                    } else {
                        if ( $stats["{$metricName}_avg"] < $metric->alert ){
                            $options['bgColor'] = 'rgb(255,238,230)';
                        } else if ( $stats["{$metricName}_avg"] < $metric->caution ){
                            $options['bgColor'] = 'rgb(255,255,230)';
                        } else {
                            unset($options['bgColor']);
                        }
                    }
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

    public function warmupCache(
        $job,
        $config
    )
    {
        $this->_initJob($job);

        /* collect all metrics required for node table and job table sorting */
        $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();  /* stat table */
        $stats = $this->_metricDataRepository->getJobStats($job, $metrics);

        $this->_generatePlots($job, 'view', $config, $metrics, $stats);
        $item = $this->_cache->getItem($job->getJobId().'view');
        $this->_cache->save($item->set($job->jobCache));

        $job->jobCache = NULL;
        $this->_generatePlots($job, 'list', $config, $metrics, $stats);
        $item = $this->_cache->getItem($job->getJobId().'list');
        $this->_cache->save($item->set($job->jobCache));

        $tableSortRepository = $this->_em->getRepository(\App\Entity\TableSortConfig::class);
        $sortMetrics = $tableSortRepository->findDataMetrics($job);
        $this->_computeAverages($job, $sortMetrics);
        $this->_computeSeverity($job, $sortMetrics);
    }

    public function checkCache(
        $job,
        $mode,
        $config
    )
    {
        $this->_initJob($job);
        $item = $this->_cache->getItem($job->getJobId().$mode);

        /* collect all metrics required for node table and job table sorting */
        $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();  /* stat table */
        $stats = $this->_metricDataRepository->getJobStats($job, $metrics);

        if ($item->isHit()) {
            $job->jobCache = $item->get();
            $job->hasProfile = true;
            return;
        }

        if (! $this->_metricDataRepository->hasProfile($job)){
            $job->hasProfile = false;
            return;
        }

        $job->hasProfile = true;
        $this->_generatePlots($job, $mode, $config, $metrics, $stats);
    }
}
