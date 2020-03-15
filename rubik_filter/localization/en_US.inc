<?php

use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;
use Rubik\Storage\ProcmailStorage;

$labels = array();

$labels['new_filter'] = 'New filter';

$labels[ProcmailField::FROM] = 'From';
$labels[ProcmailField::LIST_ID] = 'List-Id';
$labels[ProcmailField::CC] = 'Cc';
$labels[ProcmailField::SUBJECT] = 'Subject';
$labels[ProcmailField::TO] = 'To';
$labels[ProcmailField::BODY] = 'Body';

$labels[ProcmailOperator::STARTS_WITH] = 'Starts with';
$labels[ProcmailOperator::CONTAINS] = 'Contains';
$labels[ProcmailOperator::EQUALS] = 'Equals';
$labels[ProcmailOperator::PLAIN_REGEX] = 'Regex';

$labels[Action::MAILBOX] = 'Mailbox';
$labels[Action::FWD] = 'Forward to';
$labels[Action::DISCARD] = 'Discard';

$labels[ConditionBlock::AND] = 'all match';
$labels[ConditionBlock::OR] = 'any match';

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


/* Filters */
$labels['title_settings_filters'] = 'Procmail filters';
$labels['msg_err_load_filter_form'] = "Error loading filter form";
$labels['msg_err_load_filter_list'] = 'Error loading filter list';
$labels['msg_err_toggle_filter'] = 'Error toggling filter';
$labels['msg_success_toggle_filter'] = 'Filter state toggled';
$labels['msg_err_remove_filter'] = 'Error removing filter';
$labels['msg_success_remove_filter'] = 'Filter has been removed';
$labels['msg_err_swap_filter'] = 'Error swapping filters';
$labels['msg_success_swap_filter'] = 'Filters have been swapped';
$labels['msg_err_invalid_swap_ids'] = 'Invalid swap IDs';
$labels['msg_err_save_filter'] = 'Error saving filter';
$labels['msg_err_no_action'] = 'Missing action';
$labels['msg_err_invalid_cond'] = 'Invalid condition';
$labels['msg_err_invalid_action'] = 'Invalid action';
$labels['msg_err_invalid_filter'] = 'Error creating filter';
$labels['msg_err_invalid_condition_block_type'] = 'Invalid condition block type';
$labels['msg_success_save_filter'] = 'Filter saved';
$labels['operator_input_not'] = 'Not ';
$labels['label_filter_name'] = 'Filter name';
$labels['label_save_filter'] = 'Save filter';
$labels['label_conditions'] = 'Conditions';
$labels['label_field'] = 'Field';
$labels['label_operator'] = 'Operator';
$labels['label_value'] = 'Value';
$labels['label_add_condition'] = 'Add condition';
$labels['label_add_action'] = 'Add action';
$labels['label_condition_type'] = 'Execute actions if ';
$labels['label_action'] = 'Action';
$labels['label_actions'] = 'Actions';

/* Replies */
$labels['title_settings_replies'] = 'Vacation replies';
$labels['msg_success_save_reply'] = 'Reply successfully saved';
$labels['msg_err_save_reply'] = 'Error saving reply file';
$labels['msg_success_remove_reply'] = 'Reply successfully removed';
$labels['msg_err_remove_reply'] = 'Error removing reply';
$labels['msg_err_list_replies'] = 'Cannot list existing messages';
$labels['msg_err_filename_exists'] = 'Filename already exists!';
$labels['msg_err_missing_reply_id'] = 'Missing reply id';
$labels['msg_err_missing_reply_form_data'] = 'Missing reply form data!';
$labels['msg_err_check_reply_usage'] = 'Error checking reply usage';
$labels['msg_err_reply_in_use'] = 'Reply is used in vacation';

/* Vacations */
$labels['title_settings_vacations'] = 'Vacations';
$labels['msg_err_load_reply'] = 'Error loading reply';
$labels['msg_warn_create_reply'] = 'Create a vacation reply first';
$labels['msg_err_save_vacation'] = 'Error saving vacation';
$labels['msg_success_save_vacation'] = 'Vacation successfully saved';
$labels['msg_err_load_vacation_form'] = 'Error loading vacation form';
$labels['msg_err_invalid_date'] = 'Invalid date';
$labels['msg_err_date_overlap'] = 'Date range overlaps with other vacation';

/* Common */
$labels['msg_err_no_connection'] = 'No connection to storage server';
$labels['msg_err_cannot_write'] = 'Cannot write';
$labels['msg_err_cannot_read'] = 'Cannot read';
$labels['msg_err_no_section'] = 'Missing plugin section from procmail file';
$labels['msg_err_invalid_hash']
    = 'Plugin section is corrupted. Please remove Rubik filter section from your ~/.procmailrc file!';
$labels['msg_err_invalid_filter_id'] = 'Filter ID is invalid';
$labels['msg_err_create_filter'] = 'Cannot create filter';
$labels['msg_err_parse_filter'] = ' Error while parsing';