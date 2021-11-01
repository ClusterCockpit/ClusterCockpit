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

use Symfony\Component\Filesystem\Filesystem;

use App\Entity\Job;
use App\Service\ClusterConfiguration;

class JobArchive {

    private $_filesystem;
    private $_rootdir;

    public function __construct(
        FileSystem $filesystem,
        $projectDir
    )
    {
        $this->_filesystem = $filesystem;
        $this->_rootdir = "$projectDir/var/job-archive";
    }

    public function getJobDirectory($job)
    {
        $jobId = intval(explode('.', $job->getJobId())[0]);
        $lvl1 = intdiv($jobId, 1000);
        $lvl2 = $jobId % 1000;
        $path = sprintf('%s/%s/%d/%03d/',
            $this->_rootdir, $job->getClusterId(), $lvl1, $lvl2);
        return $path;
    }

    public function isArchived($job)
    {
        return file_exists($this->getJobDirectory($job).'/data.json');
    }

    public function getData($job)
    {
        $path = $this->getJobDirectory($job).'/data.json';
        $data = file_get_contents($path);
        return json_decode($data, true);
    }

    public function getMeta($job)
    {
        $path = $this->getJobDirectory($job).'/meta.json';
        $data = file_get_contents($path);
        return json_decode($data, true);
    }

    /*
     * This function archives the job as JSON files data.json and meta.json.
     * $jobData must contain the jobs metrics data in the format returned by JobData::getData.
     */
    public function archiveJob($job, $jobData, $destdir)
    {
        if ($destdir == null)
            $destdir = $this->getJobDirectory($job);

        $this->_filesystem->mkdir($destdir);

        $jsonMeta = [
            'job_id' => strval($job->getJobId()),
            'user_id' => $job->getUserId(),
            'project_id' => $job->getProjectId(),
            'cluster_id' => $job->getClusterId(),
            'num_nodes' => $job->getNumNodes(),
            'nodes' => $job->getNodeArray(),
            'tags' => $job->getTagsArray(),
            'start_time' => $job->getStartTime(),
            'stop_time' => $job->getStartTime() + $job->getDuration(),
            'duration' => $job->getDuration(),
            'statistics' => [],
        ];
        $jsonData = [];

        foreach ($jobData as $data) {
            $unit = $data['metric']['unit'];
            $series = $data['metric']['series'];
            $min = $series[0]['statistics']['min'];
            $max = $series[0]['statistics']['max'];
            $avg = $series[0]['statistics']['avg'];

            for ($i = 1; $i < count($series); $i++) {
                $stats = $series[$i]['statistics'];
                $min = min($min, $stats['min']);
                $max = max($max, $stats['max']);
                $avg += $stats['avg'];
            }

            $avg /= count($series);
            $jsonMeta['statistics'][$data['name']] = [
                'unit' => $unit,
                'min' => round($min, 3),
                'max' => round($max, 3),
                'avg' => round($avg, 3)
            ];

            $jsonData[$data['name']] = $data['metric'];
        }

        file_put_contents($destdir.'/meta.json', json_encode($jsonMeta));
        file_put_contents($destdir.'/data.json', json_encode($jsonData));
    }
}