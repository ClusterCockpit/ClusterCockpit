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

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
attributes: [
    'normalization_context' => ['groups' => ['read']],
    'denormalization_context' => ['groups' => ['write']],
],
    collectionOperations: [],
    itemOperations: [
        'get',
        'patch' => [
            'path' => '/jobs/stop_job/{jobId}',
            'requirements' => ['id' => '\s+'],
        ],
    ],
)]
class BatchJob
{
    /**
     *  The jobId of this job.
     *
     *  The id can be either the batch system <jobId>, or <clusterId>-<jobID>, or
     *  <clusterId>-<jobId>-<startTime> .
     *
     * @ApiProperty(identifier=true)
     * @Groups({"read"})
     * @Assert\NotBlank
     */
    public string $jobId;

    /**
     * When the job stopped in Unix epoch time seconds.
     *
     * @Groups({"read","write"})
     * @Assert\Positive
     * @Assert\NotBlank
     *
     */
    public int $stopTime;

    /**
     * The job object
     *
     * @Groups({"read"})
     */
    public $job;
}
