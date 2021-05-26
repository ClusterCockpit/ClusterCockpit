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

use App\Repository\ClusterRepository;
use App\Entity\Cluster;
use App\Service\ClusterConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ClusterConfigurationTest extends KernelTestCase
{
    private $entityManager;

    protected function setUp():void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
             ->get('doctrine')
             ->getManager();
    }

    public function testReadConfiguration()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getClusterIds();
        $this->assertCount(2, $cfg);
    }

    public function testGetCluster()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getClusterConfiguration('emmy');
        /* var_dump($cfg); */
        $this->assertTrue($cfg['coresPerSocket'] === 10);
    }

    public function testMetricConfig()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getClusterConfiguration('emmy');
        $this->assertTrue($cfg['metricConfig']['flops_any']['name'] === 'flops_any');
    }

    public function testMetricConfigException()
    {
        $this->expectExceptionMessage('No such cluster marta');
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getClusterConfiguration('marta');
    }


    public function testGetConfigurations()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getConfigurations();
        $this->assertTrue($cfg['woody']['metricConfig']['flops_any']['name'] === 'flops_any');
    }

    public function testSingleMetric()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getSingleMetric('emmy');
        $this->assertCount(8, $cfg);
    }

}
