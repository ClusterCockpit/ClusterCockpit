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
        $cfg = $config->getClusterConfig('emmy');
        /* var_dump($cfg); */
        $this->assertTrue($cfg['cores_per_socket'] === 10);
    }
    public function testMetricConfig()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getClusterConfig('emmy');
        $this->assertTrue($cfg['metric_config'][2]['name'] === 'flops_any');
    }
    public function testGetConfigurations()
    {
        $config = new ClusterConfiguration('/Users/jan/dev/web/ClusterCockpit');
        $cfg = $config->getConfigurations();
        var_dump($cfg);
        $this->assertTrue($cfg['metric_config'][2]['name'] === 'flops_any');
    }
    public function testCompareConfiguration()
    {
        $clusters = $this->entityManager
                         ->getRepository(Cluster::class)
                         ->findAllConfig();
        $cfg = array_map(function($cluster) {
            return [
                'clusterID' => $cluster->name,
                'flopRateScalar' => $cluster->flopRateScalar,
                'flopRateSimd' => $cluster->flopRateSimd,
                'memoryBandwidth' => $cluster->memoryBandwidth,
                'metricConfig' => $cluster->getMetricLists()['list'],

                // TODO: DB-Schemas differ
                'processorType' => null,
                'socketsPerNode' => 1,
                'coresPerSocket' => $cluster->coresPerNode,
                'threadsPerCore' => 1
            ];
        }, $clusters);

        var_dump($cfg);
        $this->assertTrue($cfg['metric_config'][2]['name'] === 'flops_any');
    }

}
