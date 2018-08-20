<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
*  @ORM\Entity(repositoryClass="App\Repository\UnixGroupRepository")
*/
class UnixGroup
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
    private $groupId;

    /**
     *  @ORM\Column(type="integer")
     */
    private $gid;

    /**
     *  @ORM\Column(type="string",nullable=true)
     */
    private $organisation;

    /**
     *  @ORM\Column(type="string",nullable=true)
     */
    private $contact;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getGroupId()
    {
        return $this->groupId;
    }

    public function setGroupId($groupId)
    {
        $this->groupId = $groupId;
    }

    public function getGid()
    {
        return $this->gid;
    }

    public function setGid($gid)
    {
        $this->gid = $gid;
    }

    public function getOrganisation()
    {
        return $this->organisation;
    }

    public function setOrganisation($organisation)
    {
        $this->organisation = $organisation;
    }

    public function getContact()
    {
        return $this->contact;
    }

    public function setContact($contact)
    {
        $this->contact = $contact;
    }
}


