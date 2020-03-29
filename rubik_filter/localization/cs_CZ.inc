<?php

use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field as ProcmailField;
use Rubik\Procmail\Rule\Operator as ProcmailOperator;

$labels = array();

$labels['new_filter'] = 'Nový filtr';

$labels[ProcmailField::FROM] = 'Od';
$labels[ProcmailField::LIST_ID] = 'List-Id';
$labels[ProcmailField::CC] = 'Cc';
$labels[ProcmailField::SUBJECT] = 'Předmět';
$labels[ProcmailField::TO] = 'Komu';
$labels[ProcmailField::BODY] = 'Tělo';

$labels[ProcmailOperator::STARTS_WITH] = 'Začíná na';
$labels[ProcmailOperator::CONTAINS] = 'Obsahuje';
$labels[ProcmailOperator::EQUALS] = 'Rovná se';
$labels[ProcmailOperator::PLAIN_REGEX] = 'Regex';

$labels[Action::MAILBOX] = 'Uložit do složky';
$labels[Action::FWD] = 'Přeposlat';
$labels[Action::DISCARD] = 'Zahodit';

$labels[ConditionBlock::AND] = 'všechny platí';
$labels[ConditionBlock::OR] = 'jakákoliv platí';

$labels['label_vacation_name'] = 'Název';
$labels['label_vacation_range'] = 'Datum';
$labels['label_vacation_from'] = 'Od:';
$labels['label_vacation_to'] = 'Do:';
$labels['label_vacation_message'] = 'Odpověď';
$labels['label_vacation_save'] = 'Uložit dovolenou';
$labels['label_vacation_reply'] = 'Automatická odpověď';
$labels['label_vacation_subject'] = 'Předmět';
$labels['label_vacation_select'] = 'Vybraná odpověď';
$labels['label_vacation_message_name'] = 'Název souboru';
$labels['label_vacation_new_message'] = '- Nová odpověď -';
$labels['label_vacation_message_save'] = 'Uložit odpoveď';


/* Filters */
$labels['title_settings_filters'] = 'Procmail filtry';
$labels['msg_err_load_filter_form'] = "Chyba při načítání formuláře filtru";
$labels['msg_err_load_filter_list'] = 'Chyba při načítání seznamu filtrů';
$labels['msg_err_toggle_filter'] = 'Chyba při přepínání stavu filtru';
$labels['msg_success_toggle_filter'] = 'Stav filtru přepnut';
$labels['msg_err_remove_filter'] = 'Chyba při odstraňovaní filtru';
$labels['msg_success_remove_filter'] = 'Filtr byl odstraňen';
$labels['msg_err_swap_filter'] = 'Chyba při změně pořadí filtrů';
$labels['msg_success_swap_filter'] = 'Pořadí filtrů bylo změněno';
$labels['msg_err_invalid_swap_ids'] = 'Neplatná ID pro přehození filtrů';
$labels['msg_err_save_filter'] = 'Chyba při ukládání filtru';
$labels['msg_err_no_action'] = 'Chybí akce';
$labels['msg_err_invalid_cond'] = 'Neplatná podmínka';
$labels['msg_err_invalid_action'] = 'Neplatná akce';
$labels['msg_err_invalid_filter'] = 'Chyba při vytváření filtru';
$labels['msg_err_invalid_condition_block_type'] = 'Neplatný typ bloku podmínek';
$labels['msg_success_save_filter'] = 'Filtr uložen';
$labels['operator_input_not'] = 'Ne';
$labels['label_filter_name'] = 'Název filtru';
$labels['label_save_filter'] = 'Uložit filtr';
$labels['label_conditions'] = 'Podmínky';
$labels['label_field'] = 'Pole';
$labels['label_operator'] = 'Operátor';
$labels['label_value'] = 'Hodnota';
$labels['label_add_condition'] = 'Přidat podmínku';
$labels['label_add_action'] = 'Přidat akci';
$labels['label_condition_type'] = 'Provést akce pokud ';
$labels['label_action'] = 'Akce';
$labels['label_actions'] = 'Akce';
$labels['title_filter_form'] = 'Detaily filtru';
$labels['dialog_remove_filter'] = 'Opravdu chcete odebrat tento filtr?';

/* Replies */
$labels['title_settings_replies'] = 'Odpovědi dovolených';
$labels['msg_success_save_reply'] = 'Odpověď uložena';
$labels['msg_err_save_reply'] = 'Chyba při ukládání souboru odpovědi';
$labels['msg_success_remove_reply'] = 'Odpověď úspěšně odebrána';
$labels['msg_err_remove_reply'] = 'Chyba při odebírání odpovědi';
$labels['msg_err_list_replies'] = 'Nelze načíst seznam odpovědí';
$labels['msg_err_filename_exists'] = 'Soubor již existuje!';
$labels['msg_err_missing_reply_id'] = 'Chybějící ID zprávy';
$labels['msg_err_missing_reply_form_data'] = 'Chybí formulářová data!';
$labels['msg_err_check_reply_usage'] = 'Chyba při kontrole využití zprávy';
$labels['msg_err_reply_in_use'] = 'Odpověď je použita u dovolené';
$labels['title_reply_form'] = 'Detaily odpovědi';
$labels['dialog_remove_reply'] = 'Opravdu chcete odebrat tuto odpověď?';

/* Vacations */
$labels['title_settings_vacations'] = 'Dovolená';
$labels['msg_err_load_reply'] = 'Chyba při načítání zprávy';
$labels['msg_warn_create_reply'] = 'Nejdříve vytvořte odpověď dovolené';
$labels['msg_err_save_vacation'] = 'Chyba při ukládání zprávy';
$labels['msg_success_save_vacation'] = 'Dovolená úspěšně uložena';
$labels['msg_err_load_vacation_form'] = 'Chyba při načítání formuláře dovolené';
$labels['msg_err_invalid_date'] = 'Neplatné datum';
$labels['msg_err_date_overlap'] = 'Datum se překrývá s jinou dovolenou';
$labels['title_vacation_form'] = 'Detaily dovolené';
$labels['dialog_remove_vacation'] = 'Opravdu chcete odebrat tuto dovolenou?';

/* Common */
$labels['msg_err_no_connection'] = 'Žádné spojení se serverem uložiště';
$labels['msg_err_cannot_write'] = 'Nelze zapisovat';
$labels['msg_err_cannot_read'] = 'Nelze číst';
$labels['msg_err_no_section'] = 'V procmail souboru chybí sekce pluginu';
$labels['msg_err_invalid_hash']
    = 'Sekce pluginu je poškozená. Prosím odstraňte sekci Rubik filter z vašeho ~/.procmailrc souboru!';
$labels['msg_err_invalid_filter_id'] = 'ID filtru je neplatné';
$labels['msg_err_create_filter'] = 'Nelze vytvořit filtr';
$labels['msg_err_parse_filter'] = 'Chyba při parsování';