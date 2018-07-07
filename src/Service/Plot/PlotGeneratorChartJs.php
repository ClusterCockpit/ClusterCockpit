<?php

namespace App\Service\Plot;

use Psr\Log\LoggerInterface;
use App\Entity\Trace;
use App\Entity\Plot;

class PlotGeneratorChartJs implements PlotGeneratorInterface
{
    private $_logger;
    private $_index;
    private $_colors;

    public function __construct(LoggerInterface $logger )
    {
        $this->_logger = $logger;
        $this->_index = 0;
        $this->_colors = array(
            "rgba(255,0,0,1.0)",
            "rgba(0,255,0,1.0)",
            "rgba(0,0,255,1.0)",
            "rgba(255,255,0,1.0)",
            "rgba(0,255,255,1.0)",
            "rgba(128,0,255,1.0)",
        );
    }

    public function generateScatterPlot($title, &$data, $options)
    {
        $roof = $data['roof'];

        $plot = new Plot();
        $plot->name = $title;

        $trace = new Trace();
        $trace->setName('data');
        $trace->setJson(json_encode(array(
                'x' => $data['x'],
                'y' => $data['y'],
                'marker' => array(
                    'color'  => $data['color'],
                    'showscale' => true,
                    'colorbar'  => array(
                        'x'     => -0.25,
                        'title' => $options['title']
                    ),
                    'colorscale' => $colorscale
                ),
                'mode' => 'markers',
                'type' => 'scatter',
                'showlegend' => false
            )));
        $plot->addTrace($trace);

        $trace = new Trace();
        $trace->setName('traceRooflineScalar');
        $trace->setJson(json_encode(array(
                "x" => $roof['xRFScalar'],
                "y" => $roof['yRFScalar'],
                "mode" => "lines",
                "type" => "scatter",
                /* "name" => "Roofline (scalar)", */
                "showlegend" => false,
                "color" => "blue",
            )));
        $plot->addTrace($trace);

        $trace = new Trace();
        $trace->setName('traceRooflineSimd');
        $trace->setJson(json_encode(array(
                "x" => $roof['xRFSimd'],
                "y" => $roof['yRFSimd'],
                "mode" => "lines",
                "type" => "scatter",
                /* "name" => "Roofline (simd)", */
                "showlegend" => false,
                "color" => "red",
            )));
        $plot->addTrace($trace);

        $plot->setLayout(json_encode(array(
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

        return $plot;
    }


    public function generateBarPlot($title, &$data, $options)
    {
        $plot = new Plot();
        $plot->name = $title;
        $plot->setLayout(
            json_encode(array(
                "title" => "Histogram: ".$options['caption'],
                "xaxis" => array(
                    "title" => $options['x-title']
                ),
                "yaxis" => array(
                    "title" => "count"
                )))
            );

        $trace = new Trace();
        $trace->setName($options['name']);
        $trace->setJson(
            json_encode(array(
                "x" => $data['x'],
                "y" => $data['y'],
                "type" => "bar",
            ))
        );
        $plot->addTrace($trace);

        return $plot;
    }

    public function generateLine(&$data, $name, &$x, &$y, $options)
    {
        $tmp;

        for($i = 0; $i < count($x); $i++){
            $tmp[] = array(
                'x' => $x[$i],
                'y' => $y[$i]
            ); 
        }

        $data['datasets'][] = array(
                    "data" => $tmp,
                    "label" => "$name", 
                    "type" => 'line',
                    "borderColor" => $options['color'],
                    "backgroundColor" => $options['color'],
                );
    }

    public function editLayout($layoutJSON, $keys, $options)
    {
        $layout = json_decode($layoutJSON,true);

        foreach ($keys as $key){
            $layout["$key"] = $options["$key"];
        }

        return json_encode($layout);
    }

    public function generateLayout($title, $options)
    {
        $xUnit = $options['xAxis']['unit'];
        $unit = $options['unit'];
        $y_dtick = $options['maxVal']/10.0;

        $layout = array(
            "title" => array(
                "text" => $title,
                "display" => true,
            ),
            "showLine" => false,
            "animation"  => array(
                "duration" => 0
            ),
            "elements" => array(
                "points" => array(
            ),
                "line" => array(
                    "tension" => 0
                )
            ),
            "legend" => array(
                "display" => false
                /* "display" => $options['legend'] */
            ),
        );

        $layout['scales'] =  array();
        $layout['scales']['yAxes'][] = array(
                    "display" => true,
                    /* "ticks" => array( */
                    /*     "min" => 0, */
                    /*     "max" => $options['maxVal'], */
                    /*     "stepSize" => $y_dtick */
                    /* ), */
                    "scaleLabel" => array(
                        "display" => true,
                        "labelString" => "[".$unit."]"
                    ));
        $layout['scales']['xAxes'][] = array(
                    "display" => true,
                    /* "ticks" => array( */
                    /*     "min" => 0, */
                    /*     "stepSize" => $options['xAxis']['dtick'] */
                    /* ), */
                    "scaleLabel" => array(
                        "display" => true,
                        "labelString" => "runtime [$xUnit]"
                    ));

        return json_encode($layout);
    }


    public function getBackendName(){
        return 'chartjs';
    }
}

