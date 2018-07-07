<?php

namespace App\Service\Plot;

interface PlotGeneratorInterface
{
    public function generateLine(&$data, $name, &$x, &$y, $options);
    public function generateLayout($title, $options);

    public function generateBarPlot($title, &$data, $options);
    public function generateScatterPlot($plot, &$data, $options);
    public function getBackendName();
}
