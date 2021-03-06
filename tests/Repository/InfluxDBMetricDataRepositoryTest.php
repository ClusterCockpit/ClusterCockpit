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

namespace App\Repository\Service;

use App\Repository\InfluxDBMetricDataRepository;
use App\Entity\Job;
use App\Service\ClusterConfiguration;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/* 579945 */
/* '523286' */

class InfluxDBMetricDataRepositoryTest extends KernelTestCase
{
    private $entityManager;
    private $clusterConfiguration;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
             ->get('doctrine')
             ->getManager();

        $this->clusterConfiguration = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
    }

    public function testHasProfile()
    {
        $job = $this->entityManager
                    ->getRepository(Job::class)
                    /* ->find('523286'); */
                    ->find('579945');

        $metricData = new InfluxDBMetricDataRepository();
        $returnValue = $metricData->hasProfile($job,
            $this->clusterConfiguration->getSingleMetric($job->getClusterId()));

        $this->assertTrue($returnValue);
    }

    public function testGetJobStats()
    {
        $job = $this->entityManager
                    ->getRepository(Job::class)
                    /* ->find('523286'); */
                    /* ->find('579945'); */
                    ->find('1249812');
        $metrics =
            $this->clusterConfiguration->getMetricConfiguration($job->getClusterId(), ['flops_any','mem_bw']);

        $metricData = new InfluxDBMetricDataRepository();
        $returnValue = $metricData->getJobStats($job, $metrics);
        var_dump($returnValue);
        $this->assertCount(23, $returnValue['nodeStats']);
    }

    public function testGetMetricData()
    {
        $job = $this->entityManager
                    ->getRepository(Job::class)
                    /* ->find('523286'); */
                    ->find('579945');
        $metrics =
            $this->clusterConfiguration->getMetricConfiguration($job->getClusterId(), ['flops_any','mem_bw','rapl_power','clock']);

        $metricData = new InfluxDBMetricDataRepository();
        $returnValue = $metricData->getMetricData($job, $metrics);
        /* var_dump($returnValue); */
        $this->assertCount(5, $returnValue);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
