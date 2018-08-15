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
use Symfony\Component\Ldap\Ldap;


class QueryLdap extends Command
{
    private $_ldap;

    public function __construct()
    {
	    $url = getenv('LDAP_URL');
        $this->_ldap = Ldap::create('ext_ldap', array(
		'connection_string' => $url
        ));

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
          $password = getenv('LDAP_PW');
	  /*$dn = 'cn=hpcmonitoring,ou=roadm,ou=profile,ou=hpc,dc=rrze,dc=uni-erlangen,dc=de';*/
	  $dn = getenv('LDAP_DN');
	  $this->_ldap->bind($dn, $password);

	  $query = $this->_ldap->query('ou=people,ou=hpc,dc=rrze,dc=uni-erlangen,dc=de', '(&(objectclass=posixAccount)(uid=*))');
	  $results = $query->execute()->toArray();
	  $users = array();

	  var_dump($results);

	  foreach ( $results as $entry ) {

		  $user_id;
		  $uid;
		  $name;

		  if ( $entry->hasAttribute('uid') ) {
			  $user_id = $entry->getAttribute('uid')[0];
		  }
		  if ( $entry->hasAttribute('uidNumber') ) {
			  $uid = $entry->getAttribute('uidNumber')[0];
		  }
		  if ( $entry->hasAttribute('gecos') ) {
			  $name = $entry->getAttribute('gecos')[0];
		  }

		  $users[$user_id] = array(
			  'user_id' => $user_id,
			  'uid' => $uid,
			  'name' => $name
		  );
	  }

	  $query = $this->_ldap->query('ou=Group,ou=hpc,dc=rrze,dc=uni-erlangen,dc=de', '(&(objectclass=posixGroup)(cn=*))');
	  $results = $query->execute()->toArray();
	  $groups = array();
	  $userGroup = array();
	  $activeUsers = array();

	  foreach ( $results as $entry ) {

		  $group_id;
		  $gid;
		  $members;

		  if ( $entry->hasAttribute('cn') ) {
			  $group_id = $entry->getAttribute('cn')[0];
		  }
		  if ( $entry->hasAttribute('gidNumber') ) {
			  $gid = $entry->getAttribute('gidNumber')[0];
		  }
		  if ( $entry->hasAttribute('memberUid') ) {
			  $members = $entry->getAttribute('memberUid');
		  }

		  $groups[$group_id] = array(
			  'group_id' => $group_id,
			  'gid' => $gid,
			  'members' => $members
		  );

		  foreach ( $members as $user ) {
			  $userGroup[$user][] = $group_id;

			  if ( $group_id === 'infohpc' ) {
				  $ctiveUsers[$user] = 1;
			  }
		  }

	  }

    }
}


