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
use App\Entity\User;
use App\Entity\StatisticsControl;
use App\Service\PlotGenerator;
use App\Service\JobCache;
use App\Repository\JobRepository;
use App\Form\StatisticsControlType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;
use Psr\Log\LoggerInterface;
use \DateInterval;

class StatisticsViewController extends AbstractController
{
    private function _init($request)
    {
        $control = new StatisticsControl();
        $control->setYear(date("Y"));
        $control->setMonth(date("m"));
        $control->setCluster(1);

        $form = $this->createForm(StatisticsControlType::class, $control);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $control = $form->getData();
        }

        return array(
            'control'    => $control,
            'form'       => $form,
            'repository' => $this->getDoctrine()->getRepository(\App\Entity\Job::class)
        );
    }

    public function users(Request $request)
    {
        $control = $this->_init($request);
        $users = $control['repository']->statUsers($control['control']);

        return $this->render('users/statisticsUser.html.twig',
            array(
                'form' => $control['form']->createView(),
                'control' => $control['control'],
                'users' => $users,
            ));
    }
}
