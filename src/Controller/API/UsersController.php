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
use App\Entity\User;
use FOS\RestBundle\View\View;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\FOSRestController;

class UsersController extends FOSRestController
{
    public function getUsersAction()
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\User::class);
        $data = $repository->findAll();
        $view = $this->view($data);

        return $this->handleView($view);
    } // "get_users"            [GET] /users

    public function getUserAction($slug)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\User::class);
        $user = $repository->findOneByUserId($slug);

        if (empty($user)) {
            throw new HttpException(400, "No such user ID.");
        }
        $view = $this->view($user);

        return $this->handleView($view);

    } // "get_user"             [GET] /users/{slug}

    public function postUsersAction()
    {
        $data = new User;
        $name = $request->get('name');
        $role = $request->get('role');
        if(empty($name) || empty($role))
        {
            return new View("NULL VALUES ARE NOT ALLOWED", Response::HTTP_NOT_ACCEPTABLE);
        }
        $data->setName($name);
        $data->setRole($role);
        $em = $this->getDoctrine()->getManager();
        $em->persist($data);
        $em->flush();
        return new View("User Added Successfully", Response::HTTP_OK);

    } // "post_users"           [POST] /users
}

