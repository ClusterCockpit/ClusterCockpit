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

use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;
use Curl\Curl;

class PrometheusMetricDataRepository implements MetricDataRepository
{
    private $_timing;
    private $_database;
    /* private $_logger; */

    public function __construct(
	/* LoggerInterface $logger */
	//URL = http://localhost:7281/api/v1
        $promURL = getenv('PROMETHEUSDB_URL');
        $curl = new Curl();
        $curl->setBasicAuthentication(getenv('PROMETHEUS_USR'), getenv('PROMETHEUS_PWD'));

    )
    {
        $this->_timer = new Stopwatch();
        /* $this->_logger = $logger; */
    }

    //TODO: 	Curl Exceptions!
    //		Rethink Variable names!
    //		Logger!
    //		Password Prometheus?


    public function hasProfile($job, $metric)
    {

       //TODO: 	Test for each metric?
       // 	Likwid Plugin: Result for every CPU or Sum?
       // 	$nodes = $job->getNodes('|'); + Nodes in Curl
        	
        $startTime = gmdate("Y-m-d\TH:i:s",$job->startTime);
	$stopTime = gmdate("Y-m-d\TH:i:s",$job->startTime + $job->duration);
    
        $nodes = $job->getNodeArray();
        if ( count($nodes) < 1 ){
            $job->hasProfile = false;
            return false;

        foreach ($metrics as $key => $metric){
        $metricname = $metric['name'];}

	    $curl->get("$promURL/query_range?query=$metricname".
		    "{instance=~'mistral03.dkrz.de:9100|mistral02.dkrz.de:9100'}".
		    "&start=$startTime.781Z&end=$stopTime.781Z&step=10");
        $points = array_column($curl->response->data->result,'values');

        if ( count($points) == 0 || $points[0]['count'] < 4 ){
            $job->hasProfile = false;
            return false;
        } else {
            $job->hasProfile = true;
            return true;
        }
    }

    public function getJobStats($job, $metrics)
    {

        //TODO: $nodes = $job->getNodes('|'); + Nodes in Curl

        $nodearray = $job->getNodeArray();
        $stopTime = gmdate("Y-m-d\TH:i:s",$job->startTime + $job->duration);


        foreach ($nodearray as $nodename){
            $result=array($nodename => array());}

        foreach ($metrics as $key => $metric){
          $metricname = $metric['name'];

          $curl->get("$promURL/query?query=min_over_time($metricname".
		  "{instance=~'mistral01.dkrz.de:9100|mistral02.dkrz.de:9100|mistral03.dkrz.de:9100'}".
		  "[5m])&time=$stopTime.781Z");
          $dmin = array_column(array_column($curl->response->data->result,'value'),1);
	  $curl->get("$promURL/query?query=max_over_time($metricname".
		  "{instance=~'mistral01.dkrz.de:9100|mistral02.dkrz.de:9100|mistral03.dkrz.de:9100'}".
		  "[5m])&time=$stopTime.781Z");
          $dmax = array_column(array_column($curl->response->data->result,'value'),1);
	  $curl->get("$promURL/query?query=avg_over_time($metricname".
		  "{instance=~'mistral01.dkrz.de:9100|mistral02.dkrz.de:9100|mistral03.dkrz.de:9100'}".
		  "[5m])&time=$stopTime.781Z");
          $davg = array_column(array_column($curl->response->data->result,'value'),1);

          $minval=round(min($dmin),2);
          $maxval=round(max($dmax),2);
          $avgval=round(array_sum($davg)/count($davg),2);

          foreach ($nodearray as $key2 => $nodename){
            $array=array();
            if ($key == 0){
                $array['nodeId'] = $nodename;}
            $array[$metricname."_avg"] = round($davg[$key2],2);
            $array[$metricname."_min"] = round($dmin[$key2],2);
            $array[$metricname."_max"] = round($dmax[$key2],2);
            if ($key == 0){
                $result[$nodename]=$array;}
            else {
                $result[$nodename] = array_merge($result[$nodename],$array);}
            }
   
        $Result[$metricname."_avg"] = $avgval;
        $Result[$metricname."_min"] = $minval;
        $Result[$metricname."_max"] = $maxval;
        }

      $nodestats['nodestats'] = $result;
      $stats = array_merge($Result, $nodestats);

      return $stats;

    }

    public function getMetricData($job, $metrics)
    {

      $nodearray = $job->getNodeArray();
      $startTime = gmdate("Y-m-d\TH:i:s",$job->startTime);
      $stopTime = gmdate("Y-m-d\TH:i:s",$job->startTime + $job->duration);

      foreach ($metrics as $key => $metric){
        $metricname = $metric['name'];

	$curl->get("$promURL/query_range?query=$metricname".
		"{instance=~'mistral03.dkrz.de:9100|mistral02.dkrz.de:9100'}".
		"&start=$startTime.781Z&end=$stopTime.781Z&step=10");
        $points = array_column($curl->response->data->result,'values');

        foreach ($nodearray as $key2 => $nodename){

          if ($key == 0){
              $timearray[$nodename]=array_column($points[$key2],0);}
            $metricarray[$nodename]=array_column($points[$key2],1);
          }

        if ($key == 0){
          $data = array();
          $data['time'] = $timearray;
          $data[$metricname] = $metricarray;}

        $data[$metricname] = $metricarray;
        }

      return $data;    
    
}
