<?php

use Rubik\Procmail\Field as ProcmailField;
use Rubik\Procmail\Operator as ProcailOperator;

$labels = array();

$labels['settings_title'] = 'Procmail filters';
$labels['new_rule'] = 'New rule';
$labels['form_conditions'] = 'Conditions';

$labels[ProcmailField::FROM] = 'From';
$labels[ProcmailField::LIST_ID] = 'List-Id';
$labels[ProcmailField::CC] = 'Cc';
$labels[ProcmailField::SUBJECT] = 'Subject';
$labels[ProcmailField::TO] = 'To';
$labels[ProcmailField::BODY] = 'Body';
$labels['field_input_title'] = 'Field';

$labels[ProcailOperator::STARTS_WITH] = 'Starts with';
$labels[ProcailOperator::CONTAINS] = 'Contains';
$labels[ProcailOperator::EQUALS] = 'Equals';
$labels[ProcailOperator::PLAIN_REGEX] = 'Regex';
$labels[ProcailOperator::EXISTS] = 'Exists';
$labels['operator_input_title'] = 'Operator';
$labels['operator_input_not'] = 'Not ';

$labels['condition_value_input_title'] = 'Value';
$labels['form_add_condition'] = 'Add condition';
$labels['form_save_rule'] = 'Save rule';