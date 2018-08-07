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
use App\Repository\RunningJobRepository;
use App\Service\JobCache;
use App\Service\ColorMap;
use App\Service\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\JobSearch;
use App\Entity\Job;
use App\Entity\Plot;
use App\Entity\TraceResolution;
use App\Entity\Trace;
use \DateInterval;

/**
 * Class: BuildSeverity
 *
 * @see Command
 * @author Jan Eitzinger
 * @version 0.1
 */
class BuildSeverity extends Command
{
    private $_em;
    private $_configuration;
    private $_jobCache;

    public function __construct(
        EntityManagerInterface $em,
        Configuration $configuration,
        JobCache $jobCache
    )
    {
        $this->_em = $em;
        $this->_configuration = $configuration;
        $this->_jobCache = $jobCache;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:job:severity')
            ->setDescription('Rebuild job severity metric')
            ->setHelp('This command builds or rebuild the job severity metric and stores it in the job database.')
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
            'Build job severity',
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
        }


        $search = new JobSearch();
        $search->setNumNodesFrom(1);
        $search->setNumNodesTo(64);
        $search->setDurationFrom(new DateInterval('PT1H'));
        $search->setDurationTo(new DateInterval('PT24H'));
        $search->setDateFrom($starttime);
        $search->setDateTo($stoptime);

        $jobs = $repository->findByJobSearch($search);
        /* $job = $repository->find(370036); */
        /* $jobs[] = $job; */
        /* $job = $repository->find(369875); */
        /* $jobs[] = $job; */

        $jobCount = count($jobs);
        $progressBar = new ProgressBar($output, $jobCount);
        $progressBar->setRedrawFrequency(25);

        $output->writeln([
            "Processing $jobCount jobs!",
            '',
        ]);

        $progressBar->start();

        $metrics = array(
            'flops_any',
            'mem_bw'
        );

        foreach ( $jobs as $job ) {
            $progressBar->advance();

            $this->_jobCache->checkCache(
                $job,
                array(
                    'mode' => 'data'
                ),
                $this->_configuration->getConfig()
            );

            if ( $job->hasProfile ) {
                $severity = 0;
                /* get thresholds for metrics */
                $metricsStat = $job->getCluster()->getMetricList('stat')->getMetrics();
                $jobCache = $job->jobCache;

                foreach ( $metrics as $metric ) {
                    $plots[$metric] = $jobCache->getPlot($metric);
                    $nodes[$metric] = $plots[$metric]->traceResolution->getTraces()->toArray();
                    $thresholds[$metric] = array(
                        'caution' => $metricsStat[$metric]->caution,
                        'alert' => $metricsStat[$metric]->alert
                    );
                }

                /* iterate over nodes */
                for ($i=0; $i<count($nodes['flops_any']); $i++) {
                    $dataFlops = $nodes['flops_any'][$i]->getData();
                    $dataMemBw = $nodes['mem_bw'][$i]->getData();

                    /* iterate over time */
                    for ($j=0; $j<count($dataFlops['x']); $j++) {

                        if ( $dataFlops['y'][$j] < $thresholds['flops_any']['alert'] ){
                            $severity += 10;
                        } elseif ( $dataFlops['y'][$j] < $thresholds['flops_any']['caution'] ) {
                            $severity += 5;
                        }

                        if ( $dataMemBw['y'][$j] < $thresholds['mem_bw']['alert'] ){
                            $severity += 10;
                        } elseif ( $dataMemBw['y'][$j] < $thresholds['mem_bw']['caution'] ) {
                            $severity += 5;
                        }
                    }
                }

                $job->severity = $severity;
                $repository->persistJobSeverity($job);

                /* $jobId = $job->getJobId(); */
                /* $output->writeln([ */
                /*     "Job: $jobId", */
                /*     "Severity: $severity", */
                /*     '', */
                /* ]); */
            }
        }
        $progressBar->finish();
    }
}


