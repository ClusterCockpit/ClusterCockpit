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

namespace App\Tests\Service;

use App\Service\NodeFileReader;
use PHPUnit\Framework\TestCase;

class NodeFileReaderTest extends TestCase
{
    public function testParsePBS_1()
    {
        $path = '/Users/jan/Sites/ClusterCockpit/tests/InputFiles/emmy-nodes.txt';
        $fileReader = new NodeFileReader();
        $nodes = $fileReader->parse($path);
        /* var_dump($nodes); */
        $this->assertCount(559, $nodes);
    }

    public function testParsePBS_2()
    {
        $path = '/Users/jan/Sites/ClusterCockpit/tests/InputFiles/woody-nodes.txt';
        $fileReader = new NodeFileReader();
        $nodes = $fileReader->parse($path);
        var_dump($nodes);
        $this->assertCount(176, $nodes);
    }

    public function testParseSlurm()
    {
        $path = '/Users/jan/Sites/ClusterCockpit/tests/InputFiles/meggie-nodes.txt';
        $fileReader = new NodeFileReader();
        $nodes = $fileReader->parse($path);
        /* var_dump($nodes); */
        $this->assertCount(728, $nodes);
    }

}
