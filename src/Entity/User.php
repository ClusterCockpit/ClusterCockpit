<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
*  @ORM\Entity(repositoryClass="App\Repository\UserRepository")
*/
class User
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
    private $userId;

    /**
     *  @ORM\Column(type="integer")
     */
    private $uid;

    /**
     *  @ORM\Column(type="string")
     */
    private $name;

    /**
     *  @ORM\Column(type="string")
     */
    private $email;

    /**
     *  @ORM\Column(type="string", nullable=true)
     */
    private $phone;

    /**
     * @ORM\ManyToMany(targetEntity="UnixGroup")
     * @ORM\JoinTable(name="users_groups", joinColumns={
     * @ORM\JoinColumn(name="user_id",referencedColumnName="id")}, inverseJoinColumns={
     * @ORM\JoinColumn(name="group_id",referencedColumnName="id")}
     * )
     */
    private $groups;

    /**
     *  @ORM\Column(type="boolean", nullable=true)
     */
    private $active;

    /**
     * @ORM\ManyToMany(targetEntity="Project")
     * @ORM\JoinTable(name="users_projects",joinColumns={
     * @ORM\JoinColumn(name="user_id",referencedColumnName="id")},inverseJoinColumns={
     * @ORM\JoinColumn(name="project_id",referencedColumnName="id")}
     * )
     */
    private $projects;

    public function __construct() {
        $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->projects = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getUserId()
    {
        /* return substr(md5($this->userId), 0, 8); */
        return $this->userId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getUid()
    {
        return $this->uid;
    }

    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    public function getName()
    {
        /* return substr(md5($this->name), 0, 8); */
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function setGroups($groups)
    {
        $this->groups = $groups;
    }

    public function getProjects()
    {
        return $this->projects;
    }

    public function setProjects($projects)
    {
        $this->projects = $projects;
    }
}


