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
use App\Service\JobCache;
use App\Service\Configuration;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use \DateTime;
use \DateInterval;

/**
 * @RouteResource("Jobs", pluralize=false)
 */
class JobListController extends AbstractFOSRestController
{
    private $_jobCache;
    private $_authChecker;
    private $_em;

    public function __construct(
        JobCache $jobCache,
        AuthorizationCheckerInterface $authChecker,
        EntityManagerInterface $em
    )
    {
        $this->_jobCache = $jobCache;
        $this->_authChecker = $authChecker;
        $this->_em = $em;
    }

    private function _createJobSorting($columns, $index, $direction, $sortMetrics)
    {
        if ( $index != 0 ){
            $sortMetric = $sortMetrics[$columns[$index]['data']];

            if ( $sortMetric->getType() === 'data' ){
                $column = 'slot_'.$sortMetric->getSlot();
            } else {
                $column = $sortMetric->getAccessKey();
            }

            $sorting = array(
                'col'   => $column,
                'order' => $direction
            );
        } else {
            $sorting = array(
                'col'   => $columns[1]['data'],
                'order' => 'desc'
            );
        }

        return $sorting;
    }

    private function _addJobPerformanceProfile(
        $job,
        $mode,
        $sortMetrics = NULL)
    {
        $configuration = new Configuration($this->_em);
        $config = $configuration->getUserConfig($this->getUser());

        $options['plot_view_showPolarplot']      = $config['plot_view_showPolarplot']->value;
        $options['plot_view_showRoofline']       = $config['plot_view_showRoofline']->value;
        $options['plot_view_showStatTable']      = $config['plot_view_showStatTable']->value;
        $options['plot_list_samples']            = $config['plot_list_samples']->value;
        $options['plot_general_colorBackground'] = $config['plot_general_colorBackground']->value;
        $options['plot_general_colorscheme']     = $config['plot_general_colorscheme']->value;
        $options['plot_general_lineWidth']       = $config['plot_general_lineWidth']->value;
        $options['data_time_digits']             = $config['data_time_digits']->value;

        $this->_jobCache->checkCache(
            $job,
            $mode,
            $options
        );

        $d1 = new DateTime();
        $d2 = new DateTime();
        $d2->add(new DateInterval('PT'.$job->duration.'S'));
        $iv = $d2->diff($d1);

        /* add job meta data */
        $jobData = array(
            "jobinfo" => array(
                "jobid" => $job->getJobId(),
                'id' => $job->getId(),
                "username" => $job->getUser()->getUserId(),
                "userid" => $job->getUser()->getId(),
                "numnodes" => $job->getNumNodes(),
                "runtime" => $iv->format('%h h %i m'),
                "starttime" => $job->getStartTime(),
                "tags" => $job->getTagsArray()
            )
        );

        if( $job->hasProfile ){
            if ( $mode === 'list' ){
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
                    'plots' => $job->jobCache->getPlotsArray(
                        $job->getCluster()->getMetricList($mode)->getMetrics()),
                    'plotOptions' => '{staticPlot: true}'
                );

            }

            if ( $mode === 'view' ){
                $jobData["plots"] = $job->jobCache->getPlotsArray(
                    $job->getCluster()->getMetricList($mode)->getMetrics());

                if ( $options['plot_view_showRoofline'] === 'true' ){
                    $plot = $job->jobCache->getPlot('roofline');
                    $jobData["plots"][] =  array(
                        'name' => $plot->name,
                        'options' => $plot->options,
                        'data' => $plot->data
                    );
                }

                if ( $options['plot_view_showPolarplot'] === 'true' ){
                    $plot = $job->jobCache->getPlot('polarplot');

                    $jobData["plots"][] =  array(
                        'name' => $plot->name,
                        'options' => $plot->options,
                        'data' => $plot->data
                    );
                }

                $jobData['nodeStats'] = $job->jobCache->nodeStat;
            }
        } else {
            if ( $mode === 'list' ){
                foreach ( $sortMetrics as $metric ){
                    $name = $metric->getAccessKey();
                    $jobData[$name] = 0;
                }

            }

            $jobData["plots"] = false;
        }

        return $jobData;
    }

    public function getAction($slug)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $configuration = new Configuration($this->_em);
        $config = $configuration->getUserConfig($this->getUser());
        $userId = 0;

        if ( false === $this->_authChecker->isGranted('ROLE_ADMIN') ) {
            $userId = $this->getUser()->getId();
        }

        $repository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $job = $repository->findJobById($slug, $userId);

        if (empty($job)) {
            throw new HttpException(400, "No such job $slug.");
        }

        $jobData = $this->_addJobPerformanceProfile($job, 'view');

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
     * @QueryParam(name="jobQuery")
     */
    public function cgetAction( ParamFetcher $paramFetcher)
    {
        $draw     = $paramFetcher->get('draw');
        $start    = $paramFetcher->get('start');
        $length   = $paramFetcher->get('length');
        $order    = $paramFetcher->get('order');
        $search   = $paramFetcher->get('search');
        $columns  = $paramFetcher->get('columns');
        $jobQuery = $paramFetcher->get('jobQuery');

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userId = 0;

        if ( false === $this->_authChecker->isGranted('ROLE_ADMIN') ) {
            $userId = $this->getUser()->getId();
        }

        $sortMetrics = $this->getDoctrine()
                            ->getRepository(\App\Entity\TableSortConfig::class)
                            ->findMetrics();

        $sorting = $this->_createJobSorting(
            $columns,
            $order[0]['column'],
            $order[0]['dir'],
            $sortMetrics
        );

        $filter = $search['value'];

        if ($filter === ''){
            $filter = NULL;
        }

        /* setup job query */
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $total = $repository->countJobs($userId, NULL, $jobQuery);
        $filtered = $total;

        $jobs = $repository->findFilteredJobs(
            $userId,
            $start, $length,
            $sorting,
            $filter,
            $jobQuery);

        if ( $filter ){
            $filtered = $repository->countJobs($userId, $filter, $jobQuery);
        }

        /* get performance profile and setup message data */
        foreach ( $jobs as $job ){
            $tableData[] =
                $this->_addJobPerformanceProfile(
                    $job,
                    'list',
                    $sortMetrics);
        }

        if ($filtered == 0) {
            $tableData = array();
        }

        $view = $this->view(array(
            "draw"            => (int) $draw,
            "recordsTotal"    => $total,
            "recordsFiltered" => $filtered,
            "data"            => $tableData
        ));

        return $this->handleView($view);
    } // "get_jobs"             [GET] /web/joblist/
}
