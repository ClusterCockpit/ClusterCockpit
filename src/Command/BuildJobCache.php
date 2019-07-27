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

use App\Repository\JobRepository;
use App\Repository\RunningJobRepository;
use App\Service\JobCache;
use App\Service\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\JobSearch;
use App\Entity\Job;
use App\Entity\Plot;
use \DateTime;
use \DateInterval;

/**
 * Class: BuildCache
 *
 * @see Command
 * @author Jan Eitzinger
 * @version 0.1
 */
class BuildJobCache extends Command
{
    private $_em;
    private $_jobCache;
    private $_configuration;

    public function __construct(
        EntityManagerInterface $em,
        JobCache $jobCache
    )
    {
        $this->_em = $em;
        $this->_jobcache = $jobCache;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:jobcache:build')
            ->setDescription('Warmup job cache')
            ->setHelp('This command builds or rebuilds the job cache for certain days.')
            ->addArgument('day', InputArgument::OPTIONAL, 'Apply for day. Day is e.g. 2018-05-30.')
            ->addOption( 'numpoints', 'N', InputOption::VALUE_REQUIRED, 'Cache is build for jobs with more points than numpoints.', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $this->_configuration = new Configuration($this->_em);
        $jobs;
        $day = $input->getArgument('day');

        $numpoints = $input->getOption('numpoints');

        $output->writeln([
            '==================',
            ' Build job cache',
            '==================',
            '',
        ]);

        $repository = $this->_em->getRepository(\App\Entity\Job::class);

        if (empty($day)) {
            $day = date('Y-m-d');
        }

        $startFrom = strtotime("$day");
        $startTo = strtotime("+1 day $day");
        $output->writeln([
            "Search jobs from $startFrom to $startTo",
            '',
        ]);

        $jobs = $repository->findByStartTime($startFrom, $startTo);
        $jobCount = count($jobs);
        $progressBar = new ProgressBar($output, $jobCount);
        $progressBar->setRedrawFrequency(25);

        $output->writeln([
            "Processing $jobCount jobs!",
            '',
        ]);

        $options['plot_view_showPolarplot']      = $this->_configuration->getValue('plot_view_showPolarplot');
        $options['plot_view_showRoofline']       = $this->_configuration->getValue('plot_view_showRoofline');
        $options['plot_view_showStatTable']      = $this->_configuration->getValue('plot_view_showStatTable');
        $options['plot_list_samples']            = $this->_configuration->getValue('plot_list_samples');
        $options['plot_general_colorBackground'] = $this->_configuration->getValue('plot_general_colorBackground');
        $options['plot_general_colorscheme']     = $this->_configuration->getValue('plot_general_colorscheme');
        $options['plot_general_lineWidth']       = $this->_configuration->getValue('plot_general_lineWidth');
        $options['data_time_digits']             = $this->_configuration->getValue('data_time_digits');
        $options['data_cache_numpoints']         = $this->_configuration->getValue('data_cache_numpoints');

        $progressBar->start();
        $stopwatch->start('BuildCache');

        foreach ( $jobs as $job ) {
            $progressBar->advance();

            if ( $job->getNumNodes() > 0 ) {
                $this->_jobcache->warmupCache(
                    $job, $options);
                $this->_em->persist($job);
                $this->_em->flush();
            }
        }
        $event = $stopwatch->stop('BuildCache');
        $progressBar->finish();

        $seconds =  floor($event->getDuration()/ 1000);
        $d1 = new DateTime();
        $d2 = new DateTime();
        $d2->add(new DateInterval('PT'.$seconds.'S'));
        $iv = $d2->diff($d1);

        $output->writeln([
            $iv->format('%h h %i m')
        ]);

    }
}
