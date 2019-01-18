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
    private $_em;

    private static $_defaultConfig = array(
        array('plot_view_showRoofline',       'true',   'default', 'Show roofline plot',                  'true|false', 'Enter true or false'),
        array('plot_view_showPolarplot',      'true',   'default', 'Show polar plot',                     'true|false', 'Enter true or false'),
        array('plot_view_showStatTable',      'true',   'default', 'Show stat table',                     'true|false', 'Enter true or false'),
        array('plot_view_plotsPerRow',        '3',      'default', 'Plots per row in job view',           '[0-9]+',     'Enter a positive integer'),
        array('plot_list_samples',            '120',    'default', 'Sample points for downsampled plots', '[0-9]+',     'Enter a positive integer'),
        array('plot_list_sortColumn',         '1',      'default', 'Default sort column',                 '[0-9]+',     'Enter a positive integer'),
        array('plot_list_sortDirection',      'desc',   'default', 'Default sort direction',              'asc|desc',   'Enter asc or desc'),
        array('plot_general_colorscheme',     'Accent', 'default', 'Plot color scheme',                   NULL,         NULL),
        array('plot_general_interactive',     'false',  'default', 'Interactive plots in job view',       'true|false', 'Enter true or false'),
        array('plot_general_linewidth',       '2',      'default', 'Line width for plots',                '[0-9]+',     'Enter a positive integer'),
        array('plot_general_colorBackground', 'true',   'default', 'Color plot background',               'true|false', 'Enter true or false'),
        array('data_time_digits',             '2',      'default', 'Timestamp rounding digits',           '[0-9]+',     'Enter a positive integer'),
        array('data_metric_digits',           '1',      'default', 'Metric rounding digits',              '[0-9]+',     'Enter a positive integer'),
        array('data_cache_period',            '40',     'default', 'Cache grace time',                    '[0-9]+',     'Enter a positive integer'),
        array('data_cache_numpoints',         '40',     'default', 'Cache build threshold',               '[0-9]+',     'Enter a positive integer'),
    );

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $em
    )
    {
        $this->_logger = $logger;
        $this->_em = $em;
        $this->_repository = $em->getRepository(\App\Entity\Configuration::class);
    }

    public function getUserConfig($user)
    {
        return $this->_repository->findAllScope(array($user->getUsername()));
    }

    public function initConfig()
    {
        foreach( self::$_defaultConfig as $config ) {
            $option = new Configuration();
            $option->setName($config[0]);
            $option->setValue($config[1]);
            $option->setScope($config[2]);
            $option->setLabel($config[3]);
            $option->setValidation($config[4]);
            $option->setFeedback($config[5]);
            $em->persist($option);
        }
        $em->flush();
    }

    public function getConfig()
    {
        return  $this->_repository->findAllDefault();
    }

    public function getValue($key)
    {
        return $this->_config[$key]->value;
    }
}
