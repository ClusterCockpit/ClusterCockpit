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

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 *  @ORM\Entity(repositoryClass="App\Repository\ClusterRepository")
 */
class Cluster
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     *  @ORM\Column(type="string",)
     */
    public $name;

    /**
     *  @ORM\Column(type="integer",)
     */
    public $coresPerNode;

    /**
     *  @ORM\Column(type="float")
     */
    public $flopRateScalar;

    /**
     *  @ORM\Column(type="float")
     */
    public $flopRateSimd;

    /**
     *  @ORM\Column(type="float")
     */
    public $memoryBandwidth;

    /**
     *  @ORM\Column(type="json")
     *  @Assert\Json()
     */
    public $metricListConfig;

    /**
     * @Assert\File(mimeTypes={ "text/plain" })
     */
    private $nodeFile;

    private $nodes;

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getCoresPerNode()
    {
        return $this->coresPerNode;
    }

    public function setCoresPerNode($coresPerNode)
    {
        $this->coresPerNode = $coresPerNode;
    }

    public function getMetricLists()
    {
        return json_decode ( $this->metricListConfig , true );
    }

    public function getMetricList($name)
    {
        //TODO Do this in constructor
        $tmpList = json_decode ( $this->metricListConfig , true );

        if (!isset($tmpList[$name])) {
            throw new \InvalidArgumentException("No list with name $name in Cluster config.");
        }

        $metricList = array();

        foreach ($tmpList[$name] as $metric){
            $metricList[$metric['name']] = $metric;
        }

        return $metricList;
    }

    public function getNodes(): ?array
    {
        if ( is_array($this->nodes) ){
            return $this->nodes;
        } else {
            return array();
        }
    }

    public function setNodes(array $nodes): self
    {
        $this->nodes = $nodes;

        return $this;
    }

    public function getNodeFile()
    {
        return $this->nodeFile;
    }

    public function setNodeFile($filename): self
    {
        $this->nodeFile = $filename;

        return $this;
    }

}
