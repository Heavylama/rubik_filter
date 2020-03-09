<?php

use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;
use Rubik\Storage\ProcmailStorage;

$labels = array();

$labels['title_settings_filters'] = 'Procmail filters';
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

$labels['title_settings_vacations'] = 'Vacations';
$labels['title_settings_replies'] = 'Vacation replies';
$labels['label_vacation_name'] = 'Name';
$labels['label_vacation_range'] = 'Date range';
$labels['label_vacation_from'] = 'From:';
$labels['label_vacation_to'] = 'To:';
$labels['label_vacation_message'] = 'Message';
$labels['label_vacation_save'] = 'Save vacation';
$labels['label_vacation_reply'] = 'Automatic reply';
$labels['label_vacation_subject'] = 'Subject';
$labels['label_vacation_select'] = 'Selected message';
$labels['label_vacation_message_name'] = 'Filename';
$labels['label_vacation_new_message'] = '- New message -';
$labels['label_vacation_message_save'] = 'Save message';
$labels['msg_err_invalid_date'] = 'Invalid date!';
$labels['msg_err_cannot_write'] = 'Cannot write message file!';
$labels['msg_err_save_vacation'] = 'Error saving vacation!';
$labels['msg_success_save_vacation'] = 'Vacation successfully saved';
$labels['msg_warn_create_reply'] = 'Create a vacation reply first';
$labels['msg_err_load_reply'] = 'Error loading reply';

/* Replies */
$labels['msg_success_save_reply'] = 'Reply successfully saved';
$labels['msg_err_save_reply'] = 'Error saving reply file';
$labels['msg_success_remove_reply'] = 'Reply successfully removed';
$labels['msg_err_remove_reply'] = 'Error removing reply';
$labels['msg_err_list_replies'] = 'Cannot list existing messages';
$labels['msg_err_filename_exists'] = 'Filename already exists!';
$labels['msg_err_missing_reply_id'] = 'Missing reply id';
$labels['msg_err_missing_reply_form_data'] = 'Missing reply form data!';

/* Vacation settings */

/* Common */
$labels['msg_err_no_connection'] = 'No connection to storage server';
$labels['msg_err_cannot_write'] = 'Cannot write';
$labels['msg_err_cannot_read'] = 'Cannot read';
$labels['msg_err_no_section'] = 'Missing plugin section from procmail file';
$labels['msg_err_invalid_hash']
    = 'Plugin section is corrupted. Please remove Rubik filter section from your ~/.procmailrc file!';
$labels['msg_err_invalid_filter_id'] = 'Given filter id is invalid';
$labels['msg_err_create_filter'] = 'Cannot create filter';