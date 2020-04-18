<?php

use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Filter;

$labels = array();

$labels['new_filter'] = 'Nový filtr';

$labels[Field::FROM] = 'Od';
$labels[Field::LIST_ID] = 'List-Id';
$labels[Field::CC] = 'Cc';
$labels[Field::SUBJECT] = 'Předmět';
$labels[Field::TO] = 'Komu';
$labels[Field::BODY] = 'Tělo';
$labels[Field::CUSTOM] = 'Vlastní';

$labels[Operator::STARTS_WITH] = 'Začíná na';
$labels[Operator::CONTAINS] = 'Obsahuje';
$labels[Operator::EQUALS] = 'Rovná se';
$labels[Operator::PLAIN_REGEX] = 'Regex';

$labels[Action::MAILBOX] = 'Uložit do složky';
$labels[Action::FWD] = 'Přeposlat';
$labels[Action::DISCARD] = 'Zahodit';

$labels[ConditionBlock::AND] = 'všechny platí';
$labels[ConditionBlock::OR] = 'jakákoliv platí';


/* Filters */
$labels['title_settings_filters'] = 'Filtry';
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
$labels['label_post_action'] = 'Po provedení akcí';
$labels[Filter::POST_END_INBOX] = 'ukončit filtrování a uložit kopii do příchozí pošty';
$labels[Filter::POST_END_DISCARD] = 'ukončit filtrování bez kopie';
$labels[Filter::POST_CONTINUE] = 'pokračovat dalším filtrem';

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
$labels['reply_time_one_day'] = 'Jednou za den';
$labels['reply_time_three_days'] = 'Jednou za 3 dny';
$labels['reply_time_week'] = 'Jednou za týden';
$labels['reply_time_only_once'] = 'Jednou za dovolenou';
$labels['label_vacation_reply_time'] = 'Každému odesílateli odeslat odpověď maximálně:';
$labels['label_set_reply'] = 'Nastavit';
$labels['dialog_set_reply'] = 'Opravdu chcete přepsat vaši zprávu touto odpovědí?';
$labels['label_vacation_select'] = 'Přednastavené odpovědi';
$labels['label_vacation_reply'] = 'Automatická odpověď';
$labels['label_vacation_message'] = 'Zpráva';
$labels['label_vacation_name'] = 'Název';
$labels['msg_err_load_vacation_list'] = 'Chyba při načítání seznamu dovolených';

/* Common */
$labels['msg_err_no_connection'] = 'Žádné spojení se serverem uložiště';
$labels['msg_err_cannot_write'] = 'Nelze zapisovat';
$labels['msg_err_cannot_read'] = 'Nelze číst';
$labels['msg_err_no_section'] = 'V procmail souboru chybí sekce pluginu';
$labels['msg_err_invalid_hash']
    = 'Sekce pluginu je poškozená. Prosím odstraňte sekci Rubik filter z vašeho ~/.procmailrc souboru!';
$labels['msg_err_invalid_id'] = 'Neplatné ID';
$labels['msg_err_create_filter'] = 'Nelze vytvořit filtr';
$labels['msg_err_parse_filter'] = 'Chyba při parsování';
$labels['dialog_repair_section'] = 'Sekce filtrů a dovolených je poškozená.</br></br>Stisknutím tlačítka Opravit je možné sekci spravit. Toto však povede ke <b>ztrátě některých filtrů a/nebo dovolených, které jsou poškozené</b>.</br></br>V opačném případě kontaktujte administrátora Vašeho webmailu.';
$labels['label_repair'] = 'Opravit';
