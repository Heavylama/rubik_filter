<?php

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

    function init() { 
        // localization
        $this->add_texts('localization/', true);
        
        $this->add_hook('settings_action', array($this, 'settings_hook'));
    }

    function settings_hook($args) {
        $section = array(
            'command' => '',
            'type' => 'link',
            'label' => 'rubik_filter.settings_title',
            'class' => ''
        );

        $args['actions'][] = $section;

        return $args;
    }
}