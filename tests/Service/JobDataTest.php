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

use App\Service\JobData;
use App\Service\ClusterConfiguration;
use App\Entity\Job;
use App\Repository\JobRepository;
use App\Repository\InfluxDBMetricDataRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JobDataTest extends KernelTestCase
{
    private $metricRepo;
    private $jobRepo;
    private $clusterCfg;
    private $projectDir;

    protected function setUp():void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
             ->get('doctrine')
             ->getManager();

        $this->clusterCfg = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $this->jobRepo = $this->entityManager->getRepository(Job::class);
        $this->metricRepo = new InfluxDBMetricDataRepository();
        $this->projectDir = '/Users/jan/dev/web/ClusterCockpit';
    }

    public function testHasDataArchive1()
    {
        $jobData = new JobData(
            $this->metricRepo,
            $this->clusterCfg,
            $this->projectDir);

        /* job_id = 1102756.eadm */
        $job = $this->jobRepo->find('14');
        $this->assertFalse($jobData->hasData($job));
    }

    public function testHasDataArchive2()
    {
        $jobData = new JobData(
            $this->metricRepo,
            $this->clusterCfg,
            $this->projectDir);

        /* job_id = 1105642.eadm */
        $job = $this->jobRepo->find('6662');
        $this->assertTrue($jobData->hasData($job));
    }

    public function testGetDataArchive()
    {
        $jobData = new JobData(
            $this->metricRepo,
            $this->clusterCfg,
            $this->projectDir);

        /* job_id = 1105642.eadm */
        $job = $this->jobRepo->find('6662');
        $data = $jobData->getData($job, ['flops_any', 'mem_bw']);
        var_dump($data);

        $this->assertTrue($data['flops_any'] === true);
    }

    /* public function testHasDataInflux() */
    /* { */
    /*     $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit'); */
    /*     $cfg = $config->getClusterConfig('emmy'); */
    /*     /1* var_dump($cfg); *1/ */
    /*     $this->assertTrue($cfg['cores_per_socket'] === 10); */
    /* } */
}
