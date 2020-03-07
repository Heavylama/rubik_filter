rcmail.addEventListener('init', function() {

    if (rcmail.gui_objects.filterlist) {
        // init list
        rcmail.filterlist = new rcube_list_widget(rcmail.gui_objects.filterlist,
            {multiselect:false, draggable:true, keyboard:true, checkbox_selection: true});
        rcmail.filterlist
            .addEventListener('dragstart', filterDragStart)
            .addEventListener('dragend', filterDragEnd)
            .addEventListener('select', filterSelect)
            .init()
            .focus();

        // actions
        rcmail.register_command('add', addFilter, true);
        rcmail.register_command('remove', removeFilter, false);
        rcmail.register_command('toggle_filter', toggleFilter, true);
    }

    function filterDragStart() {
        rcmail.filter_drag_start = rcmail.filterlist.get_single_selection();
    }

    function filterDragEnd(e) {
       if (rcmail.filter_drag_start === null) {
            return;
       }

       const target = $(e.target);

       if (isInFilterList(target)) { // target is in filter list
           const targetId = rcmail.filterlist.get_row_uid(target.parent());
           const sourceId = rcmail.filter_drag_start;

           if (targetId !== sourceId) {
               swapFilterPosition(sourceId, targetId);
           }
       }

       rcmail.filter_drag_start = null;
    }

    function toggleFilter(el) {
        if (isInFilterList(el)) {
            const filterId = rcmail.filterlist.get_row_uid($(el).parents("tr")[0]);

            let action = isVacationAction() ? "plugin.rubik_filter_toggle_vacation" : "plugin.rubik_filter_toggle_filter";

            rcmail.http_post(action, {filter_id: filterId}, true);
        }
    }

    function swapFilterPosition(id1, id2) {
        rcmail.http_post('plugin.rubik_filter_swap_filters', {filter_swap_id1: id1, filter_swap_id2: id2}, true);
    }

    function isInFilterList(el) {
        return $(rcmail.gui_objects.filterlist).has(el).length > 0;
    }

    function filterSelect(list) {
        let filterId = list.get_single_selection();

        if (filterId !== null) {
            loadContent(filterId,
                list,
                isVacationAction() ? "plugin.rubik_filter_new_vacation" : "plugin.rubik_filter_new_filter"
            );
        }
    }

    function addFilter() {
        loadContent(null,
            rcmail.filterlist,
            isVacationAction() ? "plugin.rubik_filter_edit_vacation" : "plugin.rubik_filter_edit_filter"
        );
    }

    function removeFilter() {
        const id = rcmail.filterlist.get_single_selection();

        if (id !== null) {
            let action = isVacationAction() ? "plugin.rubik_filter_remove_vacation" : "plugin.rubik_filter_remove_filter";
            rcmail.http_post(action, {filterid: id}, true);
        }
    }

    function loadContent(id, list, action) {
        const contentWindow = rcmail.get_frame_window(rcmail.env.contentframe);
        if (!contentWindow) {
            return;
        }

        let args = {
            _framed: "1",
            _action: action,
        };

        if (id === null) {
            list.clear_selection();
            rcmail.enable_command('remove', false);
        } else {
            rcmail.enable_command('remove', true);
            args._contentid = id;
        }

        rcmail.location_href(args, contentWindow, true);
    }

    function isVacationAction() {
        return rcmail.env.action === "plugin.rubik_filter_vacation";
    }

    if (rcmail.env.action === "plugin.rubik_filter_edit_filter"
        || rcmail.env.action === "plugin.rubik_filter_new_filter") {

        // invisible template rows added to form tables
        const filter_condition_template_row = $('#rubik-filter-condition-template');
        const filter_action_template_row = $('#rubik-filter-action-template');

        // form lists
        const condition_list = $('#rubik-condition-list tbody');
        const action_list = $('#rubik-action-list tbody');
        const condition_block_type_input = $('#conditions-block-options select[name=condition-block-type]');
        const filter_name = $('#rubik-rule-form input[name=filter-name]');

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
                new_row.find(':input[name=condition_value]').val(condVal);
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
        function addActionRow(list, defAction = null, defVal = null) {
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

            if (defAction !== null) {
                action_select.val(defAction);
            }

            if (defVal !== null) {
                value_input.val(defVal);
            }

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
            const filter = {
                filter_name: filter_name.val(),
                filter_conditions: [],
                filter_actions: []
            };

            $('.rubik-filter-condition-row').each(function(key, row) {
                let cond = {
                    field: $(row).find(':input[name=field]').val(),
                    op: $(row).find(':input[name=operator]').val(),
                    val: $(row).find(':input[name=condition_value]').val()
                };

                filter.filter_conditions.push(cond);
            });

            $('.rubik-filter-action-row').each(function(key, row) {
               let action = {
                   action: $(row).find(':input[name=action]').val(),
                   val:$(row).find(':input[name=action_value]').val()
               };

               filter.filter_actions.push(action);
            });

            filter.filter_conditions_type = condition_block_type_input.val();

            if ('rubik_filter' in rcmail.env) {
                filter.filter_id = rcmail.env.rubik_filter.id;
            }

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

        if ('rubik_filter' in rcmail.env) {
            let filter = rcmail.env.rubik_filter;

            filter.actions.forEach(action => {
               addActionRow(action_list, action.action, action.val);
            });

            filter.conditions.forEach(condition => {
                addConditionRow(condition_list, condition.field, condition.op, condition.val);
            });

            condition_block_type_input.val(filter.type);
            filter_name.val(filter.name);
        }
    }

    if (rcmail.env.action === "plugin.rubik_filter_edit_vacation"
        || rcmail.env.action === "plugin.rubik_filter_new_vacation") {
        let gui = rcmail.gui_objects;

        gui.vacation_start = $("#vacation-date-wrapper input[name=date-start]");
        gui.vacation_end = $("#vacation-date-wrapper input[name=date-end]");
        gui.vacation_name = $("#vacation-form input[name=vacation-name]");
        gui.vacation_selected_message = $("#vacation-message-wrapper select[name=vacation-select]");
        gui.vacation_message = $("#vacation-message-wrapper textarea[name=vacation-message]");

        function saveVacation() {
            let vacation = {
                vacation_start: gui.vacation_start.val(),
                vacation_end: gui.vacation_end.val(),
                vacation_name: gui.vacation_name.val(),
                // vacation_message: gui.vacation_message.val(),
                vacation_selected_message: gui.vacation_selected_message.val()
            };

            if ('vacation_id' in rcmail.env) {
                vacation.vacation_id = rcmail.env.vacation_id;
            }

            rcmail.http_post('plugin.rubik_filter_save_vacation', vacation, true);
        }

        function getVacationMessage(filename) {
            if (filename) {
                rcmail.http_post('plugin.rubik_filter_get_message', {message_filename: filename}, true);
            }
        }

        function onVacationMessageReceived(data) {
            gui.vacation_message.val(data.message_text);
        }

        // init select message options
        rcmail.env.vacation_select_options.forEach(opt => {
            gui.vacation_selected_message.append($("<option>").attr('value', opt).text(opt))
        });

        if ('vacation' in rcmail.env) {
            gui.vacation_selected_message.val(rcmail.env.vacation.vacation_message);
            gui.vacation_name.val(rcmail.env.vacation.vacation_name);
            gui.vacation_start.val(rcmail.env.vacation.vacation_start);
            gui.vacation_end.val(rcmail.env.vacation.vacation_end);
        }

        gui.vacation_selected_message.on('change', function (e) {
            gui.vacation_message.prop('disabled', true);
            getVacationMessage(this.value);
        });

        rcmail.register_command('save_vacation', function() {saveVacation();}, true);
        rcmail.addEventListener('plugin.rubik_filter_set_message', onVacationMessageReceived);

        // load first message in select
        getVacationMessage(gui.vacation_selected_message.val());
    }

    if (rcmail.env.action === "plugin.rubik_filter_vacation_messages") {
        rcmail.vacation_messages_list = new rcube_list_widget(
            rcmail.gui_objects.vacation_messages_list,
            {
                multiselect:false, draggable:false, keyboard:true, checkbox_selection: false
            }
        );

        rcmail.vacation_messages_list
            .addEventListener('select', messageSelect)
            .init()
            .focus();

        function messageSelect(list) {
            let id = list.get_single_selection();

            if (id !== null) {
                loadContent(id, list, 'plugin.rubik_filter_edit_message');
            }
        }

        function addMessage() {
            loadContent(null, rcmail.vacation_messages_list, 'plugin.rubik_filter_new_message');
        }

        function removeMessage() {
            let id = rcmail.vacation_messages_list.get_single_selection();

            if (id !== null) {
                rcmail.http_post('plugin.rubik_filter_remove_message', {
                    message_id: id
                });
            }
        }

        rcmail.register_command('add', addMessage, true);
        rcmail.register_command('remove', removeMessage, false);
    }

    if (rcmail.env.action === "plugin.rubik_filter_edit_message"
        || rcmail.env.action === "plugin.rubik_filter_new_message") {

        rcmail.gui_objects.msg_filename = $("#message-form input[name=vacation-name]");
        rcmail.gui_objects.msg_text = $("#message-form textarea[name=vacation-message]");

        if ('vacation_message' in rcmail.env && 'message_filename' in rcmail.env) {
            rcmail.gui_objects.msg_filename.val(rcmail.env.message_filename);
            rcmail.gui_objects.msg_text.val(rcmail.env.vacation_message);
        }

        function saveMessage() {
            let arg = {
                message_filename: rcmail.gui_objects.msg_filename.val(),
                message_text: rcmail.gui_objects.msg_text.val()
            };

            if ('message_filename' in rcmail.env) {
                arg.message_filename_original = rcmail.env.message_filename;
            }

            rcmail.http_post('plugin.rubik_filter_save_message', arg, true);
        }

        rcmail.register_command('save_message', saveMessage, true);
    }
});