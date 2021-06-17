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

use App\Entity\Job;

class JobStats
{
    private $_rootdir;

    public function __construct(
        $projectDir
    )
    {
        $this->_rootdir = "$projectDir/var/job-archive";
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

    public function getStatsForMetrics($jobs, $metrics)
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
                    $res[$idx][] = $data['statistics'][$metric];
                }
            } else {
                foreach ($metrics as $idx => $metric) {
                    $res[$idx][] = null;
                }
            }
        }

        return $res;
    }
}
