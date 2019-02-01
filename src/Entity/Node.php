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
    public $nodeId;

    /**
     *  @ORM\Column(type="string",nullable=true)
     */
    public $rackId;

    /**
     *  @ORM\Column(type="string",nullable=true)
     */
    public $uarch;

    /**
     *  @ORM\Column(type="integer")
     */
    public $cluster;

    /**
     *  @ORM\Column(type="integer",nullable=true)
     */
    public $numCores;

    /**
     *  @ORM\Column(type="integer")
     */
    public $numProcessors;

    /**
     * @ORM\ManyToMany(targetEntity="Property")
     * @ORM\JoinTable(name="nodes_properties", joinColumns={@ORM\JoinColumn(name="node_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="property_id", referencedColumnName="id")})
     */
    public $properties;

    /**
     *  @ORM\Column(type="string",options={"default":"active"})
     */
    public $status;

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


