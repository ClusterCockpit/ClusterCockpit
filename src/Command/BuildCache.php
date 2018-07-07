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
            ->setName('app:cache:build')
            ->setDescription('Build job cache')
            ->setHelp('This command builds the Job cache for view and list mode.')
            ->addArgument('jobId', InputArgument::OPTIONAL, 'Drop job cache specific Job Id.')
            ->addOption( 'numnodes', null, InputOption::VALUE_REQUIRED,
                'Range of number of nodes in jobs', '7-64')
                ->addOption( 'duration', null, InputOption::VALUE_REQUIRED,
                    'Range of job duration in hours', '4-24')
                    ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->_em->getRepository(\App\Entity\RunningJob::class);
        $jobs;

        $nodeRange = explode('-', $input->getOption('numnodes'));
        $duration = explode('-', $input->getOption('duration'));

        if (count($nodeRange)!=2 or count($duration)!=2){
            $output->writeln([
                'ERROR',
            ]);
            exit;
        }
        $fromDuration =  sprintf('PT%dH',$duration[0]);
        $toDuration =  sprintf('PT%dH',$duration[1]);

        $output->writeln([
            'Job cache: BUILD',
            '================',
            "Nodes: $nodeRange[0] to $nodeRange[1]",
            "Duration: $duration[0] to $duration[1] h",
            ''
        ]);

        $repository = $this->_em->getRepository(\App\Entity\RunningJob::class);
        /* $startTime = time() - 600; */
        $startTime = 1521057932;
        $jobs = $repository->findRunningJobs($startTime);

        $jobCount = count($jobs);
        $progressBar = new ProgressBar($output, $jobCount);
        $progressBar->setRedrawFrequency(10);

        $output->writeln([
            "$jobCount jobs match.",
            '',
        ]);

        $progressBar->start();

        foreach ( $jobs as $job ){
            $progressBar->advance();
            $this->_jobCache->buildCacheRunning($job);
            $this->_jobCache->updateJobAverage($job, $repository);
            $this->_em->persist($job);
            $this->_em->flush();
        }
        $progressBar->finish();
    }
}
