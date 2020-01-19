<?php

use Rubik\Storage\SftpIO as SftpIO;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Rubik Filter
 * 
 * Plugin facilitating email filtering and OOF messages.
 * 
 * @author Tomas Spanel
 * @version 1.0
 */
class rubik_filter extends rcube_plugin
{
    public $task = 'settings';

    private $config;

    function init() {
        // roundcube instance
        $rc = rcube::get_instance();
        
        // localization
        $this->add_texts('localization/', true);

        // config
        $this->load_config();
        $this->config = $rc->config;

        // hook to add a new item in settings list
        $this->add_hook('settings_actions', array($this, 'settings_hook'));

        $this->register_action('rubik_filter.t', array($this, 'rubik_filter'));
    }

    function settings_hook($args) {
        $section = array(
            'command' => '',
            'type' => 'link',
            'domain' => 'rubik_filter' ,
            'label' => 'settings_title',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = $section;

        return $args;
    }

    function rubik_filter_settings_actions() {

    }

}