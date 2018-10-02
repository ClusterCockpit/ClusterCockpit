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

namespace App\Controller\API;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\View;
use App\Entity\Job;
use App\Entity\RunningJob;
use App\Entity\Cluster;
use App\Entity\User;
use App\Entity\Node;
use App\Entity\Project;
use App\Service\JobCache;
use App\Service\Configuration;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;

/**
 * @RouteResource("Jobs", pluralize=false)
 */
class JobListController extends FOSRestController
{
    private $_jobCache;
    private $_configuration;
    private $_authChecker;

    public function __construct(
        JobCache $jobCache,
        AuthorizationCheckerInterface $authChecker,
        Configuration $configuration
    )
    {
        $this->_jobCache = $jobCache;
        $this->_configuration = $configuration;
        $this->_authChecker = $authChecker;
    }

    public function getAction($slug)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $this->_configuration->getUserConfig($this->getUser());
        $userId = 0;

        if ( false === $this->_authChecker->isGranted('ROLE_ADMIN') ) {
            $userId = $this->getUser()->getId();
        }

        $repository = $this->getDoctrine()->getRepository(\App\Entity\RunningJob::class);
        $test = $repository->findJobById($slug, $userId);

        if (empty($test)) {
            $repository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
            $job = $repository->findJobById($slug, $userId);
        } else {
            $job = $test[0];
        }

        if (empty($job)) {
            throw new HttpException(400, "No such job $slug.");
        }

        $this->_jobCache->checkCache(
            $job,
            'view',
            $config
        );

        $jobData = array(
            "jobinfo" => array(
                "jobid" => $job->getJobId(),
                "username" => $job->getUser()->getUserId(),
                "userid" => $job->getUser()->getId(),
                "numnodes" => $job->getNumNodes(),
                "runtime" => $job->getDuration(),
                "starttime" => $job->getStartTime()
            ),
            "numNodes" => $job->getNumNodes(),
            "startTime" => $job->getStartTime(),
            "duration" => sprintf("%.02f",$job->getDuration()/3600),
        );

        if( $job->hasProfile ){
            $jobData["plots"] =  $job->jobCache->getPlotsArray(
                    $job->getCluster()->getMetricList('view')->getMetrics()
                );

            $plot = $job->jobCache->getPlot('roofline');
            $jobData["plots"][] =  array(
                'name' => $plot->name,
                'options' => $plot->options,
                'data' => $plot->data
            );

            $plot = $job->jobCache->getPlot('polarplot');
            $jobData["plots"][] =  array(
                'name' => $plot->name,
                'options' => $plot->options,
                'data' => $plot->data
            );

            $jobData['nodeStats'] = $job->jobCache->nodeStat;
        } else {
            $jobData["severity"] = 0;
            $jobData["plots"] = false;
        }

        $view = $this->view($jobData);
        return $this->handleView($view);
    } // "get_job"             [GET] api/jobs/$slug

    /**
     * @QueryParam(name="draw", requirements="\d+")
     * @QueryParam(name="start", requirements="\d+")
     * @QueryParam(name="length", requirements="\d+")
     * @QueryParam(name="order")
     * @QueryParam(name="columns")
     * @QueryParam(name="search")
     * @QueryParam(name="jobSearch", nullable=true)
     */
    public function cgetAction( ParamFetcher $paramFetcher)
    {
        $draw = $paramFetcher->get('draw');
        $start = $paramFetcher->get('start');
        $length = $paramFetcher->get('length');
        $order = $paramFetcher->get('order');
        $search = $paramFetcher->get('search');
        $columns = $paramFetcher->get('columns');
        $jobSearch = $paramFetcher->get('jobSearch');

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $this->_configuration->getUserConfig($this->getUser());
        $userId = 0;

        if ( false === $this->_authChecker->isGranted('ROLE_ADMIN') ) {
            $userId = $this->getUser()->getId();
        }

        /* START setup column sorting */
        $index = $order[0]['column'];
        $direction = $order[0]['dir'];
        $sortMetrics = $this->getDoctrine()
                            ->getRepository(\App\Entity\TableSortConfig::class)
                            ->findMetrics(1);

        if ( $index != 0 ){
            $sortMetric = $sortMetrics[$columns[$index]['data']];

            if ( $sortMetric->getType() === 'data' ){
                $column = 'slot_'.$sortMetric->getSlot();
            } else {
                $column = $sortMetric->getAccessKey();
            }

            $sorting = array(
                'col'   => $column,
                'order' =>$direction
            );
        } else {
            $sorting = array(
                'col'   => $columns[1]['data'],
                'order' => 'desc'
            );
        }
        /* END setup column sorting */

        /* START setup job query */
        $filter = false;

        if ( is_null($jobSearch) ) { /* Running job table */
            $repository = $this->getDoctrine()->getRepository(\App\Entity\RunningJob::class);
            $total = $repository->countFilteredJobs($userId, false);
            $filtered = $total;

            if ( $search['value'] != ''){
                $filter = $search['value'];
                $filtered = $repository->countFilteredJobs($userId, $filter);
            }

            $jobs = $repository->findFilteredJobs($userId, $start, $length, $sorting, $filter);

            $url = 'running_job'; $isRunning = true;

        } else {  /* regular job table */
            $repository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
            $total = $repository->countFilteredJobs($userId, false, $jobSearch);
            $filtered = $total;


            if ( $search['value'] != ''){
                $filter = $search['value'];
                $filtered = $repository->countFilteredJobs($userId, $filter, $jobSearch);
            }

            $jobs = $repository->findFilteredJobs($userId, $start, $length, $sorting, $filter, $jobSearch);

            $url = 'job'; $isRunning = false;
       }
        /* STOP setup job query */

        /* START get performance profile and setup message data */
        foreach ( $jobs as $job ){
            $this->_jobCache->checkCache(
                $job,
                'list',
                $config
            );

            $jobData = array(
                "jobinfo" => array(
                    "jobid" => $job->getJobId(),
                    "username" => $job->getUser()->getUserId(),
                    "userid" => $job->getUser()->getId(),
                    "numnodes" => $job->getNumNodes(),
                    "runtime" => $job->getDuration(),
                    "starttime" => $job->getStartTime()
                )
            );

            if( $job->hasProfile ){
                foreach ( $sortMetrics as $metric ){
                    $name = $metric->getAccessKey();

                    if ( $metric->getType() === 'job' ){
                        $jobData[$name] = $job->{$name};
                    } else if ( $metric->getType() === 'data' ){
                        $slot = 'slot_'.$metric->getSlot();
                        $jobData[$name] = $job->{$slot};
                    }
                }

                $jobData["plots"] = array(
                    'id' => $job->getId(),
                    'url' => $url,
                    'plots' => $job->jobCache->getPlotsArray(
                        $job->getCluster()->getMetricList('list')->getMetrics()
                    ));
            } else {
                foreach ( $sortMetrics as $metric ){
                    $name = $metric->getAccessKey();
                    $jobData[$name] = 0;
                }

                $jobData["plots"] = false;
            }

            $tableData[] = $jobData;
        }
        /* STOP get performance profile and setup message data */

        if (count($jobs) == 0) {
            $tableData = array();
        }

        $view = $this->view(array(
            "draw" => (int) $draw,
            "isRunning" => $isRunning,
            "recordsTotal" => $total,
            "recordsFiltered" => $filtered,
            "data" => $tableData
        ));
        return $this->handleView($view);
    } // "get_jobs"             [GET] /web/joblist/
}
