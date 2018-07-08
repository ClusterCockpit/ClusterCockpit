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
use App\Entity\StatisticControl;
use App\Entity\StatisticCache;
use App\Entity\NodeStat;
use App\Entity\MetricStat;
use App\Repository\DoctrineMetricDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

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
    private $_jobRepository;
    private $_plotGenerator;
    private $_configuration;
    private $_traceRepository;
    private $_metricDataRepository;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $em,
        PlotGenerator $plotGenerator,
        DoctrineMetricDataRepository $metricRepo,
        Configuration $configuration
    )
    {
        $this->_logger = $logger;
        $this->_em = $em;
        $this->_plotGenerator = $plotGenerator;
        $this->_config =  $configuration->getConfig();
        $this->_jobRepository = $em->getRepository(\App\Entity\Job::class);
        $this->_metricDataRepository = $metricRepo;
        $this->_traceRepository = $em->getRepository(\App\Entity\TraceResolution::class);
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
        $statrepository = $this->_em->getRepository(\App\Entity\StatisticCache::class);
        $stat = $statrepository->findOneBySignature(
            $userId,
            $control->getCluster(),
            $control->getMonth(),
            $control->getYear());

        if ( !$stat ) {
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
        }

        return $stat;
    }

    public function updateJobAverage( $job )
    {
        $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();
        $stats = $this->_metricDataRepository->getJobStats($job, $metrics);
        $job->memUsedAvg = $stats['mem_used_avg'];
        $job->memBwAvg = $stats['mem_bw_avg'];
        $job->flopsAnyAvg = $stats['flops_any_avg'];
        $job->trafficTotalIbAvg = $stats['traffic_total_ib_avg'];
        $job->trafficTotalLustreAvg = $stats['traffic_total_lustre_avg'];
    }

    public function getResolution($plot, $name)
    {
        $resolutions = $plot->getResolutions();
        $plot->traceResolution = $this->_traceRepository->find($resolutions[$name]);
    }

    private function _persistPlot( $plot )
    {
        $resolution = $plot->traceResolution;
        $traces = $resolution->getTraces();

        foreach ( $traces as $trace ){
            $this->_em->persist($trace);
        }
        $this->_em->persist($resolution);
        $plot->resolutionCache[] = $resolution;
        $this->_em->persist($plot);
    }

    private function _buildViewPlots($job)
    {
        $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();
        $stats = $this->_metricDataRepository->getJobStats($job, $metrics);

        $this->_createJobRooflineCache($job);
        $this->_createJobPolarPlotCache($job, $stats);
        $this->_createNodeStats($job, $stats['nodeStats'], $metrics, true);
    }

    private function _createNodeStats(
        $job, $stats, $metrics, $persist = false
    )
    {
        foreach ($stats as $node){
            $nodeStat = new NodeStat();
            $nodeId = $job->getNode($node['nodeId'])->getNodeId();
            $nodeStat->setNodeName($nodeId);

            foreach ($metrics as $metric){
                $metricName = $metric->name;
                $metricStat = new MetricStat();
                $metricStat->setMetricName($metricName);
                $metricStat->setAvg($node["{$metricName}_avg"]);
                $metricStat->setMin($node["{$metricName}_min"]);
                $metricStat->setMax($node["{$metricName}_max"]);
                $nodeStat->addMetric($metricStat);
                $metricStat->setNodeStat($nodeStat);
                if ( $persist ) {
                    $this->_em->persist($metricStat);
                }
            }

            $job->jobCache->addNodeStat($nodeStat);
            $nodeStat->setJobCache($job->jobCache);

            if ( $persist ) {
                $this->_em->persist($nodeStat);
            }
        }
    }

    private function _createJobRooflineCache($job)
    {
        $data = $this->_metricDataRepository->getJobRoofline($job);
        $plot = new Plot();
        $plot->name = 'roofline';
        $x; $y;
        for($i = 0; $i < count($data); $i++) {
            if ( $data[$i]['x'] > 0.01 ) {
                $x[] = $data[$i]['x'];
                $y[] = $data[$i]['y'];
            }
        }

        if ( !isset($x) ){
            $x[] = 0.015;
            $y[] = 0.015;
        }
        $traceResolution = new TraceResolution();
        $traceResolution->resolution = 'view';
        $trace = new Trace();
        $trace->setName('roofline');
        $trace->setJson(json_encode(array(
            'x' => $x,
            'y' => $y
        )));

        $traceResolution->addTrace($trace);
        $trace->setTraceResolution($traceResolution);
        $plot->traceResolution = $traceResolution;
        $job->jobCache->addPlot($plot);
        $plot->setJobCache($job->jobCache);
        $this->_persistPlot($plot);
    }

    private function _generateMetricPlotCache(
        $plot,
        $job,
        $metric,
        $resolution,
        $perfData,
        $options)
    {
        $maxVal = 0.0;
        $metricName = $metric->getName();
        $xAxis;

        $traceResolution= new TraceResolution();
        $traceResolution->resolution = $resolution;
        $nodes = $job->getNodes();

        foreach ($nodes as $node){
            $nodeId = $node->getNodeId();
            $x = $data[$metricName][$nodeId]['x'];
            $y = $data[$metricName][$nodeId]['y'];

            /* get max y for axis range */
            $maxVal = max($maxVal,max($y));
            /* adjust x axis time unit */
            $xAxis = $this->_dataModifier->scaleTime($x);
            $trace = new Trace();
            $trace->setName($nodeId);

            if ($options['sample'] > 0){
                $this->_dataModifier->downsampleData($x,$y,$options['sample']);
            }

            $trace->setJson(json_encode(array(
                'x' => $x,
                'y' => $y
            )));
            $traceResolution->addTrace($trace);
            $trace->setTraceResolution($traceResolution);
        }

        $plot->yMax = $maxVal;
        $plot->yUnit = $metric->getUnit();
        $plot->xUnit = $xAxis['unit'];
        $plot->xDtick = $xAxis['dtick'];
        $plot->traceResolution = $traceResolution;
    }

    private function _createJobPolarPlotCache($stats)
    {
        $plot = new Plot();
        $plot->name = 'polarplot';

        $traceResolution = new TraceResolution();
        $traceResolution->resolution = 'view';
        $trace = new Trace();
        $trace->setName('polarplot');
        $trace->setJson(json_encode($avg));
        $traceResolution->addTrace($trace);
        $trace->setTraceResolution($traceResolution);
        $plot->traceResolution = $traceResolution;
        $job->jobCache->addPlot($plot);
        $plot->setJobCache($job->jobCache);
        $this->_persistPlot($plot);
    }


    private function _buildMetricPlots(
        $job,
        $mode,
        $options
    )
    {
        $jobCache = $job->jobCache;
        $metrics = $job->getCluster()->getMetricList($mode)->getMetrics();
        $data = $this->_metricDataRepository->getMetricData( $job, $metrics);
        $plots = $jobCache->getPlots();

        foreach ($metrics as $metric){

            $metricName = $metric->getName();

            if (isset($plots[$metricName])){
                $plot = $plots[$metricName];
            } else {
                $plot = new Plot();
                $plot->name = $metricName;
                $jobCache->addPlot($plot);
                $plot->setJobCache($jobCache);
            }

            $this->_plotGenerator->generateMetricPlotCache(
                $plot,
                $job,
                $metric,
                $mode,
                $data,
                $options);

            $this->_persistPlot($plot);
        }
    }

    public function buildData( $job )
    {
        $options = array();

        if ( is_null( $job->jobCache ) ) {
            $jobCache = new JobCache();

            $metrics = $job->getCluster()->getMetricList('view')->getMetrics();
            $data = $this->_metricDataRepository->getMetricData( $job, $metrics);
            $plots = $jobCache->getPlots();
            $options['sample'] = 0;

            foreach ($metrics as $metric){

                $metricName = $metric->getName();

                if (isset($plots[$metricName])){
                    $plot = $plots[$metricName];
                } else {
                    $plot = new Plot();
                    $plot->name = $metricName;
                    $jobCache->addPlot($plot);
                    $plot->setJobCache($jobCache);
                }

                $this->_generateMetricPlotCache(
                    $plot,
                    $job,
                    $metric,
                    'view',
                    $data,
                    $options);

            }

            $job->jobCache = $jobCache;
        }
    }


    /**
     * Builds and persists job cache
     *
     * This routine checks if a job cache exists. If there is no cache
     * metric plots are build in a view and list resolution.
     *
     * @param Job $job
     */
    public function buildCache( Job $job )
    {
        if ( $job->isRunning()) {
            $job->stopTime = time();
            /* $job->stopTime = 1521057932; */
            $job->duration = $job->stopTime - $job->startTime;
        }

        if ( is_null( $job->jobCache ) ) {
            $job->jobCache = new JobCache();
            $this->_buildViewPlots($job);
            $options['sample'] = 0;
            $this->_buildMetricPlots(
                $job,
                'view',
                $options
            );
            $options['sample'] = 120;
            $this->_buildMetricPlots(
                $job,
                'list',
                $options
            );
            $this->_em->persist($job->jobCache);
            $this->_em->persist($job);
            $this->_em->flush();

            /* update resolution json in plots */
            $plots = $job->jobCache->getPlots();

            foreach ( $plots as $plot ){
                foreach ( $plot->resolutionCache as $resolution ){
                    $plot->addResolution($resolution->getResolution() , $resolution->getId());
                    $this->_em->persist($plot);
                }
            }
            $this->_em->flush();
        }
    }

    public function dropCache( $job )
    {
        $jobCache = $job->jobCache;

        if ( $jobCache ) {
            /* remove node statistics */
            $stats = $jobCache->getNodeStat();

            foreach ( $stats as $stat ) {
                foreach ( $stat->metrics as $metric ) {
                    $stat->removeMetric($metric);
                    $this->_em->remove($metric);
                }
                $jobCache->removeNodeStat($stat);
                $this->_em->remove($stat);
            }

            /* remove  plot cache*/
            $plots = $jobCache->getPlots();

            foreach ( $plots as $plot ) {

                $resolutions = $plot->getResolutions();

                foreach ( $resolutions as $resolution ) {

                    $traceResolution = $this->_traceRepository->find($resolution);
                    $traces = $traceResolution->getTraces();

                    foreach ( $traces as $trace ) {
                        $traceResolution->removeTrace($trace);
                        $this->_em->remove($trace);
                    }

                    $this->_em->remove($traceResolution);
                }

                $plot->dropResolutions();
                $jobCache->removePlot($plot);
                $this->_em->remove($plot);
            }

            $job->jobCache = null;
            $this->_em->remove($jobCache);
            $this->_em->flush();
        }
    }

    /**
     * Check if job cache exists and initialize job metric data if required
     *
     * For running job set stoptime to current time and compute duration.
     * Check if cache exists: In case there is no cache check if metric
     * data is available for job and initialize data for following modes:
     *
     * * ```view```: Single job including roofline, polarplot and node
     *               statistic table
     * * ```list```: Job list
     * * ```data```: Initialize data only, do not build plot jsons
     *
     * After the call the metric data is initilized according to the mode
     * in the jobs JobCache object.
     *
     * Example usage:
     * ```
     * foreach ( $jobs as $job ) {
     *     $this->_jobCache->checkCache(
     *               $job,
     *               array(
     *                   'mode' => 'data'
     *               ),
     *               $config
     *           );
     *  }
     * ```
     *
     * @param Job $job
     * @param mixed $options
     * @param mixed $config
     * @uses App\Repository\MetricDataRepository
     * @uses App\Service\PlotGenerator
     * @api
     */
    public function checkCache(
        Job $job,
        $options,
        $config
    )
    {
        if ( $job->isRunning()) {
            $job->stopTime = time();
            /* $job->stopTime = 1521057932; */
            $job->duration = $job->stopTime - $job->startTime;
        }

        if ( is_null( $job->jobCache ) ) {

            if (! $this->_metricDataRepository->hasProfile($job)){
                return;
            }

            $job->jobCache = new \App\Entity\JobCache();

            if ( $options['mode'] === 'view' ) { /* Single Job View with job roofline and node stats */
                $options['autotick'] = true;
                $options['sample'] = 0;
                $options['legend'] = false;
                $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();
                $stats = $this->_metricDataRepository->getJobStats($job, $metrics);

                if ( $config['view.roofline.show']->value == 'true' ) {
                    $this->_plotGenerator->generateJobRoofline(
                        $job, $this->_metricDataRepository->getJobRoofline($job)
                    );
                }

                if ( $config['view.polarplot.show']->value == 'true' ) {
                    $this->_plotGenerator->generateJobPolarPlot(
                        $job, $metrics, $stats
                    );
                }

                if ( $config['view.statTable.show']->value == 'true' ) {
                    $this->_createNodeStats($job, $stats['nodeStats'], $metrics);
                }
            } else if ( $options['mode'] === 'list' ) { /* Job list  */
                $options['sample'] = 150;
                $options['legend'] = false;

            } else if ( $options['mode'] === 'data' ) { /* Extract data only, do not build plot jsons  */
                $this->buildData($job);
                return;
            }

            $metrics = $job->getCluster()->getMetricList($options['mode'])->getMetrics();
            $data = $this->_metricDataRepository->getMetricData( $job, $metrics);

            foreach ($metrics as $metric){
                $this->_plotGenerator->generateMetricPlot(
                    $job,
                    $metric,
                    $options,
                    $data
                );
            }
        } else { /* use cached data */

            $metrics;
            $jobCache = $job->jobCache;
            $job->hasProfile = true;

            if ( $options['mode'] === 'view' ) { /* Single Job View with job roofline and node stats */
                $options['autotick'] = true;
                $options['legend'] = false;

                if ( $config['view.roofline.show']->value == 'true' ) {
                    $this->_plotGenerator->generateJobRoofline($job);
                }

                $metrics= $job->getCluster()->getMetricList('stat')->getMetrics();

                if ( $config['view.polarplot.show']->value == 'true' ){
                    $this->_plotGenerator->generateJobPolarPlot($job, $metrics);
                }

            } else if ( $options['mode'] === 'list' ) {
                $options['legend'] = false;
            } else if ( $options['mode'] === 'data' ) { /* Extract data only, do not build plot jsons  */
                $metrics = $job->getCluster()->getMetricList('view')->getMetrics();

                foreach ($metrics as $metric){
                    $plot = $jobCache->getPlot($metric->name);
                    $this->getResolution($plot, 'view');
                }

                return;
            }

            $metrics = $job->getCluster()->getMetricList($options['mode'])->getMetrics();

            foreach ($metrics as $metric){
                $this->_plotGenerator->generateMetricPlot(
                    $job,
                    $metric,
                    $options
                );
            }
        }
    }
}

