<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class StatisticPlot
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
    public $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $trace;

    private $options;

    private $data;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\StatisticCache", inversedBy="plots")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statisticCache;

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

    public function getTrace(): ?string
    {
        return $this->trace;
    }

    public function setTrace(?string $trace): self
    {
        $this->trace = $trace;

        return $this;
    }


    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(string $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = json_encode($data);

        return $this;
    }


    public function getStatisticCache(): ?StatisticCache
    {
        return $this->statisticCache;
    }

    public function setStatisticCache(?StatisticCache $statisticCache): self
    {
        $this->statisticCache = $statisticCache;

        return $this;
    }
}
