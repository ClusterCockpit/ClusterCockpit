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

namespace App\Service\Plot;

use App\Entity\Trace;
use App\Entity\Plot;
use App\Service\Configuration;

class PlotGeneratorPlotly implements PlotGeneratorInterface
{
    public function generateScatterPlot( $plot, &$data, $options)
    {
        $colorscale = array(
            array(0   , 'rgb(0,0,255)'),
            array(0.25, 'rgb(0,255,0)'),
            array(0.75, 'rgb(255,255,0)'),
            array(1   , 'rgb(255,0,0)')
        );

        $roof = $data['roof'];

        $traceObject[] = array(
            'x' => $data['x'],
            'y' => $data['y'],
            'marker' => array(
                'color'  => $data['color'],
                'showscale' => false,
                'colorscale' => $colorscale
            ),
            'mode' => 'markers',
            'type' => 'scattergl',
            'showlegend' => false
        );

        $traceObject[] = array(
            "x" => $roof['xRFScalar'],
            "y" => $roof['yRFScalar'],
            "mode" => "lines",
            "type" => "scattergl",
            /* "name" => "Roofline (scalar)", */
            "showlegend" => false,
            "color" => "blue",
        );

        $traceObject[] = array(
            "x" => $roof['xRFSimd'],
            "y" => $roof['yRFSimd'],
            "mode" => "lines",
            "type" => "scattergl",
            /* "name" => "Roofline (simd)", */
            "showlegend" => false,
            "color" => "red",
        );


        $plot->setData($traceObject);
        $plot->setOptions(array(
            /* "title" => 'Job Roofline', */
            "autosize" => 'false',
            "width" => 600,
            "height" => 260,
            "margin" => array(
                "l" => 40,
                "r" => 10,
                "b" => 40,
                "t" => 10,
                "pad" => 4
            ),
            "xaxis" => array(
                'type' => 'log',
                'autorange' => true,
                'title' => 'Intensity (Flops/Byte)'
            ),
            'yaxis' => array(
                'type' => 'log',
                'autorange' => true,
                'title' => 'Performance (GF/s)'
            )
        ));
    }
    public function generatePolarPlot($plot, &$jobData, $metrics, $options)
    {
        $data; $theta_avg; $r_avg; $theta_max; $r_max;

        $plot->setOptions(
                array(
                    "margin" => array(
                        "l" => 40,
                        "r" => 70,
                        "b" => 20,
                        "t" => 30,
                        "pad" => 4
                    ),
                    "radialxaxis" => array(
                        "angle" => 45,
                    ),
                    "angularaxis" => array(
                        "direction" => "clockwise",
                        "period" => 6,
                    )
                ));

        foreach ($metrics as $metric){
            $name = $metric->name;
            $r_avg[] = $jobData["{$name}_avg"]/$metric->peak;
            $theta_avg[] = $name;
            $r_max[] = $jobData["{$name}_max"]/$metric->peak;
            $theta_max[] = $name;
        }

        $name = $metrics['mem_used']->name;
        $r_avg[] = $jobData["{$name}_avg"]/$metrics['mem_used']->peak;
        $theta_avg[] = $name;
        $r_max[] = $jobData["{$name}_max"]/$metrics['mem_used']->peak;
        $theta_max[] = $name;

        $data[] = array(
            'type' => 'scatterpolar',
            'name' => 'avg',
            'r' => $r_avg,
            'theta' => $theta_avg,
            'fill' => 'toself'
        );
        $data[] = array(
            'type' => 'scatterpolar',
            'name' => 'max',
            'r' => $r_max,
            'theta' => $theta_max,
            'fill' => 'toself'
        );

        $plot->setData($data);
    }
    public function generateBarPlot($title, &$jobData, $options)
    {
        $data;
        $plot = new Plot();
        $plot->name = $title;

        $plot->setOptions(
            array(
                "title" => "Histogram: ".$options['caption'],
                "xaxis" => array(
                    "title" => $options['x-title']
                ),
                "yaxis" => array(
                    "title" => "count"
                )
            )
        );

        $data[] = array(
            "x" => $jobData['x'],
            "y" => $jobData['y'],
            "type" => "bar",
        );
        $plot->setData($data);

        return $plot;
    }
    public function generateLine(&$data, $name, &$x, &$y, $options)
    {
        $line = array(
            "x" => $x,
            "y" => $y,
            "mode" => "lines",
            "name" => "$name",
            "line" => array(
                "color" => $options['color'],
                "width" => $options['lineWidth']
            )
        );

        if ( $options['mode'] === 'list' ){
            $line['type'] = 'scattergl';
        }

        $data[] = $line;
    }
    public function generateLayout($title, $options)
    {
        $xUnit = $options['xUnit'];
        $unit = $options['unit'];

        $layout = array(
            "title" => $title,
            "autosize" => 'false',
            "margin" => array(
                "l" => 40,
                "r" => 10,
                "b" => 40,
                "t" => 40,
                "pad" => 4
            ),
            "xaxis" => array(
                "autotick" => 'false',
                "dtick" => $options['xDtick'],
                "title" => "runtime [$xUnit]"
            ),
            "showlegend" => $options['legend']
        );


        if ( isset($options['autotick'])){
            $layout["yaxis"] = array(
                "autotick" => 'true',
                "range" => array(0,$options['maxVal']*1.2),
                "title" => "[".$unit."]"
            );

        } else {
            $y_dtick = $options['maxVal']/10.0;
            $layout["yaxis"] = array(
                "autotick" => 'false',
                "dtick" => $y_dtick,
                "range" => array(0,$options['maxVal']*1.2),
                "title" => "[".$unit."]"
            );
        }

        if ( isset($options['bgColor'])){
            $layout["plot_bgcolor"] = $options['bgColor'];
        }


        return $layout;
    }
    public function getBackendName(){
        return 'plotly';
    }
}

