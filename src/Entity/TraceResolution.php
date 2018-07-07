<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class TraceResolution
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
    public $resolution;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Trace", mappedBy="traceResolution", orphanRemoval=true)
     */
    private $traces;


    public function __construct()
    {
        $this->traces = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(string $resolution): self
    {
        $this->resolution = $resolution;

        return $this;
    }

    /**
     * @return Collection|Trace[]
     */
    public function getTraces(): Collection
    {
        return $this->traces;
    }

    public function addTrace(Trace $trace): self
    {
        if (!$this->traces->contains($trace)) {
            $this->traces[] = $trace;
            $trace->setTraceResolution($this);
        }

        return $this;
    }

    public function removeTrace(Trace $trace): self
    {
        if ($this->traces->contains($trace)) {
            $this->traces->removeElement($trace);
            // set the owning side to null (unless already changed)
            if ($trace->getTraceResolution() === $this) {
                $trace->setTraceResolution(null);
            }
        }

        return $this;
    }

    public function getPlot(): ?Plot
    {
        return $this->plot;
    }

    public function setPlot(?Plot $plot): self
    {
        $this->plot = $plot;

        return $this;
    }
}
