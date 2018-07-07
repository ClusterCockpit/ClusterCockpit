<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateGroupRequest
{
    public $groupId;

    /**
     * @Assert\NotBlank()
     * @Assert\Length(min="10", max="100")
     * @var string
     */
    public $organisation;

   /**
     * @Assert\NotBlank()
     * @var string
     */
    public $contact;

    public static function fromUnixGroup(UnixGroup $group): self
    {
        $groupRequest = new self();
        $groupRequest->groupId = $group->getGroupId();
        $groupRequest->organisation = $group->getOrganisation();

        return $groupRequest;
    }
}
