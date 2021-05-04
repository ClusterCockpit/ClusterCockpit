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

namespace App\Controller;

use App\Entity\Job;
use App\Entity\JobTag;
use App\Entity\JobSearch;
use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\SerializerInterface;
use App\Service\JobCache;
use App\Service\Configuration;
use Psr\Log\LoggerInterface;
use \DateTime;
use \DateInterval;

class JobViewController extends AbstractController
{
    public function searchId(Request $request, AuthorizationCheckerInterface $authChecker)
    {
        $searchId = $request->query->get('searchId');
        $jobRepo = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $userRepo = $this->getDoctrine()->getRepository(\App\Entity\User::class);
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ( false === $authChecker->isGranted('ROLE_ADMIN') ) {
            $userId = $this->getUser()->getId();
            $job = $jobRepo->findOneBy(
                ['jobId' => $searchId, 'user' => $userId]
            );

            if (!$job) {
                return $this->render('error/message.html.twig',
                    array(
                        'message' => 'No such job!'
                    ));
            } else {
                return $this->redirectToRoute('show_job', array('id' => $job->getId()));
            }
        } else {
            $job = $jobRepo->findOneBy(['jobId' => $searchId]);

            if (!$job) {
                $user = $userRepo->findOneBy(['username' => $searchId]);

                if (!$user) {
                    return $this->render('error/message.html.twig',
                        array(
                            'message' => 'No such job or user!'
                        ));
                } else {
                    return $this->redirectToRoute('show_user', array('id' => $user->getId()));
                }
            } else {
                return $this->redirectToRoute('show_job', array('id' => $job->getId()));
            }
        }
    }

    public function list(
        Configuration $configuration
    )
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());

        return $this->render('jobViews/listJobs.html.twig',
            array(
                'config' => $config
            ));
    }

    public function listTagTypes()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $tags = $this->getDoctrine()
                            ->getRepository(\App\Entity\JobTag::class)
                            ->findAll();
        $tagHash = array();

        foreach ( $tags as $tag ){
            $type = $tag->getType();

            $tagHash[$type][] = array(
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'count' => count($tag->getJobs())
            );
        }

        return $this->render('default/listJobTagCounts.html.twig',
            array(
                'tagHash' => $tagHash,
            ));
    }

    public function listTag(
        JobTag $id,
        Configuration $configuration
    )
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());
        $sortMetrics = $this->getDoctrine()
                            ->getRepository(\App\Entity\TableSortConfig::class)
                            ->findMetrics();

        $count = count($sortMetrics);
        $end = $count+1;

        $columnDefs = array(
            'orderable'  => "0,$end",
            'visible'    => implode(',',range(1,$count)),
            'searchable' => implode(',',range(1,$end))
        );

        return $this->render('jobViews/listJobs.html.twig',
            array(
                'jobQuery' => json_encode(array('jobTag' => $id->getId())),
                'config' => $config,
                'sortMetrics' => $sortMetrics,
                'columnDefs' => $columnDefs
            ));
    }

    public function show(
        Job $job,
        Configuration $configuration,
        JobCache $jobCache
    )
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());

        if ( $job->isRunning ) {
            $job->duration = time() - $job->startTime;
        }

        $alltags = $this->getDoctrine()
                            ->getRepository(\App\Entity\JobTag::class)
                            ->findAll();

        $d1 = new DateTime();
        $d2 = new DateTime();
        $d2->add(new DateInterval('PT'.$job->duration.'S'));
        $iv = $d2->diff($d1);
        $duration = $iv->format('%h h %i m');

        if ( $iv->days ){
            $duration = $iv->format('%a d %h h %i m');
        }

        return $this->render('jobViews/viewJob-ajax.html.twig',
            array(
                'job' => $job,
                'duration' => $duration,
                'config' => $config,
                'tags' => $alltags,
                'backend' => $jobCache->getBackend()
            ));
    }
}
