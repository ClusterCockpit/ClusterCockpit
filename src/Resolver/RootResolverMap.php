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

use App\Entity\User;
use App\Service\JobData;
use App\Service\JobStats;
use App\Service\Configuration;
use App\Service\ClusterConfiguration;
use App\Repository\JobRepository;
use App\Repository\JobTagRepository;
use App\Repository\MetricDataRepository;

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
    private $scrambleNames;
    private $metricDataRepository;

    public function __construct(
        JobRepository $jobRepo,
        JobData $jobData,
        JobStats $jobStats,
        ClusterConfiguration $clusterCfg,
        JobTagRepository $jobTagRepo,
        LoggerInterface $logger,
        Configuration $configuration,
        Security $security,
        MetricDataRepository $metricRepo,
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
        $this->metricDataRepository = $metricRepo;
        $this->projectDir = $projectDir;
        $this->scrambleNames = filter_var($this->configuration->getValue("general_user_scramble"), FILTER_VALIDATE_BOOLEAN);
    }

    private function jobEntityToArray($job)
    {
        return [
            'id' => $job->id,
            'jobId' => $job->getJobId(),
            'userId' => $this->scrambleNames
                ? User::hideName($job->getUserId())
                : $job->getUserId(),
            'clusterId' => $job->getClusterId(),
            'startTime' => $job->getStartTime(),
            'duration' => $job->getDuration(),
            'numNodes' => $job->getNumNodes(),
            'tags' => $this->getTagsArray($job->tags->getValues()),
            'hasProfile' => $this->jobData->hasData($job),
            'state' => $job->isRunning ? 'running' : 'completed',
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

    /*
     * Every user can access his/her own jobs.
     * Every user can add/remove tags to his/her own jobs.
     * Admins can add/remove tags to every job.
     * If no job is provided, the user has to be admin.
     */
    private function checkIfActionAllowed($job)
    {
        $user = $this->security->getUser();
        if ($user == null)
            throw new Error('Unauthorized (login first)');

        if (in_array('ROLE_ADMIN', $user->getRoles()))
            return true;

        if ($job != null && $job->getUserId() == $user->getUsername())
            return true;

        throw new Error('Unauthorized');
    }

    public function map()
    {
        return [
            // Root
            'Query' => [
                'job' => function($value, Argument $args) {
                    $job = $this->jobRepo->findJobById($args['id']);
                    if (!$job)
                        return null;

                    $this->checkIfActionAllowed($job);
                    return $this->jobEntityToArray($job);
                },

                'jobs' => function($value, Argument $args) {
                    $filter = $args['filter'];
                    $page = $args['page'];
                    $orderBy = $args['order'];

                    $user = $this->security->getUser();
                    if ($user == null)
                        throw new Error('Unauthorized (login first)');

                    // Non-admins shall only see theire own jobs.
                    if (!in_array('ROLE_ADMIN', $user->getRoles()))
                        $filter[] = [ 'userId' => [ 'eq' => $user->getUsername() ] ];

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
                                if ($cluster['filterRanges']['startTime']['to'] == null) {
                                    $cluster['filterRanges']['startTime']['to'] = strtotime("1.1.".(intval(date("Y")) + 1));
                                }
                                continue;
                            }

                            // The following database query can be very expensive, so it is only done if there are
                            // no filterRanges specified in the cluster.json files and it is explicitly requested.
                            $cluster['filterRanges'] = $this->jobRepo->getFilterRanges($cluster['clusterID']);
                        }
                    }

                    return $clusters;
                },

                'tags' => function($value, Argument $args) {
                    return $this->getTagsArray($this->jobTagRepo->getAllTags());
                },

                'jobMetrics' => function($value, Argument $args) {
                    $job = $this->jobRepo->findJobById($args['id']);
                    if (!$job)
                        throw new Error("No job for ID '".$args['id']."'");

                    $this->checkIfActionAllowed($job);

                    $metrics = $args['metrics'];
                    if ($metrics == null) {
                        $clusters = $this->clusterCfg->getClusterConfiguration($job->getClusterId());
                        $metrics = array_map(function ($metric) { return $metric['name']; }, $clusters['metricConfig']);
                    }

                    $data = $this->jobData->getData($job, $metrics);
                    if ($data === false)
                        throw new Error("No profiling data for this job");

                    return $data;
                },

                'jobsStatistics' => function($value, Argument $args, $context, ResolveInfo $info) {
                    $filter = $args['filter'];
                    $user = $this->security->getUser();
                    if ($user == null)
                        throw new Error('Unauthorized (login first)');

                    // Non-admins shall only see theire own jobs.
                    if (!in_array('ROLE_ADMIN', $user->getRoles()))
                        $filter[] = [ 'userId' => [ 'eq' => $user->getUsername() ] ];

                    try {
                        return $this->jobRepo->findStatistics($filter, $this->clusterCfg->getConfigurations(),
                            $args['groupBy'] ?? null,
                            array_key_exists('histWalltime', $info->getFieldSelection()),
                            array_key_exists('histNumNodes', $info->getFieldSelection()));
                    } catch (\Throwable $e) {
                        throw new Error($e->getMessage());
                    }
                },

                'jobsFootprints' => function($value, Argument $args) {
                    try {
                        $jobs = $this->jobRepo->findFilteredJobs(false, $args['filter'], null);
                        if (count($jobs) > RootResolverMap::ANALYSIS_MAX_JOBS)
                            throw new Error("too many jobs matched (".count($jobs).", max: ".RootResolverMap::ANALYSIS_MAX_JOBS.")");

                        return $this->jobStats->getFootprints($jobs, $args['filter'], $args['metrics']);
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

                'nodeMetrics' => function($value, Argument $args) {
                    $cluster = $this->clusterCfg->getClusterConfiguration($args['cluster']);
                    $nodes = $args['nodes'];
                    $metrics = $args['metrics'];
                    $data = $this->metricDataRepository->getNodeMetrics($cluster, $nodes, $metrics, $args['from'], $args['to']);
                    if ($data === false)
                        throw new Error("The configured MetricDataRepository does not support this View/Query");
                    return $data;
                }
            ],

            'Mutation' => [
                'createTag' => function($value, Argument $args) {
                    $user = $this->security->getUser();
                    if ($user == null)
                        throw new Error('Cannot create tags without beeing logged in');

                    $tagType = $args['type'];
                    $tagName = $args['name'];
                    $tag = $this->jobTagRepo->createTag($tagType, $tagName);
                    return [
                        'id' => $tag->getId(), 'tagType' => $tag->getType(), 'tagName' => $tag->getName()
                    ];
                },

                // 'deleteTag' => function($value, Argument $args) {
                //     $this->checkIfActionAllowed(null);
                //     $tagId = $args['id'];
                //     $this->jobTagRepo->deleteTag($tagId);
                //     return $tagId;
                // },

                'addTagsToJob' => function($value, Argument $args) {
                    $tagIds = $args['tagIds'];
                    $job = $this->jobRepo->findJobById($args['job']);
                    if ($job == null)
                        throw new Error('No jobs found');

                    $this->checkIfActionAllowed($job);

                    $tags = $this->jobTagRepo->findTagsByIds($tagIds);
                    foreach ($tags as $tag) {
                        $job->addTag($tag);
                    }

                    $this->jobData->getArchive()->updateTags($job);
                    $this->jobRepo->persistJob($job);
                    return $this->getTagsArray($job->tags->getValues());
                },

                'removeTagsFromJob' => function($value, Argument $args) {
                    $tagIds = $args['tagIds'];
                    $job = $this->jobRepo->findJobById($args['job']);
                    if ($job == null)
                        throw new Error('No jobs found');

                    $this->checkIfActionAllowed($job);

                    $tags = $job->getTags()->toArray();
                    foreach ($tags as $tag) {
                        if (in_array(strval($tag->getId()), $tagIds)) {
                            $job->removeTag($tag);
                        }
                    }

                    $this->jobData->getArchive()->updateTags($job);
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
