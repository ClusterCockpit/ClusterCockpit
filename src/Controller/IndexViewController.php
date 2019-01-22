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

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Configuration;

class IndexViewController extends Controller
{
    private function createAdminUser(
        $request,
        $page,
        UserPasswordEncoderInterface $passwordEncoder
    )
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $user = $form->getData();
                $user->setUid(0);
                $user->setName('Local account');
                $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
                $user->setPassword($password);
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
            }

            return $this->redirectToRoute('init', array('slug' => $page++));
        }

        return $this->render("init/page-$page.html.twig",
            array(
                'form' => $form->createView(),
            ));
    }

    private function addCluster()
    {

    }

    public function init(Request $request, Configuration $configuration, $slug)
    {
        $page = (int) $slug;

        switch ( $page ){
        case 1:
            $configuration->initConfig();

            return $this->render("init/page-$page.html.twig",
                array(
                    'form' => $form->createView(),
                ));
        case 2:
            createAdminUser($request, $page);
        case 3:
            addCluster($request, $page);

        default:
        throw $this->createNotFoundException('The page does not exist');
        }

    }

    public function home(Configuration $configuration)
    {
        if ( count($configuration->getConfig()) == 0 ){
            return $this->redirectToRoute('init', array('slug' => 1));
        }

        return $this->render('default/index.html.twig');
    }
}


