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

namespace App\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Psr\Log\LoggerInterface;
use App\Repository\JobRepository;
use App\Entity\Job;
use App\Entity\BatchJob;

final class BatchJobItemDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
     private JobRepository $_repository;
     private $_logger;

     public function __construct(
         LoggerInterface $logger,
         JobRepository $repository
     )
    {
        $this->_repository = $repository;
        $this->_logger = $logger;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return BatchJob::class === $resourceClass;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?BatchJob
    {
        $job;

        if (!is_string($id)) {
            throw new InvalidIdentifierException('Invalid id key type.');
        }

        $suffix = '.eadm';
        $conditions = explode("-", $id);
        $numConditions = count($conditions);

        if ( $numConditions == 1 ) {
            $jobId = $conditions[0] . $suffix;
            $job = $this->_repository->findBatchJob($jobId, null, null);
        } else if ( $numConditions == 2 ) {
            $jobId = $conditions[0] . $suffix;
            $job = $this->_repository->findBatchJob($conditions[1], $jobId, null);
        } else if ( $numConditions == 3 ) {
            $jobId = $conditions[0] . $suffix;
            $job = $this->_repository->findBatchJob($conditions[1], $jobId, $conditions[2]);
        } else {
            throw new InvalidIdentifierException('Invalid id key format.');
        }

        if ( is_null($job) ) {
            return null;
        }

        $batchJob = new BatchJob();
        $batchJob->jobId = $id;
        $batchJob->stopTime = $job->startTime + $job->duration;
        $batchJob->job = $job;

        return $batchJob;
    }
}
