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

namespace App\Entity;

use App\Entity\Metric;

class JobCache
{
    public $nodeStat;

    public $plots;

    public function getNodeStat()
    {
        return $this->nodeStat;
    }

    public function addNodeStat(NodeStat $nodeStat): self
    {
        if (!$this->nodeStat->contains($nodeStat)) {
            $this->nodeStat[] = $nodeStat;
        }

        return $this;
    }

    public function removeNodeStat(NodeStat $nodeStat): self
    {
        if ($this->nodeStat->contains($nodeStat)) {
            $this->nodeStat->removeElement($nodeStat);
        }

        return $this;
    }

    public function getPlots()
    {
        return $this->plots;
    }

    public function getPlotsArray($metrics)
    {
        $jsonPlots = array();

        foreach ($metrics as $metric){
            $plot = $this->plots[$metric->name];
            $jsonPlots[] = array(
                'name' => $plot->name,
                'options' => $plot->options,
                'data' => $plot->data
            );
        }
        return $jsonPlots;
    }

    public function removePlot(Plot $plot): self
    {
        if ($this->plots->contains($plot)) {
            $this->plots->removeElement($plot);
        }

        return $this;
    }

    public function addPlot(Plot $plot): self
    {
        $this->plots[$plot->name] = $plot;
        return $this;
    }

    public function getPlot($name)
    {
        if (!isset($this->plots[$name])) {
            return false;
        }

        return $this->plots[$name];
    }
}

