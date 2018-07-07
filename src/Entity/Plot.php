<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
*  @ORM\Entity
*/
class Plot
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     *  @ORM\Column(type="string", nullable=true)
     */
    public $name;

    /**
     *  @ORM\Column(type="string", nullable=true)
     */
    public $xUnit;

    /**
     *  @ORM\Column(type="string", nullable=true)
     */
    public $yUnit;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $yMax;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $xDtick;

    public $options;

    public $data;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\JobCache", inversedBy="plots")
     */
    private $jobCache;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $resolutions;

    public $traceResolution;

    public $resolutionCache;


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

    public function getResolutions()
    {
        return json_decode($this->resolutions, true);
    }

    public function setResolutions(array $resolutions): self
    {
        $this->resolutions = json_encode($resolutions);

        return $this;
    }

    public function addResolution($name, $id): self
    {
        $resolutions = json_decode($this->resolutions, true);
        $resolutions[$name] = $id;
        $this->resolutions = json_encode($resolutions);

        return $this;
    }

    public function dropResolutions()
    {
        $this->resolutions = null;
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


