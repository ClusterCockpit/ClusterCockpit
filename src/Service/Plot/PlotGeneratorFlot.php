<?php

namespace App\Service\Plot;

use Psr\Log\LoggerInterface;
use App\Entity\Trace;
use App\Entity\Plot;

class PlotGeneratorFlot implements PlotGeneratorInterface
{
    private $_logger;

    public function __construct(LoggerInterface $logger )
    {
        $this->_logger = $logger;
    }

    public function generateScatterPlot($title, &$data, $options)
    {
        $colorscale = array(
            array(0   , 'rgb(0,0,255)'),
            array(0.25, 'rgb(0,255,0)'),
            array(0.75, 'rgb(255,255,0)'),
            array(1   , 'rgb(255,0,0)')
        );

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
            $tmp[$i] = array(
                $x[$i],
                $y[$i]
            ); 
        }

        $data[] = array(
                    "data" => $tmp,
                    "label" => "$name", 
                    "color" => $options['color']
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

        $layout = array(
            "series" => array(
                "lines" => array(
                    "show" => true
                )
            ),
            "legend" => array(
                "show" => false
            ),
            "xaxis" => array(
                "show" => true,
                "position" => "bottom",
            ),
            "yaxis" => array(
                "show" => true,
                "position" => "left",
            )
        );

        return json_encode($layout);
    }


    public function getBackendName(){
        return 'flot';
    }
}

