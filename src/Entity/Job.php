<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2021 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;

/**
*  @ORM\Entity(repositoryClass="App\Repository\JobRepository")
*  @ORM\Table(name="job",indexes={@ORM\Index(name="search_idx", columns={"is_running","cluster_id"})})
*/
#[ApiResource(
attributes: [
    'pagination_type' => 'page',
    'normalization_context' => ['groups' => ['read']],
    'denormalization_context' => ['groups' => ['write']],
],
    collectionOperations: [
        'post' => [
            'path' => '/jobs/start_job/',
        ],
    ],
    itemOperations: ['get','patch'],
)]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'partial', 'jobId' => 'start', 'tags.name' => 'exact'])]
#[ApiFilter(RangeFilter::class, properties: ['startTime','numNodes','duration'])]
#[ApiFilter(OrderFilter::class, properties: ['startTime','duration','numNodes'])]
#[ApiFilter(BooleanFilter::class, properties: ['isRunning'])]
class Job
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     *  @Groups({"read"})
     */
    public $id;

    /**
     *  The jobId of this job.
     *
     *  @ORM\Column(type="string")
     *  @Groups({"read","write"})
     */
    private $jobId;

    /**
     * The userId for this job.
     *
     *  @ORM\Column(type="string")
     *  @Groups({"read","write"})
     */
    private $userId;

    /**
     * The cluster on which the job was executed.
     *
     *  @ORM\Column(type="string")
     *  @Groups({"read","write"})
     */
    private $clusterId;

    /**
     * The number of nodes used by the job.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read","write"})
     */
    public $numNodes;

    /**
     * When the job was started.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read","write"})
     */
    public $startTime;

    /**
     * The duration of the job.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read","write"})
     */
    public $duration;

    /**
     * The node list of the job.
     *
     *  @ORM\Column(type="text", nullable=true)
     *  @Groups({"read","write"})
     */
    public $nodeList;

    public $jobCache;

    /**
     *  @ORM\Column(type="boolean")
     */
    public $isRunning;

    /**
     *  @ORM\Column(type="boolean", options={"default":false})
     */
    public $isCached;

    /**
     *  @ORM\Column(type="text", nullable=true)
     *  @Groups({"write"})
     */
    private $jobScript;

    /**
     *  @ORM\Column(type="text", options={"default":"noProject"})
     *  @Groups({"write"})
     */
    private $projectId;

    /**
     * The maximum memory capacity used by the job.
     *
     *  @ORM\Column(type="float")
     */
    public $memUsedMax;

    /**
     * The average flop rate of the job.
     *
     *  @ORM\Column(type="float")
     */
    public $flopsAnyAvg;

    /**
     * The average memory bandwidth of the job.
     *
     *  @ORM\Column(type="float")
     */
    public $memBwAvg;

    /**
     * The average load of the job.
     *
     *  @ORM\Column(type="float")
     */
    public $loadAvg;

    /**
     * The average network bandwidth of the job.
     *
     *  @ORM\Column(type="float")
     */
    public $netBwAvg;

    /**
     * The average file io bandwidth of the job.
     *
     *  @ORM\Column(type="float")
     */
    public $fileBwAvg;

    public $hasProfile;

    /**
     * Tags of the job.
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\JobTag", inversedBy="jobs")
     *  @Groups({"read"})
     */
    public $tags;


    public function __construct() {
        $this->tags = new ArrayCollection();
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getClusterId()
    {
        return $this->clusterId;
    }

    public function setClusterId($clusterId)
    {
        $this->clusterId = $clusterId;
    }

    public function getNumNodes()
    {
        return $this->numNodes;
    }

    public function setNumNodes($numNodes)
    {
        $this->numNodes = $numNodes;
    }

    public function getNodes($delimiter)
    {
        $nodes = explode(',', $this->nodeList);
        return implode($delimiter, $nodes);
    }

    public function getNodeArray()
    {
        return explode(',', $this->nodeList);
    }

    public function getProjectId()
    {
        return $this->projectId;
    }

    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;
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
        return $this->isRunning;
    }

    /**
     * @return Collection|JobTag[]
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function getTagsArray()
    {
        $tags = array();

        foreach ($this->tags as $tag){
            $tags[] = array(
                'name' => $tag->getName(),
                'id' => $tag->getId(),
                'type' => $tag->getType()
            );
        }
        return $tags;
    }

    public function addTag(JobTag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function removeTag(JobTag $tag): self
    {
        if ($this->tags->contains($tag)) {
            $this->tags->removeElement($tag);
        }

        return $this;
    }
}
