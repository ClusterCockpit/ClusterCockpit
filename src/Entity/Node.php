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
     *  @ORM\Column(type="integer")
     */
    public $cluster;

    /**
     *  @ORM\Column(type="string",options={"default":"active"})
     */
    public $status;

    public function getId()
    {
        return $this->id;
    }

    public function getNodeId()
    {
        return $this->nodeId;
    }
}


