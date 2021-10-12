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
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Core\Annotation\ApiResource;

/**
*  @ORM\Entity(repositoryClass="App\Repository\JobRepository")
*  @ORM\Table(name="job",indexes={@ORM\Index(name="search_idx", columns={"is_running","cluster_id"})})
*/
#[ApiResource(
    attributes: [
        'validation_groups' => ['start_validation', 'stop_validation']
    ],
    collectionOperations: [
        'post' => [
            'path' => '/jobs/start_job/',
            'denormalization_context' => ['groups' => ['start']],
            'validation_groups' => ['start_validation']
        ],
    ],
    itemOperations: [
        'get' => [
            'path' => '/jobs/{id}',
            'normalization_context' => ['groups' => ['read']],
        ],
        'put' => [
            'path' => '/jobs/stop_job/{id}',
            'denormalization_context' => ['groups' => ['stop']],
            'validation_groups' => ['stop_validation']
        ],
        'patch' => [
            'path' => '/jobs/tag_job/{id}',
            'denormalization_context' => ['groups' => ['tag']],
            'validation_groups' => []
        ],
    ],
)]
class Job
{
    /**
     *  The db id of this job.
     *
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     *  @Groups({"read"})
     */
    public int $id;

    /**
     *  The jobId of this job.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read","start"})
     *  @Assert\Positive
     *  @Assert\NotBlank(groups={"start_validation"})
     */
    private int $jobId;

    /**
     * The userId for this job.
     *
     *  @ORM\Column(type="string")
     *  @Groups({"read","start"})
     *  @Assert\NotBlank(groups={"start_validation"})
     */
    private string $userId;

    /**
     * The cluster on which the job was executed.
     *
     *  @ORM\Column(type="string")
     *  @Groups({"read","start"})
     *  @Assert\NotBlank(groups={"start_validation"})
     */
    private string $clusterId;

    /**
     * The number of nodes used by the job.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read"})
     */
    public int $numNodes = 0;

    /**
     * When the job was started in unxi epoch time seconds.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read","start"})
     *  @Assert\Positive
     *  @Assert\NotBlank(groups={"start_validation"})
     */
    public int $startTime = 0;

    /**
     * When the job was started in unix epoch time seconds.
     *
     *  @Groups({"stop"})
     *  @Assert\Positive
     *  @Assert\NotBlank(groups={"stop_validation"})
     */
    public int $stopTime = 0;

    /**
     * The duration of the job in seconds.
     *
     *  @ORM\Column(type="integer")
     *  @Groups({"read"})
     */
    public int $duration = 0;

    /**
     * The node list of the job as string list separated by | character.
     *
     *  @ORM\Column(type="text")
     *  @Groups({"read","start"})
     *  @Assert\NotBlank(groups={"start_validation"})
     */
    public string $nodeList;

    /**
     * Boolean flag if job is still running.
     *
     *  @ORM\Column(type="boolean")
     */
    public $isRunning = true;

    /**
     * The job script.
     *
     *  @ORM\Column(type="json", nullable=true)
     *  @Groups({"start"})
     */
    private $metaData = null;

    /**
     * The project Id for this job.
     *
     *  @ORM\Column(type="text")
     *  @Groups({"start"})
     */
    private string $projectId= "noProject";

    /**
     * The maximum memory capacity used by the job.
     *
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $memUsedMax = 0;

    /**
     * The average flop rate of the job.
     *
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $flopsAnyAvg = 0;

    /**
     * The average memory bandwidth of the job.
     *
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $memBwAvg = 0;

    /**
     * The average load of the job.
     *
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $loadAvg = 0;

    /**
     * The average network bandwidth of the job.
     *
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $netBwAvg = 0;

    /**
     * The average file io bandwidth of the job.
     *
     *  @ORM\Column(type="float", options={"default":0})
     */
    public $fileBwAvg = 0;

    public $hasProfile;

    /**
     * Tags of the job.
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\JobTag", inversedBy="jobs")
     * @Groups({"read","tag"})
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
        if ( strcmp($delimiter,'|') === 0 ) {
            return $this->nodeList;
        } else {
            $nodes = explode('|', $this->nodeList);
            return implode($delimiter, $nodes);
        }
    }

    public function getNodeArray()
    {
        return explode('|', $this->nodeList);
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

    public function getStopTime(): int
    {
        return $this->stopTime;
    }

    public function setStopTime($stopTime)
    {
        $this->stopTime = $stopTime;
    }

    public function getDuration()
    {
        if ($this->isRunning() && $this->duration == 0) {
            $this->duration = time() - $this->startTime;
        }

        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    public function getMetaData()
    {
        return $this->metaData;
    }

    public function setMetaData($metaData)
    {
        $this->metaData = $metaData;
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
