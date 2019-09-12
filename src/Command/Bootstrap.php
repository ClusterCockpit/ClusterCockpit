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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Configuration;
use App\Entity\TableSortConfig;

class Bootstrap extends Command
{
    private static $_defaultSortConfig = array(
        array('1', '0', 'Severity', 'job',  'severity'),
        array('2', '0', 'MemBwAvg', 'data', 'mem_bw'),
        array('3', '1', 'FlopsAny', 'data', 'flops_any'),
        array('4', '0', 'NumNodes', 'job',  'numNodes')
    );

    private static $_defaultConfig = array(
        array('plot_view_showRoofline',       'true',   'default', 'Show roofline plot',                  'true|false', 'Enter true or false'),
        array('plot_view_showPolarplot',      'true',   'default', 'Show polar plot',                     'true|false', 'Enter true or false'),
        array('plot_view_showStatTable',      'true',   'default', 'Show stat table',                     'true|false', 'Enter true or false'),
        array('plot_view_plotsPerRow',        '3',      'default', 'Plots per row in job view',           '[0-9]+',     'Enter a positive integer'),
        array('plot_list_samples',            '120',    'default', 'Sample points for downsampled plots', '[0-9]+',     'Enter a positive integer'),
        array('plot_list_sortColumn',         '1',      'default', 'Default sort column',                 '[0-9]+',     'Enter a positive integer'),
        array('plot_list_sortDirection',      'desc',   'default', 'Default sort direction',              'asc|desc',   'Enter asc or desc'),
        array('plot_general_colorscheme',     'Accent', 'default', 'Plot color scheme',                   '',           ''),
        array('plot_general_interactive',     'false',  'default', 'Interactive plots in job view',       'true|false', 'Enter true or false'),
        array('plot_general_lineWidth',       '2',      'default', 'Line width for plots',                '[0-9]+',     'Enter a positive integer'),
        array('plot_general_colorBackground', 'true',   'default', 'Color plot background',               'true|false', 'Enter true or false'),
        array('data_time_digits',             '2',      'default', 'Timestamp rounding digits',           '[0-9]+',     'Enter a positive integer'),
        array('data_metric_digits',           '1',      'default', 'Metric rounding digits',              '[0-9]+',     'Enter a positive integer'),
        array('data_cache_period',            '40',     'default', 'Cache grace time',                    '[0-9]+',     'Enter a positive integer'),
        array('data_cache_numpoints',         '40',     'default', 'Cache build threshold',               '[0-9]+',     'Enter a positive integer'),
        array('ldap_connection_url',          '',       'default', 'Connection URL',                      '',           ''),
        array('ldap_user_base',               '',       'default', 'Base DN users',                       '',           ''),
        array('ldap_user_key',                '',       'default', 'Key user id',                         '',           ''),
        array('ldap_search_dn',               '',       'default', 'Search DN',                           '',           ''),
        array('ldap_user_filter',             '',       'default', 'User query filter',                   '',           ''),
        array('general_user_scramble',        'false',  'default', 'Anonymize user names',                '',           ''),
        array('general_user_emailbase',            '@mail.de',  'default', 'Email base adress used in user imports',                '',           ''),
    );


    private $_em;

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
            ->setName('app:init')
            ->setDescription('Initialize database and add admin user.')
            ->setHelp('This command allows to create and manage user accounts for the web application.')
            ;
    }

    public function load()
    {
        $repository = $this->_em->getRepository(\App\Entity\Configuration::class);
        $count =  $repository->createQueryBuilder('c')
            ->select('count(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ( $count > 0 ){
            return false;
        }

        /* create default configuration */
        foreach( self::$_defaultConfig as $config ) {
            $option = new Configuration();
            $option->setName($config[0]);
            $option->setValue($config[1]);
            $option->setScope($config[2]);
            $option->setLabel($config[3]);
            $option->setValidation($config[4]);
            $option->setFeedback($config[5]);
            $this->_em->persist($option);
        }
        $this->_em->flush();

        /* create default sort config */
        foreach( self::$_defaultSortConfig as $config ) {
            $option = new TableSortConfig();
            $option->setPosition($config[0]);
            $option->setSlot($config[1]);
            $option->setLabel($config[2]);
            $option->setType($config[3]);
            $option->setAccessKey($config[4]);
            $this->_em->persist($option);
        }
        $this->_em->flush();

        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Initialize database?', false);

        if ($helper->ask($input, $output, $question)) {
            if ($this->load()) {
                $output->writeln('Initialize database: done.');
            } else {
                $output->writeln('Database already initialized: skipped.');
            }
        }

        $question = new ConfirmationQuestion('Create admin user?', false);

        if ($helper->ask($input, $output, $question)) {

            $repository = $this->_em->getRepository(\App\Entity\User::class);
            $user = $repository->findOneByUsername('admin');

            if ( is_null($user) ){
                $question = new Question('Please enter a valid email adress for the admin user: ');
                $email = $helper->ask($input, $output, $question);

                $question = new Question('Enter a password for the admin user: ');
                $question->setValidator(function ($value) {
                    if (trim($value) == '') {
                        throw new \Exception('The password cannot be empty');
                    }

                    return $value;
                });
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                $question->setMaxAttempts(5);
                $plainPassword = $helper->ask($input, $output, $question);

                $user = new User();
                $user->setUsername('admin');
                $user->setName('Local account');
                $user->setEmail($email);
                $user->addRole('ROLE_USER');
                $user->addRole('ROLE_ANALYST');
                $user->addRole('ROLE_ADMIN');
                $user->setIsActive(true);
                $password = $this->_encoder->encodePassword($user, $plainPassword);
                $user->setPassword($password);
                $this->_em->persist($user);
                $this->_em->flush();
                $output->writeln('Create admin user: done.');
            } else {
                $output->writeln('Admin user already exists: skipped.');
            }
        }
    }
}
