<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock as ConditionBlock;
use Rubik\Procmail\FilterActionBlock;
use Rubik\Procmail\FilterBuilder as FilterBuilder;
use Rubik\Procmail\FilterParser;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;
use Rubik\Storage\ProcmailStorage as ProcmailStorage;
use Rubik\Storage\RubikSftpClient;

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

    /** @var rcube_config */
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

        // actions
        $this->register_action("plugin.rubik_filter_settings", array($this, 'show_filter_settings'));
        $this->register_action("plugin.rubik_filter_new_filter", array($this, 'show_filter_form'));
        $this->register_action("plugin.rubik_filter_edit_filter", array($this, 'show_filter_form'));
        $this->register_action("plugin.rubik_filter_save_filter", array($this, 'save_filter'));
        $this->register_action("plugin.rubik_filter_remove_filter", array($this, 'remove_filter'));
        $this->register_action("plugin.rubik_filter_swap_filters", array($this, 'swap_filters'));
        $this->register_action("plugin.rubik_filter_toggle_filter", array($this, 'toggle_filter'));

        // ui handlers
        $this->register_handler("plugin.rubik_filter_form", array($this, 'filter_form'));
        $this->register_handler("plugin.rubik_filter_list", array($this, 'filter_list'));
    }

    function settings_hook($args) {
        $section = array(
            'command' => 'plugin.rubik_filter_settings',
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'settings_title',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = $section;

        return $args;
    }

    function show_filter_settings() {
        $rc = rcmail::get_instance();
        $rc->output->set_pagetitle($this->gettext('settings_title'));
        $rc->output->send("rubik_filter.filter_settings");
    }

    function show_filter_form() {
        $rc = rcmail::get_instance();
        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $filterId = rcube_utils::get_input_value('_filterid', rcube_utils::INPUT_GET);

        if ($filterId !== null) {

            $filters = $this->getFilters($rc);

            if (isset($filters[intval($filterId)])) {

                /** @var FilterBuilder $filter */
                $filter = $filters[$filterId];

                $arg = array(
                    'id' => $filterId,
                    'name' => $filter->getName(),
                    'conditions' => array(),
                    'actions' => array()
                );

                foreach ($filter->getActionBlock()->getActions() as $action => $values) {
                    foreach ($values as $val) {
                        $arg['actions'][] = array(
                            'action' => $action,
                            'val' => $val
                        );
                    }
                }

                $conditionBlock = $filter->getConditionBlock();

                if ($conditionBlock !== null) {
                    /** @var Condition $condition */
                    foreach ($conditionBlock->getConditions() as $condition) {
                        $clientCondition = array(
                            'field' => $condition->field,
                            'op' => $condition->op,
                            'val' => $condition->value
                        );

                        if ($condition->negate) {
                            $clientCondition['op'] = "!".$clientCondition['op'];
                        }

                        if ($condition->op !== ProcmailOperator::PLAIN_REGEX) {
                            $clientCondition['val'] = stripslashes($clientCondition['val']);
                        }

                        $arg['conditions'][] = $clientCondition;
                    }

                    $arg['type'] = $conditionBlock->getType();
                } else {
                    $arg['type'] = ConditionBlock::AND;
                }

                $output->set_env('rubik_filter', $arg);
            }
        }

        $output->send("rubik_filter.filter_form");
    }

    /**
     * Create html form for creating a procmail rule.
     * @return string
     */
    function filter_form() {
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

        $handle = html::tag('i', array('class' => 'rubik-handle'));

        // Table containing template elements
        $table = new html_table(array('class' => 'hidden'));
        $table->add_row(array('id' => 'rubik-filter-condition-template'));
        $table->add('rubik-handle-cell', $handle);
        $table->add('input rubik-field-cell', $field_select->show());
        $table->add('input rubik-operator-cell', $operator_select->show());
        $table->add('input rubik-cond-value-cell',  $condition_value->show());
        $table->add('rubik-controls', $controls);

        $action_select = new html_select(array('name' => 'action'));
        foreach (FilterActionBlock::VALID_FILTER_ACTIONS as $value) {
            $action_select->add($this->gettext($value), $value);
        }
        $action_value = new html_inputfield(array('name' => 'action_value'));

        $table->add_row(array('id' => 'rubik-filter-action-template'));
        $table->add('rubik-handle-cell', $handle);
        $table->add('input rubik-action-cell', $action_select->show());
        $table->add('input rubik-action-value-cell', $action_value->show());
        $table->add('rubik-controls', $controls);

        $out = '';

        $out .= $table->show();

        // Filter name
        $nameInput = new html_inputfield(array('name' => 'filter-name'));
        $nameLegend = html::tag('legend', null, $this->gettext('label_filter_name'));
        $nameFieldset = html::tag('fieldset', null, $nameLegend.$nameInput->show());
        $out .= $nameFieldset;

        // Table of conditions
        $conditions = new html_table(array('id' => 'rubik-condition-list', 'class' => 'propform'));

        $conditions->add_header('rubik-handle-cell', null);
        $conditions->add_header("title rubik-field-cell", html::label('field', $this->gettext('field_input_title')));
        $conditions->add_header("title rubik-operator-cell", html::label('operator', $this->gettext('operator_input_title')));
        $conditions->add_header('title rubik-cond-value-cell',
            html::label('condition_value', $this->gettext('condition_value_input_title'))
        );
        $conditions->add_header('rubik-controls',null);

        $add_condition_button = new html_button(array('class' => 'btn-primary create'));
        $buttons = html::div(array('class' => 'formbuttons'),
            $add_condition_button->show($this->gettext('form_add_condition'), array('id'=> 'rubik-condition-add')));


        $conditionsLegend = html::tag('legend', null, $this->gettext('title_conditions'));

        $conditionTypeLabel = html::label('condition-block-type', $this->gettext('condition_type_input_title'));
        $conditionTypeInput = new html_select(array('name' => 'condition-block-type'));
        $conditionTypeInput->add(
            array($this->gettext(ConditionBlock::AND), $this->gettext(ConditionBlock::OR)),
            array(ConditionBlock::AND, ConditionBlock::OR)
        );

        $conditionsFieldset = '';
        $conditionsFieldset .= $conditionsLegend;
        $conditionsFieldset .= html::div(array('id' => 'conditions-block-options'),$conditionTypeLabel.$conditionTypeInput->show());
        $conditionsFieldset .= $conditions->show();
        $conditionsFieldset .= $buttons;

        $out .= html::tag('fieldset',
            null,
            $conditionsFieldset
        );

        // Table of actions
        $actions = new html_table(array('id' => 'rubik-action-list', 'class' => 'propform'));
        $actions->add_header('rubik-handle-cell', null);
        $actions->add_header('title rubik-action-cell', html::label('action', $this->gettext('action_input_title')));
        $actions->add_header('title rubik-action-value-cell',
            html::label('action_value', $this->gettext('condition_value_input_title'))
        );
        $actions->add_header('rubik-controls', null);

        $actionLegend = html::tag('legend', null, $this->gettext('title_actions'));
        $add_action_button = new html_button(array('class' => 'btn-primary create'));
        $buttons = html::div(array('class' => 'formbuttons'),
            $add_action_button->show($this->gettext('form_add_action'), array('id' => 'rubik-action-add'))
        );

        $out .= html::tag('fieldset',
            null,
            $actionLegend.$actions->show().$buttons);

        // Close the form content
        $out = $rc->output->form_tag(array("id" => "rubik-rule-form", "method" => "POST", "class" => "propform"), $out);
        $out = html::div(array('class' => 'formcontent'), $out);

        // Form buttons
        $buttons = '';

        $save_button = new html_button(array('class' => 'btn-primary submit'));

        $buttons .= $save_button->show($this->gettext('form_save_filter'), array('id' => 'rubik-save-rule'));

        $out .= html::div(array('class' => 'formbuttons'), $buttons);

        $out = html::div(array('class' => 'formcontainer'), $out);

        return $out;
    }

    function filter_list($attrib) {
        $rc = rcmail::get_instance();

        $attrib['id'] = 'filterlist';

        $out = '';

        $filters = $this->getFilters($rc);

        if (!is_array($filters)) {
            $rc->output->show_message('plugin.msg_error_load_filter','error');
        } else {
            $names = array();

            $enabledReplace = array();

            /** @var FilterBuilder $filter */
            foreach ($filters as $key => $filter) {
                $name = $filter->getName();

                if (empty($name)) {
                    $name = "Filter $key";
                }

                $id = $key;

                $names[] = array(
                    'id' => $id,
                    'name' => $name,
                );

                $isEnabled = $filter->getFilterEnabled();
                $command = "rcmail.command('toggle_filter', this)";

                $enabledCheckbox =
                    "</td><td class='checkbox-cell' style='text-align: right;'>".
                    "<div class='custom-control custom-switch'><input class='form-check-input custom-control-input' type='checkbox'";
                $enabledCheckbox .= ($isEnabled ? 'checked' : '');
                $enabledCheckbox .= " /><label onclick=\"$command\" class='custom-control-label'/></div>";
                $enabledCheckbox .= "</td>";

                $enabledReplace[] = $enabledCheckbox;
            }

            $out = $rc->table_output($attrib, $names, array('name'),'id');

            preg_match_all("/<\/td>/", $out, $matches, PREG_OFFSET_CAPTURE);

            $injectedOut = $out;

            $offset = 0;

            foreach ($matches[0] as $key => $match) {
                $replacement = $enabledReplace[$key];

                $injectedOut = substr_replace($injectedOut, $replacement, $match[1] + $offset, 5);

                $offset += strlen($replacement) - 5;
            }

            $out = $injectedOut;

            $rc->output->add_gui_object('filterlist', $attrib['id']);
            $rc->output->include_script('list.js');
        }

        return $out;
    }

    function save_filter() {
        $rc = rcube::get_instance();
        $rcmd = 'plugin.rubik_filter_save_result';

        $clientActions = rcube_utils::get_input_value('filter_actions', rcube_utils::INPUT_POST);
        $clientConditions = rcube_utils::get_input_value('filter_conditions', rcube_utils::INPUT_POST);
        $clientConditionsType = rcube_utils::get_input_value('filter_conditions_type', rcube_utils::INPUT_POST);
        $clientFilterId = rcube_utils::get_input_value('filter_id', rcube_utils::INPUT_POST);
        $clientFilterName = rcube_utils::get_input_value('filter_name', rcube_utils::INPUT_POST);

        if (empty($clientActions)) {
            $this->outputResult($rc, $rcmd, false, 'msg_no_action');
            return;
        }

        if (empty($clientConditions)) {
            $clientConditions = array();
        }

        // parse conditions
        $conditionBlock = new ConditionBlock();

        if ($conditionBlock->setType($clientConditionsType) === false) {
            $this->outputResult($rc, $rcmd, false, 'msg_invalid_condition_block_type');
            return;
        }

        foreach ($clientConditions as $clientCond) {
            $field = $clientCond['field'];
            $operator = $clientCond['op'];

            if ($operator[0] === '!') {
                $negate = true;
                $operator = substr($operator, 1);
            } else {
                $negate = false;
            }

            $value = $clientCond['val'];

            $cond = Condition::create($field, $operator, $value, $negate);

            if ($cond === null) {
                $this->outputResult($rc, $rcmd, false, 'msg_invalid_cond');
                return;
            }

            $conditionBlock->addCondition($cond);
        }

        $filterBuilder = new FilterBuilder();
        $filterBuilder->setConditionBlock($conditionBlock);

        foreach ($clientActions as $clientAction) {
            if (!$filterBuilder->addAction($clientAction['action'], $clientAction['val'])) {
                $this->outputResult($rc, $rcmd, false, 'msg_invalid_action');
                return;
            }
        }

        if (!empty($clientFilterName)) {
            $filterBuilder->setName($clientFilterName);
        }

        $filter = $filterBuilder->createFilter();

        if ($filter === null) {
            $this->outputResult($rc, $rcmd, false, 'msg_invalid_filter');
            return;
        }

        if ($this->updateFilter($rc, $clientFilterId, $filter) !== true) {
            $this->outputResult($rc, $rcmd, false, 'msg_error_storage');
            return;
        }

        $this->outputResult($rc, $rcmd, true, 'msg_filter_saved');
        $rc->output->redirect('plugin.rubik_filter_settings');
        $rc->output->send();
    }

    function toggle_filter() {
        $rc = rcmail::get_instance();
        $output = $rc->output;

        $filterId = rcube_utils::get_input_value('filter_id', rcube_utils::INPUT_POST);

        if ($filterId === null || !is_numeric($filterId)) {
            $output->show_message('rubik_filter.msg_error_disable_filter', 'error');
            return;
        }

        $filterId = intval($filterId);

        if ($this->toggleFilter($rc, $filterId) !== true) {
            $output->show_message('rubik_filter.msg_error_disable_filter', 'error');
        } else {
            $output->show_message('rubik_filter.msg_success_disable_filter', 'confirmation');
            $output->redirect('plugin.rubik_filter_settings');
        }
    }

    function remove_filter() {
        $rc = rcmail::get_instance();

        $filterId = rcube_utils::get_input_value('filterid', rcube_utils::INPUT_POST);

        if (!empty($filterId)) {
            $result = $this->updateFilter($rc, $filterId, null);

            if ($result !== true) {
                $rc->output->show_message('rubik_filter.msg_error_remove_filter', 'error');
            } else {
                $rc->output->show_message('rubik_filter.msg_success_remove_filter', 'confirmation');
            }

            $rc->output->redirect('plugin.rubik_filter_settings');
            $rc->output->send();
        }
    }

    function swap_filters() {
        $rc = rcmail::get_instance();
        $output = $rc->output;

        $id1 = rcube_utils::get_input_value('filter_swap_id1', rcube_utils::INPUT_POST);
        $id2 = rcube_utils::get_input_value('filter_swap_id2', rcube_utils::INPUT_POST);

        if($id1 === null || $id2 === null) {
            $output->show_message('rubik_filter.msg_error_swap_filter', 'error');
            return;
        }

        $id1 = intval($id1);
        $id2 = intval($id2);

        if ($this->swapFilters($rc, $id1, $id2) !== true) {
            $output->show_message('rubik_filter.msg_error_swap_filter', 'error');
        } else {
            $output->show_message('rubik_filter.msg_success_swap_filter', 'confirmation');
        }

        $output->redirect('plugin.rubik_filter_settings');
    }


    private function outputResult($rc, $cmd, $success, $message) {
        $rc->output->show_message("rubik_filter.$message", $success ? "confirmation" : "error");
//        $rc->output->command($cmd, array('success' => $success, 'msg' => $message));
    }

    private function getFilters($rc, $client = null) {
        if ($client == null) {
            $client = $this->getStorageClient($rc);
        }

        $procmail = $client->getProcmailRules();

        $parser = new FilterParser();

        return $parser->parse($procmail);
    }

    private function updateFilter($rc, $id, $newFilter) {
        if ($id === null && $newFilter === null) {
            return false;
        }

        if ($newFilter === null) {
            $newFilter = '';
        }

        $client = $this->getStorageClient($rc);

        $procmail = '';

        if ($id !== null) {
            $filters = $this->getFilters($rc, $client);
            if ($filters === null) {
                return false;
            }

            $id = intval($id);

            if(isset($filters[$id])) {
                $filters[$id] = null;
            }

            /** @var FilterBuilder $oldFilter */
            foreach ($filters as $key => $oldFilter) {
                if ($key === $id) {
                    $procmail .= $newFilter;
                } else {
                    $procmail .= $oldFilter->createFilter();
                }
            }
        } else {
            $oldProcmail = $client->getProcmailRules();

            if(!is_string($oldProcmail)) {
                if ($oldProcmail === ProcmailStorage::ERR_NO_FILE) {
                    $procmail = '';
                } else {
                    return false;
                }
            } else {
                $procmail .= $oldProcmail;
            }

            $procmail .= $newFilter;
        }

        return $client->putProcmailRules($procmail);
    }

    private function swapFilters($rc, $id1, $id2) {
        $client = $this->getStorageClient($rc);

        $filters = $this->getFilters($rc, $client);
        if ($filters === null || !array_key_exists($id1, $filters) || !array_key_exists($id2, $filters)) {
            return false;
        }

        $filter1 = $filters[$id1];
        $filters[$id1] = $filters[$id2];
        $filters[$id2] = $filter1;

        return $this->storeFilters($filters, $client);
    }

    private function toggleFilter($rc, $id) {
        $client = $this->getStorageClient($rc);

        $filters = $this->getFilters($rc, $client);

        if ($filters === null || !isset($filters[$id])) {
            return null;
        }

        $filters[$id]->setFilterEnabled(!$filters[$id]->getFilterEnabled());

        return $this->storeFilters($filters, $client);
    }

    /**
     * @param $filters array
     * @param $storage ProcmailStorage
     * @return bool|int
     */
    private function storeFilters($filters, $storage) {
        $procmail = '';

        /** @var FilterBuilder $filter */
        foreach ($filters as $filter) {
            $procmail .= $filter->createFilter();
        }

        return $storage->putProcmailRules($procmail);
    }

    /**
     * @param $rc rcube
     * @return ProcmailStorage
     */
    private function getStorageClient($rc) {
        $client = new RubikSftpClient($this->config->get('rubik_ftp_host'));
        $pw = $rc->get_user_password();
        $userName = explode("@", $rc->get_user_name())[0];

        return new ProcmailStorage(
            $client ,
            $userName,
            $pw
        );
    }
}