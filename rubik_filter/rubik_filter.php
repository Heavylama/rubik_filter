<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock as ConditionBlock;
use Rubik\Procmail\FilterActionBlock;
use Rubik\Procmail\Filter;
use Rubik\Procmail\FilterParser;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;
use Rubik\Procmail\Vacation;
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
    private const A_FILTER_SETTINGS = "plugin.rubik_settings_filter";
    private const A_VACATION_SETTINGS = "plugin.rubik_settings_vacation";
    private const A_REPLY_SETTINGS = "plugin.rubik_settings_replies";

    private const A_REMOVE_ENTITY = "plugin.rubik_remove_entity";
    private const A_SAVE_ENTITY = "plugin.rubik_save_entity";
    private const A_TOGGLE_ENTITY_ENABLED = "plugin.rubik_toggle_entity_enabled";
    private const A_SHOW_ENTITY_DETAIL = "plugin.rubik_show_entity_detail";
    private const A_SWAP_FILTERS = "plugin.rubik_swap_filters";
    
    private const CC_SET_REPLY = "plugin.rubik_filter_set_message";

    private const INPUT_ENTITY_TYPE = "_rubik_entity_type";
    private const INPUT_ENTITY_ID = "_rubik_entity_id";

    private const ENTITY_FILTER = "rubik_filter";
    private const ENTITY_VACATION = "rubik_vacation";
    private const ENTITY_REPLY = "rubik_reply";

    private const ID_ENTITY_LIST = "rubik-entity-list";

    /** @var string tells roundcube to run plugin only in specific task */
    public $task = "settings";

    function init() {
        // localization
        $this->add_texts('localization/', true);

        // config
        $this->load_config();

        $this->include_script('scripts/Sortable.js');
        $this->include_script('scripts/rubik_filter.js');
        $this->include_stylesheet('styles/rubik_filter.css');

        // hook to add a new item in settings list
        $this->add_hook('settings_actions', array($this, 'hook_settings'));

        // Filter settings actions
        $this->register_action(self::A_FILTER_SETTINGS, array($this, 'show_rubik_settings'));
        $this->register_action(self::A_VACATION_SETTINGS, array($this, 'show_rubik_settings'));
        $this->register_action(self::A_REPLY_SETTINGS, array($this, 'show_rubik_settings'));

        $this->register_action(self::A_REMOVE_ENTITY, array($this, 'action_remove_entity'));
        $this->register_action(self::A_SAVE_ENTITY, array($this, 'action_save_entity'));
        $this->register_action(self::A_TOGGLE_ENTITY_ENABLED, array($this, 'action_toggle_entity'));
        $this->register_action(self::A_SHOW_ENTITY_DETAIL, array($this, 'action_show_entity_detail'));

        $this->register_action(self::A_SWAP_FILTERS, array($this, 'action_swap_filters'));
        $this->register_action("plugin.rubik_filter_get_message", array($this, 'action_get_reply'));

        // ui handlers
        $this->register_handler("plugin.rubik_filter_form", array($this, 'ui_filter_form'));
        $this->register_handler("plugin.rubik_entity_list", array($this, 'ui_entity_list'));
    }

    /**
     * Hook to append plugin settings items to settings page.
     *
     * @param $args array
     * @return array
     */
    function hook_settings($args) {
        $args['actions'][] = array(
            'command' => self::A_FILTER_SETTINGS,
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'title_settings_filters',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = array(
            'command' => self::A_REPLY_SETTINGS,
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'title_settings_replies',
            'class' => 'rubikfilter'
        );

        $args['actions'][] = array(
            'command' => self::A_VACATION_SETTINGS,
            'type' => 'link',
            'domain' => 'rubik_filter',
            'label' => 'title_settings_vacations',
            'class' => 'rubikfilter'
        );

        return $args;
    }

    /**
     * Show a plugin settings page.
     *
     * @see rubik_filter::ui_entity_list()
     */
    function show_rubik_settings() {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        switch ($rc->action) {
            case self::A_FILTER_SETTINGS:
                $title = $this->gettext('title_settings_filters');
                $entityType = self::ENTITY_FILTER;
                break;
            case self::A_VACATION_SETTINGS:
                $title = $this->gettext('title_settings_vacations');
                $entityType = self::ENTITY_VACATION;
                break;
            case self::A_REPLY_SETTINGS:
                $title = $this->gettext('title_settings_replies');
                $entityType = self::ENTITY_REPLY;
                break;
            default:
                $title = '';
                $entityType = null;
                break;
        }

        $output->set_env(self::INPUT_ENTITY_TYPE, $entityType);

        $output->set_pagetitle($title);
        $output->send("rubik_filter.rubik_settings");
    }

    function action_show_entity_detail()
    {
        $rc = rcmail::get_instance();

        $type = $this->getInput(self::INPUT_ENTITY_TYPE, rcube_utils::INPUT_GET);
        $id = $this->getInput(self::INPUT_ENTITY_ID, rcube_utils::INPUT_GET);

        $rc->output->set_env(self::INPUT_ENTITY_ID, $id);
        $rc->output->set_env(self::INPUT_ENTITY_TYPE, $type);

        switch ($type) {
            case self::ENTITY_FILTER:
                $this->show_filter_form($id);
                break;
            case self::ENTITY_VACATION:
                $this->show_vacation_form($id);
                break;
            case self::ENTITY_REPLY:
                $this->show_reply_form($rc, $id);
                break;
        }


        ;
    }

    function show_filter_form($filterId) {
        $rc = rcmail::get_instance();
        /** @var rcmail_output_html $output */
        $output = $rc->output;

        if ($filterId !== null) {

            $filters = $this->getFilters($rc, 'msg_err_load_filter_form', null);
            if (isset($filters[intval($filterId)])) {

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

    function show_vacation_form($vacationId) {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $client = $this->getStorageClient($rc);

        if ($vacationId !== null) {
            $filters = $this->getFilters($rc, $client);

            if (isset($filters[$vacationId])) {
                /** @var Vacation $vacation */
                $vacation = $filters[$vacationId];

                $messageFilename = end(explode("/", $vacation->getMessagePath()));

                $dateRange = $vacation->getRange();
                $dateFormat = "Y-m-d";

                $vacationOut = array(
                    'vacation_name' => $vacation->getName(),
                    'vacation_message' => $messageFilename,
                    'vacation_start' => $dateRange['start']->format($dateFormat),
                    'vacation_end' => $dateRange['end']->format($dateFormat)
                );

                $output->set_env('vacation', $vacationOut);
                $output->set_env('vacation_id', $vacationId);
            }
        }

        $messageList = $this->listReplies($rc, $client);

        if ($messageList === null) {
            $this->showMessage($rc, 'msg_err_missing_reply_id', 'error');
            $output->send('iframe');
            return;
        } else if (count($messageList) === 0) {
            $this->showMessage($rc, 'msg_warn_create_reply', 'warning');
            $output->send('iframe');
            return;
        }


        $output->set_env('vacation_select_options', $messageList);

        $output->send('rubik_filter.vacation_form');
    }

    /**
     * Show reply form page.
     *
     * @param $rc rcmail
     * @param $messageId string
     */
    function show_reply_form($rc, $messageId) {
        /** @var rcmail_output_html $output */
        $output = $rc->output;

        if ($messageId !== null) {
            $message = $this->getReply($rc, $messageId);
            if ($message === null) {
                $output->send('iframe');
                return;
            }

            $output->set_env('rubik_reply_text', $message);
            $output->set_env('rubik_reply_filename', $messageId);
        }


        $output->send('rubik_filter.message_form');
    }

    function action_toggle_entity() {
        $type = $this->getInput(self::INPUT_ENTITY_TYPE);
        $id = $this->getInput(self::INPUT_ENTITY_ID);

        switch ($type) {
            case self::ENTITY_FILTER:
                $this->action_toggle_filter($id, self::A_FILTER_SETTINGS);
                break;
            case self::ENTITY_VACATION:
                $this->action_toggle_filter($id,self::A_VACATION_SETTINGS);
                break;
        }
    }

    function action_save_entity() {
        $type = $this->getInput(self::INPUT_ENTITY_TYPE);
        $id = $this->getInput(self::INPUT_ENTITY_ID);

        switch ($type) {
            case self::ENTITY_FILTER:
                $this->action_save_filter($id);
                break;
            case self::ENTITY_VACATION:
                $this->action_save_vacation($id);
                break;
            case self::ENTITY_REPLY:
                $this->action_save_reply($id);
                break;
        }
    }

    /**
     * Handler for creating a list of entries for one of plugin's settings pages.
     * Check skin template file rubik_settings.html
     *
     * @param $attrib array attributes from template file
     * @return string|null
     *
     * @see rubik_filter::ui_filter_list()
     * @see rubik_filter::ui_reply_list()
     */
    function ui_entity_list($attrib) {
        $rc = rcmail::get_instance();

        $attrib['id'] = self::ID_ENTITY_LIST;

        switch ($rc->action) {
            case self::A_FILTER_SETTINGS:
                $list = $this->ui_filter_list($attrib, $rc, false);
                break;
            case self::A_VACATION_SETTINGS:
                $list = $this->ui_filter_list($attrib, $rc, true);
                break;
            case self::A_REPLY_SETTINGS:
                $list = $this->ui_reply_list($attrib, $rc);
                break;
            default:
                $list = null;
                break;
        }

        if ($list != null) {
            $rc->output->add_gui_object('rubik_entity_list', $attrib['id']);
            $rc->output->include_script('list.js');
        }

        return $list;
    }

    /**
     * Render filter/vacation list for settings page.
     *
     * @param $attrib array attributes from template file
     * @param $rc rcmail
     * @param $showVacations bool true to show only vacation filters, false to show only non-vacation filters
     * @return string|null
     */
    function ui_filter_list($attrib, $rc, $showVacations) {
        $filters = $this->getFilters($rc, 'msg_err_load_filter_list', null);
        if ($filters === null) {
            return null;
        }

        $names = array();
        $enabledReplace = array();

        // filter out vacations and create list entries
        foreach ($filters as $key => $filter) {
            // filter out either filters or vacations
            if ($showVacations !== $filter instanceof Vacation) continue;

            $name = $filter->getName();

            if (empty($name)) {
                $name = "Filter $key";
            }

            $names[] = array(
                'id' => $key,
                'name' => $name,
            );


            $isEnabled = $filter->getFilterEnabled();
            $command = "rcmail.command('toggle_enabled', this)";

            $enabledCheckbox =
                "</td><td class='checkbox-cell' style='text-align: right;'>".
                "<div class='custom-control custom-switch'><input class='form-check-input custom-control-input' type='checkbox'";
            $enabledCheckbox .= ($isEnabled ? 'checked' : '');
            $enabledCheckbox .= " /><label onclick=\"$command\" class='custom-control-label'/></div>";
            $enabledCheckbox .= "</td>";

            $enabledReplace[] = $enabledCheckbox;
        }

        // list output
        $out = $rc->table_output($attrib, $names, array('name'),'id');

        // inject checkboxes to each row
        preg_match_all("/<\/td>/", $out, $matches, PREG_OFFSET_CAPTURE);
        $injectedOut = $out;
        $offset = 0;

        foreach ($matches[0] as $key => $match) {
            $replacement = $enabledReplace[$key];

            $injectedOut = substr_replace($injectedOut, $replacement, $match[1] + $offset, 5);

            $offset += strlen($replacement) - 5;
        }

        $out = $injectedOut;

        return $out;
    }

    /**
     * Render replies list for settings page.
     *
     * @param $attrib array template attributes
     * @param $rc rcmail
     * @return string|null
     */
    function ui_reply_list($attrib, $rc) {
        $messageList = $this->listReplies($rc);
        if ($messageList === null) {
            return null;
        }

        $names = array();

        foreach ($messageList as $messageFile) {
            $names[] = array(
                'name' => $messageFile
            );
        }

        $output = $rc->table_output($attrib, $names, array('name'), 'name');

        return $output;
    }

    function action_remove_entity() {
        $type = $this->getInput(self::INPUT_ENTITY_TYPE);
        $id = $this->getInput(self::INPUT_ENTITY_ID);

        switch ($type) {
            case self::ENTITY_FILTER:
                $this->action_remove_filter($id,self::A_FILTER_SETTINGS);
                break;
            case self::ENTITY_VACATION:
                $this->action_remove_filter($id,self::A_VACATION_SETTINGS);
                break;
            case self::ENTITY_REPLY:
                $this->action_remove_reply($id);
                break;
        }
    }

    function action_save_filter($id) {
        $rc = rcube::get_instance();
        $rcmd = 'plugin.rubik_filter_save_result';

        $clientActions = rcube_utils::get_input_value('filter_actions', rcube_utils::INPUT_POST);
        $clientConditions = rcube_utils::get_input_value('filter_conditions', rcube_utils::INPUT_POST);
        $clientConditionsType = rcube_utils::get_input_value('filter_conditions_type', rcube_utils::INPUT_POST);
        $clientFilterId = $id;
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

    function action_toggle_filter($filterId, $redirectTo) {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        if ($filterId === null || !is_numeric($filterId)) {
            $output->show_message('msg_error_disable_filter', 'error');
            return;
        }

        $filterId = intval($filterId);

        if ($this->toggleFilterEnabled($rc, $filterId) !== true) {
            $output->show_message('msg_error_disable_filter', 'error');
        } else {
            $output->show_message('msg_success_disable_filter', 'confirmation');

            $output->redirect($redirectTo);
        }
    }

    function action_remove_filter($filterId, $redirectTo) {
        $rc = rcmail::get_instance();

        if ($filterId !== null) {
            $result = $this->updateFilter($rc, $filterId, null);

            if ($result !== true) {
                $this->showMessage($rc, 'msg_error_remove_filter', 'error');
            } else {
                $this->showMessage($rc, 'msg_success_remove_filter', 'confirmation');
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
            $output->show_message('msg_error_swap_filter', 'error');
            return;
        }

        $id1 = intval($id1);
        $id2 = intval($id2);

        if ($this->swapFilters($rc, $id1, $id2) !== true) {
            $output->show_message('msg_error_swap_filter', 'error');
        } else {
            $output->show_message('msg_success_swap_filter', 'confirmation');
        }

        $output->redirect('plugin.rubik_filter_settings');
    }

    function action_save_vacation($clientVacationId) {
        $rc = rcmail::get_instance();

        $clientDateStart = rcube_utils::get_input_value("vacation_start", rcube_utils::INPUT_POST);
        $clientDateEnd = rcube_utils::get_input_value("vacation_end", rcube_utils::INPUT_POST);
        $clientSelectedMessage = rcube_utils::get_input_value("vacation_selected_message", rcube_utils::INPUT_POST);
        $clientVacationName = rcube_utils::get_input_value("vacation_name", rcube_utils::INPUT_POST);

        try {
            $dateStart = new DateTime($clientDateStart);
            $dateEnd = new DateTime($clientDateEnd);
        } catch (Exception $e) {
            $this->showMessage($rc, 'msg_err_invalid_date', 'error');
            return;
        }

        $clientSelectedMessage = $this->sanitizeReplyFilename($clientSelectedMessage);

        $message = $this->getReply($rc, $clientSelectedMessage);

        if ($message === null) { // check if message exists
            $this->showMessage($rc, 'msg_err_load_reply', 'error');
            return;
        }

        $vacation = new Vacation();
        $vacation->setName($clientVacationName);
        $vacation->setRange($dateStart, $dateEnd);
        $vacation->setMessagePath(ProcmailStorage::VACATION_REPLIES_LOCATION . "/$clientSelectedMessage");

        $res = $this->updateFilter($rc, $clientVacationId, $vacation->createFilter(), false);

        if ($res !== true) {
            $this->showMessage($rc, 'msg_err_save_vacation', 'error');
        } else {
            $this->showMessage($rc, 'msg_success_save_vacation', 'confirmation');
            $rc->output->redirect('plugin.rubik_filter_vacation');
        }
    }

    //region Actions - Replies
    /**
     * Remove reply file from storage.
     * @param $replyId string filename
     */
    function action_remove_reply($replyId) {
        $rc = rcmail::get_instance();

        if ($replyId === null) {
            $this->showMessage($rc, 'msg_err_missing_reply_id', 'error', null);
            return;
        }

        if ($this->deleteReply($rc, $replyId)) {
            $this->showMessage($rc, 'msg_success_remove_reply', 'confirmation', null);
            $rc->output->redirect(self::A_REPLY_SETTINGS);
        }
    }

    /**
     * Save reply message to storage.
     * @param $replyId string|null original id
     */
    function action_save_reply($replyId) {
        $rc = rcmail::get_instance();

        $clientMessageFilename = trim($this->getInput('rubik_reply_filename'));
        $clientMessageText = $this->getInput('rubik_reply_text');
        $clientMessageFilenameOriginal = $replyId;

        if (empty($clientMessageFilename) || empty($clientMessageText)) {
            $this->showMessage($rc, 'msg_err_missing_reply_form_data', 'error', null);
            return;
        }

        $clientMessageFilename = $this->sanitizeReplyFilename($clientMessageFilename);

        // either original is null => creating a new message or we are updating and we write under a new filename
        $filenameChanged = $clientMessageFilename !== $clientMessageFilenameOriginal;

        if (!$this->saveReply($rc, $clientMessageFilename, $clientMessageText, $filenameChanged)) {
            return;
        }

        if ($filenameChanged && !empty($clientMessageFilenameOriginal)) {
            $this->deleteReply($rc, $clientMessageFilenameOriginal);
        }

        $this->showMessage($rc, 'msg_success_save_reply', 'confirmation', null);

        $rc->output->redirect(self::A_REPLY_SETTINGS);
    }

    /**
     * Called through ajax, load one reply message from storage for display.
     */
    function action_get_reply() {
        $rc = rcmail::get_instance();

        $messageFilename = rcube_utils::get_input_value('message_filename', rcube_utils::INPUT_POST);

        $message = $this->getReply($rc, $messageFilename);

        if ($message !== null) {
            $rc->output->command(self::CC_SET_REPLY, array(
                'message_filename' => $messageFilename,
                'message_text' => $message
            ));
        }
    }
    //endregion

    //region Filter/Vacation ops

    /**
     * Read and parse procmail filters.
     *
     * If storage reports {@link ProcmailStorage::ERR_NO_SECTION} or {@link ProcmailStorage::ERR_CANNOT_READ}
     * an empty array is returned => plugin's filter section haven't been initialized yet or the file doesn't exist.
     *
     * @param $rc rcmail
     * @param $errorMsgPrefix string error message prefix
     * @param $client ProcmailStorage
     * @return Filter[]|null array of filters, null on parsing or storage error
     * @see ProcmailStorage::getProcmailRules() for error codes
     */
    private function getFilters($rc, $errorMsgPrefix, $client) {
        if ($client === null) {
            $client = $this->getStorageClient($rc);
        }

        $procmail = $client->getProcmailRules();

        if ($procmail & (ProcmailStorage::ERR_NO_SECTION | ProcmailStorage::ERR_CANNOT_READ)) {
            return array();
        }

        if (!$this->checkStorageErrorCode($rc, $procmail, $errorMsgPrefix)) {
            return null;
        }

        $filters = FilterParser::parseFilters($procmail);

        if ($filters === null) {
            $this->showMessage($rc, 'msg_err_parse_filter', 'error', $errorMsgPrefix);
        }

        return $filters;
    }

    /**
     * Combination of filter update operations.
     *
     * If $id is null, $newFilter is appended at the end/start of filter list => new
     *
     * If $newFilter is null, filter with $id is removed from filter list => remove
     *
     * If both $id and $ newFilter are non-null, filter is updated => edit
     *
     * @param $rc rcmail
     * @param $id int filter ID
     * @param $newFilter string filter text
     * @param $errorMsgPrefix string error message prefix
     * @param $appendEnd bool if creating a new filter append it at end, default true. Has no effect on edit/remove operations.
     * @return bool indicating success
     */
    private function updateFilter($rc, $id, $newFilter, $errorMsgPrefix, $appendEnd = true) {
        if ($id === null && $newFilter === null) {
            return false;
        }

        $client = $this->getStorageClient($rc);

        // this will be saved to file
        $procmail = '';

        if ($id !== null) { // update/remove

            // we need to parse filters in this case
            $filters = $this->getFilters($rc, $errorMsgPrefix, $client);
            if ($filters === null) { // error when getting filters occurred
                return false;
            }

            $id = intval($id);

            if(!isset($filters[$id])) {
                // id to update or remove doesn't exist
                $this->showMessage($rc, 'msg_err_invalid_filter_id', 'error', $errorMsgPrefix);
                return false;
            }

            // concat filters
            foreach ($filters as $key => $oldFilter) {
                if ($key === $id) { // replace filter
                    $procmail .= ($newFilter === null) ? '' : $newFilter;
                } else { // append other filters unchanged
                    $procmail .= $oldFilter->createFilter();
                }
            }
        } else { // create new

            // we don't need to parse all filters since we only need to append new filter text at start or end
            $oldProcmail = $client->getProcmailRules();

            // cannot read and no section errors are ok, since the finally hasn't been probably initialized yet
            // and we are creating a new filter anyway
            // on other errors show error message
            if (is_numeric($oldProcmail)
                && !($oldProcmail & (ProcmailStorage::ERR_CANNOT_READ | ProcmailStorage::ERR_NO_SECTION))) {
                $this->checkStorageErrorCode($rc, $oldProcmail, $errorMsgPrefix);
                return false;
            }

            $procmail .= $oldProcmail;

            if ($appendEnd) {
                $procmail = $procmail . $newFilter;
            } else {
                $procmail = $newFilter . $procmail;
            }
        }

        $res = $client->putProcmailRules($procmail);

        return $this->checkStorageErrorCode($rc, $res, $errorMsgPrefix);
    }

    /**
     * Swap positions of two filters.
     *
     * @param $rc rcmail
     * @param $id1 int first filter id
     * @param $id2 int second filter id
     * @param $errorMsgPrefix string
     * @return bool success
     */
    private function swapFilters($rc, $id1, $id2, $errorMsgPrefix) {
        $client = $this->getStorageClient($rc);

        $filters = $this->getFilters($rc, $errorMsgPrefix, $client);
        if ($filters === null) {
            return false;
        }

        // check if given IDs exist
        if (!array_key_exists($id1, $filters) || !array_key_exists($id2, $filters)) {
            $this->showMessage($rc, 'msg_err_invalid_filter_id', 'error', $errorMsgPrefix);
            return false;
        }

        // swap
        $filter1 = $filters[$id1];
        $filters[$id1] = $filters[$id2];
        $filters[$id2] = $filter1;

        // write back
        return $this->storeFilters($rc, $filters, $client, $errorMsgPrefix);
    }

    /**
     * Toggle filter enabled state.
     *
     * @param $rc rcmail
     * @param $id int filter ID
     * @param $errorMsgPrefix
     * @return bool success
     */
    private function toggleFilterEnabled($rc, $id, $errorMsgPrefix) {
        $client = $this->getStorageClient($rc);

        $filters = $this->getFilters($rc, $errorMsgPrefix, $client);
        if ($filters === null) {
            return false;
        }

        if (!isset($filters[$id])) {
            $this->showMessage($rc, 'msg_err_invalid_filter_id', $errorMsgPrefix, null);
            return false;
        }

        $filters[$id]->setFilterEnabled(!$filters[$id]->getFilterEnabled());

        return $this->storeFilters($rc, $filters, $client, $errorMsgPrefix);
    }

    /**
     * Store filter array to procmail file.
     *
     * @param $rc rcmail
     * @param $filters Filter[]
     * @param $client ProcmailStorage
     * @param $errorMsgPrefix string
     * @return bool success
     */
    private function storeFilters($rc, $filters, $client, $errorMsgPrefix) {
        $procmail = '';

        foreach ($filters as $filter) {
            $filterText = $filter->createFilter();

            if ($filterText === null) {
                $this->showMessage($rc, 'msg_err_create_filter', 'error', $errorMsgPrefix);
                return false;
            }

            $procmail .= $filterText;
        }

        $res = $client->putProcmailRules($procmail);

        return $this->checkStorageErrorCode($rc, $res, $errorMsgPrefix);
    }
    //endregion

    //region Reply ops
    /**
     * Save reply message to storage.
     *
     * @param $rc rcmail
     * @param $filename string
     * @param $content string
     * @param $checkDuplicate bool true to check for duplicate filenames
     * @return bool success
     */
    private function saveReply($rc, $filename, $content, $checkDuplicate) {
        $client = $this->getStorageClient($rc);

        $filename = $this->sanitizeReplyFilename($filename);

        $messageList = $this->listReplies($rc, $client);
        if ($messageList === null) {
            return false;
        }

        if ($checkDuplicate && in_array($filename, $messageList)) {
            $this->showMessage($rc, 'msg_err_filename_exists', 'error', null);
            return false;
        }

        $res = $client->putReply($filename, $content);
        if (!$this->checkStorageErrorCode($rc, $res, 'msg_err_save_reply')) {
            return false;
        }

        return true;
    }

    /**
     * Delete reply message file in storage.
     *
     * @param $rc rcmail
     * @param $filename string
     * @return bool success
     */
    private function deleteReply($rc, $filename) {
        $client = $this->getStorageClient($rc);

        $filename = $this->sanitizeReplyFilename($filename);

        return $this->checkStorageErrorCode($rc, $client->deleteReply($filename), 'msg_err_remove_reply');
    }

    /**
     * Read vacation reply message from storage.
     *
     * @param $rc rcmail
     * @param $filename string
     * @param $client ProcmailStorage|null if null a new storage client is created
     * @return string|null reply message or null on error
     */
    private function getReply($rc, $filename, $client = null) {
        if ($client === null) {
            $client = $this->getStorageClient($rc);
        }

        $filename = $this->sanitizeReplyFilename($filename);

        $message = $client->getReply($filename);

        if (!$this->checkStorageErrorCode($rc, $message, 'msg_err_load_reply')) {
            return null;
        } else {
            return $message;
        }
    }

    private function listReplies($rc, $client = null) {
        if ($client === null) $client = $this->getStorageClient($rc);

        $replies = $client->listVacationMessages();

        if (!$this->checkStorageErrorCode($rc, $replies, 'msg_err_list_replies')) {
            $replies = null;
        }

        return $replies;
    }

    //endregion

    //region Utility
    /**
     * Check if result is one of ProcmailStorage error codes.
     *
     * If result is one of error code and $errMsgPrefixId is non-null then print error message with given prefix.
     *
     * @param $rc rcmail
     * @param $result mixed
     * @param $errMsgPrefix string|null error prefix message or null to not show any message in browser
     * @return bool false when $result is one of storage error constants
     * @see ProcmailStorage::ERR_ constants
     */
    private function checkStorageErrorCode($rc, $result, $errMsgPrefix) {
        if (!is_numeric($result)) {
            return true;
        }

        switch ($result) {
            case ProcmailStorage::ERR_NO_SECTION:
                $msgId = "msg_err_no_section";
                break;
            case ProcmailStorage::ERR_INVALID_HASH:
                $msgId = "msg_err_invalid_hash";
                break;
            case ProcmailStorage::ERR_CANNOT_READ:
                $msgId = "msg_err_cannot_read";
                break;
            case ProcmailStorage::ERR_CANNOT_WRITE:
                $msgId = "msg_err_cannot_write";
                break;
            case ProcmailStorage::ERR_NO_CONNECTION:
                $msgId = "msg_err_no_connection";
                break;
            default:
                return true;
        }

        if ($errMsgPrefix !== null) {
            $this->showMessage($rc, $msgId, 'error', $errMsgPrefix);
        }

        return false;
    }

    /**
     * Get a new storage object, email client's credentials are used for login.
     *
     * @param $rc rcmail
     * @return ProcmailStorage
     */
    private function getStorageClient($rc) {
        $client = new RubikSftpClient($rc->config->get('rubik_ftp_host'));

        $pw = $rc->get_user_password();
        $userName = explode("@", $rc->get_user_name())[0];

        return new ProcmailStorage(
            $client ,
            $userName,
            $pw
        );
    }

    /**
     * Show a message in user's browser.
     *
     * @param $rc rcmail
     * @param $msg string message label
     * @param $msgPrefix string|null prefix label
     * @param $type string one of 'error', 'confirmation', 'warning', 'notice'
     */
    private function showMessage($rc, $msg, $type, $msgPrefix) {
        $msg = $this->gettext("rubik_filter.$msg");

        if ($msgPrefix !== null) {
            $msgPrefix = $this->gettext("rubik_filter.$msgPrefix");

            $msg = "$msgPrefix: $msg";
        }

        $rc->output->show_message($msg, $type, null, false, 5);
    }

    /**
     * Replace whitespace and path delimiter characters with '_', only flat message hierarchy is used.
     *
     * @param $filename string
     * @return string
     */
    private function sanitizeReplyFilename($filename) {
        return preg_replace(array("/\s/", "/\//"), "_", trim($filename));
    }

    /**
     * Get script input data from GET/POST requests.
     *
     * @param $what string input name
     * @param int $source oen of rcube_utils::INPUT_ constants
     * @return string|null
     */
    private function getInput($what, $source = rcube_utils::INPUT_POST) {
        return rcube_utils::get_input_value($what, $source);
    }
    //endregion
}