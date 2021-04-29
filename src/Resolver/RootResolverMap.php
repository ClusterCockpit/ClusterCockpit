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

namespace App\Resolver;

use Psr\Log\LoggerInterface;

use GraphQL\Error\Error;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Resolver\ResolverMap;

use App\Repository\ClusterRepository;
use App\Repository\JobRepository;

class RootResolverMap extends ResolverMap
{
    private $jobRepo;
    private $clusterRepo;
    private $logger;
    private $projectDir;

    public function __construct(
        JobRepository $jobRepo,
        ClusterRepository $clusterRepo,
        LoggerInterface $logger,
        $projectDir
    )
    {
        $this->jobRepo = $jobRepo;
        $this->clusterRepo = $clusterRepo;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    private function jobEntityToArray($job)
    {
        return [
            'id' => $job->id,
            'jobId' => $job->getJobId(),
            'userId' => $job->getUser()->getUsername(),
            'clusterId' => $job->getClusterId(),
            'startTime' => $job->getStartTime(),
            'duration' => $job->getDuration(),
            'numNodes' => $job->getNumNodes(),

            // TODO:
            'projectId' => "TODO",
            'hasProfile' => true,
            'tags' => []
        ];
    }

    private function readMetircData($jobId, $clusterId)
    {
        $jobId = intval(explode('.', $jobId)[0]);
        $lvl1 = intdiv($jobId, 1000);
        $lvl2 = $jobId % 1000;

        $path = sprintf('%s/job-data/%s/%d/%03d/data.json',
            $this->projectDir, $clusterId, $lvl1, $lvl2);


        $file = @file_get_contents($path);
        if ($file === false)
            return false;

        return json_decode($file);
    }

    public function map()
    {
        return [
            // Root
            'Query' => [
                'jobById' => function($value, Argument $args) {
                    $jobId = $args['jobId'];
                    $job = $this->jobRepo->findJobById($jobId, null);
                    if (!$job)
                        return null;

                    return $this->jobEntityToArray($job);
                },

                'jobs' => function($value, Argument $args) {
                    $filter = $args['filter'];
                    $page = $args['page'];
                    $orderBy = $args['order'];

                    $jobs = $this->jobRepo->findFilteredJobs($page, $filter, $orderBy);
                    $count = $this->jobRepo->countJobs($filter, $orderBy);

                    $items = [];
                    foreach ($jobs as $job) {
                        $items[] = $this->jobEntityToArray($job);
                    }

                    return [
                        'items' => $items,
                        'count' => $count
                    ];
                },

                'clusters' => function($value, Argument $args) {
                    $clusters = $this->clusterRepo->findAllConfig();
                    return array_map(function($cluster) {
                        return [
                            // TODO: Other fields
                            'clusterID' => $cluster->id,
                            'metricConfig' => $cluster->getMetricLists()['list']
                        ];
                    }, $clusters);
                },

                'jobMetrics' => function($value, Argument $args) {
                    $jobId = $args['jobId'];
                    $clusterId = $args['clusterId'];
                    $metrics = $args['metrics'];
                    $data = $this->readMetircData($jobId, $clusterId);
                    if ($data === false)
                        return [];

                    $res = [];
                    foreach ($data as $metricName => $metricData) {
                        if ($metrics && !in_array($metricName, $metrics))
                            continue;

                        $res[] = [
                            'name' => $metricName,
                            'metric' => $metricData
                        ];
                    }
                    return $res;
                }
            ],

            // use RFC3339 to communicate and UNIX-timestamps internally
            'Time' => [
                self::SERIALIZE => function ($value) {
                    if (!is_int($value))
                        throw new Error('Cannot serialize to Time scalar: '.gettype($value));

                    $dt = new \DateTime();
                    $dt->setTimestamp($value);
                    return $dt->format(\DateTime::RFC3339);
                },
                self::PARSE_VALUE => function ($value) { return strtotime($value); },
                self::PARSE_LITERAL => function ($valueNode) { return strtotime($valueNode->value); }
            ]
        ];
    }
}
