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
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use App\Entity\JobTag;

class TagController extends AbstractFOSRestController
{
    /**
     * @QueryParam(name="jobId", requirements="\d+")
     */
    public function getTagsAction( ParamFetcher $paramFetcher)
    {
        $jobId = $paramFetcher->get('jobId');
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $job = $repository->find($jobId);
        $tags = $job->getTagsArray();

        $view = $this->view($tags);
        return $this->handleView($view);
    } // "get_jobtag"          [GET] /web/jobtags/$slug

    public function postTagAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\JobTag::class);
        $jobRepository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $em = $this->getDoctrine()->getManager();

        $jobId = $request->request->get('id');
        $name = $request->request->get('name');
        $type = $request->request->get('type');

        /* check if tag already exists */
        $jobTag = $repository->findOneByName($name);

        /* add tag if not yet existing */
        if (empty($jobTag)) {
            $jobTag = new JobTag;
            $jobTag->setName($name);
            $jobTag->setType($type);
            $em->persist($jobTag);
        }

        /* get job */
        $job = $jobRepository->find($jobId);

        /* add tag to job */
        if (!empty($job)) {
            $jobTag->addJob($job);
            $em->persist($job);
            $em->flush();
        }

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    } // "post_jobtag"           [POST] /api/tags

    public function deleteTagAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\JobTag::class);
        $jobRepository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $em = $this->getDoctrine()->getManager();

        $jobId = $request->request->get('jobId');
        $tagId = $request->request->get('tagId');

        $job = $jobRepository->find($jobId);
        $jobTag = $repository->find($tagId);

        if (!empty($job)) {
            $jobTag->removeJob($job);
            $em->persist($jobTag);
            $em->persist($job);
            $em->flush();
        }

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    } // "patch_configuration"           [PATCH] api/configurations/$id
}
