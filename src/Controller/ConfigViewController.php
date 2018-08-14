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

use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use App\Form\ApiKeyType;
use App\Entity\ApiKey;
use App\Form\ClusterType;
use App\Entity\Cluster;
use App\Entity\Configuration;
use App\Form\UserAccountType;
use App\Entity\UserAccount;
use App\Service\ColorMap;

class ConfigViewController extends Controller
{
    private function _generateSidebar($config)
    {

    }

    private function _sidebar($active = 0)
    {
        $sidebar = array(
            array(
                'label' => 'Access control',
                'items' => array(
                    array(
                        'label' => 'Users',
                        'icon' => 'users',
                        'link' => '/admin/userAccounts',
                        'addlink' => '/admin/create_userAccount',
                        'active' => false
                    ),
                    array(
                        'label' => 'ApiKeys',
                        'icon' => 'lock',
                        'link' => '/admin/apiKeys',
                        'addlink' => '/admin/create_apiKey',
                        'active' => false
                    ),
                )
            ),
            array(
                'label' => 'Options',
                'items' => array(
                    array(
                        'label' => 'Plot defaults',
                        'icon' => 'bar-chart-2',
                        'link' => '/admin/default',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Plot user',
                        'icon' => 'bar-chart-2',
                        'link' => '/admin/user',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Colormap',
                        'icon' => 'edit',
                        'link' => '/admin/colormap',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Cache',
                        'icon' => 'copy',
                        'link' => '/admin/cache',
                        'addlink' => false,
                        'active' => false
                    ),
                )
            ),
            array(
                'label' => 'System config',
                'items' => array(
                   array(
                        'label' => 'Metrics',
                        'icon' => 'activity',
                        'link' => '/admin/metrics',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Clusters',
                        'icon' => 'server',
                        'link' => '/admin/clusters',
                        'addlink' => '/admin/create_cluster',
                        'active' => false
                    ),
                )
            ),
        );

        if ( $active != 0 ){
            $sidebar[$active['menu']]['items'][$active['item']]['active'] = true;
        }

        return $sidebar;
    }

    public function init()
    {
        $em = $this->getDoctrine()->getManager();
        $config = new Configuration();
        $config->setName('view.roofline');
        $config->setScope('default');
        $config->setValue(array('show' => true));
        $em->persist($config);
        $config = new Configuration();
        $config->setName('view.polarplot');
        $config->setScope('default');
        $config->setValue(array('show' => true));

        $em->flush();

        return $this->redirectToRoute('config_index');
    }

    public function index()
    {
        return $this->render('config/index.html.twig',
            array(
                'sidebar' => $this->_sidebar(),
                'init' => $this->getDoctrine()
                     ->getRepository(\App\Entity\Configuration::class)
                     ->isInit()
            ));
    }

    public function defaultOptions(Request $request)
    {
        $config = $this->getDoctrine()
                       ->getRepository(\App\Entity\Configuration::class)
                       ->findAllDefaultHierarchy();

        return $this->render('config/editConfigOptions.html.twig',
            array(
                'configHash' => $config['plot'],
                'defaultmode' => true,
                'sidebar' => $this->_sidebar(
                    array('menu'=>1,'item'=>0)
                )
            ));
    }

    public function colorMapOptions(Request $request, ColorMap $colormap)
    {
        $config = $this->getDoctrine()
                       ->getRepository(\App\Entity\Configuration::class)
                       ->findAllDefault();

        $currentColorMap = $config['plot_general_colorscheme'];

        $colors = $colormap->getAllColorMaps();
        sort($colors);

        return $this->render('config/colorMap.html.twig',
            array(
                'colors' => $colors,
                'current' => $currentColorMap,
                'sidebar' => $this->_sidebar(
                    array('menu'=>1,'item'=>2)
                )
            ));
    }

    public function userOptions(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        $config = $this->getDoctrine()
                       ->getRepository(\App\Entity\Configuration::class)
                       ->findAllScopeHierarchy(array($user->getUsername()));

        return $this->render('config/editConfigOptions.html.twig',
            array(
                'configHash' => $config['plot'],
                'defaultmode' => false,
                'sidebar' => $this->_sidebar(
                    array('menu'=>1,'item'=>1)
                )
            ));
    }

    public function cacheOptions(Request $request)
    {
        $config = $this->getDoctrine()
                       ->getRepository(\App\Entity\Configuration::class)
                       ->findAllDefaultHierarchy();

        return $this->render('config/editConfigOptions.html.twig',
            array(
                'configHash' => $config['data'],
                'defaultmode' => true,
                'sidebar' => $this->_sidebar(
                    array('menu'=>1,'item'=>3)
                )
            ));
    }


    /* ####################### */
    /*       API KEYS          */
    /* ####################### */

    public function listApiKeys(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\ApiKey::class);
        $keys = $repository->findAll();

        return $this->render('config/listApiKeys.html.twig',
            array(
                'keys' => $keys,
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>1)
                )
            ));
    }

    public function deleteApiKey(ApiKey $key, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($key);
        $em->flush();

        return $this->redirectToRoute('list_api_keys');
    }

    public function editApiKey(ApiKey $key, Request $request)
    {
        $form = $this->createForm(ApiKeyType::class, $key);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $key = $form->getData();
                $em = $this->getDoctrine()->getManager();
                $em->persist($key);
                $em->flush();
            }

            return $this->redirectToRoute('list_api_keys');
        }

        return $this->render('config/editApiKey.html.twig',
            array(
                'form' => $form->createView(),
                'key' => $key,
                'title' => "Edit Key ".$key->getId(),
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>1)
                    )
            ));
    }

    public function createApiKey(Request $request)
    {
        $key = new ApiKey();
        $key->setToken(sha1(random_bytes(30)));
        $form = $this->createForm(ApiKeyType::class, $key);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ( $form->get('save')->isClicked() )  {
                $key = $form->getData();
                $em = $this->getDoctrine()->getManager();
                $em->persist($key);
                $em->flush();
            }

            return $this->redirectToRoute('list_api_keys');
        }

        return $this->render('config/editApiKey.html.twig',
            array(
                'form' => $form->createView(),
                'key' => $key,
                'title' => "Create API Key",
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>1)
                )
            ));
    }

    /* ####################### */
    /*    USER ACCOUNTS        */
    /* ####################### */

    public function listUserAccounts(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\UserAccount::class);
        $users = $repository->findAll();

        return $this->render('config/listUserAccounts.html.twig',
            array(
                'users' => $users,
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>0)
                )
            ));
    }

    public function deleteUserAccount(UserAccount $user, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($user);
        $em->flush();

        return $this->redirectToRoute('list_user_accounts');
    }

    public function editUserAccount(
        UserAccount $user,
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder)
    {
        $form = $this->createForm(UserAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $user = $form->getData();
                $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
                $user->setPassword($password);
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
            }

            return $this->redirectToRoute('list_user_accounts');
        }

        return $this->render('config/editUserAccount.html.twig',
            array(
                'form' => $form->createView(),
                'user' => $user,
                'title' => "Edit user account",
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>0)
                )
            ));
    }

    public function createUserAccount(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder
    )
    {
        $user = new UserAccount();
        $form = $this->createForm(UserAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $user = $form->getData();
                $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
                $user->setPassword($password);
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
            }

            return $this->redirectToRoute('list_user_accounts');
        }

        return $this->render('config/editUserAccount.html.twig',
            array(
                'form' => $form->createView(),
                'user' => $user,
                'title' => "Create user account",
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>0)
                )
            ));
    }


    /* ####################### */
    /*       Clusters          */
    /* ####################### */

    public function listMetrics(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Cluster::class);
        $clusters = $repository->findAll();

        $slots = array();

        foreach ( $clusters as $cluster ) {
            foreach ( $cluster->metricLists as $list ) {
                foreach ( $list->metrics as $metric ) {
                    if ( array_key_exists($metric->slot,$slots) ) {
                        $slots[$metric->slot][$cluster->getName()][] = array(
                            'list' => $list->getName(),
                            'name' => $metric->getName(),
                            'position' => $metric->position
                        );
                    } else {
                        $slots[$metric->slot] = array();
                        $slots[$metric->slot][$cluster->getName()][] = array(
                            'list' => $list->getName(),
                            'name' => $metric->getName(),
                            'position' => $metric->position
                        );
                    }
                }
            }
        }

        ksort($slots);

        return $this->render('config/listMetrics.html.twig',
            array(
                'clusters' => $clusters,
                'slots' => $slots,
                'sidebar' => $this->_sidebar(
                    array('menu'=>2,'item'=>0)
                )
            ));
    }

    public function listClusters(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Cluster::class);
        $clusters = $repository->findAllConfig();

        return $this->render('config/listClusters.html.twig',
            array(
                'clusters' => $clusters,
                'sidebar' => $this->_sidebar(
                    array('menu'=>2,'item'=>1)
                )
            ));
    }

    public function deleteCluster(ApiKey $key, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($key);
        $em->flush();

        return $this->redirectToRoute('list_api_keys');
    }

    public function editCluster(Cluster $cluster, Request $request)
    {
        $form = $this->createForm(ClusterType::class, $cluster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $cluster = $form->getData();
                $em = $this->getDoctrine()->getManager();
                $em->persist($cluster);
                $em->flush();
            }

            return $this->redirectToRoute('list_clusters');
        }

        return $this->render('config/editCluster.html.twig',
            array(
                'form' => $form->createView(),
                'cluster' => $cluster,
                'title' => "Edit Cluster ".$cluster->getName(),
                'sidebar' => $this->_sidebar(
                    array('menu'=>2,'item'=>1)
                    )
            ));
    }

    public function createCluster(Request $request)
    {
        $key = new ApiKey();
        $key->setToken(sha1(random_bytes(30)));
        $form = $this->createForm(ApiKeyType::class, $key);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ( $form->get('save')->isClicked() )  {
                $key = $form->getData();
                $em = $this->getDoctrine()->getManager();
                $em->persist($key);
                $em->flush();
            }

            return $this->redirectToRoute('list_api_keys');
        }

        return $this->render('config/editApiKey.html.twig',
            array(
                'form' => $form->createView(),
                'key' => $key,
                'title' => "Create API Key",
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>1)
                )
            ));
    }
}

