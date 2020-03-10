rcmail.addEventListener('init', function() {
    if (!('_rubik_entity_type' in rcmail.env)) {
        return;
    }

    const gui = rcmail.gui_objects;
    const env = rcmail.env;

    function RubikData(id = null) {
        this._rubik_entity_type = env._rubik_entity_type;

        if (id === null && '_rubik_entity_id' in env) {
            this._rubik_entity_id = env._rubik_entity_id;
        } else {
            this._rubik_entity_id = id;
        }
    }

    if ('rubik_entity_list' in gui) {
        env.rubik_entity_list = new rcube_list_widget(gui.rubik_entity_list,
            {multiselect:false, draggable:true, keyboard:true, checkbox_selection: true});

        env.rubik_entity_list
            .addEventListener('dragstart', listDragStart)
            .addEventListener('dragend', listDragEnd)
            .addEventListener('select', function(list) {
                const id = list.get_single_selection();

                if (id !== null) {
                    loadContent(id, list);
                }
            })
            .init()
            .focus();

        function inEntityList(el) {
            return $(gui.rubik_entity_list).has(el).length > 0;
        }

        function listDragStart() {
            env.rubik_drag_start = env.rubik_entity_list.get_single_selection();
        }

        function listDragEnd(e) {
            if (env.rubik_drag_start === null) {
                return;
            }

            const target = $(e.target);

            if (inEntityList(target)) { // target is in filter list
                const targetId = env.rubik_entity_list.get_row_uid(target.parent());
                const sourceId = env.rubik_drag_start;

                if (targetId !== sourceId) {
                    swapEntityPosition(sourceId, targetId);
                }
            }

            env.rubik_drag_start = null;
        }

        function toggleEntity(el) {
            if (inEntityList(el)) {
                const id = env.rubik_entity_list.get_row_uid($(el).parents("tr")[0]);

                if (id != null) {
                    rcmail.http_post("plugin.rubik_toggle_entity_enabled", new RubikData(id), true);
                }
            }
        }

        function loadContent(id) {
            const contentWindow = rcmail.get_frame_window(env.contentframe);
            if (!contentWindow) {
                return;
            }

            const data = new RubikData();

            data._framed = "1";
            data._action = "plugin.rubik_show_entity_detail";
            data._rubik_entity_id = id;

            rcmail.enable_command('remove', id !== null);
            rcmail.location_href(data, contentWindow, true);
        }

        function swapEntityPosition(id1, id2) {
            rcmail.http_post('plugin.rubik_swap_filters', { filter_swap_id1: id1, filter_swap_id2: id2 }, true);
        }

        function addEntity() {
            loadContent(null);
        }

        function removeEntity() {
            const id = env.rubik_entity_list.get_single_selection();

            if (id !== null) {
                rcmail.http_post("plugin.rubik_remove_entity", new RubikData(id));
            }
        }

        rcmail.register_command('toggle_enabled', toggleEntity, true);
        rcmail.register_command('add', addEntity, true);
        rcmail.register_command('remove', removeEntity, false);
    }

    if (env.action === "plugin.rubik_settings_replies") {
        env.rubik_entity_list.draggable = false;
        env.rubik_entity_list.checkbox_selection = false;
    }

    if (env.action === "plugin.rubik_show_entity_detail") {
        switch (env._rubik_entity_type) {
            case "rubik_filter":
                initFilterForm();
                break;
            case "rubik_vacation":
                initVacationForm();
                break;
            case "rubik_reply":
                initReplyForm();
                break;
        }
    }

    function save(data) {
        rcmail.http_post('plugin.rubik_save_entity', data, true);
    }

    function initFilterForm() {
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

        rcmail.addEventListener('plugin.rubik_filter_save_result', saveCallback);

        $('#rubik-save-rule').click(saveFilter);

        if ('rubik_filter' in env) {
            let filter = env.rubik_filter;

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

    function initVacationForm() {

        gui.vacation_start = $("#vacation-date-wrapper input[name=date-start]");
        gui.vacation_end = $("#vacation-date-wrapper input[name=date-end]");
        gui.vacation_name = $("#vacation-form input[name=vacation-name]");
        gui.vacation_selected_message = $("#vacation-message-wrapper select[name=vacation-select]");
        gui.vacation_message = $("#vacation-message-wrapper textarea[name=vacation-message]");

        function saveVacation() {
            const vacation = {
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

        gui.vacation_selected_message.on('change', function () {
            gui.vacation_message.prop('disabled', true);
            getVacationMessage(this.value);
        });

        rcmail.register_command('save_vacation', function() {saveVacation();}, true);
        rcmail.addEventListener('plugin.rubik_filter_set_message', onVacationMessageReceived);

        // load first message in select
        getVacationMessage(gui.vacation_selected_message.val());
    }

    function initReplyForm() {
        gui.rubik_reply_filename = $("#message-form input[name=reply-filename]");
        gui.rubik_reply_text = $("#message-form textarea[name=reply-text]");

        if ('rubik_reply_filename' in env && 'rubik_reply_text' in env) {
            gui.rubik_reply_filename.val(env.rubik_reply_filename);
            gui.rubik_reply_text.val(env.rubik_reply_text);
        }

        function saveMessage() {
            const data = new RubikData();

            data.rubik_reply_filename = ui.rubik_reply_filename.val();
            data.rubik_reply_text = gui.rubik_reply_text.val();

            save(data);
        }

        rcmail.register_command('save_message', saveMessage, true);
    }
});