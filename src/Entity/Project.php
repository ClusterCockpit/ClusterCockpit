<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
*  @ORM\Entity
*/
class Project
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
    private $projectId;
    /**
     *  @ORM\Column(type="integer")
     */
    private $computeUnits;
}
