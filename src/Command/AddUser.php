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

use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class AddUser extends Command
{
    private $_em;
    private $_jobData;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordEncoderInterface $encoder
    )
    {
        $this->_em = $em;
        $this->_encoder = $encoder;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:user')
            ->setDescription('Add and manage User accounts.')
            ->setHelp('This command allows to create and manage user accounts for the web application.')
            ->addArgument('username', InputArgument::REQUIRED, 'The user name of the new account.')
            ->addArgument('password', InputArgument::REQUIRED, 'Password of new account')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address of user.')
            ->addArgument('roles', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'List of user roles (ROLE_USER, ROLE_ADMIN, ROLE_API, ROLE_ANALYST)')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');
        $email = $input->getArgument('email');
        $roles = $input->getArgument('roles');

        $repository = $this->_em->getRepository(\App\Entity\User::class);

        /* validate input */
        /* if (empty($username)){ */
            /* $output->writeln('<error></error>'); */
        /* } */

        $output->writeln([
            'Create user ',$username,
            'with roles', implode(',',$roles),
            '',
        ]);

        $user = new User();
        $password = $this->_encoder->encodePassword($user, $plainPassword);

        $user->setUsername($username);
        $user->setPassword($password);
        $user->setName('Local account');
        $user->setEmail($email);

        foreach ( $roles as $role ){
            $user->addRole($role);
        }

        $user->setIsActive(true);
        $this->_em->persist($user);
        $this->_em->flush();
    }
}


