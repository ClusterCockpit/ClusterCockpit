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
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use App\Entity\JobTag;

class JobTagController extends FOSRestController
{
    public function getJobTagAction($slug)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\JobTag::class);
        $tags = $repository->findByJobId($slug);

        if (empty($tags)) {
            throw new HttpException(400, "No such job id ".$slug);
        }

        $view = $this->view($tags);
        return $this->handleView($view);
    } // "get_jobtag"          [GET] web/jobtags/$slug

    public function postJobTagAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\JobTag::class);
        $id = $request->request->get('id');
        $name = $request->request->get('name');
        $type = $request->request->get('type');

        $jobtag = $repository->find($id);
        if (empty($config)) {
            throw new HttpException(400, "No such configuration key: $id");
        }

        /* check if tag already exists */
        /* add tag */
        /* add tag to job if not */


        $newConfig =  clone $config;
        $newConfig->setValue($value);
        $newConfig->setScope($this->getUser()->getUsername());

        $em = $this->getDoctrine()->getManager();
        $em->persist($newConfig);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    } // "post_jobtag"           [POST] /api/jobtags


    public function deleteJobTagAction(Configuration $id)
    {
        if (empty($id)) {
            throw new HttpException(400, "No such configuration key: $id");
        }
        if ( $id->getScope() === 'default' ){
            $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Not allowed to change default configuration!');
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($id);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    } // "patch_configuration"           [PATCH] api/configurations/$id
}

