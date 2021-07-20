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

namespace App\Service;

class ClusterConfiguration
{
    private $projectDir;
    private $_config;

    public function __construct(
        $projectDir
    )
    {
        $this->_config = [];
        $rootdir = "$projectDir/var/job-archive";
        $dh =  opendir($rootdir);

        while (false !== ($entry = readdir($dh))) {
            if ($entry != "." && $entry != "..") {
                if (file_exists("$rootdir/$entry/cluster.json")){
                    $str = file_get_contents("$rootdir/$entry/cluster.json");
                    $this->_config[$entry] = json_decode($str, true);

                    $metricConfig = [];

                    foreach ($this->_config[$entry]['metricConfig'] as $metric) {
                        $cfg[$metric['name']] = $metric;
                    }

                    $this->_config[$entry]['metricConfig'] = $cfg;
                }
            }
        }

        closedir($dh);
    }

    public function getMetricConfiguration($clusterId, $metrics)
    {
        $cfg = [];

        foreach ($metrics as $metric) {
            $cfg[$metric] = $this->_config[$clusterId]['metricConfig'][$metric];
        }

        return $cfg;
    }

    public function getClusterIds()
    {
        return array_keys($this->_config);
    }

    public function getSingleMetric($clusterId)
    {
        if ( array_key_exists($clusterId, $this->_config) ) {
            return reset($this->_config[$clusterId]['metricConfig']);
        } else {
            throw new \Exception("No such cluster $clusterId");
        }
    }

    public function getClusterConfiguration($clusterId)
    {
        if ( array_key_exists($clusterId, $this->_config) ) {
            return $this->_config[$clusterId];
        } else {
            throw new \Exception("No such cluster $clusterId");
        }
    }

    public function getConfigurations()
    {
        return $this->_config;
    }
}
