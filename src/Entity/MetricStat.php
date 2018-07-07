<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class MetricStat
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
    private $metricName;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $avg;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $min;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $max;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\NodeStat", inversedBy="metrics")
     */
    private $nodeStat;

    public function getId()
    {
        return $this->id;
    }

    public function getMetricName(): ?string
    {
        return $this->metricName;
    }

    public function setMetricName(string $metricName): self
    {
        $this->metricName = $metricName;

        return $this;
    }

    public function getAvg(): ?float
    {
        return $this->avg;
    }

    public function setAvg(?float $avg): self
    {
        $this->avg = $avg;

        return $this;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function setMin(?float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }

    public function setMax(?float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function getNodeStat(): ?NodeStat
    {
        return $this->nodeStat;
    }

    public function setNodeStat(?NodeStat $nodeStat): self
    {
        $this->nodeStat = $nodeStat;

        return $this;
    }
}
