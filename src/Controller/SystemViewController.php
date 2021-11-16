<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2020 Jan Eitzinger
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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Configuration;
use App\Service\ColorMap;

class SystemViewController extends AbstractController
{
    public function system(
        Request $request,
        string $clusterId,
        Configuration $configuration,
        ColorMap $colorMaps,
        $projectDir
    )
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());
        $colorMaps->setColormap($config['plot_general_colorscheme']->value, $projectDir);

        return $this->render('systems/systems.html.twig',
            array(
                'jwt' => $request->getSession()->get('jwt'),
                'config' => $config,
                'colormap' => $colorMaps->getColorMap(),
                'clusterId' => $clusterId
            ));
    }

    public function node(
        Request $request,
        string $cluster,
        string $node,
        Configuration $configuration,
        ColorMap $colorMaps,
        $projectDir
    )
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $config = $configuration->getUserConfig($this->getUser());
        $colorMaps->setColormap($config['plot_general_colorscheme']->value, $projectDir);

        return $this->render('systems/node.html.twig',
            array(
                'jwt' => $request->getSession()->get('jwt'),
                'config' => $config,
                'colormap' => $colorMaps->getColorMap(),
                'nodeId' => $node, 'clusterId' => $cluster
            ));
    }
}
