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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Doctrine\ORM\EntityManagerInterface;
use \DateInterval;

use App\Repository\JobRepository;
use App\Service\JobCache;
use App\Service\ColorMap;
use App\Service\Configuration;
use App\Entity\JobSearch;
use App\Entity\Job;
use App\Entity\Plot;
use App\Entity\User;

class ExportJob extends Command
{
    private $_em;
    private $_filesystem;
    private $_root;
    private $_jobcache;

    public function __construct(
        EntityManagerInterface $em,
        JobCache $jobCache,
        $projectDir,
        FileSystem $filesystem
    )
    {
        $this->_em = $em;
        $this->_jobcache = $jobCache;
        $this->_filesystem = $filesystem;
        $this->_root = $projectDir.'/var/export';

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:job:export')
            ->setDescription('Export a job to disk')
            ->setHelp('.')
            ->addArgument('id', InputArgument::REQUIRED, 'The jobID for the job to export.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $repository = $this->_em->getRepository(\App\Entity\Job::class);
        $configuration = new Configuration($this->_em);

        $output->writeln([
            'Job File Export',
            '===============',
            '',
        ]);

        $job = $repository->findOneBy(['jobId' => $id]);

        $rows = array();
        $rows[] = array("Job id",$job->getJobId());
        $rows[] = array("User name",$job->getUser()->getName());
        $rows[] = array("User id",$job->getUser()->getUserId());
        $rows[] = array("NumNodes",$job->getNumNodes());
        $rows[] = array("Duration [h]",  $job->getDuration()/3600);
        $rows[] = array("Start time",date('r', $job->getStartTime()));
        $rows[] = array("Stop time", date('r', $job->getStopTime()));

        $table = new Table($output);
        $table
            ->setHeaders(array('Label', 'Value'))
            ->setRows($rows);
        $table->render();

        $this->_jobcache->getArchive($job);

        if ( $job->hasProfile ) {
            $jobID = $job->getJobId();
            $jobID = str_replace(".eadm", "", $jobID);
            $level1 = $jobID/1000;
            $level2 = $jobID%1000;
            $dstPath = sprintf("%s/%d/%03d", $this->_root, $level1, $level2);

            try {
                $this->_filesystem->mkdir($dstPath);
            } catch (IOExceptionInterface $exception) {
                echo "An error occurred while creating job export directory at ".$exception->getPath();
            }

            $duration = $job->getDuration();
            $jobCache = $job->jobCache;
            $jsonMeta = array();
            $jsonMeta['job_id'] = $job->getJobId();
            $jsonMeta['user_id'] = $job->getUser()->getUserId();
            $jsonMeta['project_id'] = 'no project';
            $jsonMeta['cluster_id'] = $job->getCluster()->getName();
            $jsonMeta['num_nodes'] = $job->getNumNodes();
            $jsonMeta['walltime'] = 10;
            $jsonMeta['job_state'] = 'completed';
            $jsonMeta['start_time'] = $job->getStartTime();
            $jsonMeta['stop_time'] = $job->getStopTime();
            $jsonMeta['duration'] = $duration;
            $jsonMeta['nodes'] = $job->getNodeIdArray();
            $jsonMeta['tags'] = $job->getTagsArray();
            $output->writeln(['Export to ', $dstPath]);

            $jsonData = array();
            $plots = $jobCache->getPlots();
            $statistics = array();

            foreach ( $plots as $plot ) {
                $nodes = $plot->getData();
                $options = $plot->getOptions();
                $statData = array();

                $metricData     = array(
                    'unit'     => $options['unit'],
                    'scope'    => 'node',
                    'timestep' => $options['timestep'],
                    'series'   => array()
                );

                $sum = 0.0;
                $max = 0.0;
                $min = PHP_FLOAT_MAX;
                $count = 0;

                foreach ($nodes as $node){
                    $length = count($node['y']);
                    $data = array();
                    $sum_node = 0.0;
                    $max_node = 0.0;
                    $min_node = PHP_FLOAT_MAX;
                    $count_node = 0;

                    for ($j=1; $j<$length-1; $j++) {
                        if (is_null($node['y'][$j])) {
                            $data[] = NULL;
                        } else {
                            $sum_node += $node['y'][$j];
                            $max_node = $max_node>$node['y'][$j]?$max_node:$node['y'][$j];
                            $min_node = $min_node<$node['y'][$j]?$min_node:$node['y'][$j];
                            $count_node++;
                            $data[] = round($node['y'][$j],2);
                        }
                    }

                    $sum += $sum_node;
                    $max = $max>$max_node?$max:$max_node;
                    $min = $min<$min_node?$min:$min_node;
                    $count += $count_node;

                    $metricData['series'][] = array(
                        'node_id' => $node['name'],
                        'statistics' => array(
                            'avg'  => round($sum_node / $count_node, 2),
                            'min'  => round($min_node, 2),
                            'max'  => round($max_node, 2)
                        ),
                        'data' => $data
                    );
                }

                $statistics[$plot->name] = array(
                    'unit' => $options['unit'],
                    'avg'  => round($sum / $count, 2),
                    'min'  => round($min, 2),
                    'max'  => round($max, 2)
                );

                $jsonData[$plot->name] = $metricData;
            }

            $jsonMeta['statistics'] = $statistics;
            $this->_filesystem->dumpFile($dstPath.'/meta.json', json_encode($jsonMeta));
            $this->_filesystem->dumpFile($dstPath.'/data.json', json_encode($jsonData));
        } else {
            $output->writeln("Job has no profile!");
        }
    }
}

