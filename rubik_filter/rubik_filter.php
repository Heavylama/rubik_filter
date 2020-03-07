<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock as ConditionBlock;
use Rubik\Procmail\FilterActionBlock;
use Rubik\Procmail\Filter;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;
use Rubik\Procmail\Vacation;
use Rubik\Storage\FilterCache;
use Rubik\Storage\ProcmailStorage as ProcmailStorage;
use Rubik\Storage\RubikSftpClient;

require_once __DIR__ . '/vendor/autoload.php';

//TODO Subject u vacation pridavat prikazem
//TODO Zpravy se budou brat tak jak jsou



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
        $this->add_hook('settings_actions', array($this, 'hook_settings'));

        // actions
        $this->register_action("plugin.rubik_filter_settings", array($this, 'show_filter_settings'));
        $this->register_action("plugin.rubik_filter_new_filter", array($this, 'show_filter_form'));
        $this->register_action("plugin.rubik_filter_edit_filter", array($this, 'show_filter_form'));
        $this->register_action("plugin.rubik_filter_save_filter", array($this, 'action_save_filter'));
        $this->register_action("plugin.rubik_filter_remove_filter", array($this, 'action_remove_filter'));
        $this->register_action("plugin.rubik_filter_swap_filters", array($this, 'action_swap_filters'));
        $this->register_action("plugin.rubik_filter_toggle_filter", array($this, 'action_toggle_filter'));
        $this->register_action("plugin.rubik_filter_vacation", array($this, 'show_vacation_settings'));
        $this->register_action("plugin.rubik_filter_new_vacation", array($this, 'show_vacation_form'));
        $this->register_action("plugin.rubik_filter_edit_vacation", array($this, 'show_vacation_form'));
        $this->register_action("plugin.rubik_filter_save_vacation", array($this, 'action_save_vacation'));
        $this->register_action("plugin.rubik_filter_remove_vacation", array($this, 'action_remove_vacation'));
        $this->register_action("plugin.rubik_filter_toggle_vacation", array($this, 'action_toggle_vacation'));
        $this->register_action("plugin.rubik_filter_vacation_messages", array($this, 'show_messages_settings'));
        $this->register_action("plugin.rubik_filter_remove_message", array($this, 'action_remove_message'));
        $this->register_action("plugin.rubik_filter_new_message", array($this, 'show_message_form'));
        $this->register_action("plugin.rubik_filter_edit_message", array($this, 'show_message_form'));
        $this->register_action("plugin.rubik_filter_save_message", array($this, 'action_save_message'));
        $this->register_action("plugin.rubik_filter_get_message", array($this, 'action_get_message'));

        // ui handlers
        $this->register_handler("plugin.rubik_filter_form", array($this, 'ui_filter_form'));
        $this->register_handler("plugin.rubik_filter_list", array($this, 'ui_settings_list'));
    }

    function hook_settings($args) {
        $args['actions'][] = array(
            'command' => 'plugin.rubik_filter_settings',
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'settings_title',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = array(
            'command' => 'plugin.rubik_filter_vacation_messages',
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'title_vacation_messages',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = array(
            'command' => 'plugin.rubik_filter_vacation',
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'title_vacation',
            'class' => 'rubikfilter'
        );

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

        $filterId = rcube_utils::get_input_value('_contentid', rcube_utils::INPUT_GET);

        if ($filterId !== null) {

            $filters = $this->getFilters($rc);

            if (isset($filters[intval($filterId)])) {

                /** @var Filter $filter */
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

    function show_vacation_settings() {
        $rc = rcmail::get_instance();
        $rc->output->set_pagetitle($this->gettext('title_vacation'));
        $rc->output->send("rubik_filter.filter_settings");
    }

    function show_vacation_form() {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $client = $this->getStorageClient($rc);

        $vacationId = rcube_utils::get_input_value("_contentid", rcube_utils::INPUT_GET);
        if ($vacationId !== null) {
            $filters = $this->getFilters($rc, $client);

            if (isset($filters[$vacationId])) {
                /** @var Vacation $vacation */
                $vacation = $filters[$vacationId];

                $vacationOut = array(
                    'vacation_name' => $vacation->getName(),
                    'vacation_message' => $vacation->getMessagePath()
                    // TODO
                );
            }
        }

        $messageList = $this->getStorageClient($rc)->listVacationMessages();

        if ($messageList === null) {
            $this->showMessage($rc, 'rubik_filter.msg_err_list_messages', 'error');
            $output->send('iframe');
            return;
        } else if (count($messageList) === 0) {
            $this->showMessage($rc, 'rubik_filter.msg_warn_create_reply', 'warning');
            $output->send('iframe');
            return;
        }



        $output->set_env('vacation_select_options', $messageList);

        $output->send('rubik_filter.vacation_form');
    }

    function show_messages_settings() {
        $rc = rcmail::get_instance();
        $rc->output->set_pagetitle($this->gettext('title_vacation_replies'));
        $rc->output->send("rubik_filter.filter_settings");
    }

    function show_message_form() {
        $rc = rcmail::get_instance();

        $messageId = rcube_utils::get_input_value('_contentid', rcube_utils::INPUT_GET);


        if ($messageId !== null) {
            $message = $this->getMessage($rc, $messageId);

            if ($message === null) {
                $this->showMessage($rc, 'rubik_filter.msg_err_load_reply',  'error');
                $rc->output->send('iframe');

//                $rc->output->raise_error(-1, 'Cannot load vacation reply');

                return;
            }

            $rc->output->set_env('vacation_message', $message);
            $rc->output->set_env('message_filename', $messageId);
        }


        $rc->output->send('rubik_filter.message_form');
    }

    /**
     * Create html form for creating a procmail rule.
     * @return string
     */
    function ui_filter_form() {
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

        return $out;
    }

    function ui_settings_list($attrib) {
        $action = $_REQUEST['_action'];

        if ($action === 'plugin.rubik_filter_vacation' || $action === 'plugin.rubik_filter_settings') {
            return $this->ui_filter_list($attrib);
        } else if ($action === 'plugin.rubik_filter_vacation_messages') {
            return $this->ui_messages_list($attrib);
        }
    }

    function ui_messages_list($attrib) {
        $rc = rcmail::get_instance();

        $messageList = $this->getStorageClient($rc)->listVacationMessages();
        if ($messageList === null) {
            $this->showMessage($rc, 'rubik_filter.msg_err_list_messages', 'error');
            return null;
        }

        $names = array();

        foreach ($messageList as $messageFile) {
            $names[] = array(
                'name' => $messageFile
            );
        }

        $attrib['id'] = 'messagelist';

        $output = $rc->table_output($attrib, $names, array('name'), 'name');

        $rc->output->add_gui_object('vacation_messages_list', $attrib['id']);
        $rc->output->include_script('list.js');

        return $output;
    }

    function ui_filter_list($attrib) {
        $rc = rcmail::get_instance();

        $attrib['id'] = 'filterlist';

        $out = '';

        $filters = $this->getFilters($rc);

        if (!is_array($filters)) {
            $this->showMessage($rc, 'rubik_filter.msg_error_load_filter','error');
        } else {
            $names = array();
            $enabledReplace = array();
            $showVacations = $_REQUEST['_action'] === 'plugin.rubik_filter_vacation';

            /** @var Filter $filter */
            foreach ($filters as $key => $filter) {

                // filter out either filters or vacations
                if ($showVacations !== $filter instanceof Vacation) continue;

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

    function action_save_filter() {
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

        $filterBuilder = new Filter();
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

    function action_toggle_vacation() {
        $this->action_toggle('plugin.rubik_filter_vacation');
    }

    function action_remove_vacation() {
        $this->action_remove('plugin.rubik_filter_vacation');
    }

    function action_toggle_filter() {
        $this->action_toggle('plugin.rubik_filter_settings');
    }

    function action_remove_filter() {
        $this->action_remove('plugin.rubik_filter_settings');
    }

    function action_toggle($redirectTo) {
        $rc = rcmail::get_instance();
        $output = $rc->output;

        $filterId = rcube_utils::get_input_value('filter_id', rcube_utils::INPUT_POST);

        if ($filterId === null || !is_numeric($filterId)) {
            $output->show_message('rubik_filter.msg_error_disable_filter', 'error');
            return;
        }

        $filterId = intval($filterId);

        if ($this->toggleFilterEnabled($rc, $filterId) !== true) {
            $output->show_message('rubik_filter.msg_error_disable_filter', 'error');
        } else {
            $output->show_message('rubik_filter.msg_success_disable_filter', 'confirmation');

            $output->redirect($redirectTo);
        }
    }

    function action_remove($redirectTo) {
        $rc = rcmail::get_instance();

        $filterId = rcube_utils::get_input_value('filterid', rcube_utils::INPUT_POST);

        if ($filterId !== null) {
            $result = $this->updateFilter($rc, $filterId, null);

            if ($result !== true) {
                $this->showMessage($rc, 'rubik_filter.msg_error_remove_filter', 'error');
            } else {
                $this->showMessage($rc, 'rubik_filter.msg_success_remove_filter', 'confirmation');
            }

            $rc->output->redirect($redirectTo);
        }
    }

    function action_swap_filters() {
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

    function action_save_vacation() {
        $rc = rcmail::get_instance();

        $clientDateStart = rcube_utils::get_input_value("vacation_start", rcube_utils::INPUT_POST);
        $clientDateEnd = rcube_utils::get_input_value("vacation_end", rcube_utils::INPUT_POST);
        $clientSelectedMessage = rcube_utils::get_input_value("vacation_selected_message", rcube_utils::INPUT_POST);
        $clientVacationId = rcube_utils::get_input_value("vacation_id", rcube_utils::INPUT_POST);
        $clientVacationName = rcube_utils::get_input_value("vacation_name", rcube_utils::INPUT_POST);

        try {
            $dateStart = new DateTime($clientDateStart);
            $dateEnd = new DateTime($clientDateEnd);
        } catch (Exception $e) {
            $this->showMessage($rc, 'rubik_filter.msg_err_invalid_date', 'error');
            return;
        }

        $clientSelectedMessage = $this->sanitizeFilename($clientSelectedMessage);

        $message = $this->getMessage($rc, $clientSelectedMessage);

        if ($message === null) { // check if message exists
            $this->showMessage($rc, 'rubik_filter.msg_err_load_reply', 'error');
            return;
        }

        $vacation = new Vacation();
        $vacation->setName($clientVacationName);
        $vacation->setRange($dateStart, $dateEnd);
        $vacation->setMessagePath(ProcmailStorage::VACATION_MESSAGES_LOCATION . "/$clientSelectedMessage");

        $res = $this->updateFilter($rc, $clientVacationId, $vacation->createFilter(), false);

        if ($res !== true) {
            $this->showMessage($rc, 'rubik_filter.msg_err_save_vacation', 'error');
        } else {
            $this->showMessage($rc, 'rubik_filter.msg_success_save_vacation', 'confirmation');
            $rc->output->redirect('plugin.rubik_filter_vacation');
        }
    }

    function action_remove_message() {
        $rc = rcmail::get_instance();

        $messageId = rcube_utils::get_input_value('message_id', rcube_utils::INPUT_POST);

        if ($messageId === null) {
            $this->showMessage($rc, 'rubik_filter.msg_err_missing_message_id', 'error');
        } else {
            $res = $this->getStorageClient($rc)->delVacationMessage($messageId);

            if ($res === true) {
                $this->showMessage($rc, 'rubik_filter.msg_success_remove_message', 'confirmation');
            } else {
                $this->showMessage($rc, 'rubik_filter.msg_err_remove_message', 'error');
            }

            $rc->output->redirect('plugin.rubik_filter_vacation_messages');
        }
    }

    function action_save_message() {
        $rc = rcmail::get_instance();

        $clientMessageFilename = trim(
            rcube_utils::get_input_value('message_selecct', rcube_utils::INPUT_POST)
        );

        $clientMessageText = rcube_utils::get_input_value('message_text', rcube_utils::INPUT_POST);
        $clientMessageFilenameOriginal = rcube_utils::get_input_value('message_filename_original', rcube_utils::INPUT_POST);

        if (empty($clientMessageFilename) || empty($clientMessageText)) {
            $this->showMessage($rc, 'rubik_filter.msg_err_missing_message_form_data', 'error');
            return;
        }

        $res = $this->saveMessage($clientMessageFilename, $clientMessageText);

        if ($res !== true) {
            $this->showMessage($rc, 'rubik_filter.msg_err_cannot_write', 'error');
            return;
        }

        // delete last in case write fails first
        if (!empty($clientMessageFilenameOriginal)) {
            $this->deleteMessage($clientMessageFilenameOriginal);
        }

        $this->showMessage($rc, 'rubik_filter.msg_success_save_message', 'confirmation');
        $rc->output->redirect('plugin.rubik_filter_vacation_messages');
    }

    function action_get_message() {
        $rc = rcmail::get_instance();

        $messageFilename = rcube_utils::get_input_value('message_filename', rcube_utils::INPUT_POST);

        $message = $this->getMessage($rc, $messageFilename);

        if ($message === null) {
            $this->showMessage($rc, 'rubik_filter.msg_err_load_reply', 'error');
        } else {
            $rc->output->command('plugin.rubik_filter_set_message', array(
                'message_filename' => $messageFilename,
                'message_text' => $message
            ));
        }
    }

    private function outputResult($rc, $cmd, $success, $message) {
        $this->showMessage($rc, "rubik_filter.$message", $success ? "confirmation" : "error");
    }

    private function getFilters($rc, $client = null) {
        if ($client == null) {
            $client = $this->getStorageClient($rc);
        }

        $cache = new FilterCache($client);

        $res = $cache->getFilters();

        if ($res === ProcmailStorage::ERR_NO_SECTION
            || $res === ProcmailStorage::ERR_NO_FILE
            || $res === ProcmailStorage::ERR_EMPTY_RULES) {
            return array();
        } else if ($res === ProcmailStorage::ERR_WRONG_HASH) {
            $this->showMessage($rc, 'rubik_filter.msg_err_wrong_hash', 'warning');
        }

        return $res;
    }

    /**
     * Combination of filter update operations.
     * If $id is null, $newFilter is appended at the end of filter list => new
     * If $newFilter is null, filter with $id is removed from filter list => remove
     * If both $id and $ newFilter are non-null, filter is updated => edit
     *
     * @param $rc rcmail
     * @param $id int filter ID
     * @param $newFilter string filter text
     * @return bool|int
     */
    private function updateFilter($rc, $id, $newFilter, $appendEnd = true) {
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

            /** @var Filter $oldFilter */
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
                if ($oldProcmail === ProcmailStorage::ERR_NO_FILE
                    || $oldProcmail === ProcmailStorage::ERR_NO_SECTION
                    || $oldProcmail === ProcmailStorage::ERR_EMPTY_RULES) {
                    $procmail = '';
                } else {
                    return false;
                }
            } else {
                $procmail .= $oldProcmail;
            }

            if ($appendEnd) {
                $procmail = $procmail . $newFilter;
            } else {
                $procmail = $newFilter . $procmail;
            }
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

    private function toggleFilterEnabled($rc, $id) {
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

        /** @var Filter $filter */
        foreach ($filters as $filter) {
            $procmail .= $filter->createFilter();
        }

        return $storage->putProcmailRules($procmail);
    }

    private function saveMessage($filename, $content) {
        $rc = rcmail::get_instance();
        $client = $this->getStorageClient($rc);

        $filename = $this->sanitizeFilename($filename);

        $messageList = $client->listVacationMessages();
        if ($messageList === null) {
            return false;
        }

        if (in_array($filename, $messageList)) {
            $this->showMessage($rc, 'rubik_filter.msg_err_filename_exists', 'error');
            return false;
        }

        $res = $client->putVacationMessage($filename, $content);

        return $res;
    }

    private function deleteMessage($filename) {
        $rc = rcmail::get_instance();
        $client = $this->getStorageClient($rc);

        $filename = $this->sanitizeFilename($filename);

        $client->delVacationMessage($filename);
    }

    /**
     * Read vacation reply message from storage.
     *
     * @param $rc rcmail
     * @param $filename string
     * @param null $client ProcmailStorage
     * @return null | string
     */
    private function getMessage($rc, $filename, $client = null) {
        if ($client === null) {
            $client = $this->getStorageClient($rc);
        }

        $filename = $this->sanitizeFilename($filename);
        $message = $client->getVacationMessage($filename);

        if ($message === null) {
            $this->showMessage($rc, 'rubik_filter.msg_err_load_message', 'error');
            return null;
        }

        return $message;
    }

    /**
     * @param $rc rcmail
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

    /**
     * @param $rc rcmail
     * @param $msg string
     * @param $type string
     */
    private function showMessage($rc, $msg, $type) {
        $rc->output->show_message($msg, $type, null, false, 5);
    }

    /**
     * @param $filename string
     * @return string
     */
    private function sanitizeFilename($filename) {
        return preg_replace(array("/\s/", "/\//"), "_", trim($filename));
    }
}