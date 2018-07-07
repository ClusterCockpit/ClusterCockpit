<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
*  @ORM\Entity
*/
class Node
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     *  @ORM\Column(type="string",)
     */
    private $nodeId;

    /**
     *  @ORM\Column(type="string",nullable=true)
     */
    private $rackId;

    /**
     *  @ORM\Column(type="string",nullable=true)
     */
    private $uarch;

    /**
     *  @ORM\Column(type="integer")
     */
    private $cluster;

    /**
     *  @ORM\Column(type="integer")
     */
    private $numCores;

    /**
     *  @ORM\Column(type="integer")
     */
    private $numProcessors;

    /**
     * @ORM\ManyToMany(targetEntity="Property")
     * @ORM\JoinTable(name="nodes_properties", joinColumns={@ORM\JoinColumn(name="node_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="property_id", referencedColumnName="id")})
     */
    private $properties;

    /**
     *  @ORM\Column(type="string",options={"default":"free"})
     */
    private $status;

    public function __construct() {
        $this->properties = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNodeId()
    {
        return $this->nodeId;
    }
}


