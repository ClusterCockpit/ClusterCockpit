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
use App\Entity\Job;
use App\Entity\User;
use App\Service\JobData;
use App\Service\ClusterConfiguration;

class ExportJob extends Command
{
    private $_em;
    private $_filesystem;
    private $_root;
    private $_jobData;
    private $_clusterCfg;

    public function __construct(
        EntityManagerInterface $em,
        JobData $jobData,
        ClusterConfiguration $clusterCfg,
        $projectDir,
        FileSystem $filesystem
    )
    {
        $this->_em = $em;
        $this->_jobData = $jobData;
        $this->_clusterCfg = $clusterCfg;
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
            ->addArgument('cluster', InputArgument::REQUIRED, 'The cluster for the job to export.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobId = $input->getArgument('id');
        $clusterId = $input->getArgument('cluster');
        $repository = $this->_em->getRepository(\App\Entity\Job::class);

        $output->writeln([
            'Job File Export',
            '===============',
            '',
        ]);

        $job = $repository->findOneBy(['jobId' => $jobId, 'clusterId' => $clusterId]);
        if ($job == null) {
            $output->writeln("Job does not exist");
            return 1;
        }

        $rows = array();
        $rows[] = array("DB Id", $job->id);
        $rows[] = array("Job Id", $job->getJobId());
        $rows[] = array("User Id", $job->getUserId());
        $rows[] = array("NumNodes", $job->getNumNodes());
        $rows[] = array("Duration [h]",  $job->getDuration()/3600);
        $rows[] = array("Start time",date('r', $job->getStartTime()));
        $rows[] = array("Stop time", date('r', $job->getStartTime() + $job->getDuration()));

        $table = new Table($output);
        $table
            ->setHeaders(array('Label', 'Value'))
            ->setRows($rows);
        $table->render();

        $cluster = $this->_clusterCfg->getClusterConfiguration($clusterId);
        $allMetrics = array_keys($cluster['metricConfig']);
        $jobData = $this->_jobData->getData($job, $allMetrics);
        if ($jobData === false) {
            $output->writeln("Job has no profile!");
            return 1;
        }

        $level1 = $jobId / 1000;
        $level2 = $jobId % 1000;
        $dstPath = sprintf("%s/%d/%03d", $this->_root, $level1, $level2);
        try {
            $this->_filesystem->mkdir($dstPath);
        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating job export directory at ".$exception->getPath();
        }
        $output->writeln(['Export to ', $dstPath]);

        $jsonMeta = [
            'job_id' => $jobId,
            'user_id' => $job->getUserId(),
            'project_id' => $job->getProjectId(),
            'cluster_id' => $clusterId,
            'num_nodes' => $job->getNumNodes(),
            'nodes' => $job->getNodeArray(),
            'tags' => $job->getTagsArray(),
            'start_time' => $job->getStartTime(),
            'stop_time' => $job->getStartTime() + $job->getDuration(),
            'duration' => $job->getDuration(),
            'statistics' => [],
        ];
        $jsonData = [];

        foreach ($jobData as $data) {
            $unit = $data['metric']['unit'];
            $series = $data['metric']['series'];
            $min = $series[0]['statistics']['min'];
            $max = $series[0]['statistics']['max'];
            $avg = $series[0]['statistics']['avg'];

            for ($i = 1; $i < count($series); $i++) {
                $stats = $series[$i]['statistics'];
                $min = min($min, $stats['min']);
                $max = max($max, $stats['max']);
                $avg += $stats['avg'];
            }

            $avg /= count($series);
            $jsonMeta['statistics'][$data['name']] = [
                'unit' => $unit,
                'min' => $min,
                'max' => $max,
                'avg' => $avg
            ];

            $jsonData[$data['name']] = $data['metric'];
        }

        $this->_filesystem->dumpFile($dstPath.'/meta.json', json_encode($jsonMeta));
        $this->_filesystem->dumpFile($dstPath.'/data.json', json_encode($jsonData));
        return 0;
    }
}
