<?php

namespace App\Service\Plot;

use Psr\Log\LoggerInterface;
use App\Entity\Trace;
use App\Entity\Plot;
use App\Entity\StatisticPlot;

class PlotGeneratorPlotly implements PlotGeneratorInterface
{
    private $_logger;

    public function __construct(LoggerInterface $logger )
    {
        $this->_logger = $logger;
    }

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
        $plot->setOptions(json_encode(array(
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
        )));
    }

    public function generatePolarPlot($plot, &$jobData, $metrics, $options)
    {
        $data; $theta_avg; $r_avg; $theta_max; $r_max;

        $plot->setOptions(
            json_encode(
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
                    ))
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
        $plot = new StatisticPlot();
        $plot->name = $title;

        $plot->setOptions(
            json_encode(array(
                "title" => "Histogram: ".$options['caption'],
                "xaxis" => array(
                    "title" => $options['x-title']
                ),
                "yaxis" => array(
                    "title" => "count"
                )))
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
                "width" => 1
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
                "range" => array(0,$options['maxVal']),
                "title" => "[".$unit."]"
            );
        }

        return json_encode($layout);
    }


    public function getBackendName(){
        return 'plotly';
    }
}

