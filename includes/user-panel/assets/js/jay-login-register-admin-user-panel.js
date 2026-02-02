jQuery(document).ready(function($) {
    'use strict';
    // منطق نمایش سوییچ‌های خطی (Inline Toggles)
    $(document).on('change', '.jay-main-trigger', function() {
        const wrapper = $(this).closest('.jay-setting-wrapper');
        const subs = wrapper.find('.jay-inline-subs');
        
        if ($(this).is(':checked')) {
            subs.addClass('active');
        } else {
            subs.removeClass('active');
        }
    });
    
   // =============================================
    // 1. مدیریت نمایش وابستگی‌ها (Logic Visibility)
    // =============================================
    const menuCheckbox = $('input[name="jay_login_register_user_panel_settings[enable_custom_menus]"]');
    const menuWrapper = $('#jay_up_menus_wrapper');
    menuCheckbox.on('change', function() {
        if($(this).is(':checked')) menuWrapper.slideDown();
        else menuWrapper.slideUp();
    });
    // الف) لاجیک "ویرایش مشخصات" -> نمایش زیرمجموعه‌ها (نام، کدملی، گذرنامه)
    const profileCheckbox = $('input[name="jay_login_register_user_panel_settings[enable_profile_info]"]');
    const profileSubRows = $('tr.jay-profile-sub');

    function toggleProfileSubs() {
        if (profileCheckbox.is(':checked')) {
            profileSubRows.fadeIn(200);
        } else {
            profileSubRows.hide();
        }
    }
    if (profileCheckbox.length) {
        toggleProfileSubs();
        profileCheckbox.on('change', toggleProfileSubs);
    }

    // ب) لاجیک نمایش سوییچ‌های خطی (وقتی تیک اصلی یک ردیف زده می‌شود)
    $(document).on('change', '.jay-main-trigger', function() {
        const wrapper = $(this).closest('.jay-setting-wrapper');
        const subs = wrapper.find('.jay-inline-subs');
        
        if ($(this).is(':checked')) {
            subs.addClass('active');
        } else {
            subs.removeClass('active');
        }
    });

    // ==========================================
    // 2. فیلد ساز پیشرفته پنل کاربری (نسخه شرطی هوشمند)
    // ==========================================
    
    const upFieldsList = $('#jay_login_register_up_fields_list');
    const upJsonInput  = $('#jay_login_register_up_json_input');
    const upAddBtn     = $('#jay_login_register_up_add_btn');

    /**
     * تابع کمکی: دریافت تمام فیلدهای موجود در صفحه برای پر کردن لیست شرط‌ها
     */
    function getAllAvailableFieldsForLogic(excludeId = null) {
        let fields = [];
        $('.jay-login-register-up-card').each(function() {
            const id = $(this).data('id');
            if (excludeId && id == excludeId) return;

            const label = $(this).find('.jay-login-register-up-label').val();
            const key   = $(this).find('.jay-login-register-up-key').val();
            const type  = $(this).find('.jay-login-register-up-type').val();
            
            // جمع‌آوری آپشن‌ها برای فیلدهای انتخابی
            let options = [];
            if (['select', 'radio', 'checkbox'].includes(type)) {
                $(this).find('.jay-login-register-up-option-row').each(function() {
                    const val = $(this).find('.jay-login-register-up-opt-value').val();
                    const lbl = $(this).find('.jay-login-register-up-opt-label').val();
                    if(val) options.push({value: val, label: lbl});
                });
            }
            if (key) {
                fields.push({ id, label, key, type, options });
            }
        });
        return fields;
    }
    /**
     * رندر کردن یک ردیف شرط (Field Logic Row)
     */
    function getFieldLogicRowHTML(rule = {}, availableFields) {
        let fieldOptionsHTML = '<option value="">انتخاب فیلد...</option>';
        
        availableFields.forEach(f => {
            const selected = (rule.target === f.key) ? 'selected' : '';
            fieldOptionsHTML += `<option value="${f.key}" data-type="${f.type}" data-options='${JSON.stringify(f.options)}' ${selected}>${f.label || f.key}</option>`;
        });

        // تصمیم‌گیری برای فیلد مقدار (Value Input)
        let valueInputHTML = `<input type="text" class="jay-logic-rule-value" placeholder="مقدار" value="${rule.value || ''}" style="flex:1;">`;
        
        return `
            <div class="jay-logic-rule-row" style="display:flex; gap:5px; margin-bottom:5px; align-items:center;">
                <select class="jay-logic-rule-target" style="flex:1;">
                    ${fieldOptionsHTML}
                </select>
                
                <select class="jay-logic-rule-operator" style="width:100px;">
                    <option value="=" ${rule.operator === '=' ? 'selected' : ''}>هست</option>
                    <option value="!=" ${rule.operator === '!=' ? 'selected' : ''}>نیست</option>
                </select>
                
                <div class="jay-logic-value-wrapper" style="flex:1; display:flex;">
                    ${valueInputHTML}
                </div>
                
                <span class="dashicons dashicons-no-alt jay-remove-logic-rule" style="cursor:pointer; color:#d63638;" title="حذف شرط"></span>
            </div>
        `;
    }

    /**
     * رندر کردن یک ردیف شرط متاکی (Meta Key Logic Row)
     */
    function getMetaLogicRowHTML(metaKey = '') {
        return `
            <div class="jay-meta-logic-row" style="display:flex; gap:10px; align-items:center; margin-bottom:5px;">
                <input type="text" class="jay-meta-logic-key" placeholder="نام متاکی (مثال: is_vendor)" value="${metaKey}" style="flex:1; direction:ltr; text-align:left;">
                <span class="dashicons dashicons-no-alt jay-remove-meta-logic" style="cursor:pointer; color:#d63638;" title="حذف"></span>
            </div>
        `;
    }
    // 1. تابع اصلی ساخت HTML یک فیلد
    function renderUpFieldItem(data = {}) {
        const fieldId = Date.now() + Math.floor(Math.random() * 1000);
        const label = data.label || '';
        const key    = data.key || '';
        const type   = data.type || 'text';
        
        const isJalali = data.is_jalali ? 'checked' : '';
        const isRequired = data.is_required ? 'checked' : '';
        // تنظیمات فیلد شماره
        const numberLen = data.number_len || ''; 
        const numberStart = data.number_start || '';
        const hasLen = numberLen ? 'checked' : '';
        const hasStart = numberStart ? 'checked' : '';
        // ساخت HTML گزینه‌های فیلد (آپشن‌ها)
        let optionsHtml = '';
        if (data.options && Array.isArray(data.options)) {
            data.options.forEach(opt => {
                optionsHtml += `
                    <div class="jay-login-register-up-option-row" style="display:flex; gap:10px; align-items:center; margin-bottom:5px;">
                        <input type="text" class="jay-login-register-up-opt-label" placeholder="عنوان گزینه" value="${opt.label}" style="flex:1;">
                        <input type="text" class="jay-login-register-up-opt-value" placeholder="مقدار (Value)" value="${opt.value}" style="flex:1;">
                        <span class="dashicons dashicons-no-alt jay-login-register-up-remove-opt-btn" style="cursor:pointer; color:#888;" title="حذف گزینه"></span>
                    </div>`;
            });
        }

        // --- آماده‌سازی بخش منطق شرطی ---
        // الف) متاکی‌ها
        let metaLogicRows = '';
        if (data.logic_meta_rules && Array.isArray(data.logic_meta_rules)) {
            data.logic_meta_rules.forEach(mKey => {
                metaLogicRows += getMetaLogicRowHTML(mKey);
            });
        }
        // ب) فیلدها
        let fieldLogicRows = ''; 
        const description = data.description || '';
        const html = `
            <div class="jay-login-register-up-card" data-id="${fieldId}">
                
                <div class="jay-login-register-up-header jay-accordion-toggle" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <span class="dashicons dashicons-arrow-down-alt2 jay-accordion-icon" style="margin-left:5px;"></span>
                        <strong class="jay-field-header-title">${label ? label : 'تنظیمات فیلد (جدید)'}</strong>
                    </div>
                    <span class="dashicons dashicons-trash jay-login-register-up-remove-btn" title="حذف فیلد"></span>
                </div>
                <div class="jay-login-register-up-body" style="display: none;">
                    <div class="jay-login-register-up-row">
                        <input type="text" class="jay-login-register-up-label widefat" placeholder="عنوان نمایشی (مثال: تاریخ تولد)" value="${label}">
                        <input type="text" class="jay-login-register-up-key widefat" placeholder="کلید متا (انگلیسی - مثال: birth_date)" value="${key}">
                    </div>

                    <div class="jay-login-register-up-row">
                        <input type="text" class="jay-login-register-up-description widefat" placeholder="توضیحات راهنما (زیر فیلد نمایش داده می‌شود)" value="${description}">
                        <p class="description" style="margin:2px 0 0; font-size:11px;">فقط حروف مجاز است (کاراکترهای خاص حذف می‌شوند).</p>
                    </div>

                    <div class="jay-login-register-up-row">
                        <select class="jay-login-register-up-type widefat">
                            <option value="text" ${type === 'text' ? 'selected' : ''}>متن (Text)</option>
                            <option value="select" ${type === 'select' ? 'selected' : ''}>لیست بازشو (Select)</option>
                            <option value="radio" ${type === 'radio' ? 'selected' : ''}>رادیو باتن (Radio)</option>
                            <option value="checkbox" ${type === 'checkbox' ? 'selected' : ''}>چک‌باکس (Checkbox)</option>
                            <option value="date" ${type === 'date' ? 'selected' : ''}>تاریخ (Date)</option>
                            <option value="number" ${type === 'number' ? 'selected' : ''}>شماره (Number)</option>
                            <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>پاراگراف (Textarea)</option>
                        </select>
                    </div>

                    <div class="jay-login-register-up-row" style="background:#fff8e5; padding:10px; border:1px solid #eee; border-radius:4px; margin-bottom:10px;">
                        <label>
                            <input type="checkbox" class="jay-login-register-up-required" value="1" ${isRequired}> 
                            <strong>این فیلد ضروری است </strong>
                        </label>
                    </div>

                    <div class="jay-login-register-up-date-options" style="display: ${type === 'date' ? 'block' : 'none'};">
                        <label>
                            <input type="checkbox" class="jay-login-register-up-jalali" value="1" ${isJalali}> 
                            استفاده از تقویم شمسی (Jalali)
                        </label>
                    </div>
                    <div class="jay-login-register-up-number-options" style="background:#f0f6fc; padding:10px; border-radius:4px; margin-bottom:10px; border:1px solid #cce5ff; display: ${type === 'number' ? 'block' : 'none'};">
                        <div style="margin-bottom:8px;">
                            <label style="display:flex; align-items:center; gap:5px;">
                                <input type="checkbox" class="jay-gf-has-len" ${hasLen}> 
                                محدودیت تعداد ارقام
                            </label>
                            <input type="number" class="jay-gf-number-len small-text" placeholder="مثال: 11" value="${numberLen}" style="margin-top:5px; display:${hasLen ? 'block' : 'none'}; width:100%;">
                        </div>
                        
                        <div>
                            <label style="display:flex; align-items:center; gap:5px;">
                                <input type="checkbox" class="jay-gf-has-start" ${hasStart}> 
                                شروع شود با...
                            </label>
                            <input type="text" class="jay-gf-number-start small-text" placeholder="مثال: 09" value="${numberStart}" style="margin-top:5px; display:${hasStart ? 'block' : 'none'}; width:100%; direction:ltr; text-align:left;">
                        </div>
                    </div>
                    <div class="jay-login-register-up-options-wrapper" style="display: ${['select', 'radio', 'checkbox'].includes(type) ? 'block' : 'none'};">
                        <p style="margin:0 0 10px;">گزینه‌ها:</p>
                        <div class="jay-login-register-up-options-list">
                            ${optionsHtml}
                        </div>
                        <button type="button" class="button-link jay-login-register-up-add-opt-btn" style="margin-top:10px;">+ افزودن گزینه</button>
                    </div>

                    <div class="jay-login-register-up-row" style="margin-top:15px; border-top:1px dashed #ccc; padding-top:10px;">
                        <button type="button" class="button button-small jay-up-toggle-logic">تنظیمات پیشرفته / شرطی</button>
                        
                        <div class="jay-up-logic-container" style="display:none; background:#f9f9f9; padding:15px; margin-top:5px; border:1px solid #e5e5e5; border-radius:4px;">
                            
                            <div class="jay-logic-section">
                                <p><strong>۱. شرط نمایش بر اساس متاکی (Server Side):</strong></p>
                                <p class="description">نمایش فیلد تنها در صورت وجود متاکی‌های زیر:</p>
                                
                                <div style="margin-bottom:10px;">
                                    <label>منطق:</label>
                                    <select class="jay-up-logic-meta-relation">
                                        <option value="AND" ${data.logic_meta_relation === 'AND' ? 'selected' : ''}>همه (AND)</option>
                                        <option value="OR" ${data.logic_meta_relation === 'OR' ? 'selected' : ''}>حداقل یکی (OR)</option>
                                    </select>
                                </div>

                                <div class="jay-meta-logic-list">
                                    ${metaLogicRows}
                                </div>
                                <button type="button" class="button-link jay-add-meta-logic">+ افزودن متاکی</button>
                            </div>

                            <hr style="margin:15px 0; border-bottom:1px solid #ddd;width: 100%;">

                            <div class="jay-logic-section">
                                <p><strong>۲. شرط نمایش بر اساس فیلد دیگر (Client Side):</strong></p>
                                <div style="margin-bottom:10px;">
                                    <label>اقدام:</label> <strong>نمایش</strong> فیلد اگر
                                    <select class="jay-up-logic-field-relation">
                                        <option value="AND" ${data.logic_field_relation === 'AND' ? 'selected' : ''}>همه (AND)</option>
                                        <option value="OR" ${data.logic_field_relation === 'OR' ? 'selected' : ''}>حداقل یکی (OR)</option>
                                    </select>
                                    شرط‌های زیر برقرار باشند:
                                </div>

                                <div class="jay-field-logic-list">
                                    </div>
                                <button type="button" class="button-link jay-add-field-logic">+ افزودن شرط</button>
                            </div>

                        </div>
                    </div>
                    </div>
            </div>
        `;

        const newItem = $(html);
        upFieldsList.append(newItem);

        // پر کردن ردیف‌های شرط فیلد (چون نیاز به محاسبه فیلدهای موجود دارد)
        const logicList = newItem.find('.jay-field-logic-list');
        const availableFields = getAllAvailableFieldsForLogic(fieldId);
        
        if (data.logic_field_rules && Array.isArray(data.logic_field_rules)) {
            data.logic_field_rules.forEach(rule => {
                const rowHTML = getFieldLogicRowHTML(rule, availableFields);
                logicList.append(rowHTML);
                // تریگر کردن تغییر برای نمایش صحیح اینپوت مقدار
                const lastRow = logicList.find('.jay-logic-rule-row').last();
                refreshLogicValueInput(lastRow, rule.value);
            });
        }
    }
    /**
     * تابع به‌روزرسانی اینپوت "مقدار" در شرط‌ها بر اساس نوع فیلد هدف
     */
    function refreshLogicValueInput(row, currentValue = '') {
        const targetSelect = row.find('.jay-logic-rule-target');
        const selectedOption = targetSelect.find('option:selected');
        const wrapper = row.find('.jay-logic-value-wrapper');
        
        // اگر هیچ فیلدی انتخاب نشده باشد
        if (!selectedOption.val()) {
            wrapper.html(`<input type="text" class="jay-logic-rule-value" placeholder="مقدار" value="${currentValue}" style="flex:1;">`);
            return;
        }

        const type = selectedOption.data('type');
        const optionsData = selectedOption.data('options');

        if (['select', 'radio', 'checkbox'].includes(type) && optionsData && optionsData.length > 0) {
            let selectHTML = `<select class="jay-logic-rule-value" style="flex:1;">`;
            optionsData.forEach(opt => {
                const isSel = (opt.value == currentValue) ? 'selected' : '';
                selectHTML += `<option value="${opt.value}" ${isSel}>${opt.label}</option>`;
            });
            selectHTML += `</select>`;
            wrapper.html(selectHTML);
        } else {
            // برای فیلدهای متنی یا تاریخ
            wrapper.html(`<input type="text" class="jay-logic-rule-value" placeholder="مقدار" value="${currentValue}" style="flex:1;">`);
        }
    }

    // --- رویدادهای فیلد ساز ---
    if (upAddBtn.length) {
        upAddBtn.on('click', function() {
            renderUpFieldItem(); 
        });
    }
    upFieldsList.on('click', '.jay-login-register-up-remove-btn', function() {
        if(confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
            $(this).closest('.jay-login-register-up-card').remove();
            updateUpJson();
        }
    });

    upFieldsList.on('change', '.jay-login-register-up-type', function() {
        const type = $(this).val();
        const wrapper = $(this).closest('.jay-login-register-up-body');
        const dateOpt = wrapper.find('.jay-login-register-up-date-options');
        const optsWrap = wrapper.find('.jay-login-register-up-options-wrapper');

        if (type === 'date') {
            dateOpt.slideDown(200);
            optsWrap.slideUp(200);
        } else if (['select', 'radio', 'checkbox'].includes(type)) {
            dateOpt.slideUp(200);
            optsWrap.slideDown(200);
        } else {
            dateOpt.slideUp(200);
            optsWrap.slideUp(200);
        }
        updateUpJson();
    });

    // مدیریت گزینه‌های فیلد اصلی
    upFieldsList.on('click', '.jay-login-register-up-add-opt-btn', function() {
        const row = `
            <div class="jay-login-register-up-option-row" style="display:flex; gap:10px; align-items:center; margin-bottom:5px;">
                <input type="text" class="jay-login-register-up-opt-label" placeholder="عنوان گزینه" style="flex:1;">
                <input type="text" class="jay-login-register-up-opt-value" placeholder="مقدار (Value)" style="flex:1;">
                <span class="dashicons dashicons-no-alt jay-login-register-up-remove-opt-btn" style="cursor:pointer; color:#888;" title="حذف گزینه"></span>
            </div>`;
        $(this).siblings('.jay-login-register-up-options-list').append(row);
    });
    upFieldsList.on('click', '.jay-login-register-up-remove-opt-btn', function() {
        $(this).closest('.jay-login-register-up-option-row').remove();
        updateUpJson();
    });
    // باز/بسته کردن پنل شرطی
    upFieldsList.on('click', '.jay-up-toggle-logic', function() {
        const container = $(this).next('.jay-up-logic-container');
        
        // هنگام باز کردن، لیست فیلدهای هدف را به‌روزرسانی می‌کنیم
        if (container.is(':hidden')) {
            const currentId = $(this).closest('.jay-login-register-up-card').data('id');
            const fields = getAllAvailableFieldsForLogic(currentId);
            
            // آپدیت کردن تمام سلکت‌های موجود در این بخش
            container.find('.jay-logic-rule-target').each(function() {
                const currentVal = $(this).val();
                let opts = '<option value="">انتخاب فیلد...</option>';
                fields.forEach(f => {
                    const sel = (f.key === currentVal) ? 'selected' : '';
                    opts += `<option value="${f.key}" data-type="${f.type}" data-options='${JSON.stringify(f.options)}' ${sel}>${f.label || f.key}</option>`;
                });
                $(this).html(opts);
            });
        }
        
        container.slideToggle(200);
    });

    // افزودن شرط متاکی
    upFieldsList.on('click', '.jay-add-meta-logic', function() {
        $(this).siblings('.jay-meta-logic-list').append(getMetaLogicRowHTML());
    });
    upFieldsList.on('click', '.jay-remove-meta-logic', function() {
        $(this).closest('.jay-meta-logic-row').remove();
        updateUpJson();
    });

    // افزودن شرط فیلد
    upFieldsList.on('click', '.jay-add-field-logic', function() {
        const currentId = $(this).closest('.jay-login-register-up-card').data('id');
        const fields = getAllAvailableFieldsForLogic(currentId);
        const row = getFieldLogicRowHTML({}, fields);
        $(this).siblings('.jay-field-logic-list').append(row);
    });
    upFieldsList.on('click', '.jay-remove-logic-rule', function() {
        $(this).closest('.jay-logic-rule-row').remove();
        updateUpJson();
    });

    // تغییر فیلد هدف -> تغییر ورودی مقدار
    upFieldsList.on('change', '.jay-logic-rule-target', function() {
        const row = $(this).closest('.jay-logic-rule-row');
        refreshLogicValueInput(row); // مقدار خالی شود
        updateUpJson();
    });

    // ذخیره خودکار
    upFieldsList.on('change input', 'input, select', function() {
        // جلوگیری از لوپ بی نهایت در برخی مرورگرها
        if (!$(this).hasClass('jay-login-register-up-json-input')) {
            updateUpJson();
        }
    });

    $('form').on('submit', function() {
        updateUpJson();
        updateMenusJson();
    });

    // تابع نهایی ساخت JSON
    function updateUpJson() {
        let fieldsData = [];
        
        upFieldsList.find('.jay-login-register-up-card').each(function() {
            const item = $(this);
            const type = item.find('.jay-login-register-up-type').val();
            
            let field = {
                label: item.find('.jay-login-register-up-label').val(),
                key: item.find('.jay-login-register-up-key').val(),
                description: item.find('.jay-login-register-up-description').val(),
                type: type,
                is_required: item.find('.jay-login-register-up-required').is(':checked') ? 1 : 0, 
                options: [],
                
                // تنظیمات شرطی
                logic_meta_relation: item.find('.jay-up-logic-meta-relation').val(),
                logic_meta_rules: [],

                logic_field_relation: item.find('.jay-up-logic-field-relation').val(),
                logic_field_rules: [],
            };

            // ذخیره آپشن‌ها
            if (['select', 'radio', 'checkbox'].includes(type)) {
                item.find('.jay-login-register-up-option-row').each(function() {
                    const l = $(this).find('.jay-login-register-up-opt-label').val();
                    const v = $(this).find('.jay-login-register-up-opt-value').val();
                    if(l && v) field.options.push({ label: l, value: v });
                });
            }
            if (type === 'date') {
                field.is_jalali = item.find('.jay-login-register-up-jalali').is(':checked') ? 1 : 0;
            }
            // ذخیره تنظیمات شماره
            if (type === 'number') {
                // اگر تیک خورده باشد مقدار را ذخیره کن، وگرنه خالی
                if (item.find('.jay-gf-has-len').is(':checked')) {
                    field.number_len = item.find('.jay-gf-number-len').val();
                }
                if (item.find('.jay-gf-has-start').is(':checked')) {
                    field.number_start = item.find('.jay-gf-number-start').val();
                }
            }
            // ذخیره قوانین متاکی
            item.find('.jay-meta-logic-key').each(function() {
                const val = $(this).val();
                if(val) field.logic_meta_rules.push(val);
            });

            // ذخیره قوانین فیلد
            item.find('.jay-logic-rule-row').each(function() {
                const target = $(this).find('.jay-logic-rule-target').val();
                const operator = $(this).find('.jay-logic-rule-operator').val();
                const value = $(this).find('.jay-logic-rule-value').val();
                
                if (target) {
                    field.logic_field_rules.push({ target, operator, value });
                }
            });

            if (field.key && field.key.trim() !== '') { 
                fieldsData.push(field);
            }
        });

        upJsonInput.val(JSON.stringify(fieldsData));
    }

    // بارگذاری اولیه
    if (upJsonInput.length) {
        let savedData = [];
        try { savedData = JSON.parse(upJsonInput.val()); } catch(e) {}

        if (Array.isArray(savedData)) {
            savedData.forEach(function(item) {
                renderUpFieldItem(item);
            });
        }
    }

    // Drag & Drop
    if (upFieldsList.length) {
        upFieldsList.sortable({
            handle: '.jay-login-register-up-header',
            placeholder: 'jay-up-sortable-placeholder',
            axis: 'y',
            opacity: 0.8,
            update: function() { updateUpJson(); }
        });
    }

// --- 3. قابلیت‌های جدید UI (آکاردئون و سینک عنوان) ---

    // آکاردئون: باز و بسته کردن
    upFieldsList.on('click', '.jay-accordion-toggle', function(e) {
        // اگر روی دکمه حذف کلیک شده، باز و بسته نکن
        if ($(e.target).hasClass('jay-login-register-up-remove-btn')) return;

        const body = $(this).siblings('.jay-login-register-up-body');
        const icon = $(this).find('.jay-accordion-icon');
        
        body.slideToggle(200);
        
        if (icon.hasClass('dashicons-arrow-down-alt2')) {
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        } else {
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    // تغییر نام هدر همزمان با تایپ در فیلد عنوان
    upFieldsList.on('input', '.jay-login-register-up-label', function() {
        const val = $(this).val();
        const headerTitle = $(this).closest('.jay-login-register-up-card').find('.jay-field-header-title');
        headerTitle.text(val ? val : 'تنظیمات فیلد (بدون نام)');
    });

    // اعتبارسنجی فیلد توضیحات (فقط حروف و فاصله)
    upFieldsList.on('input', '.jay-login-register-up-description', function() {
        let val = $(this).val();
        let clean = val.replace(/[^a-zA-Z\u0600-\u06FF\s]/g, '');
        if (val !== clean) {
            $(this).val(clean);
        }
        updateUpJson();
    });
    
    // نمایش/مخفی کردن تنظیمات فیلد شماره
    upFieldsList.on('change', '.jay-gf-has-len', function() {
        $(this).closest('div').find('.jay-gf-number-len').slideToggle(200).focus();
        updateUpJson();
    });
    
    upFieldsList.on('change', '.jay-gf-has-start', function() {
        $(this).closest('div').find('.jay-gf-number-start').slideToggle(200).focus();
        updateUpJson();
    });

    // اضافه کردن شرط number به رویداد تغییر نوع (که قبلا داشتید)
    // این بخش را باید در کد قبلی "upFieldsList.on('change', '.jay-login-register-up-type'..." پیدا کنید و اصلاح کنید:
    
    /* توجه: کد قبلی تغییر نوع فیلد را پیدا کنید و این شرط را به آن اضافه کنید:
       else if (type === 'number') {
           dateOpt.slideUp();
           optsWrap.slideUp();
           // کلاس جدید را نمایش بده
           wrapper.find('.jay-login-register-up-number-options').slideDown(200);
       }
       
       یا اگر کد قبلی پیچیده است، این کد مستقل را اضافه کنید (کار می‌کند):
    */
    upFieldsList.on('change', '.jay-login-register-up-type', function() {
        const type = $(this).val();
        const wrapper = $(this).closest('.jay-login-register-up-body');
        const numOpt = wrapper.find('.jay-login-register-up-number-options');
        
        if (type === 'number') {
            numOpt.slideDown(200);
        } else {
            numOpt.slideUp(200);
        }
    });
    
    // ==========================================
    // 4. منو ساز پیشرفته پنل کاربری (Custom Menus Builder)
    // ==========================================

    const menusList = $('#jay_up_menus_list');
    const menusJsonInput = $('#jay_up_menus_json_input');
    const addMenuBtn = $('#jay_up_add_menu_btn');

    // تملپیت ردیف متاکی برای شرط‌ها
    function getMenuMetaLogicRow(metaKey = '') {
        return `
            <div class="jay-menu-meta-row" style="display:flex; gap:5px; margin-bottom:5px;">
                <input type="text" class="jay-menu-meta-key" placeholder="نام متاکی (مثال: is_vip)" value="${metaKey}" style="flex:1; direction:ltr; text-align:left;">
                <span class="dashicons dashicons-no-alt jay-remove-menu-meta" style="cursor:pointer; color:#d63638; align-self:center;"></span>
            </div>`;
    }

// رندر کردن یک آیتم منو
    function renderMenuItem(data = {}) {
        const id = data.id || Date.now() + Math.floor(Math.random() * 1000);
        const label = data.label || '';
        const type = data.type || 'content';
        const url = data.url || '';
        
        // --- اصلاح مهم ۱: دیکد کردن محتوا (اگر Base64 باشد) ---
        let content = '';
        if (data.content) {
            try {
                // تلاش برای دیکد کردن Base64
                content = decodeURIComponent(escape(atob(data.content)));
            } catch (e) {
                content = data.content;
            }
        }
        
        const logicType = data.logic_type || 'show'; 
        const logicRelation = data.logic_relation || 'AND';
        
        let metaRows = '';
        if (data.logic_metas && Array.isArray(data.logic_metas)) {
            data.logic_metas.forEach(m => metaRows += getMenuMetaLogicRow(m));
        }

        const html = `
            <div class="jay-admin-field-item jay-menu-item-card" data-id="${id}">
                <div class="jay-admin-field-header jay-menu-accordion-toggle" style="background:#f0f0f1; border-bottom:1px solid #ddd; padding:10px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center;">
                        <span class="dashicons dashicons-menu" style="color:#888;"></span>
                        <strong class="jay-menu-title" style="margin-right:10px;">${label ? label : 'آیتم منو (جدید)'}</strong>
                        <span class="jay-menu-type-badge" style="font-size:10px; background:#ddd; padding:2px 5px; border-radius:3px; margin-right:5px;">${type === 'link' ? 'لینک' : 'محتوا'}</span>
                    </div>
                    <div>
                        <span class="dashicons dashicons-arrow-down-alt2 jay-menu-arrow"></span>
                        <span class="dashicons dashicons-trash jay-remove-menu-btn" title="حذف" style="color:#d63638; margin-right:5px;"></span>
                    </div>
                </div>

                <div class="jay-admin-field-body" style="display:none; padding:15px; background:#fff;">
                    
                    <div class="jay-admin-field-row">
                        <label>عنوان منو:</label>
                        <input type="text" class="jay-menu-label widefat" value="${label}">
                    </div>

                    <div class="jay-admin-field-row">
                         <label>نوع آیتم:</label>
                         <select class="jay-menu-type widefat">
                             <option value="content" ${type === 'content' ? 'selected' : ''}>محتوا / شورت‌کد (تب داخلی)</option>
                             <option value="link" ${type === 'link' ? 'selected' : ''}>لینک مستقیم (هدایت به صفحه دیگر)</option>
                         </select>
                    </div>

                    <div class="jay-menu-content-box" style="display:${type === 'content' ? 'block' : 'none'};">
                        <label>محتوا (HTML یا Shortcode):</label>
                        <div class="jay-menu-content-editor-wrapper">
                            <textarea id="jay-menu-content-${id}" class="jay-menu-content-editor widefat" rows="4">${content}</textarea>
                        </div>
                    </div>

                    <div class="jay-menu-url-box" style="display:${type === 'link' ? 'block' : 'none'};">
                        <label>لینک مقصد:</label>
                        <input type="text" class="jay-menu-url widefat" placeholder="https://example.com/dashboard" value="${url}" style="direction:ltr; text-align:left;">
                    </div>
                    
                    <div class="jay-login-register-add-sider-menu" style="margin-top:15px; border-top:1px dashed #ccc; padding-top:10px;">
                        <button type="button" class="button button-small jay-toggle-menu-logic">تنظیمات پیشرفته نمایش (شرطی) ▼</button>
                        
                        <div class="jay-menu-logic-container" style="display:none; background:#f9f9f9; padding:15px; margin-top:10px; border:1px solid #eee; border-radius:4px;">
                            <p style="margin-top:0;width: 100%;"><strong>شرط نمایش بر اساس متاکی:</strong></p>
                            
                            <div style="display:flex; gap:10px; margin-bottom:10px; align-items: center;">
                                <select class="jay-menu-logic-type" style="width:140px;">
                                    <option value="show" ${logicType === 'show' ? 'selected' : ''}>نمایش بده اگر...</option>
                                    <option value="hide" ${logicType === 'hide' ? 'selected' : ''}>مخفی کن اگر...</option>
                                </select>
                                <span>متاکی‌های زیر وجود داشته باشند:</span>
                            </div>
                            
                            <div style="margin-bottom:10px;width: 100%;">
                                <label>رابط:</label>
                                <select class="jay-menu-logic-relation">
                                    <option value="AND" ${logicRelation === 'AND' ? 'selected' : ''}>همه (AND)</option>
                                    <option value="OR" ${logicRelation === 'OR' ? 'selected' : ''}>حداقل یکی (OR)</option>
                                </select>
                            </div>

                            <div class="jay-menu-metas-list">
                                ${metaRows}
                            </div>
                            <button type="button" class="button-link jay-add-menu-meta" style="margin-top:5px;">+ افزودن متاکی</button>
                        </div>
                    </div>

                </div>
            </div>
        `;
        menusList.append(html);
        // فعال‌سازی ادیتور وردپرس روی textarea جدید
        if (type === 'content') {
            initWPEditor('jay-menu-content-' + id, content);
        }
    }
    function initWPEditor(editorId, content) {
        if (typeof wp !== 'undefined' && wp.editor) {
            wp.editor.remove(editorId); // اگر قبلاً بود حذف کن
            wp.editor.initialize(editorId, {
                tinymce: {
                    wpautop: true,
                    plugins : 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
                    toolbar1: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker',
                    setup: function (editor) {
                        editor.on('change keyup paste', function () {
                            editor.save();
                            updateMenusJson();
                        });
                    }
                },
                quicktags: true,
                mediaButtons: true
            });
        }
    }
    // رویدادها
    addMenuBtn.on('click', function() {
        renderMenuItem();
    });

    // حذف آیتم
    menusList.on('click', '.jay-remove-menu-btn', function(e) {
        e.stopPropagation();
        if(confirm('حذف شود؟')) {
            $(this).closest('.jay-menu-item-card').remove();
            updateMenusJson();
        }
    });

    // آکاردئون باز/بسته
   menusList.on('click', '.jay-menu-accordion-toggle', function(e) {
        if ($(e.target).hasClass('jay-remove-menu-btn')) return;
        const body = $(this).siblings('.jay-admin-field-body');
        
        body.slideToggle(200, function() {
            // اگر باز شد و نوعش محتوا بود، ادیتور را رفرش کن
            if (body.is(':visible')) {
                const type = body.find('.jay-menu-type').val();
                if (type === 'content') {
                    const textarea = body.find('.jay-menu-content-editor');
                    const editorId = textarea.attr('id');
                    const content = textarea.val();
                    // اگر ادیتور هنوز اینیت نشده، اینیت کن
                    if (typeof tinyMCE !== 'undefined' && !tinyMCE.get(editorId)) {
                        initWPEditor(editorId, content);
                    }
                }
            }
        });
        $(this).find('.jay-menu-arrow').toggleClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2');
    });
    // تغییر نام زنده
    menusList.on('input', '.jay-menu-label', function() {
        const val = $(this).val();
        $(this).closest('.jay-menu-item-card').find('.jay-menu-title').text(val || 'آیتم منو');
        updateMenusJson();
    });

    // تغییر نوع (Link/Content)
    menusList.on('change', '.jay-menu-type', function() {
        const type = $(this).val();
        const body = $(this).closest('.jay-admin-field-body');
        const badge = $(this).closest('.jay-menu-item-card').find('.jay-menu-type-badge');
        
        badge.text(type === 'link' ? 'لینک' : 'محتوا');
        
        if (type === 'link') {
            body.find('.jay-menu-content-box').slideUp();
            body.find('.jay-menu-url-box').slideDown();
        } else {
            body.find('.jay-menu-url-box').slideUp();
            body.find('.jay-menu-content-box').slideDown();
        }
        updateMenusJson();
    });

    // دکمه تنظیمات پیشرفته
    menusList.on('click', '.jay-toggle-menu-logic', function() {
        $(this).next('.jay-menu-logic-container').slideToggle();
    });

    // افزودن/حذف متاکی در لاجیک
    menusList.on('click', '.jay-add-menu-meta', function() {
        $(this).siblings('.jay-menu-metas-list').append(getMenuMetaLogicRow());
    });
    menusList.on('click', '.jay-remove-menu-meta', function() {
        $(this).closest('.jay-menu-meta-row').remove();
        updateMenusJson();
    });

// ذخیره اطلاعات در JSON
    function updateMenusJson() {
        const data = [];
        menusList.find('.jay-menu-item-card').each(function() {
            const item = $(this);
            const id = item.data('id');
            const logicMetas = [];
            
            item.find('.jay-menu-meta-key').each(function() {
                const k = $(this).val().trim();
                if(k) logicMetas.push(k);
            });

            // دریافت محتوا از ادیتور (اگر فعال باشد)
            const editorId = 'jay-menu-content-' + id;
            let rawContent = '';
            
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                rawContent = tinyMCE.get(editorId).getContent();
            } else {

                rawContent = item.find('textarea.jay-menu-content-editor').val();
            }
            const encodedContent = rawContent ? btoa(unescape(encodeURIComponent(rawContent))) : '';

            data.push({
                id: id,
                label: item.find('.jay-menu-label').val(),
                type: item.find('.jay-menu-type').val(),
                content: encodedContent, // ذخیره به صورت کد شده
                url: item.find('.jay-menu-url').val(),
                
                logic_type: item.find('.jay-menu-logic-type').val(),
                logic_relation: item.find('.jay-menu-logic-relation').val(),
                logic_metas: logicMetas
            });
        });
        menusJsonInput.val(JSON.stringify(data));
    }
    // تریگر آپدیت با هر تغییر
    menusList.on('change input', 'input, select, textarea', function() {
        // جلوگیری از لوپ روی اینپوت JSON
        if (!$(this).is(menusJsonInput)) updateMenusJson();
    });

    // بارگذاری اولیه
    if (menusJsonInput.length && menusJsonInput.val()) {
        try {
            const savedData = JSON.parse(menusJsonInput.val());
            if (Array.isArray(savedData)) {
                savedData.forEach(d => renderMenuItem(d));
            }
        } catch(e) { console.log('Error parsing menus JSON'); }
    }

    // فعالسازی Sortable
 if (menusList.length && $.fn.sortable) {
        menusList.sortable({
            handle: '.jay-menu-accordion-toggle',
            axis: 'y',
            opacity: 0.8,
            start: function(e, ui) {
                // قبل از درگ، ادیتور آیتم در حال جابجایی را حذف کن
                const textarea = ui.item.find('.jay-menu-content-editor');
                if (textarea.length) {
                    const id = textarea.attr('id');
                    if (typeof wp !== 'undefined' && wp.editor) wp.editor.remove(id);
                }
            },
            stop: function(e, ui) {
                // بعد از درگ، دوباره ادیتور را بساز
                const textarea = ui.item.find('.jay-menu-content-editor');
                if (textarea.length && ui.item.find('.jay-menu-type').val() === 'content') {
                    const id = textarea.attr('id');
                    initWPEditor(id, textarea.val());
                }
                updateMenusJson();
            }
        });
    }
    
});
