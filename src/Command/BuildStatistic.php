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

use App\Repository\JobRepository;
use App\Service\JobCache;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\StatisticsControl;
use \DateInterval;

class BuildStatistic extends Command
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
            ->setName('app:job:avg')
            ->setDescription('Compute and store job averages.')
            ->setHelp('This command computes job averages of metrics in the stat metric list and stores them in the job database.')
            ->addArgument('month', InputArgument::OPTIONAL, 'Apply for month. Month is e.g. 2018-05.')
            ->addOption( 'running', 'r', InputOption::VALUE_NONE, 'Apply for all running jobs. Month argument is ignored.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobs;
        $month = $input->getArgument('month');
        $running = $input->getOption('running');

        $output->writeln([
            'Build job averages',
            '==================',
            '',
        ]);

        if ( $running ){
            $repository = $this->_em->getRepository(\App\Entity\RunningJob::class);
            $jobs = $repository->findAvgTodo();
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
        $progressBar->setRedrawFrequency(25);

        $output->writeln([
            "Processing $jobCount jobs!",
            '',
        ]);

        $progressBar->start();

        foreach ( $jobs as $job ){
            $this->_jobCache->updateJobAverage($job);
            $this->_em->persist($job);
            $this->_em->flush();
            $progressBar->advance();
        }

        $progressBar->finish();
    }
}


