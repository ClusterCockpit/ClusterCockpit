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
use Psr\Log\LoggerInterface;
use App\Entity\Cluster;
use App\Entity\Metric;

class ClusterController extends AbstractFOSRestController
{
    private function _hasMetric( $list, $name )
    {
        foreach ($list as $entry) {
            if ( !empty($metric) ){
                if ( $entry['name'] === $name ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function _copyMetric( $metricEntry, $metric )
    {
        foreach ( $metricEntry as $key => $value ){
            if ( array_key_exists($key, $metric)){
                $metricEntry->key = $metric[$key];
            }
        }

        $metricEntry->name = $metric['name'];
        $metricEntry->unit = $metric['unit'];
        $metricEntry->scale = $metric['scale'];
        $metricEntry->position = $metric['position'];
        $metricEntry->measurement = $metric['measurement'];
        $metricEntry->sampletime = $metric['sampletime'];

        if ( $metric['peak'] === "" ) {
            $metricEntry->peak = NULL;
        } else {
            $metricEntry->peak = $metric['peak'];
        }
        if ( $metric['normal'] === "" ) {
            $metricEntry->normal = NULL;
        } else {
            $metricEntry->normal = $metric['normal'];
        }
        if ( $metric['caution'] === "" ) {
            $metricEntry->caution = NULL;
        } else {
            $metricEntry->caution = $metric['caution'];
        }
        if ( $metric['alert'] === "" ) {
            $metricEntry->alert = NULL;
        } else {
            $metricEntry->alert = $metric['alert'];
        }
    }

    public function getClusterAction(Cluster $id)
    {
        if (empty($id)) {
            throw new HttpException(400, "No such cluster ".$id);
        }

        $view = $this->view($config);
        return $this->handleView($view);
    } // "get_configuration"          [GET] web/clusters/$id

    public function patchClusterAction(
        Cluster $id,
        Request $request,
        LoggerInterface $logger
    ){
        $metricLists = $request->request->get('metricLists');

        if (empty($metricLists)) {
            throw new HttpException(400, "No such cluster: $id");
        }
        if (empty($id)) {
            throw new HttpException(400, "No such cluster: $id");
        }

        $em = $this->getDoctrine()->getManager();

        foreach ( $metricLists as $list ){
            $listName = $list['name'];
            $currentList = $id->metricLists[$listName];

            /* remove rows */
            foreach ( $currentList->metrics as $metric ){
                if ( !empty($metric) ){
                    $name = $metric->name;

                    if ( ! $this->_hasMetric( $list['rows'], $name)) {
                        $metricEntry = $currentList->metrics[$name];
                        $currentList->removeMetric($metricEntry);
                        $em->remove($metricEntry);
                        $logger->info("REMOVE",array($listName, '##'.$name.'##'));
                    }
                }
            }

            /* edit or add rows */
            foreach ( $list['rows'] as $metric ){

                if ( !empty($metric) ){
                    $name = $metric['name'];
                    if ( $currentList->metrics->containsKey($name)) {
                        $metricEntry = $currentList->metrics[$name];
                        $this->_copyMetric( $metricEntry, $metric);
                        $logger->info("EDIT",array($listName, $name));
                    } else {
                        $metricEntry = new Metric();
                        $this->_copyMetric($metricEntry, $metric);
                        $currentList->addMetric($metricEntry);
                        $metricEntry->setMetricList($currentList);
                        $logger->info("ADD",array($listName, $name));
                    }
                    $em->persist($metricEntry);
        $em->flush();
                }
            }
            $em->persist($currentList);
        }

        $em->persist($id);
        $em->flush();

        $view = new View();
        $view->setStatusCode(200);
        $view->setData("SUCCESS");
        return $this->handleView($view);
    } // "patch_configuration"           [PATCH] web/clusters/$id
}
