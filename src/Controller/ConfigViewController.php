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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Psr\Log\LoggerInterface;
use App\Form\ApiKeyType;
use App\Entity\ApiKey;
use App\Form\ClusterType;
use App\Entity\Cluster;
use App\Entity\MetricList;
use App\Entity\Node;
use App\Entity\Configuration;
use App\Form\UserType;
use App\Entity\User;
use App\Service\ColorMap;
use App\Service\NodeFileReader;

class ConfigViewController extends AbstractController
{
    /* ####################### */
    /*       SIDEBARS          */
    /* ####################### */

    private function _sidebar($active = 0)
    {
        $sidebar = array(
            array(
                'label' => 'Access control',
                'items' => array(
                    array(
                        'label' => 'Users',
                        'icon' => 'users',
                        'link' => 'list_user_accounts',
                        'addlink' => 'create_user_account',
                        'active' => false
                    ),
                    array(
                        'label' => 'ApiKeys',
                        'icon' => 'lock',
                        'link' => 'list_api_keys',
                        'addlink' => 'create_api_key',
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
                        'link' => 'default_options',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Plot user',
                        'icon' => 'bar-chart-2',
                        'link' => 'user_options',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Colormap',
                        'icon' => 'edit',
                        'link' => 'color_options',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Cache',
                        'icon' => 'copy',
                        'link' => 'cache_options',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Ldap',
                        'icon' => 'database',
                        'link' => 'ldap_options',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'General',
                        'icon' => 'settings',
                        'link' => 'general_options',
                        'addlink' => false,
                        'active' => false
                    ),
                )
            ),
            array(
                'label' => 'System config',
                'items' => array(
                    array(
                        'label' => 'Clusters',
                        'icon' => 'server',
                        'link' => 'list_clusters',
                        'addlink' => 'create_cluster',
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

    private function _userSidebar($active = 0)
    {
        $sidebar = array(
            array(
                'label' => 'Options',
                'items' => array(
                    array(
                        'label' => 'Plot',
                        'icon' => 'bar-chart-2',
                        'link' => 'config_plot',
                        'addlink' => false,
                        'active' => false
                    ),
                    array(
                        'label' => 'Colormap',
                        'icon' => 'edit',
                        'link' => 'config_color',
                        'addlink' => false,
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

    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }

    /* ####################### */
    /*       ENTRIES           */
    /* ####################### */

    /* public function init() */
    /* { */
    /*     $em = $this->getDoctrine()->getManager(); */
    /*     $config = new Configuration(); */
    /*     $config->setName('view.roofline'); */
    /*     $config->setScope('default'); */
    /*     $config->setValue(array('show' => true)); */
    /*     $em->persist($config); */
    /*     $config = new Configuration(); */
    /*     $config->setName('view.polarplot'); */
    /*     $config->setScope('default'); */
    /*     $config->setValue(array('show' => true)); */
    /*     $em->flush(); */

    /*     return $this->redirectToRoute('config_index'); */
    /* } */

    public function index()
    {
        return $this->render('config/index.html.twig',
            array(
                'sidebar' => $this->_sidebar()
            ));
    }

    public function config()
    {
        return $this->render('config/index.html.twig',
            array(
                'sidebar' => $this->_userSidebar()
            ));
    }

    /* ####################### */
    /*       OPTIONS           */
    /* ####################### */

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

    public function colorMapOptions(
        Request $request,
        ColorMap $colormap,
        AuthorizationCheckerInterface $authChecker
    )
    {
        $mode = false;

        if ( $authChecker->isGranted('ROLE_ADMIN') ) {
            $config = $this->getDoctrine()
                           ->getRepository(\App\Entity\Configuration::class)
                           ->findAllDefault();
            $mode = true;
            $sidebar = $this->_sidebar(
                    array('menu'=>1,'item'=>2)
                );

        } else {
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
            $user = $this->getUser();
            $config = $this->getDoctrine()
                           ->getRepository(\App\Entity\Configuration::class)
                           ->findAllScope(array($user->getUsername()));

            $sidebar = $this->_userSidebar(
                    array('menu'=>0,'item'=>1)
                );
        }

        $currentColorMap = $config['plot_general_colorscheme'];

        $colors = $colormap->getAllColorMaps();
        sort($colors);

        return $this->render('config/colorMap.html.twig',
            array(
                'colors' => $colors,
                'current' => $currentColorMap,
                'defaultmode' => $mode,
                'sidebar' => $sidebar
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
                'sidebar' => $this->_userSidebar(
                    array('menu'=>0,'item'=>0)
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

    public function ldapOptions(Request $request)
    {
        $config = $this->getDoctrine()
                       ->getRepository(\App\Entity\Configuration::class)
                       ->findAllDefaultHierarchy();

        return $this->render('config/editConfigOptions.html.twig',
            array(
                'configHash' => $config['ldap'],
                'defaultmode' => true,
                'sidebar' => $this->_sidebar(
                    array('menu'=>1,'item'=>4)
                )
            ));
    }

    public function generalOptions(Request $request)
    {
        $config = $this->getDoctrine()
                       ->getRepository(\App\Entity\Configuration::class)
                       ->findAllDefaultHierarchy();

        return $this->render('config/editConfigOptions.html.twig',
            array(
                'configHash' => $config['general'],
                'defaultmode' => true,
                'sidebar' => $this->_sidebar(
                    array('menu'=>1,'item'=>5)
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
        $repository = $this->getDoctrine()->getRepository(\App\Entity\User::class);
        $users = $repository->findLocalUsers();

        return $this->render('config/listUserAccounts.html.twig',
            array(
                'users' => $users,
                'sidebar' => $this->_sidebar(
                    array('menu'=>0,'item'=>0)
                )
            ));
    }

    public function deleteUserAccount(User $user, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($user);
        $em->flush();

        return $this->redirectToRoute('list_user_accounts');
    }

    public function editUserAccount(
        User $user,
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder)
    {
        $form = $this->createForm(UserType::class, $user);
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
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $user = $form->getData();
                $user->setName('Local account');
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

    public function listClusters(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Cluster::class);
        $clusters = $repository->findAllConfig();

        return $this->render('config/listClusters.html.twig',
            array(
                'clusters' => $clusters,
                'sidebar' => $this->_sidebar(
                    array('menu'=>2,'item'=>0)
                )
            ));
    }

    public function deleteCluster(Cluster $cluster, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($cluster);
        $em->flush();

        return $this->redirectToRoute('list_clusters');
    }

    public function editCluster(Cluster $cluster, Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\Cluster::class);
        $form = $this->createForm(ClusterType::class, $cluster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $cluster = $form->getData();
                $em = $this->getDoctrine()->getManager();
                $file = $cluster->getNodeFile();

                if (! is_null($file)){

                    $fileName = $this->generateUniqueFileName().'.'.$file->guessExtension();
                    $filePath = $this->getParameter('upload_directory');

                    try {
                        $file->move(
                            $filePath,
                            $fileName
                        );
                    } catch (FileException $e) {
                        // ... handle exception if something happens during file upload
                    }

                    $fileReader = new NodeFileReader();
                    $nodes = $fileReader->parse($filePath.'/'.$fileName);
                    $currentNodes = $cluster->getNodes();
                    $nodeLookup = array();

                    if ( count($currentNodes) > 0 ){
                        foreach ( $currentNodes as  $node ) {
                            $nodeLookup[$node->nodeId] = 1;
                        }
                    }

                    foreach ( $nodes as  $node ) {
                        if ( array_key_exists($node['nodeId'], $nodeLookup) ) {
                            /* TODO: Sync new data */

                        } else {
                            $newNode = new Node();
                            $newNode->nodeId = $node['nodeId'];
                            $newNode->cluster = $cluster->getId();
                            $newNode->status = 'active';
                            $em->persist($newNode);
                        }
                    }
                }

                $em->persist($cluster);
                $em->flush();
            }

            return $this->redirectToRoute('list_clusters');
        }

        return $this->render('config/editCluster.html.twig',
            array(
                'form' => $form->createView(),
                'id' => $cluster->getId(),
                'cluster' => $repository->addNodes($cluster),
                'title' => "Edit Cluster ".$cluster->getName(),
                'sidebar' => $this->_sidebar(
                    array('menu'=>2,'item'=>0)
                    )
            ));
    }

    public function createCluster(Request $request, NodeFileReader $fileReader)
    {
        $cluster = new Cluster();
        $form = $this->createForm(ClusterType::class, $cluster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ( $form->get('save')->isClicked() )  {
                $cluster = $form->getData();
                $em = $this->getDoctrine()->getManager();
                $file = $cluster->getNodeFile();
                $em->persist($cluster);
                $em->flush();

                foreach ( array('list','view','stat','sort') as $listName ) {
                    $list = new MetricList();
                    $list->setName($listName);
                    $cluster->addMetricList($list);
                    $em->persist($list);
                }

                $em->persist($cluster);
                $em->flush();

                if (! is_null($file)){

                    $fileName = $this->generateUniqueFileName().'.'.$file->guessExtension();
                    $filePath = $this->getParameter('upload_directory');

                    try {
                        $file->move(
                            $filePath,
                            $fileName
                        );
                    } catch (FileException $e) {
                        // ... handle exception if something happens during file upload
                    }

                    $fileReader = new NodeFileReader();
                    $nodes = $fileReader->parse($filePath.'/'.$fileName);
                    $currentNodes = $cluster->getNodes();
                    $nodeLookup = array();

                    if ( count($currentNodes) > 0 ){
                        foreach ( $currentNodes as  $node ) {
                            $nodeLookup[$node->nodeId] = 1;
                        }
                    }

                    foreach ( $nodes as  $node ) {
                        if ( array_key_exists($node['nodeId'], $nodeLookup) ) {
                            /* TODO: Sync new data */

                        } else {
                            $newNode = new Node();
                            $newNode->nodeId = $node['nodeId'];
                            $newNode->cluster = $cluster->getId();
                            $newNode->status = 'active';
                            $em->persist($newNode);
                        }
                    }
                    $em->flush();
                }
            }

            return $this->redirectToRoute('list_clusters');
        }

        return $this->render('config/editCluster.html.twig',
            array(
                'form' => $form->createView(),
                'cluster' => $cluster,
                'title' => "Create Cluster",
                'sidebar' => $this->_sidebar(
                    array('menu'=>2,'item'=>0)
                )
            ));
    }
}
