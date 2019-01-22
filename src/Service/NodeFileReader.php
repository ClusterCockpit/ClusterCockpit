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

class NodeFileReader
{
    private function parsePbs(String $buffer){
        $records = preg_split("/\n{2,}/", $buffer);
        /* $nodes = array(); */

        foreach ( $records as  $node ) {
            $parameters = preg_split("/\n/", $record);
            preg_match("/(.*)/", $parameters[0], $matches);

            $node = array(
                'nodeId' => $matches[0]
            );

            $node['parameters'] = array();

            /* parse node parameters */
            for ($i=1; i<count($parameters); $i++) {
                if (preg_match("/[ ]+([a-z_]+) = (.*)/", $parameters[$i], $matches)) {
                    $node['parameters'][$matches[0]] = $matches[1];
                }
            }

            $node['properties'] =  preg_split("/,/", $node['parameters']['properties']);

            /* parse status parameters */
            preg_match("/[  ]+status = (.*)/", $node['parameters']['status'], $matches);
            $status = preg_split("/,/", $matches[0]);
            $node['status'] = array();

            foreach ( $status as $prop ) {
                preg_match("/([a-z]+)=(.*)/", $prop, $matches);
                $node['status'][$matches[0]] = "$matches[1]";
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    private function parseSlurm(String $buffer){

        $nodelines = preg_split("/\n/", $buffer);
        $columnKeys = preg_split("/[ ]+/", $nodelines[1]);

        for ($i=2; i<count($nodelines); $i++) {
            $columns = preg_split("/[ ]+/", $nodelines[$i]);
            $nodeInfo = array();

            for ($j=2; j<count($nodelines); $j++) {
                $nodeInfo[$columnKeys[$j] = $columns[$j];
            }

            $nodes[] = $nodeInfo;
        }

        return $nodes;
    }

    public function parse(String $filename)
    {
        $string = file_get_contents($filename, FALSE);

        parsePbs($string);
    }
}
