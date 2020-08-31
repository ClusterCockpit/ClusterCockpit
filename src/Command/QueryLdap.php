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

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Adapter\LdapManager;
use App\Service\Configuration;


class QueryLdap extends Command
{
    private $_ldap;
    private $_configuration;

    public function __construct(
        LdapManager $ldap,
        EntityManagerInterface $em
    )
    {
        $this->_em = $em;
        $this->_ldap = $ldap;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:user:ldap')
            ->setDescription('Query ldap directory.')
            ->setHelp('This command allows to sync users and groups from a ldap server.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_configuration = new Configuration($this->_em);
        $config['ldap_connection_url'] = $this->_configuration->getValue('ldap_connection_url');
        $config['ldap_search_dn'] = $this->_configuration->getValue('ldap_search_dn');
        $config['ldap_user_base'] = $this->_configuration->getValue('ldap_user_base');
        $config['ldap_user_filter'] = $this->_configuration->getValue('ldap_user_filter');

        $results = $this->_ldap->queryUsers($config);
        var_dump($results);
    }
}
