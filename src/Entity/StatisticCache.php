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

class StatisticCache
{
    private $userId;

    private $month;

    private $year;

    private $clusterId;

    public $jobCount;

    public $totalWalltime;

    public $totalCoreHours;

    public $shortJobCount;

    public $plots;


    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getClusterId(): ?int
    {
        return $this->clusterId;
    }

    public function setClusterId(int $clusterId): self
    {
        $this->clusterId = $clusterId;

        return $this;
    }

    public function getJobCount(): ?int
    {
        return $this->jobCount;
    }

    public function setJobCount(?int $jobCount): self
    {
        $this->jobCount = $jobCount;

        return $this;
    }

    public function getTotalWalltime(): ?float
    {
        return $this->totalWalltime;
    }

    public function setTotalWalltime(?float $totalWalltime): self
    {
        $this->totalWalltime = $totalWalltime;

        return $this;
    }

    public function getTotalCoreHours(): ?float
    {
        return $this->totalCoreHours;
    }

    public function setTotalCoreHours(?float $totalCoreHours): self
    {
        $this->totalCoreHours = $totalCoreHours;

        return $this;
    }

    public function getShortJobCount(): ?int
    {
        return $this->shortJobCount;
    }

    public function setShortJobCount(?int $shortJobCount): self
    {
        $this->shortJobCount = $shortJobCount;

        return $this;
    }

    public function getPlots()
    {
        return $this->plots;
    }

    public function removePlot($plot): self
    {
        if ($this->plots->contains($plot)) {
            $this->plots->removeElement($plot);
        }

        return $this;
    }

    public function addPlot($plot): self
    {
        $this->plots[$plot->name] = $plot;
        return $this;
    }

    public function getPlot($name)
    {
        if (!isset($this->plots[$name])) {
            throw new \InvalidArgumentException("No plot with name $name in Job cache.");
        }

        return $this->plots[$name];
    }
}
