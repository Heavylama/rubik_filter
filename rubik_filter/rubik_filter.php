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
    private $rc;

    function init() {
        $this->rc = rcube::get_instance();
        
        // localization
        $this->add_texts('localization/', true);

        $this->add_hook('folders_list', array($this, 'test'));
        $this->add_hook('settings_actions', array($this, 'settings_hook'));
    }

    function test($args) {
        $this->rc->console("test hook\n");
    }

    function settings_hook($args) {
        $rc = rcube::get_instance();
        $rc->console("In the hook\n");
        $rc->console($args);

        $section = array(
            'command' => 'test',
            'type' => 'link',
            'label' => 'rubik_filter.settings_title',
            'class' => 'filter'
        );

        $args['actions'][] = $section;

        return $args;
    }
}