<?php

namespace App\Entity;


class StatisticsControl
{
    private $month;

    private $year;

    private $cluster;

    private $submit;

    /**
     * Get month.
     *
     * @return month.
     */
    public function getMonth()
    {
        return $this->month;
    }
    /**
     * Set month.
     *
     * @param month the value to set.
     */
    public function setMonth($month)
    {
        $this->month = $month;
    }
    /**
     * Get year.
     *
     * @return year.
     */
    public function getYear()
    {
        return $this->year;
    }
    /**
     * Set year.
     *
     * @param year the value to set.
     */
    public function setYear($year)
    {
        $this->year = $year;
    }
    /**
     * Get system.
     *
     * @return cluster.
     */
    public function getCluster()
    {
        return $this->cluster;
    }
    /**
     * Set cluster.
     *
     * @param cluster the value to set.
     */
    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
    }
    /**
     * Get submit.
     *
     * @return submit.
     */
    public function getSubmit()
    {
        return $this->submit;
    }
    /**
     * Set submit.
     *
     * @param submit the value to set.
     */
    public function setSubmit($submit)
    {
        $this->submit = $submit;
    }
}
