<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class MetricList
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Metric", mappedBy="metricList", indexBy="name", orphanRemoval=true)
     * @ORM\OrderBy({"position" = "ASC"})
     */
    public $metrics;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Cluster", inversedBy="metricLists")
     */
    private $cluster;

    public function __construct()
    {
        $this->metrics = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|Metric[]
     */
    public function getMetrics(): Collection
    {
        return $this->metrics;
    }

    public function addMetric(Metric $metric): self
    {
        $this->metrics[$metric->name] = $metric;
        return $this;

        /* /1* if (!$this->metrics->contains($metric)) { *1/ */
        /*     $this->metrics[] = $metric; */
        /*     $metric->setMetricList($this); */
        /* } */
        /* return $this; */
    }

    public function removeMetric(Metric $metric): self
    {
        if ($this->metrics->contains($metric)) {
            $this->metrics->removeElement($metric);
            // set the owning side to null (unless already changed)
            if ($metric->getMetricList() === $this) {
                $metric->setMetricList(null);
            }
        }

        return $this;
    }

    public function getMetric($name)
    {
        if (!isset($this->metrics[$name])) {
            throw new \InvalidArgumentException("No metric with name $name in Metric List.");
        }

        return $this->metric[$name];
    }

    public function getCluster(): ?Cluster
    {
        return $this->cluster;
    }

    public function setCluster(?Cluster $cluster): self
    {
        $this->cluster = $cluster;

        return $this;
    }
}
