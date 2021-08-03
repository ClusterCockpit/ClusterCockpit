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
use Symfony\Component\Security\Core\Security;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Resolver\ResolverMap;

use App\Service\JobData;
use App\Service\JobStats;
use App\Service\Configuration;
use App\Service\ClusterConfiguration;
use App\Repository\JobRepository;
use App\Repository\JobTagRepository;

class RootResolverMap extends ResolverMap
{
    const ANALYSIS_MAX_JOBS = 500;

    private $jobRepo;
    private $jobData;
    private $jobStats;
    private $clusterCfg;
    private $jobTagRepo;
    private $logger;
    private $configuration;
    private $security;
    private $projectDir;

    public function __construct(
        JobRepository $jobRepo,
        JobData $jobData,
        JobStats $jobStats,
        ClusterConfiguration $clusterCfg,
        JobTagRepository $jobTagRepo,
        LoggerInterface $logger,
        Configuration $configuration,
        Security $security,
        $projectDir
    )
    {
        $this->jobRepo = $jobRepo;
        $this->jobData = $jobData;
        $this->jobStats = $jobStats;
        $this->clusterCfg = $clusterCfg;
        $this->jobTagRepo = $jobTagRepo;
        $this->logger = $logger;
        $this->configuration = $configuration;
        $this->security = $security;
        $this->projectDir = $projectDir;
    }

    private function jobEntityToArray($job)
    {
        return [
            'id' => $job->id,
            'jobId' => $job->getJobId(),
            'userId' => $job->getUserId(),
            'clusterId' => $job->getClusterId(),
            'startTime' => $job->getStartTime(),
            'duration' => $job->getDuration(),
            'numNodes' => $job->getNumNodes(),
            'tags' => $this->getTagsArray($job->tags->getValues()),
            'hasProfile' => $this->jobData->hasData($job),
            'projectId' => $job->getProjectId(),

            'loadAvg' => $job->loadAvg,
            'memUsedMax' => $job->memUsedMax,
            'flopsAnyAvg' => $job->flopsAnyAvg,
            'memBwAvg' => $job->memBwAvg,
            'netBwAvg' => $job->netBwAvg,
            'fileBwAvg' => $job->fileBwAvg,
        ];
    }

    private function getTagsArray($tags)
    {
        return array_map(function ($tag) {
            return [
                'id' => $tag->getId(),
                'tagType' => $tag->getType(),
                'tagName' => $tag->getName()
            ];
        }, $tags);
    }

    public function map()
    {
        return [
            // Root
            'Query' => [
                'jobById' => function($value, Argument $args) {
                    $job = $this->jobRepo->findJobById($args['id']);
                    if (!$job)
                        return null;

                    return $this->jobEntityToArray($job);
                },

                'jobs' => function($value, Argument $args) {
                    $filter = $args['filter'];
                    $page = $args['page'];
                    $orderBy = $args['order'];

                    try {
                        $jobs = $this->jobRepo->findFilteredJobs($page, $filter, $orderBy);
                        $count = $this->jobRepo->countJobs($filter, $orderBy);

                        $items = [];
                        foreach ($jobs as $job) {
                            $items[] = $this->jobEntityToArray($job);
                        }
                    } catch (\Throwable $e) {
                        throw new Error($e->getMessage());
                    }

                    return [
                        'items' => $items,
                        'count' => $count
                    ];
                },

                'clusters' => function($value, Argument $args, $context, ResolveInfo $info) {
                    $clusters = $this->clusterCfg->getConfigurations();
                    if ($clusters == null || count($clusters) == 0)
                        throw new Error("No clusters configured");

                    if (array_key_exists('filterRanges', $info->getFieldSelection())) {
                        foreach ($clusters as &$cluster) {
                            if (isset($cluster['filterRanges'])) {
                                // This startTime of the last job on that cluster is a special
                                // case, let's simply use now as the upper bound.
                                if ($cluster['filterRanges']['startTime']['to'] == null)
                                    $cluster['filterRanges']['startTime']['to'] = time();
                                continue;
                            }

                            // The following database query can be very expensive, so it is only done if there are
                            // no filterRanges specified in the cluster.json files and it is explicitly requested.
                            $cluster['filterRanges'] = $this->jobRepo->getFilterRanges($cluster['clusterID']);
                        }
                    }

                    return $clusters;
                },

                'filterRanges' => function() {
                    // TODO: Use filterRanges from cluster.json files?
                    // This query is not used by the frontend anymore,
                    // so it could also be removed.
                    return $this->jobRepo->getFilterRanges(null);
                },

                'tags' => function($value, Argument $args) {
                    return $this->getTagsArray($this->jobTagRepo->getAllTags());
                },

                'jobMetrics' => function($value, Argument $args) {
                    $jobId = $args['jobId'];
                    $clusterId = $args['clusterId'];
                    $metrics = $args['metrics'];
                    if ($metrics == null) {
                        $clusters = $this->clusterCfg->getClusterConfiguration($clusterId);
                        $metrics = array_map(function ($metric) { return $metric['name']; }, $clusters['metricConfig']);
                    }

                    $job = $this->jobRepo->findBatchJob($jobId, $clusterId, null);
                    if ($job === false) {
                        throw new Error("No job for '$jobId' (on '$clusterId')");
                    }

                    $data = $this->jobData->getData($job, $metrics);
                    if ($data === false) {
                        throw new Error("No profiling data for this job");
                    }

                    return $data;
                },

                'jobsStatistics' => function($value, Argument $args) {
                    try {
                        return $this->jobRepo->findFilteredStatistics(
                            $args['filter'], $this->clusterCfg);
                    } catch (\Throwable $e) {
                        throw new Error($e->getMessage());
                    }
                },

                'jobMetricAverages' => function($value, Argument $args) {
                    try {
                        $jobs = $this->jobRepo->findFilteredJobs(false, $args['filter'], null);
                        if (count($jobs) > RootResolverMap::ANALYSIS_MAX_JOBS)
                            throw new Error("too many jobs matched (".count($jobs).", max: ".RootResolverMap::ANALYSIS_MAX_JOBS.")");

                        return $this->jobStats->getAverages($jobs, $args['filter'], $args['metrics']);
                    } catch (\Throwable $e) {
                        throw new Error($e->getMessage());
                    }
                },

                'rooflineHeatmap' => function($value, Argument $args) {
                    try {
                        $jobs = $this->jobRepo->findFilteredJobs(false, $args['filter'], null);
                        if (count($jobs) > RootResolverMap::ANALYSIS_MAX_JOBS)
                            throw new Error("too many jobs matched (".count($jobs).", max: ".RootResolverMap::ANALYSIS_MAX_JOBS.")");

                        return $this->jobStats->rooflineHeatmap($jobs, $args['filter'], $args['rows'], $args['cols'],
                            $args['minX'], $args['minY'], $args['maxX'], $args['maxY']);
                    } catch (\Throwable $e) {
                        throw new Error($e->getMessage());
                    }
                },

                'userStats' => function($value, Argument $args) {
                    $users = $this->jobRepo->statUsers(
                        $args['startTime'], $args['stopTime'], $args['clusterId'],
                        $this->clusterCfg->getConfigurations());
                    return $users;
                }
            ],

            'Mutation' => [
                'createTag' => function($value, Argument $args) {
                    $tagType = $args['type'];
                    $tagName = $args['name'];
                    $tag = $this->jobTagRepo->createTag($tagType, $tagName);
                    return [
                        'id' => $tag->getId(), 'tagType' => $tag->getType(), 'tagName' => $tag->getName()
                    ];
                },

                'deleteTag' => function($value, Argument $args) {
                    $tagId = $args['id'];
                    $this->jobTagRepo->deleteTag($tagId);
                    return $tagId;
                },

                'addTagsToJob' => function($value, Argument $args) {
                    $tagIds = $args['tagIds'];
                    $job = $this->jobRepo->findJobById($args['job']);

                    $tags = $this->jobTagRepo->findTagsByIds($tagIds);
                    foreach ($tags as $tag) {
                        $job->addTag($tag);
                    }

                    $this->jobRepo->persistJob($job);
                    return $this->getTagsArray($job->tags->getValues());
                },

                'removeTagsFromJob' => function($value, Argument $args) {
                    $tagIds = $args['tagIds'];
                    $job = $this->jobRepo->findJobById($args['job']);

                    $tags = $job->getTags()->toArray();
                    foreach ($tags as $tag) {
                        if (in_array(strval($tag->getId()), $tagIds)) {
                            $job->removeTag($tag);
                        }
                    }

                    $this->jobRepo->persistJob($job);
                    return $this->getTagsArray($job->tags->getValues());
                },

                'updateConfiguration' => function($value, Argument $args) {
                    $user = $this->security->getUser();
                    if ($user == null)
                        throw new Error('Cannot change configuration without beeing logged in');

                    $username = $user->getUsername();
                    $ok = $this->configuration->setValue($username, $args['name'], $args['value']);
                    if ($ok === false)
                        throw new Error('Invalid configuration');

                    return null;
                }
            ],

            // use RFC3339 to communicate and UNIX-timestamps internally
            'Time' => [
                self::SERIALIZE => function ($value) {
                    // If a string is served by another resolver, it better
                    // allready be formated as RFC3339!
                    if (is_string($value))
                        return $value;

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
