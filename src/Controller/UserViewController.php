<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2021 Jan Eitzinger
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
use App\Entity\JobSearch;
use App\Entity\User;
use App\Entity\UpdateGroupRequest;
use App\Entity\StatisticsControl;
use App\Repository\UserRepository;
use App\Service\Configuration;
use App\Service\ColorMap;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

class UserViewController extends AbstractController
{
    public function list(Request $request)
    {
        // Only include jobs started within the last month in
        // the statistics shown in the users table.
        $today = new \DateTime("now");
        $today->setTime(0, 0);
        $lastMonth = clone $today;
        $lastMonth->modify('-1 month');
        return $this->render('users/listUsers.html.twig',
            array(
                'jwt' => $request->getSession()->get('jwt'),
                'filterPresets' => [
                    'startTime' => [
                        'from' => $lastMonth->format(\DateTime::RFC3339),
                        'to' => $today->format(\DateTime::RFC3339)
                    ]
                ]
            ));
    }

    public function show(
        User $user,
        Request $request,
        Configuration $configuration,
        ColorMap $colorMaps,
        $projectDir
    ) {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());
        $colorMaps->setColormap($config['plot_general_colorscheme']->value, $projectDir);

        $scrambleNames = filter_var($configuration->getValue("general_user_scramble"), FILTER_VALIDATE_BOOLEAN);
        if ($scrambleNames == true) {
            $user = [
                'username' => $user->getUserId(true),
                'name' => 'Anonymized',
                'email' => ''
            ];
        }

        return $this->render('users/showUser.html.twig',
            array(
                'jwt' => $request->getSession()->get('jwt'),
                'user' => $user,
                'config' => $config,
                'colormap' => $colorMaps->getColorMap()
            ));
    }
}
