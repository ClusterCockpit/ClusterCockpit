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

namespace App\Service;

class TimeseriesHelper
{
    public function scaleMetric(&$y, $scale)
    {
        for($i=0; $i<count($y); $i++) {
            $y[$i] = $y[$i] * $scale;
        }
    }

    public function scaleTime($options, &$x):array
    {
        $unit = 'm';
        $dtick = 1;
        $scale = 60;
        $round = (int) $options['data_time_digits'];
        $range = end($x) - $x[0];
        $minutes = $range / 60;

        if ($minutes < 10 ){
            $dtick = 1;
        } else if ($minutes < 120){
            $dtick = 10;
        } else if ($minutes < 360){
            $dtick = 30;
        } else {
            $dtick = 1;
            $scale = 3600;
            $unit = 'h';
        }

        for($i=0; $i<count($x); $i++) {
            $x[$i] = round($x[$i] / $scale,$round);
        }

        return array(
            'unit' => $unit,
            'dtick' => $dtick
        );
    }

    public function downsampling(&$x, &$y, $numPoints)
    {
        $a = 0;
        $maxAreaPoint_x;
        $maxAreaPoint_y;
        $maxArea;
        $area;
        $nextA;
        $lengthX = count($x);
        $lengthY = count($y);
        $sampled_x;
        $sampled_y;

        if ($numPoints >= $lengthY){
            return array(
                'x'=> $x,
                'y'=> $y
            );
        }

        $bucketSize = intdiv(($lengthY-2), ($numPoints-2));
        $sampled_x[] = $x[$a];
        $sampled_y[] = $y[$a];

        for($i=0; $i< $numPoints-2; $i++){

            /* compute AVG in next bucket */
            $avg_x = 0;
            $avg_y = 0;
            $rangeStart = floor(($i+1) * $bucketSize) + 1;
            $rangeStop = floor(($i+2) * $bucketSize) + 1;
            $rangeStop = $rangeStop < $lengthY ? $rangeStop : $lengthY;

            $rangeLength = $rangeStop - $rangeStart;

            for(; $rangeStart < $rangeStop; $rangeStart++){
                $avg_x += $x[$rangeStart];
                $avg_y += $y[$rangeStart];
            }

            $avg_x /= $rangeLength;
            $avg_y /= $rangeLength;

            $rangeStart = floor(($i+0) * $bucketSize) + 1;
            $rangeStop = floor(($i+1) * $bucketSize) + 1;

            $point_a_x = $x[$a];
            $point_a_y = $y[$a];

            $maxArea = -1;
            $area = -1;

            for(; $rangeStart < $rangeStop; $rangeStart++){
                $area = abs( ($point_a_x - $avg_x) * ($y[$rangeStart] - $point_a_y ) -
                    ( $point_a_x - $x[$rangeStart] ) * ( $avg_y - $point_a_y )) * 0.5;

                if ( $area > $maxArea ){
                    $maxArea = $area;
                    $maxAreaPoint_x = $x[$rangeStart];
                    $maxAreaPoint_y = $y[$rangeStart];
                    $nextA = $rangeStart;
                }
            }

            $sampled_x[] = $maxAreaPoint_x;
            $sampled_y[] = $maxAreaPoint_y;
            $a = $nextA;
        }

        $sampled_x[] = $x[$lengthX-1];
        $sampled_y[] = $y[$lengthY-1];
        $x = $sampled_x;
        $y = $sampled_y;
    }

    public function getDurationString($start, $stop)
    {
        $duration = $stop - $start;
        $hours = intdiv($duration,3600);
        $minutes = ($duration - ($duration%3600)) / 60;

        return "$hours h $minutes m";
    }
}
