<?php

use Rubik\Procmail\Field as ProcmailField;
use Rubik\Procmail\Operator as ProcmailOperator;
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

        $this->include_script('scripts/Sortable.js');
        $this->include_script('scripts/rubik_filter.js');
        $this->include_stylesheet('styles/rubik_filter.css');

        // hook to add a new item in settings list
        $this->add_hook('settings_actions', array($this, 'settings_hook'));

        $this->register_action("plugin.rubik_filter", array($this, 'rubik_filter_main'));
        $this->register_handler("plugin.rubik_form", array($this, 'rubik_filter_rule_form'));
    }

    function settings_hook($args) {
        $section = array(
            'command' => 'plugin.rubik_filter',
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'settings_title',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = $section;

        return $args;
    }

    function rubik_filter_main() {
        $rc = rcmail::get_instance();
        $rc->output->set_pagetitle($this->gettext('new_rule'));
        $rc->output->send("rubik_filter.rubik_filter");
    }

    /**
     * Create html form for creating a procmail rule.
     * @return string
     */
    function rubik_filter_rule_form() {
        $rc = rcmail::get_instance();

        // Field select
        $field_select = new html_select(array('name' => 'field'));
        foreach (ProcmailField::values as $val) {
            $field_select->add($this->gettext($val), $val);
        }

        // Operator select
        $operator_select = new html_select(array('name' => 'operator'));
        $not = $this->gettext('operator_input_not');
        foreach(ProcmailOperator::values as $val) {
            $name = $this->gettext($val);
            $operator_select->add($name, $val);
            $operator_select->add($not . $name, '!' . $val);
        }

        // Condition value
        $condition_value = new html_inputfield(array('name' => 'condition_value'));

        // Row controls
        $del_button = new html_button(array('class' => 'btn-primary delete'));

        $controls = $del_button->show(null, null);

        // Table containing template element
        $table = new html_table(array('class' => 'hidden'));
        $table->add_row(array('id' => 'rubik-rule-input-row'));
        $table->add('rubik-handle-cell', html::tag('i', array('class' => 'rubik-handle')));
        $table->add('input', $field_select->show());
        $table->add('input', $operator_select->show());
        $table->add('input',  $condition_value->show());
        $table->add('rubik-controls', $controls);

        $out = '';

        $out .= $table->show();

        // Table of conditions
        $conditions = new html_table(array('id' => 'rubik-rule-list', 'class' => 'propform'));

        $conditions->add_header(null, null);
        $conditions->add_header("title", html::label('field', $this->gettext('field_input_title')));
        $conditions->add_header("title", html::label('operator', $this->gettext('operator_input_title')));
        $conditions->add_header('title', html::label('condition_value', $this->gettext('condition_value_input_title')));

        $out .= html::tag('fieldset', null, $conditions->show());

        // Close the form
        $out = $rc->output->form_tag(array("id" => "rubik-rule-form", "method" => "POST", "class" => "propform"), $out);
        $out = html::div(array('class' => 'formcontent'), $out);

        // Form buttons
        $buttons = '';

        $add_button = new html_button(array('class' => 'btn-primary create'));
        $save_button = new html_button(array('class' => 'btn-primary submit'));

        $buttons .= $add_button->show($this->gettext('form_add_condition'), array('id'=> 'rubik-condition-add'));
        $buttons .= $save_button->show($this->gettext('form_save_rule'), array('id' => 'rubik-save-rule'));

        $out .= html::div(array('class' => 'formbuttons'), $buttons);

        $out = html::div(array('class' => 'formcontainer'), $out);

        return $out;
    }

}