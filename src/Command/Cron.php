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

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Adapter\LdapManager;
use App\Service\JobCache;
use App\Entity\User;
use App\Entity\UnixGroup;
use Psr\Log\LoggerInterface;


class Cron extends Command
{
    private $_logger;
    private $_em;
    private $_ldap;
    private $_jobCache;

    public function __construct(
        LdapManager $ldap,
        LoggerInterface $logger,
        EntityManagerInterface $em,
        JobCache $jobCache
    )
    {
        $this->_logger = $logger;
        $this->_em = $em;
        $this->_ldap = $ldap;
        $this->_jobCache = $jobCache;

        parent::__construct();
    }

    private function warmupCache($output)
    {
        $repository = $this->_em->getRepository(\App\Entity\RunningJob::class);
        $jobs = $repository->findAll();

        foreach ( $jobs as $job ){
            if ( $job->getNumNodes() > 0 ) {
                $this->_jobCache->warmupCache($job);
                $this->_em->persist($job);
                $this->_em->flush();
            }
        }
    }

    private function syncUsers($output)
    {
        $results = $this->_ldap->queryGroups();
        $groups = array();
        $userGroup = array();
        $activeUsers = array();

        foreach ( $results as $entry ) {

            $group_id = 'no_group';
            $gid= '000';
            $members = array();

            if ( $entry->hasAttribute('cn') ) {
                $str = $entry->getAttribute('cn')[0];
		$group_id = str_replace("hpc_","", $str);
            }
            if ( $entry->hasAttribute('gidNumber') ) {
                $gid = $entry->getAttribute('gidNumber')[0];
            }
            if ( $entry->hasAttribute('memberUid') ) {
                $members = $entry->getAttribute('memberUid');
            }

            $groups[$group_id] = array(
                'group_id' => $group_id,
                'gid' => $gid,
                'members' => $members
            );

            foreach ( $members as $user ) {
                $userGroup[$user][] = $group_id;

                if ( $group_id === 'infohpc' ) {
                    $activeUsers[$user] = 1;
                }
            }
        }

        $results = $this->_ldap->queryUsers();
        $users = array();

        foreach ( $results as $entry ) {

            $user_id;
            $uid;
            $name;
            $active;
            $groupsUser;

            if ( $entry->hasAttribute('uid') ) {
                $user_id = $entry->getAttribute('uid')[0];
            }
            if ( $entry->hasAttribute('uidNumber') ) {
                $uid = $entry->getAttribute('uidNumber')[0];
            }
            if ( $entry->hasAttribute('gecos') ) {
                $name = $entry->getAttribute('gecos')[0];
            }
            if ( array_key_exists($user_id, $activeUsers) ) {
                $active = 1;
            } else {
                $active = 0;
            }
            if ( array_key_exists($user_id, $userGroup) ) {
                $groupsUser = $userGroup[$user_id];
            } else {
                $groupsUser = array();
            }

            $users[$user_id] = array(
                'user_id'  => $user_id,
                'uid'      => $uid,
                'name'     => $name,
                'email'    => $user_id.'@mailhub.uni-erlangen.de',
                'active'   => $active,
                'groups'   => $groupsUser
            );
        }

        /* get current DB tables */
        $userRepo = $this->_em->getRepository(\App\Entity\User::class);
        $usersDB = $userRepo->findAll();

        $groupRepo = $this->_em->getRepository(\App\Entity\UnixGroup::class);
        $groupsDB = $groupRepo->findAll();

        /* update groups */
        foreach  ( $groups as $group ){
		    $groupId = $group['group_id'];

            if (! array_key_exists($groupId, $groupsDB) ) {
                $this->_logger->info("CRON:syncUsers Add group $groupId");
		$output->writeln("Add group $groupId");

                $newGroup = new UnixGroup();
                $newGroup->setGroupId($group['group_id']);
                $newGroup->setGid($group['gid']);
                $this->_em->persist($newGroup);
	    }
        }
        $this->_em->flush();

        /* update users */
        foreach  ( $users as $user ){
            $userId = $user['user_id'];

            if ( array_key_exists($userId, $usersDB) ) {
                $name = $user['name'];
                $DbUser = $usersDB[$userId];

                if ( $name !== $DbUser->getName() ){
		$output->writeln("Change name for $userId");
                    $this->_logger->info("CRON:syncUsers Change name for $userId");
                    $DbUser->setName($name);
                    $this->_em->persist($DbUser);
                }
            } else {
		$output->writeln("Add user $userId");
                $this->_logger->info("CRON:syncUsers Add user $userId");

                $newUser = new User();
                $newUser->setUsername($user['user_id']);
                $newUser->setUid($user['uid']);
                $newUser->setName($user['name']);
                $newUser->setEmail($user['email']);
                $newUser->setIsActive('false');

                foreach  ( $user['groups'] as $group ) {
		$output->writeln("Add user $userId to $group");
                    $this->_logger->info("CRON:syncUsers Add $userId to $group");
                    $dbGroup = $groupRepo->findOneBy(['groupId' => $group]);
                    $newUser->addGroup($dbGroup);
                }

                $this->_em->persist($newUser);
            }
        }
        $this->_em->flush();

        $userRepo->resetActiveUsers($activeUsers);
    }

    protected function configure()
    {
        $this
            ->setName('app:cron')
            ->setDescription('Cron job execution manager.')
            ->setHelp('This command allows to sync users and groups from a ldap server.')
            ->addArgument('task', InputArgument::REQUIRED, 'Task to perform')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $task = $input->getArgument('task');

        if ( $task === 'syncUsers' ){
            $this->syncUsers($output);
        } else if ( $task === 'warmupCache' ){
            $this->warmupCache($output);
        }
    }
}

