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

use Doctrine\ORM\EntityManagerInterface;
use App\Service\Plot\PlotGeneratorInterface;
use App\Service\Plot\FilePlotGeneratorXmGrace;
use App\Service\ColorMap;
use App\Entity\Plot;

class PlotGenerator
{
    private $_plotter;
    private $_tsHelper;
    private $_projectDir;

    public function __construct(
        TimeseriesHelper $tsHelper,
        EntityManagerInterface $em,
        PlotGeneratorInterface $plotter,
        $projectDir
    )
    {
        $this->_tsHelper = $tsHelper;
        $this->_plotter = $plotter;
        $this->_projectDir = $projectDir;
    }

    private function _createClusterRoof(&$cluster)
    {
        # compute roofline points
        $yCut =(0.01 * $cluster->memoryBandwidth);
        $scalarKnee = ($cluster->flopRateScalar - $yCut) / $cluster->memoryBandwidth;
        $simdKnee = ($cluster->flopRateSimd - $yCut) / $cluster->memoryBandwidth;

        return array(
            'xRFScalar' => array(
                0.01, $scalarKnee, 1000),
            'yRFScalar' => array(
                $yCut, $cluster->flopRateScalar, $cluster->flopRateScalar),
            'xRFSimd' => array(
                0.01, $simdKnee, 1000),
            'yRFSimd' => array(
                $yCut, $cluster->flopRateSimd, $cluster->flopRateSimd)
            );
    }

    private function _createHistogram($stat, $options)
    {
        for($i = $options['start']; $i < $options['stop']; $i++) {
            $x[] = "$i";
            $y[] = 0;
        }

        foreach ( $stat as $bin=>$count ){
            if ( $bin < $options['stop'] ){
                $index = (int) $bin;
                $y[$index] = (int) $count;
            }
        }

        $data['x'] = $x;
        $data['y'] = $y;

        return $this->_plotter->generateBarPlot($options['name'], $data, $options);
    }

    public function getBackend()
    {
        return $this->_plotter->getBackendName();
    }

    public function createJobCloud($statCache, $cluster, &$data, &$status)
    {
        if ( ! $data ){
            $status['hasPerf'] = false;
            return;
        }

        $data['roof ']= $this->_createClusterRoof($cluster);

        $statCache->addPlot($this->_plotter->generateScatterPlot(
            'roofline',
            $data,
            array(
                'name'=>'data',
                'title' => 'nodes'
            )));
    }

    public function generateJobPolarPlot($job, $metrics, $data = NULL)
    {
        $plot = new Plot();
        $plot->name = 'polarplot';
        $job->jobCache->addPlot($plot);

        $this->_plotter->generatePolarPlot($plot, $data, $metrics,
            array(
                'caption' => 'Job Area',
                'x-title' => 'Usage'
            ));
    }

    public function generateJobRoofline($job, $data = NULL, $fileOut = false)
    {
        $cluster = $job->getCluster();
        $plot = $job->jobCache->getPlot('roofline');

        for($i = 0; $i < count($data); $i++) {
            if ( $data[$i]['x'] > 0.01 ) {
                $x[] = floatval($data[$i]['x']);
                $y[] = floatval($data[$i]['y']);
            }
        }

        if ( !isset($x) ){
            $x[] = 0.015;
            $y[] = 0.015;
        }

        $data['x']= $x;
        $data['y']= $y;

        if ($fileOut) {
            $this->_filePlotter->generateScatterPlot(
                $tmpdata,
                array(
                    'name'=>'data',
                    'title' => 'time [min]'
                ));
        }

        $plot = new Plot();
        $plot->name = 'roofline';
        $job->jobCache->addPlot($plot);

        $data['roof']= $this->_createClusterRoof($cluster);
        $data['color']= range(0,count($data['x']));

        $this->_plotter->generateScatterPlot(
            $plot,
            $data,
            array(
                'name'=>'data',
                'title' => 'time [min]'
            ));
    }

    public function createClusterRoofline($data, $cluster)
    {
        $x; $y;
        $roof = $this->_createClusterRoof($cluster);

        for($i = 0; $i < count($data); $i++) {
            if ( $data[$i]['x'] > 0.01 ) {
                $x[] = $data[$i]['x'];
                $y[] = $data[$i]['y'];
            }
        }

        $data['x']= $x;
        $data['y']= $y;

        return $this->_plotter->generateScatterPlot('time [min]', $data, array('name'=>'data'));
    }

    public function generateJobHistograms($statCache, &$stat)
    {
        $statCache->addPlot(
            $this->_createHistogram($stat['histo_runtime'], array(
                'start' => 0,
                'stop' => 25,
                'name' => 'histoPlotRuntime',
                'caption' => 'Runtime',
                'x-title' => 'runtime [h]',
            ))
        );

        $statCache->addPlot(
            $this->_createHistogram($stat['histo_numnodes'], array(
                'start' => 1,
                'stop' => 64,
                'name' => 'histoPlotNumnodes',
                'caption' => 'Number of nodes',
                'x-title' => 'number of nodes',
            ))
        );
    }

    public function generateMetricPlot(
        $job,
        $metric,
        $options,
        &$data = NULL
    )
    {
        $maxVal = 0.0;
        $metricName = $metric->getName();
        $xAxis;
        $lineData;
        $colorState;

        $plot = new  Plot();
        $plot->name = $metricName;
        $nodes = $job->getNodes();

        $color = new ColorMap();
        $color->setColormap($options['plot_general_colorscheme'], $this->_projectDir);
        $color->init($colorState, count($nodes));

        foreach ($nodes as $node){

            $nodeId = $node->getNodeId();
            $x = $data[$metricName][$nodeId]['x'];
            $y = $data[$metricName][$nodeId]['y'];

            /* get max y for axis range */
            $maxVal = max($maxVal,max($y));
            /* adjust x axis time unit */
            $xAxis = $this->_tsHelper->scaleTime($options, $x);

/*             /1* downsample data frequency *1/ */
/*             if ( $options['sample'] > 0 ){ */
/*                 $this->_tsHelper->downsampling($x,$y,$options['sample']); */
/*             } */

            $options['color'] = $color->getColor($colorState);

            $this->_plotter->generateLine(
                $lineData,
                $nodeId,
                $x, $y,
                $options);
        }

        if ( $options['mode'] === 'list' ){
            /* add reference line */
            $options['color'] = 'rgb(0,0,0)';
            $value = $metric->normal;
            $x = array(0, end($x));
            $y = array($value,$value);

            $this->_plotter->generateLine(
                $lineData,
                'Reference',
                $x, $y,
                $options);

            if ($value > $maxVal){
                $maxVal = $value;
            }
        }

        $options['maxVal'] = $maxVal;
        $options['unit'] = $metric->getUnit();
        $options['xUnit'] = $xAxis['unit'];
        $options['xDtick'] = $xAxis['dtick'];

        $plot->setOptions($this->_plotter->generateLayout($metricName, $options));
        $plot->setData($lineData);
        $job->jobCache->addPlot($plot);
    }
}
