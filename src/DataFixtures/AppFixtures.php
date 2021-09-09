<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Configuration;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    private static $_defaultConfig = array(
        array('plot_view_showRoofline',       'true',   'default', 'Show roofline plot',                  'true|false', 'Enter true or false'),
        array('plot_view_showPolarplot',      'true',   'default', 'Show polar plot',                     'true|false', 'Enter true or false'),
        array('plot_view_showStatTable',      'true',   'default', 'Show stat table',                     'true|false', 'Enter true or false'),
        array('plot_view_plotsPerRow',        '3',      'default', 'Plots per row in job view',           '[0-9]+',     'Enter a positive integer'),
        array('plot_general_colorscheme',     'Accent', 'default', 'Plot color scheme',                   '',           ''),
        array('plot_general_interactive',     'false',  'default', 'Interactive plots in job view',       'true|false', 'Enter true or false'),
        array('plot_general_lineWidth',       '2',      'default', 'Line width for plots',                '[0-9]+',     'Enter a positive integer'),
        array('plot_general_colorBackground', 'true',   'default', 'Color plot background',               'true|false', 'Enter true or false'),
        array('general_user_scramble',        'false',  'default', 'Anonymize user names',                '',           ''),
        array('general_user_emailbase', '@mail.de',  'default', 'Email base adress used in user imports', '',           ''),
        array('plot_list_selectedMetrics', '["flops_any","mem_bw","mem_used"]', 'default', 'Metrics to show in job list', '', 'Enter a JSON list of metrics'),
        array('plot_list_jobsPerPage', '25', 'default', 'Jobs per page in job list', '', 'Enter a positive integer'),
        array('analysis_view_histogramMetrics', '["flops_any","mem_bw","mem_used"]', 'default', 'Metrics to show in histograms on analysis view', '', 'Enter a JSON list of metrics'),
        array('analysis_view_scatterPlotMetrics', '[["flops_any", "mem_bw"], ["flops_any", "cpu_load"], ["cpu_load", "mem_bw"]]', 'default', 'Pairs of metrics to show in scatter plots', '', 'Enter a JSON list of metric pairs'),
        array('job_view_selectedMetrics', '["flops_any","mem_bw","mem_used"]', 'default', 'Plots of metrics to show in job view', '', 'Enter a JSON list of metrics'),
        array('job_view_nodestats_selectedMetrics', '["flops_any","mem_bw","mem_used"]', 'default', 'Metrics to show in node statistics table', '', 'Enter a JSON list of metrics'),
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

        /* create admin user */
        $user = new User();
        $user->setUsername('admin');
        $user->setName('Local account');
        $user->setEmail('admin@ccdemo.de');
        $user->addRole('ROLE_USER');
        $user->addRole('ROLE_ANALYST');
        $user->addRole('ROLE_ADMIN');
        $user->setIsActive(true);
        $user->setPassword('$2y$13$oaRyvkkCZYjL/iNsztqLjeQ36QhDQPXpYYmzTB6TBa3hNho9.56qS');
        $manager->persist($user);
        $manager->flush();
    }
}
