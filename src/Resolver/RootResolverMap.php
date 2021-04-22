<?php

namespace App\Resolver;

use Psr\Log\LoggerInterface;

use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Resolver\ResolverMap;

use App\Repository\JobRepository;

class RootResolverMap extends ResolverMap
{

    private $jobRepo;
    private $logger;

    public function __construct(
        JobRepository $jobRepo,
        LoggerInterface $logger
    )
    {
        $this->jobRepo = $jobRepo;
        $this->logger = $logger;
    }

    public function map()
    {
        return [
            'Query' => [
                'jobById' => function($value, Argument $args) {
                    $jobId = $args['jobId'];
                    $job = $this->jobRepo->findJobById("'".$jobId."'", null);
                    if (!$job)
                        return null;

                    return [
                        'id' => $job->id,
                        'jobId' => $job->getJobId(),
                        'userId' => $job->getUser()->getUsername(),
                        'projectId' => "TODO",
                        'clusterId' => $job->getClusterId(),
                        'startTime' => $job->getStartTime(),
                        'duration' => $job->getDuration(),
                        'numNodes' => $job->getNumNodes()
                    ];
                }
            ]
        ];
    }
}
