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
use App\Entity\RunningJob;
use App\Entity\JobSearch;
use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
use \DateInterval;

class JobViewController extends Controller
{
    public function searchId(Request $request)
    {
        $searchId = $request->query->get('searchId');
        $jobRepo = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $userRepo = $this->getDoctrine()->getRepository(\App\Entity\User::class);

        $job = $jobRepo->findOneBy(['jobId' => $searchId]);

        if (!$job) {
            $user = $userRepo->findOneBy(['userId' => $searchId]);

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

    private function getSystems(){
        return array(
            'ALL' => 0,
            'emmy' => 1,
            'lima' => 2,
            'meggie' => 3,
            'woody' => 4,
        );
    }


    public function search(
        Request $request,
        SerializerInterface $serializer)
    {
        $search = new JobSearch();
        $search->setNumNodesFrom(1);
        $search->setNumNodesTo(64);
        $search->setDurationFrom(new DateInterval('PT1H'));
        $search->setDurationTo(new DateInterval('PT24H'));
        $search->setDateFrom(1520640000);
        $search->setDateTo(time());

        $form = $this->createFormBuilder($search)
            ->add('jobId', TextType::class, array('required' => false))
            ->add('userId', TextType::class, array('required' => false))
            ->add('clusterId', ChoiceType::class,array(
                'choices'  => $this->getSystems(),
                'required' => true))
            ->add('numNodesFrom', IntegerType::class, array('required' => false))
            ->add('numNodesTo', IntegerType::class, array('required' => false))
            ->add('durationFrom', DateIntervalType::class, array(
                'with_hours' => true,
                'with_minutes' => true,
                'with_days' => false,
                'with_months' => false,
                'with_years' => false,
            ))
            ->add('durationTo', DateIntervalType::class, array(
                'with_hours' => true,
                'with_minutes' => true,
                'with_days' => false,
                'with_months' => false,
                'with_years' => false,
            ))
            ->add('dateFrom', DateTimeType::class, array(
                'input' => 'timestamp'
            ))
            ->add('dateTo', DateTimeType::class, array(
                'input' => 'timestamp'
            ))
            ->add('search', SubmitType::class, array('label' => 'Search Jobs'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $search = $form->getData();
            $joblist = array();

            $repository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
            $jobId = $search->getJobId();

            if (isset($jobId)){
                $job = $repository->findOneBy(['jobId' => $jobId]);
                return $this->redirectToRoute('show_job', array('id' => $job->getId()));
            } else {
                $durationFrom =$search->getDurationFrom()->h*3600+$search->getDurationFrom()->m*60;
                $durationTo =$search->getDurationTo()->h*3600+$search->getDurationFrom()->m*60;
                $search->setDurationFrom($durationFrom);
                $search->setDurationTo($durationTo);

                return $this->render('jobViews/listJobs.html.twig',
                    array(
                        'jobSearch' => $serializer->serialize($search, 'json'),
                    ));
            }
        }

        return $this->render('default/searchJobs.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function list()
    {
        return $this->render('jobViews/listJobs.html.twig');
    }

    public function showRunning(
        RunningJob $job,
        Configuration $configuration,
        JobCache $jobCache
    )
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());

        $jobCache->checkCache(
            $job,
            array(
                'mode' => 'view',
            ),
            $config
        );

        return $this->render('jobViews/viewJob.html.twig',
            array(
                'job' => $job,
                'config' => $config,
                'backend' => $jobCache->getBackend()
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

        $jobCache->checkCache(
            $job,
            array(
                'mode' => 'view',
            ),
            $config
        );

        return $this->render('jobViews/viewJob.html.twig',
            array(
                'job' => $job,
                'config' => $config,
                'backend' => $jobCache->getBackend()
            ));
    }
}
