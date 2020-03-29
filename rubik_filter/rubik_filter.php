<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Filter;
use Rubik\Procmail\FilterParser;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;
use Rubik\Procmail\Vacation;
use Rubik\Storage\ProcmailStorage;
use Rubik\Storage\RubikSftpClient;

require_once __DIR__ . '/vendor/autoload.php';


/**
 * Rubik Filter plugin
 * 
 * Plugin provides email filtering and OOF automatic replies using procmail on a linux host.
 * Plugin connects to linux host using SFTP with email user's credentials and manages .procmailrc filters.
 * 
 * @author Tomáš Španěl (xspane04@stud.fit.vutbr.cz)
 * @version 1.0
 * @license MIT
 */
class rubik_filter extends rcube_plugin
{
    private const REDIRECT_TIMEOUT = 2;

    private const A_FILTER_SETTINGS = "plugin.rubik_settings_filter";
    private const A_VACATION_SETTINGS = "plugin.rubik_settings_vacation";
    private const A_REPLY_SETTINGS = "plugin.rubik_settings_replies";

    private const A_REMOVE_ENTITY = "plugin.rubik_remove_entity";
    private const A_SAVE_ENTITY = "plugin.rubik_save_entity";
    private const A_TOGGLE_ENTITY_ENABLED = "plugin.rubik_toggle_entity_enabled";
    private const A_SHOW_ENTITY_DETAIL = "plugin.rubik_show_entity_detail";
    private const A_SWAP_FILTERS = "plugin.rubik_swap_filters";
    private const A_GET_REPLY = "plugin.rubik_get_reply";
    
    private const CC_SET_REPLY = "plugin.rubik_set_reply";

    private const INPUT_ENTITY_TYPE = "_rubik_entity_type";
    private const INPUT_ENTITY_ID = "_rubik_entity_id";

    private const ENTITY_FILTER = "rubik_filter";
    private const ENTITY_VACATION = "rubik_vacation";
    private const ENTITY_REPLY = "rubik_reply";

    private const ID_ENTITY_LIST = "rubik-entity-list";

    private const UI_VALID_ACTIONS = array(Action::MAILBOX, Action::FWD, Action::DISCARD);
    private const UI_VALID_OPERATORS = Operator::values;
    private const UI_VALID_FIELDS = array(Field::SUBJECT, Field::FROM, Field::BODY, Field::TO, Field::LIST_ID, Field::CC);

    // TODO skryvani val u akce discard (zkontrolovat larry theme)
    // TODO message pri tvoreni ui - nezobrazi se?
    // TODO moznost urcit filter final
    // TODO u vacation dat cestu do uvozovek

    /**
     * 1. Nastavit konecny nebo ne vs akce
     * 2. UI u mensi obrazovky/telefonu zkontrolovat
     * 3. Dialogy u smazani polozky
     * 4. Prepinatka nejdou videt? -- divny
     * 5. Odpovidat jen jednou
     * 6. Copy to folder
     * 7. Roundcube folders API pro vyber mailboxu
     * 8. Vyber co se ma stat po posledni akci
     * 10. Cachovani - do prace
     */

    /** @var string tells roundcube to run plugin only in a specific task */
    public $task = "settings";

    /**
     * Plugin initialization code.
     */
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
        $this->register_action(self::A_GET_REPLY, array($this, 'action_get_reply'));

        // UI template handlers
        $this->register_handler("plugin.rubik_entity_list", array($this, 'ui_entity_list'));
        $this->register_handler("plugin.rubik_entity_details", array($this, 'ui_entity_details'));

        $this->register_handler("plugin.rubik_filter_field_select", array($this, 'ui_filter_field_select'));
        $this->register_handler("plugin.rubik_filter_operator_select", array($this, 'ui_filter_operator_select'));
        $this->register_handler("plugin.rubik_filter_action_select", array($this, 'ui_filter_action_select'));
        $this->register_handler("plugin.rubik_filter_condition_type_select", array($this, 'ui_filter_condition_type_select'));

        $this->register_action('plugin.show_procmail', array($this, 'show_procfile'));
        $this->register_handler('plugin.procfile', array($this, 'procfile'));
    }

//    function show_procfile() {
//        $rc = rcmail::get_instance();
//
//        $rc->output->send('rubik_filter.procfile');
//    }
//
//    function procfile() {
//        $rc = rcmail::get_instance();
//
//
//        /** @var rcmail_output_html $output */
//        $output = $rc->output;
//
//        $client = $this->getStorageClient($rc);
//
//        $procmail = $client->test_getfile();
//
//        if ($procmail === false) {
//            $procmail = '';
//        }
//
//        return $procmail;
//
//    }

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

    //region Actions/UI - Common
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
                $deleteMsg = $this->gettext('dialog_remove_filter');
                $entityType = self::ENTITY_FILTER;
                break;
            case self::A_VACATION_SETTINGS:
                $title = $this->gettext('title_settings_vacations');
                $deleteMsg = $this->gettext('dialog_remove_vacation');
                $entityType = self::ENTITY_VACATION;
                break;
            case self::A_REPLY_SETTINGS:
                $title = $this->gettext('title_settings_replies');
                $deleteMsg = $this->gettext('dialog_remove_reply');
                $entityType = self::ENTITY_REPLY;
                break;
            default:
                $title = '';
                $deleteMsg = '';
                $entityType = null;
                break;
        }

        $output->set_env(self::INPUT_ENTITY_TYPE, $entityType);
        $output->set_env('rubik_section_title', $title);
        $output->set_env('rubik_remove_message', $deleteMsg);

        $output->set_pagetitle($title);
        $output->send("rubik_filter.rubik_settings");
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
            //TODO initialize error message later?
            $rc->output->add_gui_object('rubik_entity_list', $attrib['id']);
        }

        $rc->output->include_script('list.js');

        return $list;
    }

    /**
     * Show entity details form page.
     *
     * @see rubik_filter::ui_filter_form()
     * @see rubik_filter::ui_reply_form()
     * @see rubik_filter::ui_vacation_form()
     */
    function action_show_entity_detail() {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $type = $this->getInput(self::INPUT_ENTITY_TYPE, rcube_utils::INPUT_GET);

        switch ($type) {
            case self::ENTITY_FILTER:
                $title = $this->gettext('rubik_filter.title_filter_form');
                break;
            case self::ENTITY_VACATION:
                $title = $this->gettext('rubik_filter.title_vacation_form');
                break;
            case self::ENTITY_REPLY:
                $title = $this->gettext('rubik_filter.title_reply_form');
                break;
            default:
                $title = '';
                break;
        }

        $output->set_env('rubik_details_title', $title);

        $output->send('rubik_filter.rubik_entity_details');
    }

    function ui_entity_details() {
        $rc = rcmail::get_instance();

        $type = $this->getInput(self::INPUT_ENTITY_TYPE, rcube_utils::INPUT_GET);
        $id = $this->getInput(self::INPUT_ENTITY_ID, rcube_utils::INPUT_GET);

        $rc->output->set_env(self::INPUT_ENTITY_ID, $id);
        $rc->output->set_env(self::INPUT_ENTITY_TYPE, $type);

        if ($id === '') { $id = null; }

        switch ($type) {
            case self::ENTITY_FILTER:
                $out = $this->ui_filter_form($id);
                break;
            case self::ENTITY_VACATION:
                $out = $this->ui_vacation_form($id);
                break;
            case self::ENTITY_REPLY:
                $out = $this->ui_reply_form($rc, $id);
                break;
        }

        return $out;
    }

    /**
     * Toggle entity enabled state.
     */
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

    /**
     * Save entity to storage.
     *
     * @see rubik_filter::action_save_filter()
     * @see rubik_filter::action_save_reply()
     * @see rubik_filter::action_save_vacation()
     */
    function action_save_entity() {
        $type = $this->getInput(self::INPUT_ENTITY_TYPE);
        $id = $this->getInput(self::INPUT_ENTITY_ID);

        if ($id === '') {
            $id = null;
        }

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
     * Remove entity from storage.
     */
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
    //endregion

    //region Actions/UI - Filters/Vacations
    /**
     * Show filter form page.
     *
     * @param $filterId string|null
     * @return string|null
     */
    function ui_filter_form($filterId) {
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

                        if ($condition->op !== Operator::PLAIN_REGEX) {
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


        $output->add_gui_object('rubik_condition_template', 'rubik-filter-condition-template');
        $output->add_gui_object('rubik_action_template', 'rubik-filter-action-template');
        $output->add_gui_object('rubik_condition_list', 'rubik-condition-list');
        $output->add_gui_object('rubik_action_list', 'rubik-action-list');
        $output->add_gui_object('rubik_name_input', 'filter-name-input');
        $output->add_gui_object('rubik_condition_type_input', 'condition-type-input');

        return $output->parse("rubik_filter.filter_form", false, false);
    }

    /**
     * Render field type input for filter form page.
     *
     * @return string html code
     */
    function ui_filter_field_select() {
        $select = new html_select(array('name' => 'field'));

        foreach (self::UI_VALID_FIELDS as $field) {
            $select->add($this->gettext($field), $field);
        }

        return $select->show();
    }

    /**
     * Render operator type input for filter form page.
     *
     * @return string html code
     */
    function ui_filter_operator_select() {
        $select = new html_select(array('name' => 'operator'));

        $not = $this->gettext('operator_input_not');
        foreach (self::UI_VALID_OPERATORS as $op) {
            $name = $this->gettext($op);

            $select->add($name, $op);

            $name = strtolower($name);
            $select->add("$not$name", "!$op");
        }

        return $select->show();
    }

    /**
     * Render action type input for filter form page.
     *
     * @return string html code
     */
    function ui_filter_action_select() {
        $select = new html_select(array('name' => 'action'));

        foreach (self::UI_VALID_ACTIONS as $action) {
            $select->add($this->gettext($action), $action);
        }

        return $select->show();
    }

    /**
     * Render condition block type input for filter form page.
     *
     * @param $attr array html attributes
     * @return string html code
     */
    function ui_filter_condition_type_select($attr) {
        $select = new html_select(array('name' => 'condition-block-type'));

        $select->add($this->gettext(ConditionBlock::AND), ConditionBlock::AND);
        $select->add($this->gettext(ConditionBlock::OR), ConditionBlock::OR);

        return $select->show(array(), $attr);
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
        $dateFmt = "d.m.y";
        foreach ($filters as $key => $filter) {
            // filter out either filters or vacations
            if ($showVacations !== $filter instanceof Vacation) continue;

            $name = $filter->getName();

            if (empty($name)) {
                $name = "Filter $key";
            }

            $listItem = array(
                'id' => $key,
                'name' => $name
            );

            if ($filter instanceof Vacation) {
                $dateRange = $filter->getRange();

                $dateStart = $dateRange['start']->format($dateFmt);
                $dateEnd = $dateRange['end']->format($dateFmt);

                $listItem['date-range'] = "$dateStart - $dateEnd";
            }

            $names[] = $listItem;

            $isEnabled = $filter->getFilterEnabled();
            $command = "rcmail.command('toggle_enabled', this)";

            $enabledCheckbox =
                "</td><td class='checkbox-cell' style='text-align: right;'>".
                "<div class='custom-control custom-switch'><input onclick=\"$command\"  class='form-check-input custom-control-input' type='checkbox' ";
            $enabledCheckbox .= ($isEnabled ? 'checked' : '');
            $enabledCheckbox .= " /><label onclick=\"$command\" class='custom-control-label'/></div>";
            $enabledCheckbox .= "</td></tr>";

            $enabledReplace[] = $enabledCheckbox;
        }

        // list output
        $out = $rc->table_output($attrib, $names, array('name', 'date-range'),'id');

        // inject checkbox switches to each row
        preg_match_all("/<\/td>\s*<\/tr>/", $out, $matches, PREG_OFFSET_CAPTURE);
        $injectedOut = $out;
        $offset = 0;

        foreach ($matches[0] as $key => $match) {
            $replacement = $enabledReplace[$key];

            $injectedOut = substr_replace($injectedOut, $replacement, $match[1] + $offset, strlen($match[0]));

            $offset += strlen($replacement) - strlen($match[0]);
        }

        $out = $injectedOut;

        return $out;
    }

    /**
     * Save filter to procmail.
     *
     * @param $id string old filter id
     */
    function action_save_filter($id) {
        $rc = rcmail::get_instance();
        $errMsgPrefix = 'msg_err_save_filter';

        $clientActions = $this->getInput('filter_actions');
        $clientConditions = $this->getInput('filter_conditions');
        $clientConditionsType = $this->getInput('filter_conditions_type');
        $clientFilterName = $this->getInput('filter_name');
        $clientFilterId = $id;

        if (empty($clientActions) || count($clientActions) === 0) {
            $this->showMessage($rc,'msg_err_no_action', 'error', $errMsgPrefix);
            return;
        }

        if (empty($clientConditions)) {
            $clientConditions = array();
        }

        // parse conditions
        $conditionBlock = new ConditionBlock();

        if ($conditionBlock->setType($clientConditionsType) === false) {
            $this->showMessage($rc, 'msg_err_invalid_condition_block_type', 'error', $errMsgPrefix);
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
                $this->showMessage($rc, 'msg_err_invalid_cond', 'error', $errMsgPrefix);
                return;
            }

            $conditionBlock->addCondition($cond);
        }

        $filterBuilder = new Filter();
        $filterBuilder->setConditionBlock($conditionBlock);

        foreach ($clientActions as $clientAction) {
            if (!$filterBuilder->addAction($clientAction['action'], $clientAction['val'])) {
                $this->showMessage($rc, 'msg_err_invalid_action', 'error', $errMsgPrefix);
                return;
            }
        }

        if (!empty($clientFilterName)) {
            $filterBuilder->setName($clientFilterName);
        }

        if ($this->updateFilter($rc, $clientFilterId, $filterBuilder, $errMsgPrefix) === true) {
            $this->showMessage($rc, 'msg_success_save_filter', 'confirmation', null);
            $rc->output->redirect(self::A_FILTER_SETTINGS, self::REDIRECT_TIMEOUT);
        }
    }

    /**
     * Toggle filter enabled state
     *
     * @param $filterId int filter id
     * @param $redirectTo string action to redirect to on success
     */
    function action_toggle_filter($filterId, $redirectTo) {
        $errMsgPrefix = 'msg_err_toggle_message';

        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        if ($filterId === null || !is_numeric($filterId)) {
            $this->showMessage($rc, 'msg_err_invalid_filter_id', 'error', $errMsgPrefix);
            return;
        }

        $filterId = intval($filterId);

        if ($this->toggleFilterEnabled($rc, $filterId, $errMsgPrefix) === true) {
            $this->showMessage($rc,'msg_success_toggle_filter', 'confirmation', null);
            $output->redirect($redirectTo, self::REDIRECT_TIMEOUT);
        }
    }

    /**
     * Remove filter from procmail.
     *
     * @param $filterId string|null
     * @param $redirectTo string action to redirect to on success
     */
    function action_remove_filter($filterId, $redirectTo) {
        $rc = rcmail::get_instance();

        if ($filterId !== null) {
            $result = $this->updateFilter($rc, $filterId, null, 'msg_err_remove_filter');

            if ($result) {
                $this->showMessage($rc, 'msg_success_remove_filter', 'confirmation', null);
            }

            $rc->output->redirect($redirectTo, self::REDIRECT_TIMEOUT);
        }
    }

    /**
     * Swap position of two filters.
     */
    function action_swap_filters() {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $errMsgPrefix = 'msg_err_swap_filter';

        $id1 = $this->getInput('filter_swap_id1');
        $id2 = $this->getInput('filter_swap_id2');

        if($id1 === null || $id2 === null) {
            $this->showMessage($rc, 'msg_err_invalid_swap_ids', 'error', $errMsgPrefix);
            return;
        }

        $id1 = intval($id1);
        $id2 = intval($id2);

        if ($this->swapFilters($rc, $id1, $id2, $errMsgPrefix) === true) {
            $this->showMessage($rc, 'msg_success_swap_filter', 'confirmation', null);
        }

        $entityType = $this->getInput(self::INPUT_ENTITY_TYPE);

        if ($entityType === self::ENTITY_FILTER) {
            $output->redirect(self::A_FILTER_SETTINGS, self::REDIRECT_TIMEOUT);
        } else if ($entityType === self::ENTITY_VACATION) {
            $output->redirect(self::A_VACATION_SETTINGS, self::REDIRECT_TIMEOUT);
        }

    }
    //endregion

    //region Actions/UI - Vacations specific

    /**
     * Show vacation form page.
     *
     * @param $vacationId string|null original vacation id
     * @return string|null
     */
    function ui_vacation_form($vacationId) {
        $rc = rcmail::get_instance();

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $client = $this->getStorageClient($rc);

        if ($vacationId !== null) {
            $filters = $this->getFilters($rc, 'msg_err_load_vacation_form',$client);

            if (isset($filters[$vacationId])) {
                /** @var Vacation $vacation */
                $vacation = $filters[$vacationId];

                $messageFilename = $vacation->getMessagePath(false);

                $dateRange = $vacation->getRange();
                $dateFormat = "Y-m-d"; // input[type=date] compatible format

                $vacationOut = array(
                    'vacation_name' => $vacation->getName(),
                    'vacation_reply' => $messageFilename,
                    'vacation_start' => $dateRange['start']->format($dateFormat),
                    'vacation_end' => $dateRange['end']->format($dateFormat)
                );

                $output->set_env('rubik_vacation', $vacationOut);
            }
        }

        $messageList = $this->listReplies($rc, 'msg_err_load_vacation_form', $client);

        if ($messageList === null) {
            return $output->parse('iframe', false, false);
        } else if (count($messageList) === 0) {
            $this->showMessage($rc, 'msg_warn_create_reply', 'warning', null);
            return $output->parse('iframe', false, false);
        }


        $output->set_env('rubik_reply_options', $messageList);

        return $output->parse('rubik_filter.vacation_form', false, false);
    }

    /**
     * Save vacation to procmail.
     *
     * @param $clientVacationId string|null old vacation id
     */
    function action_save_vacation($clientVacationId) {
        $rc = rcmail::get_instance();

        if ($clientVacationId !== null) {
            $clientVacationId = intval($clientVacationId);
        }

        $msgErrPrefix = 'msg_err_save_vacation';

        $clientDateStart = $this->getInput("vacation_start");
        $clientDateEnd = $this->getInput("vacation_end");
        $clientReply = $this->getInput("vacation_selected_reply");
        $clientVacationName = $this->getInput("vacation_name");

        if (empty($clientDateStart) || empty($clientDateEnd)) {
            $this->showMessage($rc, 'msg_err_invalid_date', 'error', $msgErrPrefix);
            return;
        }

        try {
            $dateStart = new DateTime($clientDateStart);
            $dateEnd = new DateTime($clientDateEnd);
        } catch (Exception $e) {
            $this->showMessage($rc, 'msg_err_invalid_date', 'error', $msgErrPrefix);
            return;
        }

        // try to load given message
        $clientReply = $this->sanitizeReplyFilename($clientReply);

        $message = $this->getReply($rc, $clientReply);
        if ($message === null) {
            return;
        }

        // create vacation object
        $vacation = new Vacation();
        $vacation->setName($clientVacationName);
        $vacation->setRange($dateStart, $dateEnd);
        $vacation->setMessagePath(ProcmailStorage::VACATION_REPLIES_LOCATION . "/$clientReply");

        // check if dates don't overlap with other vacations
        $client = $this->getStorageClient($rc);
        $filters = $this->getFilters($rc, $msgErrPrefix, $client);
        if (!$this->checkStorageErrorCode($rc, $filters, $msgErrPrefix)) {
            return;
        }
        foreach ($filters as $key => $filter) {
            // skip vacation being saved
            if ($key === $clientVacationId) continue;

            if ($filter instanceof Vacation && $filter->rangeOverlaps($vacation)) {
                $this->showMessage($rc, $filter->getName(), 'error', 'msg_err_date_overlap', false);
                return;
            }
        }

        $res = $this->updateFilter($rc, $clientVacationId, $vacation, $msgErrPrefix,false);

        if ($res) {
            $this->showMessage($rc, 'msg_success_save_vacation', 'confirmation', null);
            $rc->output->redirect(self::A_VACATION_SETTINGS, self::REDIRECT_TIMEOUT);
        }
    }
    //endregion

    //region Actions/UI - Replies
    /**
     * Render replies list for settings page.
     *
     * @param $attrib array template attributes
     * @param $rc rcmail
     * @return string|null
     */
    function ui_reply_list($attrib, $rc) {
        $messageList = $this->listReplies($rc, 'msg_err_list_replies');
        if ($messageList === null) {
            return null;
        }

        $names = array();

        foreach ($messageList as $messageFile) {
            $names[] = array(
                'name' => $messageFile
            );
        }

        return $rc->table_output($attrib, $names, array('name'), 'name');
    }

    /**
     * Show reply form page.
     *
     * @param $rc rcmail
     * @param $messageId string
     * @return string|null
     */
    function ui_reply_form($rc, $messageId) {
        /** @var rcmail_output_html $output */
        $output = $rc->output;

        if ($messageId !== null) {
            $message = $this->getReply($rc, $messageId);
            if ($message === null) {
                return $output->parse('iframe', false, false);
            }

            $reply = array(
                'rubik_reply_text' => $message,
                'rubik_reply_filename' => $messageId
            );

            $output->set_env('rubik_reply', $reply);
        }

        return $output->parse('rubik_filter.message_form', false, false);
    }

    /**
     * Remove reply file from storage.
     * @param $replyFilename string filename
     */
    function action_remove_reply($replyFilename) {
        $rc = rcmail::get_instance();

        if ($replyFilename === null) {
            $this->showMessage($rc, 'msg_err_missing_reply_id', 'error', 'msg_err_remove_reply');
            return;
        }

        $client = $this->getStorageClient($rc);

        // Check if the reply isn't used in a vacation.
        $filters = $this->getFilters($rc, 'msg_err_check_reply_usage', $client);
        if (!$this->checkStorageErrorCode($rc, $filters, 'msg_err_check_reply_usage')) {
            return;
        }

        $replyFilename = $this->sanitizeReplyFilename($replyFilename);
        foreach ($filters as $filter) {
            if ($filter instanceof Vacation && $filter->getMessagePath(false) === $replyFilename) {
                $this->showMessage($rc, $filter->getName(), 'error', 'msg_err_reply_in_use', false);
                return;
            }
        }

        if ($this->deleteReply($rc, $replyFilename)) {
            $this->showMessage($rc, 'msg_success_remove_reply', 'confirmation', null);
            $rc->output->redirect(self::A_REPLY_SETTINGS, self::REDIRECT_TIMEOUT);
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

        $rc->output->redirect(self::A_REPLY_SETTINGS, self::REDIRECT_TIMEOUT);
    }

    /**
     * Called through ajax, load one reply message from storage for display.
     */
    function action_get_reply() {
        $rc = rcmail::get_instance();

        $messageFilename = $this->getInput('reply_filename');

        $message = $this->getReply($rc, $messageFilename);

        if ($message !== null) {
            $rc->output->command(self::CC_SET_REPLY, array(
                'reply_filename' => $messageFilename,
                'reply_text' => $message
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
     * When updating the enabled state is preserved.
     *
     * @param $rc rcmail
     * @param $id int|null filter ID
     * @param $newFilter Filter|null filter
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

            if ($newFilter === null) { // remove => append ''
                $newFilter = '';
            } else {
                $newFilter->setFilterEnabled($filters[$id]->getFilterEnabled());
                $newFilter = $newFilter->createFilter();
                if ($newFilter === null) {
                    $this->showMessage($rc, 'msg_err_create_filter', 'error', $errorMsgPrefix);
                    return false;
                }
            }

            // concat filters
            foreach ($filters as $key => $oldFilter) {
                if ($key === $id) { // replace filter
                    $procmail .= $newFilter;
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
            if (is_numeric($oldProcmail)) {
                if ($oldProcmail & (ProcmailStorage::ERR_CANNOT_READ | ProcmailStorage::ERR_NO_SECTION)) {
                    // this is fine, file hasn't probably been initialized yet with plugin content
                    $oldProcmail = '';
                } else {
                    // otherwise display storage error
                    $this->checkStorageErrorCode($rc, $oldProcmail, $errorMsgPrefix);
                    return false;
                }
            }

            $procmail .= $oldProcmail;

            $newFilter = $newFilter->createFilter();
            if ($newFilter === null) {
                $this->showMessage($rc, 'msg_err_create_filter', 'error', $errorMsgPrefix);
                return false;
            }

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

        $messageList = $this->listReplies($rc, 'msg_err_save_reply', $client);
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
    private function deleteReply($rc, $filename, $client = null) {
        if ($client === null) {
            $client = $this->getStorageClient($rc);
        }

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

    private function listReplies($rc, $errMsgPrefix, $client = null) {
        if ($client === null) $client = $this->getStorageClient($rc);

        $replies = $client->listVacationMessages();

        if (!$this->checkStorageErrorCode($rc, $replies, $errMsgPrefix)) {
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
     * @param $msgIsId bool if true $msg is taken as localized text id
     */
    private function showMessage($rc, $msg, $type, $msgPrefix, $msgIsId = true) {
        if ($msgIsId) {
            $msg = $this->gettext("rubik_filter.$msg");
        }

        if ($msgPrefix !== null) {
            $msgPrefix = $this->gettext("rubik_filter.$msgPrefix");

            $msg = "$msgPrefix: $msg";
        }

        /** @var rcmail_output_html $output */
        $output = $rc->output;

        $output->show_message($msg, $type, null, false, 6);
        $output->command('plugin.rubik_hide_loading');
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
     * @return string|array|null
     */
    private function getInput($what, $source = rcube_utils::INPUT_POST) {
        return rcube_utils::get_input_value($what, $source);
    }
    //endregion
}