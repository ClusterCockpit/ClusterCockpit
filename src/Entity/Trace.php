<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Trace
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(type="text")
     */
    private $json;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TraceResolution", inversedBy="traces")
     * @ORM\JoinColumn(nullable=false)
     */
    private $traceResolution;

    public function getId()
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getData()
    {
        return json_decode($this->json,true);
    }


    public function getJson(): ?string
    {
        return $this->json;
    }

    public function setJson(string $json): self
    {
        $this->json = $json;

        return $this;
    }

    public function getTraceResolution(): ?TraceResolution
    {
        return $this->traceResolution;
    }

    public function setTraceResolution(?TraceResolution $traceResolution): self
    {
        $this->traceResolution = $traceResolution;

        return $this;
    }
}
