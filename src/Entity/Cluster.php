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
     * @ORM\OneToMany(targetEntity="App\Entity\MetricList", mappedBy="cluster", indexBy="name", orphanRemoval=true)
     */
    public $metricLists;

    /**
     * @Assert\File(mimeTypes={ "text/plain" })
     */
    private $nodeFile;

    private $nodes;

    public function __construct() {
        $this->metricLists = new ArrayCollection();
    }

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

    /**
     * @return Collection|MetricList[]
     */
    public function getMetricLists(): Collection
    {
        return $this->metricLists;
    }

    public function getMetricList($name)
    {
        if (!isset($this->metricLists[$name])) {
            throw new \InvalidArgumentException("No list with name $name in Cluster config.");
        }

        return $this->metricLists[$name];
    }

    public function addMetricList(MetricList $metricList): self
    {
        $this->metricLists[$metricList->getName()] = $metricList;
        $metricList->setCluster($this);
        return $this;

        /* if (!$this->metricLists->contains($metricList)) { */
        /*     $this->metricLists[] = $metricList; */
        /*     $metricList->setCluster($this); */
        /* } */
        /* return $this; */
    }

    public function removeMetricList(MetricList $metricList): self
    {
        if ($this->metricLists->contains($metricList)) {
            $this->metricLists->removeElement($metricList);
            // set the owning side to null (unless already changed)
            if ($metricList->getCluster() === $this) {
                $metricList->setCluster(null);
            }
        }

        return $this;
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
