<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
*  @ORM\Entity
*/
class Data
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     */
    private $nodeId;

    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     */
    private $epoch;

    /**
     *  @ORM\Column(type="integer", nullable=true)
     */
    private $epochClamp;


    /**
     *  @ORM\Column(type="float")
     */
    private $memUsed;

    /**
     *  @ORM\Column(type="float")
     */
    private $loadOne;

    /**
     *  @ORM\Column(type="float")
     */
    private $memBw;

    /**
     *  @ORM\Column(type="float")
     */
    private $flopsAny;

    /**
     *  @ORM\Column(type="float")
     */
    private $flopsDp;

    /**
     *  @ORM\Column(type="float")
     */
    private $flopsSp;

    /**
     *  @ORM\Column(type="float")
     */
    private $cpiAvg;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $clockSpeed;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $totalPower;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficReadEth;


    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficWriteEth;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficReadLustre;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficWriteLustre;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $reqReadLustre;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $reqWriteLustre;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $inodesLustre;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $pkgRateReadIb;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $pkgRateWriteIb;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $congestionIb;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficReadIb;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficWriteIb;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficTotalIb;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $trafficTotalLustre;
}

