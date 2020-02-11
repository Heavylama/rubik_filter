rcmail.addEventListener('init', function() {
    if (rcmail.env.action === "plugin.rubik_filter_edit_filter") {

        // invisible template rows added to form tables
        const filter_condition_template_row = $('#rubik-filter-condition-template');
        const filter_action_template_row = $('#rubik-filter-action-template');

        // form lists
        const condition_list = $('#rubik-condition-list tbody');
        const action_list = $('#rubik-action-list tbody');

        // make lists items draggable
        const sortableOptions = {
            animation: '100',
            chosenClass: 'dragged',
            handle: '.rubik-handle'
        };
        new Sortable(condition_list[0], sortableOptions);
        new Sortable(action_list[0], sortableOptions);

        // condition table handling
        function addConditionRow(list, field = null, op = null, condVal = null) {
            const new_row = filter_condition_template_row.clone(true);

            new_row.attr('id', null);
            new_row.attr('class', 'rubik-filter-condition-row');

            if (field != null) {
                new_row.find(':input[name=field]').val(field);
            }

            if (op != null) {
                new_row.find(':input[name=operator]').val(op);
            }

            if (condVal != null) {
                new_row.find(':input[name=val]').val(condVal);
            }

            new_row.find('.rubik-controls .delete').click(function() {
                new_row.remove();
            });

            list.append(new_row);
        }

        $('#rubik-condition-add').click(function() {
            addConditionRow(condition_list);
        });

        // action table handling
        function addActionRow(list) {
            const new_row = filter_action_template_row.clone(true);

            new_row.attr('id', null);
            new_row.attr('class', 'rubik-filter-action-row');

            new_row.find('.rubik-controls .delete').click(function() {
                new_row.remove();

                updateAvailableActions();
            });

            const value_input = new_row.find(':input[name=action_value]');
            const action_select = new_row.find(':input[name=action]');

            action_select.change(function() {
                const selectedValue = $(this).find('option:selected').val();

                if (selectedValue === '_discard') {
                    value_input.addClass('hidden');
                } else {
                    value_input.removeClass('hidden');
                }

                updateAvailableActions();
            });

            list.append(new_row);

            updateAvailableActions();
        }

        function getOriginalActions() {
            return filter_action_template_row.find(':input[name=action]').options;
        }

        function updateAvailableActions() {
            const inputs = $('#rubik-action-list .rubik-filter-action-row :input[name=action]');
            const presentValues = $.makeArray(inputs.map(function() {
                return $(this).val();
            }));

            const discardIndex = presentValues.indexOf('_discard');
            const options = getOriginalActions();

            for (let i = 0; i < inputs.length; i++) {
                if ((discardIndex > -1 || inputs.length > 1) && i !== discardIndex) {
                    $(inputs[i]).find('option[value=_discard]').attr('disabled', true);
                } else {
                    $(inputs[i]).find('option[value=_discard]').removeAttr('disabled');
                }
            }

            $('#rubik-action-add').attr('disabled', discardIndex >= 0);
        }

        $('#rubik-action-add').click(function() {
            addActionRow(action_list);
        });

        // plugin commands
        function saveFilter() {
            let filter = {
                conditions: [],
                actions: []
            };

            $('.rubik-filter-condition-row').each(function(key, row) {
                let cond = {
                    field: $(row).find(':input[name=field]').val(),
                    op: $(row).find(':input[name=operator]').val(),
                    val: $(row).find(':input[name=condition_value]').val()
                };

                filter.conditions.push(cond);
            });

            $('.rubik-filter-action-row').each(function(key, row) {
               let action = {
                   action: $(row).find(':input[name=action]').val(),
                   val:$(row).find(':input[name=action_value]').val()
               };

               filter.actions.push(action);
            });

            filter.condition_block_type = $('#conditions-block-options select[name=condition-block-type]').val();

            rcmail.http_post('plugin.rubik_filter_save_filter', filter, true);
        }

        function saveCallback(result) {

            if (result.msg) {
                if (result.success == true) {
                    rcmail.display_message(result.msg, 'confirmation');
                } else {
                    rcmail.display_message(result.msg, 'error');
                }
            }
        }

        rcmail.addEventListener('plugin.rubik_filter_save_result', saveCallback);

        $('#rubik-save-rule').click(saveFilter);
    }
});