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
    private function parsePbs(String $buffer)
    {
        $records = preg_split("/\n{2,}/", $buffer);

        foreach ( $records as  $record ) {
            $parameters = preg_split("/\n/", $record);
            preg_match("/(.*)/", $parameters[0], $matches);

            $node = array(
                'nodeId' => $matches[0]
            );

            $node['parameters'] = array();

            /* parse node parameters */
            for ($i=1; $i<count($parameters); $i++) {
                if (preg_match("/([a-z_]+) = (.*)/", $parameters[$i], $matches)) {
                    $node['parameters'][$matches[1]] = $matches[2];
                }
            }

            if( array_key_exists('properties', $node['parameters']) ) {
                $node['properties'] =  preg_split("/,/", $node['parameters']['properties']);

                /* parse status parameters */
                $status = preg_split("/,/", $node['parameters']['status']);
                $node['status'] = array();

                foreach ( $status as $prop ) {
                    preg_match("/([a-z]+)=(.*)/", $prop, $matches);
                    $node['status'][$matches[1]] = "$matches[2]";
                }

                $node['processors'] = (int) $node['parameters']['np'];

                if ( array_key_exists('total_cores', $node['parameters']) ){
                    $node['cores'] = (int) $node['parameters']['np'];
                }

                $nodes[] = $node;
            }

        }

        return $nodes;
    }

    private function parseSlurm(String $buffer)
    {
        $nodelines = preg_split("/\n/", $buffer);
        $columnKeys = preg_split("/[ ]+/", $nodelines[1]);
        $lookup = array();

        for ($i=2; $i<count($nodelines); $i++) {
            $columns = preg_split("/[ ]+/", $nodelines[$i]);

            if (count($columns) == count($columnKeys)){
                for ($j=0; $j<count($columnKeys); $j++) {
                    $nodeInfo[$columnKeys[$j]] = $columns[$j];
                }

                $nodeId = $nodeInfo['NODELIST'];

                if ( ! array_key_exists($nodeId, $lookup) ){
                    $lookup[$nodeId] = 1;
                    $topology = preg_split("/:/", $nodeInfo['S:C:T']);

                    $nodes[] = array(
                        'nodeId' => $nodeId,
                        'cores' => (int) $topology[1],
                        'processors' => $topology[1]*$topology[2],
                        'nodeinfo' => $nodeInfo
                    );
                }
            }
        }

        return $nodes;
    }

    public function parse(String $filename)
    {
        $string = file_get_contents($filename, FALSE);
        $test = preg_split("/\n/", $string);

        if (preg_match("/NODELIST/", $test[1], $matches)) {
            return $this->parseSlurm($string);
        } else {
            return $this->parsePbs($string);
        }
    }
}
