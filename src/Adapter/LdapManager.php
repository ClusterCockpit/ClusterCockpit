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

class LdapManager
{
    public function bindUser($config, $uid, $password)
    {
        $ldap = Ldap::create('ext_ldap', array(
            'connection_string' => $config['ldap_connection_url']
        ));
        $base = $config['ldap_user_base'];
        $key = $config['ldap_user_key'];
        $dn = $key.'='.$uid.','.$base;
        /* $username = $this->_ldap->escape($dn, '', LdapInterface::ESCAPE_DN); */

        $ldap->bind($dn, $password);
    }

    public function queryUsers($config)
    {
        $ldap = Ldap::create('ext_ldap', array(
            'connection_string' => $config['ldap_connection_url']
        ));
        $password = getenv('LDAP_PW');
        $dn = $config['ldap_search_dn'];
        $baseDn = $config['ldap_user_base'];
        $filter = $config['ldap_user_filter'];
        $ldap->bind($dn, $password);

        return $ldap->query($baseDn, $filter)->execute()->toArray();
    }

    public function queryGroups($config)
    {
        $ldap = Ldap::create('ext_ldap', array(
            'connection_string' => $config['ldap_connection_url']
        ));
        $password = getenv('LDAP_PW');
        $dn = $config['ldap_search_dn'];
        $baseDn = $config['ldap_group_base'];
        $filter = $config['ldap_group_filter'];
        $ldap->bind($dn, $password);

        return $ldap->query($baseDn, $filter)->execute()->toArray();
    }
}
