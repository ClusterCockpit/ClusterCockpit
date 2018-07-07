<?php

namespace App\Entity;

use App\Entity\Metric;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
*  @ORM\Entity
*/
class JobCache
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NodeStat", mappedBy="jobCache", orphanRemoval=true)
     */
    private $nodeStat;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Plot", mappedBy="jobCache", indexBy="name")
     */
    private $plots;

    public function __construct()
    {
        $this->nodeStat = new ArrayCollection();
        $this->plots = new ArrayCollection();
    }

    /**
     * @return Collection|NodeStat[]
     */
    public function getNodeStat(): Collection
    {
        return $this->nodeStat;
    }

    public function addNodeStat(NodeStat $nodeStat): self
    {
        if (!$this->nodeStat->contains($nodeStat)) {
            $this->nodeStat[] = $nodeStat;
            $nodeStat->setJobCache($this);
        }

        return $this;
    }

    public function removeNodeStat(NodeStat $nodeStat): self
    {
        if ($this->nodeStat->contains($nodeStat)) {
            $this->nodeStat->removeElement($nodeStat);
            // set the owning side to null (unless already changed)
            if ($nodeStat->getJobCache() === $this) {
                $nodeStat->setJobCache(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Plot[]
     */
    public function getPlots(): Collection
    {
        return $this->plots;
    }

    public function getPlotsArray($metrics)
    {
        $jsonPlots;

        foreach ($metrics as $metric){
            $plot = $this->plots[$metric->name];
            $jsonPlots[] = array(
                'name' => $plot->name,
                'options' => $plot->options,
                'data' => $plot->data
            );
        }
        return $jsonPlots;
    }

    public function removePlot(Plot $plot): self
    {
        if ($this->plots->contains($plot)) {
            $this->plots->removeElement($plot);
            // set the owning side to null (unless already changed)
            if ($plot->getJobCache() === $this) {
                $plot->setJobCache(null);
            }
        }

        return $this;
    }

    public function addPlot(Plot $plot): self
    {
        $this->plots[$plot->name] = $plot;
        return $this;
    }

    public function getPlot($name)
    {
        if (!isset($this->plots[$name])) {
            return false;
        }

        return $this->plots[$name];
    }
}

