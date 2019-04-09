<?php

namespace App\Service\Plot;

use App\Service\ColorMap;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class FilePlotGeneratorXmGrace
{
    private $_logger;
    private $_color;
    private $_fileSystem;
    private $_twig;

    public function __construct(
        Environment $twig,
        Filesystem $fileSystem,
        LoggerInterface $logger )
    {
        $this->_logger = $logger;
        $this->_fileSystem = $fileSystem;
        $this->_twig = $twig;
    }

    public function generateScatterPlot(&$data, $options)
    {
        $roof = $data['roof'];
        $roofScalar;
        $roofSimd;
        $colorState;
        $size = count($data['x']);

        $this->_color->setColormap('COLOR_thomas');
        $this->_color->init($colorState, count($data['x']), 2);

        for ($i=0; $i<3; $i++){
            $roofScalar[] = "{$roof['xRFScalar'][$i]} {$roof['yRFScalar'][$i]}";
            $roofSimd[] = "{$roof['xRFSimd'][$i]} {$roof['yRFSimd'][$i]}";
        }

        $graceData;

        for ($i=0; $i<$size; $i++){
            $this->_color->getColor($colorState);
            $graceData[] = "{$data['x'][$i]} {$data['y'][$i]} {$colorState['mapping']}";
        }

        $colormap = $this->_color->getColorMap('xmgrace');

        $content = $this->_twig->render(
            'xmgrace/roofline.agr.twig',
            array(
                'colors' => $colormap,
                'roofScalar' => $roofScalar,
                'roofSimd' => $roofSimd,
                'data' => $graceData,
            ));

        try {
            $this->_fileSystem->dumpFile('/Users/jan/test2.agr', $content);
        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while dumping grace file at ".$exception->getPath();
        }
    }


    public function generateBarPlot($title, &$jobData, $options)
    {
        $data;
        $plot = new Plot();
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
        $data[] = array(
            "x" => $x,
            "y" => $y,
            "mode" => "lines",
            "name" => "$name", 
            "line" => array(
                "color" => $options['color'],
                "width" => 1
            )
        );
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

