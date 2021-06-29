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

namespace App\DataPersister;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Psr\Log\LoggerInterface;
use App\Repository\JobRepository;
use App\Entity\BatchJob;

final class BatchJobDataPersister implements ContextAwareDataPersisterInterface
{
    private $_em;
    private JobRepository $_repository;
    private $_logger;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        JobRepository $repository
    )
    {
        $this->_em = $em;
        $this->_repository = $repository;
        $this->_logger = $logger;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof BatchJob;
    }

    public function persist($data, array $context = [])
    {
	    $this->_logger->info("DataPersister persist: {$data->jobId}");

        $id = $data->jobId;
        $stopTime = $data->stopTime;

        if (!is_string($id)) {
            throw new InvalidIdentifierException('Invalid id key type.');
        }

        $conditions = explode("-", $id);
        $numConditions = count($conditions);

        if ( $numConditions == 1 ) {
            $job = $this->_repository->findBatchJob($conditions[0], null, null);
        } else if ( $numConditions == 2 ) {
            $job = $this->_repository->findBatchJob($conditions[1], $conditions[0], null);
        } else if ( $numConditions == 3 ) {
            $job = $this->_repository->findBatchJob($conditions[1], $conditions[0], $conditions[2]);
        } else {
            throw new InvalidIdentifierException('Invalid job id key format.');
        }

        if ( is_null($job) ) {
            throw new HttpException(400, "No such job: ".$jobId);
        }

        if ( $stopTime < $job->startTime  ) {
            throw new HttpException(400, "Stop time earlier than start time");
        }

        if ( $job->isRunning ) {
            throw new HttpException(400, "Job already finished");
        }

        $job->duration = $stopTime - $job->startTime;
        $job->isRunning = false;

        $this->_em->persist($job);
        $this->_em->flush();
        $data->job = $job;

        return $data;
    }

    public function remove($data, array $context = [])
    {
	    $this->_logger->info("DataPersister remove: {$data->jobId}");
        // call your persistence layer to delete $data
    }
}
