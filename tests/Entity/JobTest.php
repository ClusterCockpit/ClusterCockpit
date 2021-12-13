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

namespace App\Tests\Service;

use App\Entity\Job;
use App\Repository\JobRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JobTest extends KernelTestCase
{
    protected function setUp():void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
                                      ->get('doctrine')
                                      ->getManager();

        $this->jobRepo = $this->entityManager->getRepository(Job::class);
        $this->projectDir = '/Users/jan/dev/web/ClusterCockpit';
    }

    public function testJobEntity()
    {
        $job = $this->jobRepo->find('9');
        var_dump($job->getNodeArray());
        /* var_dump($job->getNodes('|')); */
        $this->assertTrue($job->numNodes === 16);
    }
}
