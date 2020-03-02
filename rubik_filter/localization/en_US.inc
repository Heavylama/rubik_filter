<?php

use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;

$labels = array();

$labels['settings_title'] = 'Procmail filters';
$labels['new_filter'] = 'New filter';
$labels['form_conditions'] = 'Conditions';

$labels[ProcmailField::FROM] = 'From';
$labels[ProcmailField::LIST_ID] = 'List-Id';
$labels[ProcmailField::CC] = 'Cc';
$labels[ProcmailField::SUBJECT] = 'Subject';
$labels[ProcmailField::TO] = 'To';
$labels[ProcmailField::BODY] = 'Body';
$labels['field_input_title'] = 'Field';

$labels[ProcmailOperator::STARTS_WITH] = 'Starts with';
$labels[ProcmailOperator::CONTAINS] = 'Contains';
$labels[ProcmailOperator::EQUALS] = 'Equals';
$labels[ProcmailOperator::PLAIN_REGEX] = 'Regex';
//$labels[ProcmailOperator::EXISTS] = 'Exists';
$labels['operator_input_title'] = 'Operator';
$labels['operator_input_not'] = 'Not ';

$labels['condition_value_input_title'] = 'Value';

$labels[Action::MAILBOX] = 'Mailbox';
$labels[Action::FWD] = 'Forward to';
$labels[Action::DISCARD] = 'Discard';
$labels['action_input_title'] = 'Action';

$labels['form_add_condition'] = 'Add condition';
$labels['form_add_action'] = 'Add action';
$labels['form_save_filter'] = 'Save filter';

$labels[ConditionBlock::AND] = 'all match';
$labels[ConditionBlock::OR] = 'any match';
$labels['condition_type_input_title'] = 'Execute actions if ';

$labels['msg_no_action'] = 'Filter is missing action!';
$labels['msg_invalid_cond'] = 'Invalid conditions!';
$labels['msg_invalid_action'] = 'Invalid actions!';
$labels['msg_invalid_filter'] = 'Error creating filter!';
$labels['msg_error_storage'] = 'Error saving filter to storage!';
$labels['msg_filter_saved'] = 'Filter saved successfully';
$labels['msg_invalid_condition_block_type'] = 'Invalid condition block type!';

$labels['title_conditions'] = 'Conditions';
$labels['title_actions'] = 'Actions';
$labels['msg_error_load_filter'] = 'Error loading filters!';
$labels['msg_error_remove_filter'] = 'Error removing filter!';
$labels['msg_success_remove_filter'] = 'Filter has been removed';
$labels['label_filter_name'] = 'Filter name';
$labels['msg_error_swap_filter'] = 'Error swapping filters!';
$labels['msg_success_swap_filter'] = 'Filters have been swapped';
$labels['msg_error_disable_filter'] = 'Error disabling filter';
$labels['msg_success_disable_filter'] = 'Filter has been disabled';
$labels['title_vacation'] = 'Vacation';
$labels['label_vacation_range'] = 'Date range';
$labels['label_vacation_from'] = 'From:';
$labels['label_vacation_to'] = 'To:';
$labels['label_vacation_message'] = 'Message';
$labels['label_vacation_save'] = 'Save vacation';
