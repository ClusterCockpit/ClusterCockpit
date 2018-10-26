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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Stopwatch\Stopwatch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use App\Adapter\LdapManager;
use App\Service\JobCache;
use App\Entity\User;
use App\Entity\UnixGroup;
use Psr\Log\LoggerInterface;
use App\Service\Configuration;
use \DateTimeZone;
use \DateTime;
use \DateInterval;

class Cron extends Command
{
    private $_logger;
    private $_em;
    private $_ldap;
    private $_jobCache;
    private $_cache;
    private $_configuration;
    private $_timer;

    public function __construct(
        LdapManager $ldap,
        LoggerInterface $logger,
        Configuration $configuration,
        EntityManagerInterface $em,
        StopWatch $stopwatch,
        JobCache $jobCache,
        AdapterInterface $cache
    )
    {
        $this->_logger = $logger;
        $this->_em = $em;
        $this->_ldap = $ldap;
        $this->_timer = $stopwatch;
        $this->_configuration = $configuration;
        $this->_jobCache = $jobCache;
        $this->_cache = $cache;

        parent::__construct();
    }

    private function warmupCache($output)
    {
        $repository = $this->_em->getRepository(\App\Entity\Job::class);
        $jobs = $repository->findRunningJobs();

        $this->_timer->start('WarmupCache');
        foreach ( $jobs as $job ){

            if ( $job->getNumNodes() > 0 ) {
                $this->_jobCache->warmupCache(
                    $job, $this->_configuration->getConfig());
                $this->_em->persist($job);
                $this->_em->flush();
            }
        }
        $event = $this->_timer->stop('WarmupCache');
        $duration = $event->getDuration()/ 1000;
        $count = count($jobs);
        $this->_logger->info("CRON:warmupCache $count jobs in $duration s");
    }

    private function updateCache($output, $interactive)
    {
        $this->_timer->start('UpdateCache');
        $repository = $this->_em->getRepository(\App\Entity\Job::class);

        /* cleanup jobs with isCached flag set but cache not existing */
        $jobs = $repository->findCachedJobs();

        if ( $interactive ){
            $output->writeln(["STAGE 1: Sanitize"]);
            $jobCount = count($jobs);
            $progressBar = new ProgressBar($output, $jobCount);
            $progressBar->setRedrawFrequency(25);
            $progressBar->start();
        }

        foreach ( $jobs as $job ){

            if ( $interactive ){
                $progressBar->advance();
            }

            $item = $this->_cache->getItem($job->getJobId().'view');

            if ( ! $item->isHit()) {
                $job->isCached = false;
                $this->_em->persist($job);
                $this->_em->flush();
            }
        }

        if ( $interactive ){
            $progressBar->finish();
            $progressBar->clear();
        }

        $config = $this->_configuration->getConfig();
        $days = $config['data_cache_period']->value;
        $timestamp = strtotime("-$days day");

        /* delete cache for jobs outside grace period */
        $jobs = $repository->findJobsToClean($timestamp);

        if ( $interactive ){
            $output->writeln(["STAGE 2: Cleanup"]);
            $jobCount = count($jobs);
            $progressBar->setMaxSteps($jobCount);
            $progressBar->start();
        }

        foreach ( $jobs as $job ){

            if ( $interactive ){
                $progressBar->advance();
            }

            $this->_jobCache->dropCache($job);
            $this->_em->persist($job);
            $this->_em->flush();
        }

        if ( $interactive ){
            $progressBar->finish();
            $progressBar->clear();
        }

        /* build cache for jobs inside grace period */
        $jobs = $repository->findJobsToBuild($timestamp);

        if ( $interactive ){
            $output->writeln(["STAGE 3: Rebuild cache"]);
            $jobCount = count($jobs);
            $progressBar->setMaxSteps($jobCount);
            $progressBar->start();
        }

        foreach ( $jobs as $job ){

            if ( $interactive ){
                $progressBar->advance();
            }

            $this->_jobCache->warmupCache(
                $job, $this->_configuration->getConfig());
            $this->_em->persist($job);
            $this->_em->flush();
        }

        $event = $this->_timer->stop('UpdateCache');
        $seconds =  floor($event->getDuration()/ 1000);

        if ( $interactive ){
            $d1 = new DateTime();
            $d2 = new DateTime();
            $d2->add(new DateInterval('PT'.$seconds.'S'));
            $iv = $d2->diff($d1);

            $output->writeln([
                'Total runtime:',
                $iv->format('%h h %i m')
            ]);

            $progressBar->finish();
            $progressBar->clear();
        } else {
            $this->_logger->info("CRON:updateCache $seconds s");
        }
    }

    private function syncUsers($output)
    {
        $this->_timer->start('syncUsers');
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

        $event = $this->_timer->stop('syncUsers');
        $duration = $event->getDuration()/ 1000;
        $this->_logger->info("CRON:syncUsers  $duration s");
    }

    protected function configure()
    {
        $this
            ->setName('app:cron')
            ->setDescription('Cron job execution manager.')
            ->setHelp('This command allows to sync users and groups from a ldap server.')
            ->addArgument('task', InputArgument::REQUIRED, 'Task to perform')
            ->addOption( 'interactive', 'i', InputOption::VALUE_NONE, 'Progress output on stdout.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $d = new DateTime('NOW', new DateTimeZone('Europe/Berlin'));
        $datestr = $d->format('Y-m-d\TH:i:s');
        $task = $input->getArgument('task');
        $interactive = $input->getOption('interactive');
        $this->_logger->info("CRON Start $task at $datestr");

        if ( $task === 'syncUsers' ){
            $this->syncUsers($output);
        } else if ( $task === 'warmupCache' ){
            $this->warmupCache($output);
        } else if ( $task === 'updateCache' ){
            $this->updateCache($output, $interactive);
        } else {
            $output->writeln("CRON Error: Unknown command $task !");
        }
    }
}
