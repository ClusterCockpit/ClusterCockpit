<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
*  @ORM\Entity(repositoryClass="App\Repository\StatisticCacheRepository")
 */
class StatisticCache
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $userId;

    /**
     * @ORM\Column(type="integer")
     */
    private $month;

    /**
     * @ORM\Column(type="integer")
     */
    private $year;

    /**
     * @ORM\Column(type="integer")
     */
    private $clusterId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public $jobCount;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    public $totalWalltime;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    public $totalCoreHours;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public $shortJobCount;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\StatisticPlot", mappedBy="statisticCache", orphanRemoval=true, indexBy="name")
     */
    private $plots;

    public function __construct()
    {
        $this->plots = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getClusterId(): ?int
    {
        return $this->clusterId;
    }

    public function setClusterId(int $clusterId): self
    {
        $this->clusterId = $clusterId;

        return $this;
    }

    public function getJobCount(): ?int
    {
        return $this->jobCount;
    }

    public function setJobCount(?int $jobCount): self
    {
        $this->jobCount = $jobCount;

        return $this;
    }

    public function getTotalWalltime(): ?float
    {
        return $this->totalWalltime;
    }

    public function setTotalWalltime(?float $totalWalltime): self
    {
        $this->totalWalltime = $totalWalltime;

        return $this;
    }

    public function getTotalCoreHours(): ?float
    {
        return $this->totalCoreHours;
    }

    public function setTotalCoreHours(?float $totalCoreHours): self
    {
        $this->totalCoreHours = $totalCoreHours;

        return $this;
    }

    public function getShortJobCount(): ?int
    {
        return $this->shortJobCount;
    }

    public function setShortJobCount(?int $shortJobCount): self
    {
        $this->shortJobCount = $shortJobCount;

        return $this;
    }

    /**
     * @return Collection|StatisticPlot[]
     */
    public function getPlots(): Collection
    {
        return $this->plots;
    }

    public function removePlot(StatisticPlot $plot): self
    {
        if ($this->plots->contains($plot)) {
            $this->plots->removeElement($plot);
            // set the owning side to null (unless already changed)
            if ($plot->getStatisticCache() === $this) {
                $plot->setStatisticCache(null);
            }
        }

        return $this;
    }

    public function addPlot(StatisticPlot $plot): self
    {
        $this->plots[$plot->name] = $plot;
        return $this;
    }

    public function getPlot($name)
    {
        if (!isset($this->plots[$name])) {
            throw new \InvalidArgumentException("No plot with name $name in Job cache.");
        }

        return $this->plots[$name];
    }
}
