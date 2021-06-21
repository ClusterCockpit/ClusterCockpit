<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2021 Jan Eitzinger
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

use App\Service\JobData;
use App\Entity\Job;

class JobStats
{
    private $_rootdir;
    private $_jobData;

    public function __construct(
        $projectDir,
        JobData $jobData
    )
    {
        $this->_rootdir = "$projectDir/var/job-archive";
        $this->_jobData = $jobData;
    }

    private function _getJobMetaPath($jobId, $clusterId)
    {
        $jobId = intval(explode('.', $jobId)[0]);
        $lvl1 = intdiv($jobId, 1000);
        $lvl2 = $jobId % 1000;
        $path = sprintf('%s/%s/%d/%03d/meta.json',
            $this->_rootdir, $clusterId, $lvl1, $lvl2);
        return $path;
    }

    public function getAverages($jobs, $metrics)
    {
        $res = [];

        foreach ($metrics as $idx => $metric) {
            $res[$idx] = [];
        }

        foreach ($jobs as $job) {
            $filepath = $this->_getJobMetaPath($job->getJobId(), $job->getClusterId());
            if (file_exists($filepath)) {
                $data = json_decode(file_get_contents($filepath), true);
                foreach ($metrics as $idx => $metric) {
                    if (isset($data['statistics'][$metric]))
                        $res[$idx][] = $data['statistics'][$metric]['avg'];
                    else
                        $res[$idx][] = null;
                }
            } else {
                foreach ($metrics as $idx => $metric) {
                    $res[$idx][] = null;
                }
            }
        }

        return $res;
    }

    public function rooflineHeatmap($jobs, $rows, $cols, $minX, $minY, $maxX, $maxY)
    {
        $tiles = [];
        for ($i = 0; $i < $rows; $i++) {
            $tiles[$i] = [];
            for ($j = 0; $j < $cols; $j++) {
                $tiles[$i][$j] = 0;
            }
        }

        // All jobs should be from the same cluster!
        $minX = log10($minX);
        $minY = log10($minY);
        $maxX = log10($maxX);
        $maxY = log10($maxY);

        foreach ($jobs as $job) {
            $data = $this->_jobData->getData($job, ['flops_any', 'mem_bw']);
            if ($data === false)
                continue;

            $flopsAny = null;
            $memBw = null;
            foreach ($data as $entry) {
                if ($entry['name'] == 'flops_any')
                    $flopsAny = $entry['metric'];
                if ($entry['name'] == 'mem_bw')
                    $memBw = $entry['metric'];
            }

            for ($n = 0; $n < $job->getNumNodes(); $n++) {
                $flopsAnyData = $flopsAny->series[$n]->data;
                $memBwData = $memBw->series[$n]->data;
                $count = count($flopsAnyData);
                for ($i = 0; $i < $count; $i++) {
                    $f = $flopsAnyData[$i];
                    $m = $memBwData[$i];
                    if ($m <= 0 || $f == null || $m == null)
                        continue;

                    $x = log10($f / $m);
                    $y = log10($f);
                    if ($x < $minX || $x > $maxX  || $y < $minY || $y > $maxY)
                        continue;

                    $x = floor((($x - $minX) / ($maxX - $minX)) * $cols);
                    $y = floor((($y - $minY) / ($maxY - $minY)) * $rows);
                    if ($y >= $rows || $x >= $cols)
                        throw new Exception("Error Processing Request");

                    $tiles[$y][$x] += 1;
                }
            }
        }

        return $tiles;
    }
}
