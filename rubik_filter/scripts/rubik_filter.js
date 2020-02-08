rcmail.addEventListener('init', function() {
    if (rcmail.env.action === "plugin.rubik_filter") {
        const rules_list = $('#rubik-rule-list tbody');

        const rule_input_row = $('#rubik-rule-input-row');

        new Sortable(rules_list[0], {
            animation: '100',
            chosenClass: 'dragged',
            handle: '.rubik-handle'
        });

        function addConditionRow(list, field = null, op = null, condVal = null) {
            const new_row = rule_input_row.clone(true);

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
            addConditionRow(rules_list);
        });

    }
});