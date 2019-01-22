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
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_0;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_1;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_2;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_3;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_4;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_5;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_6;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_7;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_8;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_9;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_10;


    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_11;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_12;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_13;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_14;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_15;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_16;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_17;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_18;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_19;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_20;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_21;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_22;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_23;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_24;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    private $slot_25;
}
