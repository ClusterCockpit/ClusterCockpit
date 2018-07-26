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
use Doctrine\ORM\EntityManagerInterface;

use App\Repository\JobRepository;
use App\Service\JobCache;
use App\Entity\JobSearch;
use App\Entity\Job;
use \DateInterval;

/**
 * Class: BuildCache
 *
 * @see Command
 * @author Jan Eitzinger
 * @version 0.1
 */
class BuildCache extends Command
{
    private $_em;
    private $_jobCache;

    public function __construct(
        EntityManagerInterface $em,
        JobCache $jobCache
    )
    {
        $this->_em = $em;
        $this->_jobCache = $jobCache;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:job:cache')
            ->setDescription('Manage job cache')
            ->setHelp('This command enables to build and drop job caches.')
            ->addArgument('cmd', InputArgument::REQUIRED, 'Command: build or drop.')
            ->addArgument('month', InputArgument::OPTIONAL, 'Apply for month. Month is e.g. 2018-05.')
            ->addOption( 'running', 'r', InputOption::VALUE_NONE, 'Apply for all running jobs. Month argument is ignored.')
            ->addOption( 'numpoints', 'n', InputOption::VALUE_REQUIRED, 'Cache is build for jobs with more points than numpoints.', '5000')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobs;
        $month = $input->getArgument('month');
        $running = $input->getOption('running');
        $numpoints = $input->getOption('numpoints');

        $output->writeln([
            "Job cache: $cmd",
            '================',
            ''
        ]);

        if ( $running ){
            $repository = $this->_em->getRepository(\App\Entity\RunningJob::class);
            $jobs = $repository->findAll();
        } else {
            $repository = $this->_em->getRepository(\App\Entity\Job::class);

            if (empty($month)) {
                $month = date('Y-m');
            }

            $starttime = strtotime("$month");
            $stoptime = strtotime("+1 month $month");
            $output->writeln([
                "Search jobs from $starttime to $stoptime",
                '',
            ]);

            $jobs = $repository->findAvgTodo($starttime, $stoptime);
        }

        $jobCount = count($jobs);
        $progressBar = new ProgressBar($output, $jobCount);
        $progressBar->setRedrawFrequency(10);

        $output->writeln([
            "Processing $jobCount jobs!",
            '',
        ]);

        $progressBar->start();

        foreach ( $jobs as $job ){
            if ( $job->isRunning() ){
                $this->_jobCache->updateJobAverage($job);
            }
            $this->_jobCache->buildCache($job, array('point' => $numpoints));

            $this->_em->persist($job);
            $this->_em->flush();
            $progressBar->advance();
        }
        $progressBar->finish();
    }
}
