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
use App\Adapter\LdapManager;
use App\Service\JobCache;
use App\Service\PasswdFileReader;
use App\Service\Configuration;
use App\Entity\User;
use App\Entity\UnixGroup;
use Psr\Log\LoggerInterface;
use \DateTimeZone;
use \DateTime;
use \DateInterval;

class Cron extends Command
{
    private $_em;
    private $_configuration;
    private $_ldap;
    private $_jobCache;
    private $_cache;
    private $_timer;
    private $_logger;

    public function __construct(
        LdapManager $ldap,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        JobCache $jobcache,
        StopWatch $stopwatch
    )
    {
        $this->_em = $em;
        $this->_ldap = $ldap;
        $this->_timer = $stopwatch;
        $this->_logger = $logger;
        $this->_jobCache = $jobcache;

        parent::__construct();
    }

    private function warmupCache($output)
    {
        $repository = $this->_em->getRepository(\App\Entity\Job::class);
        $jobs = $repository->findRunningJobs();
        $this->_configuration = new Configuration($this->_em);

        $options['plot_view_showPolarplot']      = $this->_configuration->getValue('plot_view_showPolarplot');
        $options['plot_view_showRoofline']       = $this->_configuration->getValue('plot_view_showRoofline');
        $options['plot_view_showStatTable']      = $this->_configuration->getValue('plot_view_showStatTable');
        $options['plot_list_samples']            = $this->_configuration->getValue('plot_list_samples');
        $options['plot_general_colorBackground'] = $this->_configuration->getValue('plot_general_colorBackground');
        $options['plot_general_colorscheme']     = $this->_configuration->getValue('plot_general_colorscheme');
        $options['plot_general_lineWidth']       = $this->_configuration->getValue('plot_general_lineWidth');
        $options['data_time_digits']             = $this->_configuration->getValue('data_time_digits');
        $options['data_cache_numpoints']         = $this->_configuration->getValue('data_cache_numpoints');

        $this->_timer->start('WarmupCache');
        foreach ( $jobs as $job ){

            if ( $job->getNumNodes() > 0 && $job->duration > 400 ) {
                $this->_jobCache->warmupCache(
                    $job, $options);
                $this->_em->persist($job);
                $this->_em->flush();
            }
        }
        $event = $this->_timer->stop('WarmupCache');
        $seconds =  floor($event->getDuration()/ 1000);
        $count = count($jobs);
        $this->_logger->info("CRON:warmupCache $count jobs in $seconds s");
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


            if ( ! $this->_jobCache->hasCache($job, 'view') ) {
                $job->isCached = false;
                $this->_em->persist($job);
                $this->_em->flush();
            }
        }

        if ( $interactive ){
            $progressBar->finish();
            $progressBar->clear();
        }

        $options['plot_view_showPolarplot']      = $this->_configuration->getValue('plot_view_showPolarplot');
        $options['plot_view_showRoofline']       = $this->_configuration->getValue('plot_view_showRoofline');
        $options['plot_view_showStatTable']      = $this->_configuration->getValue('plot_view_showStatTable');
        $options['plot_list_samples']            = $this->_configuration->getValue('plot_list_samples');
        $options['plot_general_colorBackground'] = $this->_configuration->getValue('plot_general_colorBackground');
        $options['plot_general_lineWidth']       = $this->_configuration->getValue('plot_general_lineWidth');
        $options['data_cache_numpoints']         = $this->_configuration->getValue('data_cache_numpoints');

        $days = $this->_configuration->getValue('data_cache_period');
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

            $this->_jobCache->warmupCache($job, $options);
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

    private function _syncUsers($output, $users)
    {
        /* get current DB tables */
        $userRepo = $this->_em->getRepository(\App\Entity\User::class);
        $usersDB = $userRepo->findAll();

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
                $newUser->setName($user['name']);
                $newUser->setEmail($user['email']);
                $newUser->setIsActive('true');
                $this->_em->persist($newUser);
            }
        }
        $this->_em->flush();

        /* $userRepo->resetActiveUsers($activeUsers); */
    }

    private function importUsers($output, $filename)
    {
        $fileReader = new PasswdFileReader();
        $users = $fileReader->parse($filename, $this->_configuration->getValue('general_user_emailbase'));
        $this->_syncUsers($output, $users);
    }

    private function syncUsers($output)
    {
        $config['ldap_connection_url'] = $this->_configuration->getValue('ldap_connection_url');
        $config['ldap_search_dn'] = $this->_configuration->getValue('ldap_search_dn');
        $config['ldap_user_base'] = $this->_configuration->getValue('ldap_user_base');
        $config['ldap_user_filter'] = $this->_configuration->getValue('ldap_user_filter');

        $this->_timer->start('syncUsers');
        $results = $this->_ldap->queryUsers($config);
        $users = array();

        foreach ( $results as $entry ) {
            $user_id;
            $name;
            $active;
            $groupsUser;

            if ( $entry->hasAttribute('uid') ) {
                $user_id = $entry->getAttribute('uid')[0];
            }
            if ( $entry->hasAttribute('gecos') ) {
                $name = $entry->getAttribute('gecos')[0];
            }

            $active = 1;

            $users[$user_id] = array(
                'user_id'  => $user_id,
                'name'     => $name,
                'email'    => $user_id.$this->_configuration->getValue('general_user_emailbase'),
                'active'   => $active
            );
        }

        $this->_syncUsers($output, $users);
        $event = $this->_timer->stop('syncUsers');
        $seconds =  floor($event->getDuration()/ 1000);
        $this->_logger->info("CRON:syncUsers  $seconds s");
    }

    protected function configure()
    {
        $this
            ->setName('app:cron')
            ->setDescription('Cron job execution manager.')
            ->setHelp('This command allows to execute the following tasks: syncUsers, importUsers, warmupCache, updateCache')
            ->addArgument('task', InputArgument::REQUIRED, 'Task to perform')
            ->addOption(
                'interactive',
                'i', InputOption::VALUE_NONE,
                'Progress output on stdout.')
            ->addOption(
                'filename',
                'f', InputOption::VALUE_REQUIRED,
                'Input file.', false)
        ;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output)
    {
        $d = new DateTime('NOW', new DateTimeZone('Europe/Berlin'));
        $this->_configuration = new Configuration($this->_em);
        $datestr = $d->format('Y-m-d\TH:i:s');
        $task = $input->getArgument('task');
        $interactive = $input->getOption('interactive');
        $filename = $input->getOption('filename');
        $this->_logger->info("CRON Start $task at $datestr");

        if ( $task === 'syncUsers' ){
            $this->syncUsers($output);
        } else if ( $task === 'importUsers' ){
            if ( $filename !== false ){
                $this->importUsers($output, $filename);
            } else {
                $output->writeln("CRON Error: You have to specify the filename option for the importUsers task !");
            }
        } else if ( $task === 'warmupCache' ){
            $this->warmupCache($output);
        } else if ( $task === 'updateCache' ){
            $this->updateCache($output, $interactive);
        } else {
            $output->writeln("CRON Error: Unknown command $task !");
        }
    }
}
