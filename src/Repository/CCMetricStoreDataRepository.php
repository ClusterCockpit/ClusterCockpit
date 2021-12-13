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

namespace App\Repository;

use App\Entity\Job;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CCMetricStoreDataRepository implements MetricDataRepository
{
    private $logger;
    private $httpClient;
    private $requestStack;

    // TODO: FIXME: Use a generated or store JWT.
    // Actually, even the same JWT as for the GraphQL-API could be used.
    private $jwt = "eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJ1c2VyIjoiYWRtaW4iLCJyb2xlcyI6WyJST0xFX0FETUlOIiwiUk9MRV9BTkFMWVNUIiwiUk9MRV9VU0VSIl19.d-3_3FZTsadPjDEdsWrrQ7nS0edMAR4zjl-eK7rJU3HziNBfI9PDHDIpJVHTNN5E5SlLGLFXctWyKAkwhXL-Dw";

    // TODO: FIXME: Use a environment variable or something like that.
    private $host = "http://cc-metric-store:8081";

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $client,
        RequestStack $requestStack
    )
    {
        $this->logger = $logger;
        $this->httpClient = $client;
        $this->requestStack = $requestStack;

        $host = getenv('CCMETRICSTORE_URL');
        if ($host !== false)
            $this->host = $host;

        $jwt = getenv('CCMETRICSTORE_TOKEN');
        if ($jwt !== false) {
            $this->jwt = $jwt;
        } else {
            $req = $this->requestStack->getCurrentRequest();
            if ($req != null)
                $this->jwt = $req->getSession()->get('jwt');
        }
    }

    // TODO: FIXME: Implement this. cc-metric-store could be extended by some special
    // endpoint to make this check fast. For now, it is fine to say yes as the job-archive
    // is always asked first.
    public function hasProfile($job, $metrics)
    {
        $job->hasProfile = true;
        return true;
    }

    /*
     * Arguments:
     *   $job: A Job Entity
     *   $metrics: Array of MetricConfig objects (see `cluster.json`)
     * 
     * Returned data (example, PHP-Array, here as formated JSON):
     * {
     *   "nodeStats": {
     *     "host1": {
     *       "flops_any_avg": 42.0,
     *       "flops_any_min": 3.14,
     *       "flops_any_max": 123.0,
     *       "mem_bw_avg": 12345.0,
     *       "mem_bw_min": 1234.0,
     *       "mem_bw_max": 123456.0,
     *       ...
     *     },
     *     "host2": {...},
     *     ...
     *   }
     * }
     */
    public function getJobStats($job, $metrics)
    {
        $request = ['selectors' => [], 'metrics' => []];
        foreach ($metrics as $metric)
            $request['metrics'][] = $metric['name'];
        foreach ($job->getNodeArray() as $node)
            $request['selectors'][] = [$job->getClusterId(), $node];

        $res = $this->httpClient->request(
            'POST',
            $this->host.'/api/'.($job->getStartTime()).'/'.($job->getStartTime() + $job->getDuration()).'/stats',
            [
                'headers' => [ 'Authorization' => 'Bearer '.($this->jwt) ],
                'json' => $request
            ]);

        if ($res->getStatusCode() != 200)
            throw new \Exception("CCMetricStoreDataRepository: HTTP response status code: ".($res->getStatusCode()));

        $res = $res->toArray();

        $stats = [];
        foreach ($job->getNodeArray() as $idx => $node) {
            $nodeStats = [];
            foreach ($res[$idx] as $metric => $metricStats) {
                if (isset($metricStats['error']) || $metricStats['samples'] == 0) {
                    $this->logger->error("CCMetricStoreDataRepository: metric='".$metric."', error='".$metricStats['error']."', samples=".$metricStats['samples']);
                    $nodeStats[$metric.'_avg'] = 0;
                    $nodeStats[$metric.'_min'] = 0;
                    $nodeStats[$metric.'_max'] = 0;
                    continue;
                }

                $nodeStats[$metric.'_avg'] = $metricStats['avg'];
                $nodeStats[$metric.'_min'] = $metricStats['min'];
                $nodeStats[$metric.'_max'] = $metricStats['max'];
            }
            $stats[$node] = $nodeStats;
        }

        $this->logger->debug("CCMetricStoreDataRepository: stats=".json_encode($stats));
        return ['nodeStats' => $stats];
    }

    /*
     * Arguments:
     *   $job: A Job Entity
     *   $metrics: Array of MetricConfig objects (see `cluster.json`)
     * 
     * Returned data (example, PHP-Array, here as formated JSON):
     * {
     *   "flops_any": {
     *     "host1": [1.0, 2.0, 3.0, 4.0, ...]
     *   },
     *   ...
     * }
     */
    public function getMetricData($job, $metrics)
    {
        $request = ['selectors' => [], 'metrics' => []];
        foreach ($metrics as $metric)
            $request['metrics'][] = $metric['name'];
        foreach ($job->getNodeArray() as $node)
            $request['selectors'][] = [$job->getClusterId(), $node];

        $res = $this->httpClient->request(
            'POST',
            $this->host.'/api/'.($job->getStartTime()).'/'.($job->getStartTime() + $job->getDuration()).'/timeseries',
            [
                'headers' => [ 'Authorization' => 'Bearer '.($this->jwt) ],
                'json' => $request
            ]);
        
        if ($res->getStatusCode() != 200)
            throw new \Exception("CCMetricStoreDataRepository: HTTP response status code: ".($res->getStatusCode()));

        $res = $res->toArray();

        $data = [];
        foreach ($job->getNodeArray() as $idx => $node) {
            foreach ($res[$idx] as $metric => $metricData) {
                if (isset($metricData['error'])) {
                    $this->logger->error("CCMetricStoreDataRepository: metric='".$metric."', error='".$metricData['error']."'");
                    continue;
                }

                $data[$metric][$node] = $metricData['data'];
            }
        }
        return $data;
    }

    public function getNodeMetrics($cluster, $nodes, $metrics, $from, $to)
    {
        if ($nodes !== null) {
            $request = ['selectors' => [], 'metrics' => $metrics];
            foreach ($nodes as $node)
                $request['selectors'][] = [$cluster["clusterID"], $node];
    
            $this->logger->info('CC_METRIC_STORE: '.json_encode($request));

            $res = $this->httpClient->request(
                'POST',
                $this->host.'/api/'.$from.'/'.$to.'/timeseries',
                [
                    'headers' => [ 'Authorization' => 'Bearer '.($this->jwt) ],
                    'json' => $request
                ]);

            if ($res->getStatusCode() != 200)
                throw new \Exception("CCMetricStoreDataRepository: HTTP response status code: ".($res->getStatusCode()));
            $res = $res->toArray();

            $data = [];
            foreach ($nodes as $idx => $node) {
                $nodedata = [
                    'id' => $node,
                    'metrics' => []
                ];

                foreach ($res[$idx] as $metric => $metricData) {
                    if (isset($metricData['error'])) {
                        $this->logger->error("CCMetricStoreDataRepository: metric='".$metric."', error='".$metricData['error']."'");
                        continue;
                    }

                    $nodedata['metrics'][] = [
                        'name' => $metric,
                        'data' => $metricData["data"]
                    ];
                }
                $data[] = $nodedata;
            }
            return $data;
        }

        $res = $this->httpClient->request(
            'POST',
            $this->host.'/api/'.$cluster["clusterID"].'/'.($from).'/'.($to).'/all-nodes',
            [
                'headers' => [ 'Authorization' => 'Bearer '.($this->jwt) ],
                'json' => [ 'metrics' => $metrics ]
            ]);

        if ($res->getStatusCode() != 200)
            throw new \Exception("CCMetricStoreDataRepository: HTTP response status code: ".($res->getStatusCode()));

        $res = $res->toArray();
        $nodes = [];
        foreach ($res as $host => $metrics) {
            $nodedata = [
                'id' => $host,
                'metrics' => []
            ];

            foreach ($metrics as $name => $data) {
                if (isset($data["error"]))
                    throw new \Exception($data["error"]);

                $nodedata['metrics'][] = [
                    'name' => $name,
                    'data' => $data["data"]
                ];
            }

            $nodes[] = $nodedata;
        }

        return $nodes;
    }
}
