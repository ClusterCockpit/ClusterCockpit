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

namespace App\Adapter;

use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;
use Psr\Log\LoggerInterface;
use App\Service\Configuration;

class LdapManager
{
    private $_logger;
    private $_ldap;
    private $_configuration;

    public function __construct(
        LoggerInterface $logger,
        Configuration $configuration
    )
    {
        $this->_logger = $logger;
        $this->_configuration = $configuration;
        $this->_ldap = Ldap::create('ext_ldap', array(
            'connection_string' => $this->_configuration->getValue('ldap_connection_url')
        ));
    }

    public function bindUser($uid, $password)
    {
        $base = $this->_configuration->getValue('ldap_user_base');
        $key = $this->_configuration->getValue('ldap_user_key');
        $dn = $key.'='.$uid.$base;
        $username = $this->_ldap->escape($dn, '', LdapInterface::ESCAPE_DN);

        $this->_ldap->bind($username, $password);
    }

    public function queryUsers()
    {
          $password = getenv('LDAP_PW');
          $dn = $this->_configuration->getValue('ldap_search_dn');
          $baseDn = $this->_configuration->getValue('ldap_user_base');
          $filter = $this->_configuration->getValue('ldap_user_filter');
          $this->_ldap->bind($dn, $password);

          return $this->_ldap->query($baseDn, $filter)->execute()->toArray();
    }

    public function queryGroups()
    {
          $password = getenv('LDAP_PW');
          $dn = $this->_configuration->getValue('ldap_search_dn');
          $baseDn = $this->_configuration->getValue('ldap_group_base');
          $filter = $this->_configuration->getValue('ldap_group_filter');
          $this->_ldap->bind($dn, $password);

          return $this->_ldap->query($baseDn, $filter)->execute()->toArray();
    }
}

