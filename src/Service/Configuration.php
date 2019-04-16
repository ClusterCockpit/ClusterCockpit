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

namespace App\Service;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ConfigurationRepository;

class Configuration
{
    private $_repository;
    private $_config;
    private $_isInit;
    private $_em;

    public function __construct(
        EntityManagerInterface $em
    )
    {
        $this->_em = $em;
        $this->_isInit = 0;
    }

    private function _initConfig()
    {
        $this->_repository = $this->_em->getRepository(\App\Entity\Configuration::class);
        $this->_config =  $this->_repository->findAllDefault();
        $this->_isInit = count($this->_config);
    }

    public function getUserConfig($user)
    {
        if ( $this->_isInit == 0 ) {
            $this->_initConfig();
        }

        return $this->_repository->findAllScope(array($user->getUsername()));
    }

    public function getConfig()
    {
        if ( $this->_isInit == 0 ) {
            $this->_initConfig();
        }

        return $this->_config;
    }

    public function getValue($key)
    {
        if ( $this->_isInit == 0 ) {
            $this->_initConfig();
        }

        if ( array_key_exists ( $key , $this->_config) ){
            return $this->_config[$key]->value;
        } else {
            return false;
        }
    }
}
