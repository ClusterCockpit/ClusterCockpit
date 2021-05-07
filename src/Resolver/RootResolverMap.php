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

use App\Service\JobData;
use App\Repository\ClusterRepository;
use App\Repository\JobRepository;
use App\Repository\JobTagRepository;

class RootResolverMap extends ResolverMap
{
    private $jobData;
    private $jobRepo;
    private $jobTagRepo;
    private $clusterRepo;
    private $logger;
    private $projectDir;

    public function __construct(
        JobRepository $jobRepo,
        JobTagRepository $jobTagRepo,
        ClusterRepository $clusterRepo,
        JobData $jobData,
        LoggerInterface $logger,
        $projectDir
    )
    {
        $this->jobRepo = $jobRepo;
        $this->jobTagRepo = $jobTagRepo;
        $this->clusterRepo = $clusterRepo;
        $this->jobData = $jobData;
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
            'tags' => array_map(function ($tag) {
                return [
                    'id' => $tag->getId(),
                    'tagType' => $tag->getType(),
                    'tagName' => $tag->getName()
                ];
            }, $job->tags->getValues()),

            // TODO: DB-Schemas differ
            'hasProfile' => $this->jobData->hasData($job),
            'projectId' => $job->getProjectId()
        ];
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
                            'clusterID' => $cluster->name,
                            'flopRateScalar' => $cluster->flopRateScalar,
                            'flopRateSimd' => $cluster->flopRateSimd,
                            'memoryBandwidth' => $cluster->memoryBandwidth,
                            'metricConfig' => $cluster->getMetricLists()['list'],

                            // TODO: DB-Schemas differ
                            'processorType' => null,
                            'socketsPerNode' => 1,
                            'coresPerSocket' => $cluster->coresPerNode,
                            'threadsPerCore' => 1
                        ];
                    }, $clusters);
                },

                'tags' => function($value, Argument $args) {
                    return array_map(function ($tag) {
                        return [
                            'id' => $tag->getId(),
                            'tagType' => $tag->getType(),
                            'tagName' => $tag->getName()
                        ];
                    }, $this->jobTagRepo->getAllTags());
                },

                'jobMetrics' => function($value, Argument $args) {
                    $jobId = $args['jobId'];
                    $clusterId = $args['clusterId'];
                    $metrics = $args['metrics'];

                    $job = $this->jobRepo->findBatchJob($jobId, $clusterId, null);

                    if ($job === false)
                        throw new Error("No job for '$jobId' (on '$clusterId')");

                    $data = $this->jobData->getData($job, $metrics);

                    if ($data === false)
                        throw new Error("No profile data for '$jobId' (on '$clusterId')");

                    return $data;
                },

                'jobsStatistics' => function($value, Argument $args) {
                    return $this->jobRepo->findFilteredStatistics($args['filter']);
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
