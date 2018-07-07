<?php

namespace App\Entity;

use App\Entity\Node;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
*  @ORM\Entity(repositoryClass="App\Repository\RunningJobRepository")
*/
class RunningJob
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     *  @ORM\Column(type="string", unique=true)
     */
    private $jobId;

    /**
     *  @ORM\ManyToOne(targetEntity="User")
     */
    private $user;

    /**
     *  @ORM\ManyToOne(targetEntity="Cluster")
     */
    private $cluster;

    /**
     *  @ORM\Column(type="integer")
     */
    private $numNodes;

    /**
     *  @ORM\Column(type="integer")
     */
    public $startTime;

    public $stopTime;

    public $duration;

    /**
     *  @ORM\Column(type="integer", nullable=true, options={"default":0})
     */
    public $severity;

    /**
     * @ORM\ManyToMany(targetEntity="Node", indexBy="id")
     * @ORM\JoinTable(name="Rjobs_nodes", joinColumns={@ORM\JoinColumn(name="job_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="node_id", referencedColumnName="id")})
     */
    private $nodes;

    /**
     * @ORM\OneToOne(targetEntity="JobCache")
     * @ORM\JoinColumn(name="cache_id", referencedColumnName="id"))
     */
    public $jobCache;

    /**
     *  @ORM\Column(type="text", nullable=true)
     */
    private $jobScript;

    /**
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $memUsedAvg;

    /**
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $memBwAvg;

    /**
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $flopsAnyAvg;

    /**
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $trafficTotalIbAvg;

    /**
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $trafficTotalLustreAvg;

    public $hasProfile;

    public function __construct() {
        $this->nodes = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getCluster()
    {
        return $this->cluster;
    }

    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
    }

    public function getNumNodes()
    {
        return $this->numNodes;
    }

    public function setNumNodes($numNodes)
    {
        $this->numNodes = $numNodes;
    }

    public function getStopTime()
    {
        return $this->stopTime;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function addNode(Node $node)
    {
        if ($this->nodes->contains($node)) {
            return $node;
        }

        $this->nodes[] = $node;
    }

    public function removeNode(Node $node)
    {
        $this->nodes->removeElement($node);
    }

    public function getNode($id)
    {
        if (!isset($this->nodes[$id])) {
            throw new \InvalidArgumentException("No node with id $id in Job.");
        }

        return $this->nodes[$id];
    }

    public function getJobScript()
    {
        return $this->jobScript;
    }

    public function setJobScript($jobScript)
    {
        $this->jobScript = $jobScript;
    }

    public function isRunning()
    {
        return true;
    }
}


