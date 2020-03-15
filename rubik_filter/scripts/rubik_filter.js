rcmail.addEventListener('init', function() {
    if (!('_rubik_entity_type' in rcmail.env)) {
        return;
    }

    const gui = rcmail.gui_objects;
    const env = rcmail.env;
    let loadingMessageId = null;

    function RubikData(id = null) {
        this._rubik_entity_type = env._rubik_entity_type;

        if (id === null && '_rubik_entity_id' in env) {
            this._rubik_entity_id = env._rubik_entity_id;
        } else {
            this._rubik_entity_id = id;
        }
    }

    /**
     * Show loading message to user.
     */
    function showLoading() {
       if (loadingMessageId === null) {
           loadingMessageId = rcmail.display_message('loading', 'loading');
       }
    }

    /**
     * Hide the loading message.
     */
    function hideLoading() {
        if (loadingMessageId !== null) {
            rcmail.hide_message(loadingMessageId);
            loadingMessageId = null;
        }
    }
    rcmail.addEventListener('plugin.rubik_hide_loading', hideLoading);

    // show list of entities
    if ('rubik_entity_list' in gui) {
        env.rubik_entity_list = new rcube_list_widget(gui.rubik_entity_list,
            {multiselect:false, draggable:true, keyboard:true, checkbox_selection: false});

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
                    showLoading();
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
            const data = new RubikData();

            data.filter_swap_id1 = id1;
            data.filter_swap_id2 = id2;


            showLoading();
            rcmail.http_post('plugin.rubik_swap_filters', data, true);
        }

        function addNewEntity() {
            env.rubik_entity_list.clear_selection();

            loadContent(null);
        }

        function removeEntity() {
            const id = env.rubik_entity_list.get_single_selection();

            if (id !== null) {
                showLoading();
                rcmail.http_post("plugin.rubik_remove_entity", new RubikData(id));
            }
        }

        rcmail.register_command('toggle_enabled', toggleEntity, true);
        rcmail.register_command('add', addNewEntity, true);
        rcmail.register_command('remove', removeEntity, false);
    }

    // reply list specific settings
    if (env.action === "plugin.rubik_settings_replies") {
        env.rubik_entity_list.draggable = false;
        env.rubik_entity_list.checkbox_selection = false;
    }

    // init entity details form
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

    /**
     * Save entity details.
     * @param data
     */
    function save(data) {

        data = {...new RubikData(), ...data};

        showLoading();
        rcmail.http_post('plugin.rubik_save_entity', data, true);
    }

    /**
     * Initialize UI for filter form.
     */
    function initFilterForm() {
        // invisible template rows added to form tables
        const filter_condition_template_row = $(gui.rubik_condition_template);
        const filter_action_template_row = $(gui.rubik_action_template);

        // form lists
        const condition_list = $(gui.rubik_condition_list);
        const action_list = $(gui.rubik_action_list);
        const condition_block_type_input = $(gui.rubik_condition_type_input);
        const filter_name = $(gui.rubik_name_input);

        // make lists items draggable
        const sortableOptions = {
            animation: '100',
            chosenClass: 'dragged',
            handle: '.rubik-handle'
        };
        new Sortable(condition_list[0], sortableOptions);
        new Sortable(action_list[0], sortableOptions);

        // condition table handling
        function addConditionRow(field = null, op = null, condVal = null) {
            const new_row = filter_condition_template_row.clone(true);

            new_row.attr('id', null);
            new_row.attr('class', 'rubik-filter-condition-row');

            if (field != null) { new_row.find(':input[name=field]').val(field); }
            if (op != null) { new_row.find(':input[name=operator]').val(op); }
            if (condVal != null) { new_row.find(':input[name=condition_value]').val(condVal); }

            new_row.find('.rubik-controls .delete').click(function() {
                new_row.remove();
            });

            condition_list.append(new_row);
        }

        // action table handling
        function addActionRow(defAction = null, defVal = null) {
            const new_row = filter_action_template_row.clone(true);

            new_row.attr('id', null);
            new_row.attr('class', 'rubik-filter-action-row');

            new_row.find('.rubik-controls .delete').click(function() {
                new_row.remove();

                updateAvailableActions();
            });

            const value_input = new_row.find(':input[name=action-value]');
            const action_select = new_row.find(':input[name=action]');

            // make discard option exclusive

            action_select.change(function() {
                const selectedValue = $(this).find('option:selected').val();

                if (selectedValue === '_discard') {
                    value_input.addClass('hidden');
                } else {
                    value_input.removeClass('hidden');
                }

                updateAvailableActions();
            });

            action_list.append(new_row);

            if (defAction !== null) {
                action_select.val(defAction);
            }

            if (defVal !== null) {
                value_input.val(defVal);
            }

            updateAvailableActions();
        }

        function updateAvailableActions() {
            const inputs = action_list.find(':input[name=action]');
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

            rcmail.enable_command('add_action', discardIndex < 0);
        }

        // plugin commands
        function saveFilter() {
            const filter = {
                filter_name: filter_name.val(),
                filter_conditions: [],
                filter_actions: []
            };

            condition_list.find('.rubik-filter-condition-row').each(function(key, row) {
                const cond = {
                    field: $(row).find(':input[name=field]').val(),
                    op: $(row).find(':input[name=operator]').val(),
                    val: $(row).find(':input[name=condition_value]').val()
                };

                filter.filter_conditions.push(cond);
            });

            action_list.find('.rubik-filter-action-row').each(function(key, row) {
               let action = {
                   action: $(row).find(':input[name=action]').val(),
                   val:$(row).find(':input[name=action-value]').val()
               };

               filter.filter_actions.push(action);
            });

            filter.filter_conditions_type = condition_block_type_input.val();

            save(filter);
        }

        rcmail.register_command('save_filter', saveFilter, true);
        rcmail.register_command('add_condition', () => addConditionRow(), true);
        rcmail.register_command('add_action', () => addActionRow(), true);

        if ('rubik_filter' in env) {
            const filter = env.rubik_filter;

            filter.actions.forEach(action => addActionRow(action.action, action.val));

            filter.conditions.forEach(condition => addConditionRow(condition.field, condition.op, condition.val));

            condition_block_type_input.val(filter.type);
            filter_name.val(filter.name);
        }
    }

    /**
     * Initialize UI for vacation form.
     */
    function initVacationForm() {
        gui.vacation_start = $("#vacation-form input[name=date-start]");
        gui.vacation_end = $("#vacation-form input[name=date-end]");
        gui.vacation_name = $("#vacation-form input[name=vacation-name]");
        gui.vacation_selected_reply = $("#vacation-form select[name=vacation-select]");
        gui.vacation_reply = $("#vacation-form textarea[name=vacation-message]");

        /**
         * Save vacation.
         */
        function saveVacation() {

            const vacation = {
                vacation_start: gui.vacation_start.val(),
                vacation_end: gui.vacation_end.val(),
                vacation_name: gui.vacation_name.val(),
                vacation_selected_reply: gui.vacation_selected_reply.val()
            };

            save(vacation);
        }

        /**
         * Retrieve vacation reply text through ajax.
         * @param filename reply filename
         */
        function getVacationReply(filename) {
            if (filename) {
                gui.vacation_reply.val('...');

                showLoading();

                rcmail.http_post('plugin.rubik_get_reply', {reply_filename: filename}, true);
            }
        }

        /**
         * Callback for vacation reply text ajax response.
         * @param data
         */
        function onVacationReplyMessageReceived(data) {
            if ('reply_text' in data) {
                hideLoading();
                gui.vacation_reply.val(data.reply_text);
            }
        }

        // init select with available replies
        if ('rubik_reply_options' in env) {
            env.rubik_reply_options.forEach(opt => {
                gui.vacation_selected_reply.append($("<option>").attr('value', opt).text(opt))
            });
        }

        // fill edit data if any
        if ('rubik_vacation' in env) {
            const vacation = env.rubik_vacation;

            gui.vacation_selected_reply.val(vacation.vacation_reply);
            gui.vacation_name.val(vacation.vacation_name);
            gui.vacation_start.val(vacation.vacation_start);
            gui.vacation_end.val(vacation.vacation_end);
        }

        gui.vacation_selected_reply.on('change', function () {
            getVacationReply(this.value);
        });

        rcmail.register_command('save_vacation', saveVacation, true);
        rcmail.addEventListener('plugin.rubik_set_reply', onVacationReplyMessageReceived);

        // load first message in select input
        getVacationReply(gui.vacation_selected_reply.val());
    }

    /**
     * Initialize UI for vacation reply form.
     */
    function initReplyForm() {
        gui.rubik_reply_filename = $("#message-form input[name=reply-filename]");
        gui.rubik_reply_text = $("#message-form textarea[name=reply-text]");

        // fill edit data if any
        if ('rubik_reply' in env) {
            const reply = env.rubik_reply;

            gui.rubik_reply_filename.val(reply.rubik_reply_filename);
            gui.rubik_reply_text.val(reply.rubik_reply_text);
        }

        /**
         *  Save reply data.
         */
        function saveReply() {
            const reply = {
                rubik_reply_filename: gui.rubik_reply_filename.val(),
                rubik_reply_text: gui.rubik_reply_text.val()
            };

            save(reply);
        }

        rcmail.register_command('save_message', saveReply, true);
    }
});