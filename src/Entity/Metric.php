<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
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

use Doctrine\ORM\Mapping as ORM;

/**
*  @ORM\Entity
*/
class Metric
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     *  @ORM\Column(type="string")
     */
    public $name;

    /**
     *  @ORM\Column(type="string")
     */
    public $unit;

    /**
     *  @ORM\Column(type="float")
     */
    public $scale;

    /**
     *  @ORM\Column(type="integer")
     */
    public $position;

    /**
     *  @ORM\Column(type="string")
     */
    public $measurement;

    /**
     *  @ORM\Column(type="integer")
     */
    public $sampletime;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    public $peak;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    public $normal;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    public $caution;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    public $alert;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\MetricList", inversedBy="metrics")
     */
    private $metricList;

    /**
     * Get id.
     *
     * @return id.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get name.
     *
     * @return name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set name.
     *
     * @param name the value to set.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get unit.
     *
     * @return unit.
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * Set unit.
     *
     * @param unit the value to set.
     */
    public function setUnit($unit)
    {
        $this->unit = $unit;
    }

    /**
     * Get scale.
     *
     * @return scale.
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * Set scale.
     *
     * @param scale the value to set.
     */
    public function setScale($scale)
    {
        $this->scale = $scale;
    }

    public function getPeak(): ?float
    {
        return $this->peak;
    }

    public function setPeak(?float $peak): self
    {
        $this->peak = $peak;

        return $this;
    }

    public function getNormal(): ?float
    {
        return $this->normal;
    }

    public function setNormal(?float $normal): self
    {
        $this->normal = $normal;

        return $this;
    }

    public function getCaution(): ?float
    {
        return $this->caution;
    }

    public function setCaution(?float $caution): self
    {
        $this->caution = $caution;

        return $this;
    }

    public function getAlert(): ?float
    {
        return $this->alert;
    }

    public function setAlert(?float $alert): self
    {
        $this->alert = $alert;

        return $this;
    }

    public function getMetricList(): ?MetricList
    {
        return $this->metricList;
    }

    public function setMetricList(?MetricList $metricList): self
    {
        $this->metricList = $metricList;

        return $this;
    }
}


