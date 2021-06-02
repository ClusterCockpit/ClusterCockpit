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
use App\Service\Configuration;
use App\Service\ClusterConfiguration;
use App\Repository\JobRepository;
use App\Repository\JobTagRepository;

class RootResolverMap extends ResolverMap
{
    private $jobRepo;
    private $jobData;
    private $clusterCfg;
    private $jobTagRepo;
    private $logger;
    private $configuration;
    private $security;
    private $projectDir;

    public function __construct(
        JobRepository $jobRepo,
        JobData $jobData,
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

                'clusters' => function($value, Argument $args, $context, ResolveInfo $info) {
                    $clusters = $this->clusterCfg->getConfigurations();

                    // Getting the filter ranges is expensive, so only fetch them if requested.
                    if (array_key_exists('filterRanges', $info->getFieldSelection())) {
                        foreach ($clusters as &$cluster) {
                            $cluster['filterRanges'] = $this->jobRepo->getFilterRanges($cluster['clusterID']);
                        }
                    }

                    return $clusters;
                },

                'filterRanges' => function() {
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
                    return $this->jobRepo->findFilteredStatistics($args['filter']);
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
