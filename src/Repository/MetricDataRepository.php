<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
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

interface MetricDataRepository
{
    public function hasProfile($job, $metric);
    public function getJobStats($job, $metrics);
    public function getMetricData($job, $metrics);

    /*
     * Provide per-node data for a given time-range.
     * The returned array looks like the following:
     *
     * [
     *   {
     *     "id": "host1",
     *     "metrics:" [
     *       { "name": "flops_any", "data": [1.0, 2.0, 3.0, ...] },
     *       ...
     *     ]
     *   },
     *   { "id": "host2", ... },
     *   ...
     * ]
     *
     *
     * Arguments:
     *   $cluster: parsed cluster.json content
     *   $nodes: list of hostnames
     *   $metrics: list of metric names (if null, return data for all available metrics)
     *   $from and $to: unix timestamps in seconds
     */
    public function getNodeMetrics($cluster, $nodes, $metrics, $from, $to);
}
