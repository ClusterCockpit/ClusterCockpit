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

use Psr\Log\LoggerInterface;
use App\Entity\JobCache;

class JobStatistic
{
    private $logger;

    private function _median($arr){
        if($arr){
            $count = count($arr);
            sort($arr);
            $mid = floor($count/2);
            return ($arr[$mid]+$arr[$mid+1-$count%2])/2;
        }
        return false;
    }

    private function _average($arr){
        return ($arr) ? array_sum($arr)/count($arr) : false;
    }

    public function __construct(LoggerInterface $logger )
    {
        $this->logger = $logger;
    }

    public function buildJobStat($jobCache, $metrics, $options)
    {
        foreach ( $metrics as $metric ) {
            $plots = $jobCache->getPlot($metric);
            $resolution = $plots->getTraceResolution('view');
            $nodes = $resolution->getTraces()->toArray();

            $traceResolution= new TraceResolution();
            $traceResolution->resolution = 'stat';
            $min; $max; $median;


            /* iterate over nodes */
            for ($i=0; $i<count($nodes); $i++) {
                $data[$i] = $nodes[$i]->getData();
            }

            /* iterate over time */
            for ($j=0; $j<count($data['x']); $j++) {
                $arr;

                for ($i=0; $i<count($nodes); $i++) {
                    $arr[] = $data[$i]['y'][$j];
                }

                $min[] = min($arr);
                $max[] = max($arr);
                $median[] = $this->_median($arr);
            }

            $trace = new Trace();
            $trace->setName('min');

            if ($options['sample'] == 0){
                $trace->setJson(json_encode(array(
                    'x' => $x,
                    'y' => $y
                )));
            } else {
                $sampledData = $this->_dataModifier->downsampleData($x,$y,$options['sample']);
                $trace->setJson(json_encode($sampledData));
            }

            $traceResolution->addTrace($trace);
            $trace->setTraceResolution($traceResolution);

        }
    }
}

