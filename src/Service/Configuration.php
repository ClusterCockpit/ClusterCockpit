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
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ConfigurationRepository;

class Configuration
{
    private $_logger;
    private $_repository;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $em
    )
    {
        $this->_logger = $logger;
        $this->_repository = $em->getRepository(\App\Entity\Configuration::class);
    }

    public function getUserConfig($user)
    {
        return $this->_repository->findAllScope(array($user->getUsername()));
    }

    public function getConfig()
    {
        return $this->_repository->findAllDefault();
    }

    public function getValue($config, $key)
    {
        return $config[$key]->value;
    }
}

