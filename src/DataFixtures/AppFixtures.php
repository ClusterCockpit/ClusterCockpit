<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Configuration;
use App\Entity\TableSortConfig;

class AppFixtures extends Fixture
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
        array('plot_general_linewidth',       '2',      'default', 'Line width for plots',                '[0-9]+',     'Enter a positive integer'),
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
        array('ldap_group_base',              '',       'default', 'Base DN groups',                      '',           ''),
        array('ldap_group_filter',            '',       'default', 'Group query filter',                  '',           ''),
        array('general_user_scramble',        'false',  'default', 'Anonymize user names',                '',           ''),
    );

    public function load(ObjectManager $manager)
    {
        /* create default configuration */
        foreach( self::$_defaultConfig as $config ) {
            $option = new Configuration();
            $option->setName($config[0]);
            $option->setValue($config[1]);
            $option->setScope($config[2]);
            $option->setLabel($config[3]);
            $option->setValidation($config[4]);
            $option->setFeedback($config[5]);
            $manager->persist($option);
        }
        $manager->flush();

        /* create default sort config */
        foreach( self::$_defaultSortConfig as $config ) {
            $option = new TableSortConfig();
            $option->setPosition($config[0]);
            $option->setSlot($config[1]);
            $option->setLabel($config[2]);
            $option->setType($config[3]);
            $option->setAccessKey($config[4]);
            $manager->persist($option);
        }
        $manager->flush();
    }
}

