<?php

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

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
     * @ORM\OneToMany(targetEntity="App\Entity\MetricList", mappedBy="cluster", indexBy="name")
     */
    public $metricLists;

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
        $this->metricLists[$metricList->name] = $metricList;
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
        return $this->nodes;
    }

    public function setNodes(array $nodes): self
    {
        $this->nodes = $nodes;

        return $this;
    }
}


