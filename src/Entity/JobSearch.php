<?php

namespace App\Entity;

use AppBundle\Entity\Node;

class JobSearch
{
    private $userId;

    private $jobId;

    private $numNodesFrom;

    private $numNodesTo;

    private $durationFrom;

    private $durationTo;

    private $dateFrom;

    private $dateTo;

    private $clusterId;


    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    public function getClusterId()
    {
        return $this->clusterId;
    }

    public function setClusterId($clusterId)
    {
        $this->clusterId = $clusterId;
    }

    public function getNumNodesFrom()
    {
        return $this->numNodesFrom;
    }

    public function setNumNodesFrom($numNodesFrom)
    {
        $this->numNodesFrom = $numNodesFrom;
    }

    public function getNumNodesTo()
    {
        return $this->numNodesTo;
    }

    public function setNumNodesTo($numNodesTo)
    {
        $this->numNodesTo = $numNodesTo;
    }

    public function getDurationFrom()
    {
        return $this->durationFrom;
    }

    public function setDurationFrom($durationFrom)
    {
        $this->durationFrom = $durationFrom;
    }

    public function getDurationTo()
    {
        return $this->durationTo;
    }

    public function setDurationTo($durationTo)
    {
        $this->durationTo = $durationTo;
    }

    public function getDateFrom()
    {
        return $this->dateFrom;
    }

    public function setDateFrom($dateFrom)
    {
        $this->dateFrom = $dateFrom;
    }

    public function getDateTo()
    {
        return $this->dateTo;
    }

    public function setDateTo($dateTo)
    {
        $this->dateTo = $dateTo;
    }

}
