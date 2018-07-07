<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class NodeStat
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
    private $nodeName;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\JobCache", inversedBy="nodeStat")
     * @ORM\JoinColumn(nullable=false)
     */
    private $jobCache;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MetricStat", mappedBy="nodeStat", indexBy="metricName")
     */
    public $metrics;

    public function __construct()
    {
        $this->metrics = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNodeName(): ?string
    {
        return $this->nodeName;
    }

    public function setNodeName(string $nodeName): self
    {
        $this->nodeName = $nodeName;

        return $this;
    }

    public function getJobCache(): ?JobCache
    {
        return $this->jobCache;
    }

    public function setJobCache(?JobCache $jobCache): self
    {
        $this->jobCache = $jobCache;

        return $this;
    }

    /**
     * @return Collection|MetricStat[]
     */
    public function getMetrics(): Collection
    {
        return $this->metrics;
    }

    public function addMetric(MetricStat $metric): self
    {
        $this->metrics[$metric->getMetricName()] = $metric;

        return $this;
    }

    public function removeMetric(MetricStat $metric): self
    {
        if ($this->metrics->contains($metric)) {
            $this->metrics->removeElement($metric);
            // set the owning side to null (unless already changed)
            if ($metric->getNodeStat() === $this) {
                $metric->setNodeStat(null);
            }
        }

        return $this;
    }
}
