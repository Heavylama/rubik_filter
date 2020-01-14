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
    /**
     * @var SftpIO
     */
    private $ftp;
    private $config;

    function init() {
        // roundcube instance
        $rc = rcube::get_instance();
        
        // localization
        $this->add_texts('localization/', true);

        // config
        $this->load_config();
        $this->config = $rc->config;

        // sftp
        $user = $rc->config->get('rubik_ftp_user');
        $pw = $rc->config->get('rubik_ftp_pw');
        $host = $rc->config->get('rubik_ftp_host');

        $this->ftp = new \Rubik\Storage\ProcmailStorage($host, $user, $pw);

        $this->ftp->getProcmailRules();

        // hooks
        $this->add_hook('folders_list', array($this, 'test'));
        $this->add_hook('settings_actions', array($this, 'settings_hook'));

    }

    function test($args) {
        rcmail::get_instance()->console("test hook\n");
    }

    function settings_hook($args) {
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