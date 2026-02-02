jQuery(function($) {
    'use strict';

    let isLockTagOpen = false;
    let currentEditorInstance = null;
    let gutenbergCallback = null;
    let detectedShortcodeData = null; 

    // HTML مودال (بدون تغییر)
    const modalHTML = `
    <div id="jay-lock-modal-backdrop" class="jay-lock-modal-backdrop">
        <div id="jay-lock-modal-content" class="jay-lock-modal-content">
            <h2>تنظیمات قفل محتوا</h2>
            <div class="jay-lock-modal-body">
                <p style="margin-top:0;">کاربر برای مشاهده این بخش از محتوا، چه فرآیندی را طی کند؟</p>
                <div class="jay-lock-modal-option">
                    <label>
                        <input type="radio" name="jay_lock_mode" value="redirect" checked>
                        <strong>هدایت به صفحه ورود</strong>
                        <p class="description">کاربر به صفحه ورود هدایت می‌شود.</p>
                    </label>
                    <div class="jay-lock-settings-panel" id="jay-lock-settings-redirect" style="display:none;">
                         <label>متن عنوان:</label>
                         <input type="text" id="jay_redirect_title" class="widefat" value="محتوای ویژه اعضا">
                         <label>متن توضیحات:</label>
                         <input type="text" id="jay_redirect_message" class="widefat" value="این بخش از محتوا برای اعضای سایت قابل مشاهده است.">
                         <label>متن دکمه:</label>
                         <input type="text" id="jay_redirect_btn_text" class="widefat" value="برای مشاهده کامل، وارد شوید یا عضو شوید">
                         <label style="margin-top:10px; display:block;">رنگ دکمه:</label>
                         <input type="color" id="jay_redirect_btn_color" value="#0073aa" style="width:50px; height:30px; padding:0; border:none;">
                         <hr>
                         <label style="font-weight:bold; margin-bottom:5px; display:block;">رفتار پس از ورود:</label>
                         <label style="margin-left:15px; display:inline-block;">
                            <input type="radio" name="jay_redirect_behavior" value="stay" checked> نمایش همین محتوا (ماندن در صفحه)
                         </label>
                         <label style="display:inline-block;">
                            <input type="radio" name="jay_redirect_behavior" value="custom"> انتقال به آدرس دیگر
                         </label>
                         <div id="jay_redirect_custom_url_wrapper" style="display:none; margin-top:10px;">
                            <input type="url" id="jay_redirect_custom_url" class="widefat" placeholder="https://example.com/target-page">
                            <p class="description">لینک مقصد را وارد کنید.</p>
                         </div>
                    </div>
                </div>
                <div class="jay-lock-modal-option"> 
                    <label>
                        <input type="radio" name="jay_lock_mode" value="inline">
                        <strong>فرم ورود درون‌خطی</strong>
                        <p class="description">یک فرم کوچک در همین نقطه نمایش داده می‌شود.</p>
                    </label>
                    <div id="jay-lock-inline-warning" style="display: none;">
                        توجه: چون تایید ایمیل با OTP فعال نیست، ثبت نام جدید با ایمیل امکان‌پذیر نیست.
                    </div>
                    <div class="jay-lock-settings-panel" id="jay-lock-settings-inline" style="display:none;">
                        <label style="font-weight:bold; margin-bottom:5px; display:block;">نوع نمایش:</label>
                        <label style="margin-left:15px; display:inline-block;">
                            <input type="radio" name="jay_inline_style" value="default" checked> پیش‌فرض (باکس کامل)
                        </label>
                        <label style="display:inline-block;">
                            <input type="radio" name="jay_inline_style" value="button_only"> فقط دکمه
                        </label>
                        <div id="jay-inline-fields-container" style="margin-top:15px;">
                            <div class="jay-field-group" data-field="title">
                                <label>متن عنوان:</label>
                                <input type="text" id="jay_inline_title" class="widefat" value="محتوای ویژه اعضا">
                            </div>
                            <div class="jay-field-group" data-field="message">
                                <label>متن توضیحات:</label>
                                <input type="text" id="jay_inline_message" class="widefat" value="این بخش از محتوا برای اعضای سایت قابل مشاهده است.">
                            </div>
                            <div class="jay-field-group" data-field="button">
                                <label>متن دکمه:</label>
                                <input type="text" id="jay_inline_btn_text" class="widefat" value="برای مشاهده کامل، وارد شوید یا عضو شوید">
                                 <label style="margin-top:10px; display:block;">رنگ دکمه:</label>
                                 <input type="color" id="jay_inline_btn_color" value="#0073aa" style="width:50px; height:30px; padding:0; border:none;">
                            </div>
                        </div>
                         <hr>
                         <label style="font-weight:bold; margin-bottom:5px; display:block;">رفتار پس از ورود:</label>
                         <label style="margin-left:15px; display:inline-block;">
                            <input type="radio" name="jay_inline_behavior" value="stay" checked> نمایش همین محتوا (ماندن در صفحه)
                         </label>
                         <label style="display:inline-block;">
                            <input type="radio" name="jay_inline_behavior" value="custom"> انتقال به آدرس دیگر
                         </label>
                         <div id="jay_inline_custom_url_wrapper" style="display:none; margin-top:10px;">
                            <input type="url" id="jay_inline_custom_url" class="widefat" placeholder="https://example.com/target-page">
                            <p class="description">لینک مقصد را وارد کنید.</p>
                         </div>
                       <hr>
                         <label style="font-weight:bold; margin-bottom:5px; display:block;">اطلاعات شخصی:</label>
                         <label>
                            <input type="checkbox" id="jay_inline_get_personal_info" value="yes"> دریافت اطلاعات تکمیلی
                         </label>
                    <div id="jay_inline_personal_info_options" style="display:none; margin-right: 20px; margin-top: 5px; padding: 10px; border-right: 2px solid #ddd;">
                       <label>
                            <input type="checkbox" id="jay_inline_get_name" value="yes"> نام و نام خانوادگی
                        </label>
                            <div id="jay_inline_name_options" style="margin-right:20px; margin-top:5px; display:none;">
                                <label class="jay-editor-switch">
                                    <input type="checkbox" id="jay_inline_force_persian">
                                    <span class="jay-editor-slider"></span>
                                    فقط حروف فارسی مجاز باشد
                                </label>
                            </div>
                        <p class="description" style="font-size:11px; margin-bottom:10px;">کاربر پس از تایید کد، ملزم به وارد کردن نام و نام خانوادگی خواهد بود.</p>

                            <hr style="border-color:#ddd; margin: 10px 0;">
                            
                     <label>
                                <input type="checkbox" id="jay_inline_enable_custom_fields" value="yes"> افزودن فیلدهای اضافه (Custom Fields)
                            </label>
                            
                            <div id="jay_inline_custom_fields_container" style="display:none; margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                                <div id="jay_inline_fields_list"></div>
                                <button type="button" id="jay_inline_add_field_btn" class="button button-secondary button-small" style="margin-top:10px;">+ افزودن فیلد جدید</button>
                            </div>
                         </div>
                         
                    </div>
                </div> 
            </div> <div class="jay-lock-modal-actions">
                <button type="button" id="jay-lock-insert-shortcode" class="button button-primary">درج / بروزرسانی</button>
                <button type="button" id="jay-lock-cancel" class="button button-secondary">انصراف</button>
            </div>
        </div>
    </div>
    `;

    if ($('#jay-lock-modal-backdrop').length === 0) {
        $('body').append(modalHTML);
    }

    window.openJayLockModal = function(callback, editData = null) {
        gutenbergCallback = callback;
        isLockTagOpen = false;
        openModal(editData);
    };

    function resetModalForm() {
        $('.jay-lock-modal-content input[type="text"], .jay-lock-modal-content input[type="url"]').val('');
        $('#jay_redirect_title, #jay_inline_title').val('محتوای ویژه اعضا');
        $('#jay_redirect_message, #jay_inline_message').val('این بخش از محتوا برای اعضای سایت قابل مشاهده است.');
        $('#jay_redirect_btn_text, #jay_inline_btn_text').val('برای مشاهده کامل، وارد شوید یا عضو شوید');
        $('#jay_redirect_btn_color, #jay_inline_btn_color').val('#0073aa');
        $('input[name="jay_lock_mode"][value="redirect"]').prop('checked', true).trigger('change');
        $('input[name="jay_redirect_behavior"][value="stay"]').prop('checked', true).trigger('change');
        $('input[name="jay_inline_style"][value="default"]').prop('checked', true).trigger('change');
        $('input[name="jay_inline_behavior"][value="stay"]').prop('checked', true).trigger('change');
        $('#jay_inline_get_personal_info').prop('checked', false).trigger('change');
        $('#jay_inline_get_name').prop('checked', false);
        $('#jay_inline_enable_custom_fields').prop('checked', false).trigger('change');
        $('#jay_inline_fields_list').empty();
    }

    function openModal(editData = null) {
        $('body').addClass('jay-lock-modal-open');
        $('#jay-lock-modal-backdrop').addClass('visible');
        resetModalForm();
        if (editData) {
            $('input[name="jay_lock_mode"][value="' + editData.mode + '"]').prop('checked', true).trigger('change');
            if (editData.mode === 'redirect') {
                if(editData.title) $('#jay_redirect_title').val(editData.title);
                if(editData.message) $('#jay_redirect_message').val(editData.message);
                if(editData.button_text) $('#jay_redirect_btn_text').val(editData.button_text);
                if(editData.button_color) $('#jay_redirect_btn_color').val(editData.button_color);
                if (editData.target_url) {
                    $('input[name="jay_redirect_behavior"][value="custom"]').prop('checked', true).trigger('change');
                    $('#jay_redirect_custom_url').val(editData.target_url);
                }
            } else if (editData.mode === 'inline') {
                if(editData.style === 'button_only') {
                     $('input[name="jay_inline_style"][value="button_only"]').prop('checked', true).trigger('change');
                }
                if(editData.title) $('#jay_inline_title').val(editData.title);
                if(editData.message) $('#jay_inline_message').val(editData.message);
                if(editData.button_text) $('#jay_inline_btn_text').val(editData.button_text);
                if(editData.button_color) $('#jay_inline_btn_color').val(editData.button_color);
                if (editData.target_url) {
                    $('input[name="jay_inline_behavior"][value="custom"]').prop('checked', true).trigger('change');
                    $('#jay_inline_custom_url').val(editData.target_url);
                }
               if (editData.get_name === 'yes' || editData.custom_fields) {
                    $('#jay_inline_get_personal_info').prop('checked', true).trigger('change');
                    
                    if (editData.get_name === 'yes') { 
                        // تریگر کردن change مهم است تا پنل فارسی باز شود
                        $('#jay_inline_get_name').prop('checked', true).trigger('change'); 
                        
                        // حالا اگر فارسی هم ذخیره شده بود، تیک بزن
                        if (editData.force_persian === 'yes') {
                            $('#jay_inline_force_persian').prop('checked', true); 
                        }
                    }
                }
                if (editData.custom_fields) {
                    $('#jay_inline_enable_custom_fields').prop('checked', true).trigger('change');
                    try {
                        const fields = JSON.parse(decodeURIComponent(escape(atob(editData.custom_fields))));
                        fields.forEach(field => {
                            // 1. کلیک روی دکمه افزودن برای ساخت فیلد خام
                            $('#jay_inline_add_field_btn').trigger('click');
                            
                            // 2. گرفتن آخرین فیلد ایجاد شده
                            const lastField = $('#jay_inline_fields_list .jay-custom-field-item').last();
                            
                            // 3. پر کردن مقادیر اصلی
                            lastField.find('.jay-cf-label').val(field.label);
                            lastField.find('.jay-cf-header-title').text(field.label ? field.label : 'تنظیمات فیلد');
                            lastField.find('.jay-cf-key').val(field.key);
                            
                            // 4. تغییر نوع فیلد و تریگر کردن change برای باز شدن پنل‌های مربوطه
                            lastField.find('.jay-cf-type').val(field.type).trigger('change');
                            
                            // 5. تنظیمات جدید: ضروری بودن
                            if (field.is_required && field.is_required == 1) {
                                lastField.find('.jay-cf-required').prop('checked', true);
                            }

                            // 6. تنظیمات جدید: تاریخ شمسی
                            if (field.type === 'date' && field.is_jalali == 1) {
                                lastField.find('.jay-cf-jalali').prop('checked', true);
                            }

                            // 7. تنظیمات جدید: شماره (طول و شروع)
                            if (field.type === 'number') {
                                if (field.number_len) lastField.find('.jay-cf-num-len').val(field.number_len);
                                if (field.number_start) lastField.find('.jay-cf-num-start').val(field.number_start);
                            }

                            // 8. پر کردن گزینه‌ها (Select/Radio/Checkbox)
                            if (field.options && field.options.length > 0) {
                                field.options.forEach(opt => {
                                    lastField.find('.jay-add-option-btn').trigger('click');
                                    const lastOpt = lastField.find('.jay-cf-options-list .jay-cf-option-row').last();
                                    lastOpt.find('.jay-cf-opt-label').val(opt.label);
                                    lastOpt.find('.jay-cf-opt-value').val(opt.value);
                                });
                            }
                        });
                        
                        
                    } catch(e) { console.error('Error parsing custom fields', e); }
                }
            }
        }
    }

    $('body').on('change', 'input[name="jay_lock_mode"]', function() {
        const mode = $(this).val();
        $('.jay-lock-settings-panel').slideUp('fast');
        $('#jay-lock-settings-' + mode).slideDown('fast');
        const warningDiv = $('#jay-lock-inline-warning');
        if (mode === 'inline') {
             if (typeof jayEditorSettings !== 'undefined' && jayEditorSettings.email_login_enabled && !jayEditorSettings.email_otp_enabled) {
                warningDiv.slideDown('fast');
            } else { warningDiv.slideUp('fast'); }
        } else { warningDiv.slideUp('fast'); }
    });
    $('body').on('change', 'input[name="jay_inline_style"]', function() {
        const style = $(this).val();
        const container = $('#jay-inline-fields-container');
        if (style === 'button_only') { container.find('[data-field="title"], [data-field="message"]').slideUp('fast'); } 
        else { container.find('[data-field="title"], [data-field="message"]').slideDown('fast'); }
    });
    $('body').on('change', 'input[name="jay_redirect_behavior"]', function() {
        if ($(this).val() === 'custom') $('#jay_redirect_custom_url_wrapper').slideDown('fast');
        else $('#jay_redirect_custom_url_wrapper').slideUp('fast');
    });
    $('body').on('change', 'input[name="jay_inline_behavior"]', function() {
        if ($(this).val() === 'custom') $('#jay_inline_custom_url_wrapper').slideDown('fast');
        else $('#jay_inline_custom_url_wrapper').slideUp('fast');
    });
    $('body').on('change', '#jay_inline_get_personal_info', function() {
        if ($(this).is(':checked')) $('#jay_inline_personal_info_options').slideDown('fast');
        else $('#jay_inline_personal_info_options').slideUp('fast');
    });
    $('body').on('change', '#jay_inline_enable_custom_fields', function() {
        if ($(this).is(':checked')) $('#jay_inline_custom_fields_container').slideDown('fast');
        else $('#jay_inline_custom_fields_container').slideUp('fast');
    });
// فعال‌سازی Sortable برای لیست فیلدها
    if ($.fn.sortable) {
        $('#jay_inline_fields_list').sortable({
            handle: '.jay-cf-header',
            placeholder: 'jay-cf-sortable-placeholder',
            axis: 'y',
            opacity: 0.8
        });
    }

    $('body').on('click', '#jay_inline_add_field_btn', function() {
        const fieldId = Date.now();
        
        const fieldHTML = `
            <div class="jay-custom-field-item" data-id="${fieldId}">
                
                <div class="jay-cf-header" style="cursor: pointer;">
                    <div style="display:flex; align-items:center;">
                        <span class="dashicons dashicons-arrow-down-alt2 jay-cf-toggle-icon" style="margin-left:5px;"></span>
                        <strong class="jay-cf-header-title">تنظیمات فیلد (جدید)</strong>
                    </div>
                    <span class="dashicons dashicons-trash jay-remove-field" style="color:#b32d2e; cursor:pointer;"></span>
                </div>

                <div class="jay-cf-body">
                    <input type="text" class="widefat jay-cf-label" placeholder="نام نمایشی (مثال: تاریخ تولد)" style="margin-bottom:10px;">
                    <input type="text" class="widefat jay-cf-key" placeholder="متا کی (مثال: birth_date)" style="margin-bottom:10px;">
                    
                    <select class="widefat jay-cf-type" style="margin-bottom:10px;">
                        <option value="text">متن (Text)</option>
                        <option value="textarea">پاراگراف (Textarea)</option>
                        <option value="number">شماره (Number)</option>
                        <option value="select">لیست بازشو (Select)</option>
                        <option value="radio">رادیو باتن (Radio)</option>
                        <option value="checkbox">چک باکس (Checkbox)</option>
                        <option value="date">تاریخ (Date)</option>
                    </select>

                    <div style="margin-bottom:10px; background:#f9f9f9; padding:8px; border-radius:4px; border:1px solid #eee;">
                        <label class="jay-editor-switch">
                            <input type="checkbox" class="jay-cf-required">
                            <span class="jay-editor-slider"></span>
                            این فیلد ضروری است
                        </label>
                    </div>

                    <div class="jay-cf-date-options" style="display:none; margin-bottom:10px; background:#f0f6fc; padding:8px; border-radius:4px;">
                        <label class="jay-editor-switch">
                            <input type="checkbox" class="jay-cf-jalali">
                            <span class="jay-editor-slider"></span>
                            استفاده از تقویم شمسی
                        </label>
                    </div>

                    <div class="jay-cf-number-options" style="display:none; margin-bottom:10px; background:#f0f6fc; padding:8px; border-radius:4px;">
                        <div style="margin-bottom:5px;">
                            <label>تعداد ارقام دقیق:</label>
                            <input type="number" class="jay-cf-num-len small-text" placeholder="مثال: 11">
                        </div>
                        <div>
                            <label>شروع شود با:</label>
                            <input type="text" class="jay-cf-num-start small-text" placeholder="مثال: 09" style="direction:ltr; text-align:left;">
                        </div>
                    </div>

                    <div class="jay-cf-options-wrapper" style="display:none; border-top:1px dashed #ccc; padding-top:5px;">
                        <p style="margin:0 0 5px; font-size:11px;">گزینه‌ها (Label : Value):</p>
                        <div class="jay-cf-options-list"></div>
                        <button type="button" class="button-link jay-add-option-btn" style="font-size:11px;">+ افزودن گزینه</button>
                    </div>
                </div>
            </div>`;
        
        $('#jay_inline_fields_list').append(fieldHTML);
    });

    // رویدادهای جدید برای آکاردئون و تغییر نام
    $('body').on('click', '.jay-cf-header', function(e) {
        if ($(e.target).hasClass('jay-remove-field')) return;
        const body = $(this).siblings('.jay-cf-body');
        const icon = $(this).find('.jay-cf-toggle-icon');
        body.slideToggle(200);
        icon.toggleClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2');
    });

    $('body').on('input', '.jay-cf-label', function() {
        const val = $(this).val();
        $(this).closest('.jay-custom-field-item').find('.jay-cf-header-title').text(val ? val : 'تنظیمات فیلد (بدون نام)');
    });
    
    // مدیریت نمایش/مخفی کردن نام گزینه‌ها (فارسی)
    $('body').on('change', '#jay_inline_get_name', function() {
        if($(this).is(':checked')) $('#jay_inline_name_options').slideDown();
        else $('#jay_inline_name_options').slideUp();
    });
    $('body').on('click', '.jay-remove-field', function() { $(this).closest('.jay-custom-field-item').remove(); });
  
    $('body').on('change', '.jay-cf-type', function() {
        const type = $(this).val();
        const wrapper = $(this).closest('.jay-cf-body');
        const optionsWrapper = wrapper.find('.jay-cf-options-wrapper');
        const dateOptions = wrapper.find('.jay-cf-date-options');
        const numberOptions = wrapper.find('.jay-cf-number-options');

        optionsWrapper.hide();
        dateOptions.hide();
        numberOptions.hide();

        if (['select', 'radio', 'checkbox'].includes(type)) optionsWrapper.slideDown('fast');
        else if (type === 'date') dateOptions.slideDown('fast');
        else if (type === 'number') numberOptions.slideDown('fast');
    });    
    $('body').on('click', '.jay-add-option-btn', function() {
        const optionHTML = `
            <div class="jay-cf-option-row" style="display:flex; gap:5px; margin-bottom:5px;">
                <input type="text" class="jay-cf-opt-label" placeholder="عنوان (مرد)" style="flex:1;">
                <input type="text" class="jay-cf-opt-value" placeholder="مقدار (male)" style="flex:1;">
                <span class="dashicons dashicons-no-alt jay-remove-option" style="color:#888; cursor:pointer; align-self:center;"></span>
            </div>`;
        $(this).siblings('.jay-cf-options-list').append(optionHTML);
    });
    $('body').on('click', '.jay-remove-option', function() { $(this).closest('.jay-cf-option-row').remove(); });

    function parseShortcodeToData(shortcodeStr) {
        let content = shortcodeStr.replace('[jay_content_lock', '').replace(']', '');
        content = content.split('[/jay_content_lock]')[0];
        const data = {};
        const regex = /(\w+)\s*=\s*"([^"]*)"/g;
        let match;
        while ((match = regex.exec(content)) !== null) { data[match[1]] = match[2]; }
        return data;
    }

    // --- تابع تشخیص هوشمند اصلاح شده ---
    function checkCursorInShortcode(editor) {
        // بررسی ایمن برای جلوگیری از ارور
        if (!editor || !editor.selection) return;

        let foundShortcode = null;

        try {
            // 1. بررسی اگر متن انتخاب شده باشد
            if (!editor.selection.isCollapsed()) {
                const selContent = editor.selection.getContent();
                if (selContent && selContent.indexOf('[jay_content_lock') !== -1) {
                    foundShortcode = selContent;
                }
            }

            // 2. بررسی نود فعلی
            if (!foundShortcode) {
                const node = editor.selection.getNode();
                if (node) {
                    const nodeText = node.innerHTML || node.textContent || '';
                    const regex = /\[jay_content_lock[\s\S]*?\[\/jay_content_lock\]/g;
                    const match = regex.exec(nodeText);
                    if (match) {
                        foundShortcode = match[0];
                    }
                }
            }
        } catch (e) {
            console.log('Editor selection check skipped');
        }

        if (foundShortcode) {
            updateButtonState('edit', foundShortcode);
        } else {
            updateButtonState('add');
        }
    }

    function editorEventHandler(e) {
        // اطمینان از اینکه تار겟 ادیتور معتبر است
        if (e && e.target && e.target.selection) {
            currentEditorInstance = e.target;
            checkCursorInShortcode(e.target);
        }
    }
    function updateButtonState(state, shortcodeData = null) {
        const button = $('.jay-add-content-lock-button');
        if (button.length === 0) return;

        if (state === 'edit') {
            button.addClass('active-edit-mode')
                  .html('<span class="dashicons dashicons-edit jay-lock-button-icon" style="vertical-align: middle; margin-left: 5px;"></span> ویرایش تنظیمات قفل');
            detectedShortcodeData = shortcodeData;
        } else {
            button.removeClass('active-edit-mode')
                  .html('<span class="dashicons dashicons-lock jay-lock-button-icon" style="vertical-align: middle; margin-left: 5px;"></span> افزودن قفل محتوا');
            detectedShortcodeData = null;
        }
    }

    // --- فعال‌سازی لیسنرها روی تمام ادیتورها ---
    function attachEditorListeners() {
        if (typeof tinymce === 'undefined') return;
        
        // حلقه روی تمام ادیتورهای موجود (چون ممکن است قبل از اسکریپت ما لود شده باشند)
        for (let i = 0; i < tinymce.editors.length; i++) {
            const ed = tinymce.editors[i];
            ed.off('NodeChange KeyUp MouseUp', editorEventHandler); // جلوگیری از تکرار
            ed.on('NodeChange KeyUp MouseUp', editorEventHandler);
        }

        // برای ادیتورهایی که در آینده اضافه می‌شوند
        tinymce.on('AddEditor', function(e) {
            e.editor.on('NodeChange KeyUp MouseUp', editorEventHandler);
        });
    }


    // اجرای اولیه
    attachEditorListeners();
    // چک مجدد بعد از ۱ ثانیه برای اطمینان از لود کامل
    setTimeout(attachEditorListeners, 1000);


    $('body').on('click', '.jay-add-content-lock-button', function(e) {
        e.preventDefault();
        gutenbergCallback = null;

        if (detectedShortcodeData) {
            const editData = parseShortcodeToData(detectedShortcodeData);
            openModal(editData);
            return;
        }
        
        const button = $(this);
        const editorWrapper = button.closest('.wp-editor-wrap'); 
        let editorId = null;
        currentEditorInstance = null; 
        let selectedContent = '';

        if (editorWrapper.length && typeof tinymce !== 'undefined') { 
            const textarea = editorWrapper.find('textarea.wp-editor-area');
            if (textarea.length && textarea.attr('id')) {
                editorId = textarea.attr('id');
                const mceInstance = tinymce.get(editorId);
                if (mceInstance && !mceInstance.isHidden()) {
                    currentEditorInstance = mceInstance;
                    selectedContent = mceInstance.selection.getContent();
                }
            }
        }
        
        if (selectedContent && selectedContent.indexOf('[jay_content_lock') !== -1) {
            const editData = parseShortcodeToData(selectedContent);
            openModal(editData);
        } else {
             if (isLockTagOpen) {
                 const closingShortcode = '[/jay_content_lock]';
                 if (currentEditorInstance) currentEditorInstance.insertContent(closingShortcode);
                 else if (typeof wpActiveEditor !== 'undefined') { 
                     const textarea = $('#' + wpActiveEditor);
                     if (textarea.length) {
                         const currentVal = textarea.val();
                         const cursorPos = textarea.prop('selectionStart');
                         textarea.val(currentVal.substring(0, cursorPos) + closingShortcode + currentVal.substring(cursorPos));
                     }
                 }
                 button.removeClass('active').html('<span class="dashicons dashicons-lock jay-lock-button-icon" style="vertical-align: middle; margin-left: 5px;"></span> افزودن قفل محتوا');
                 isLockTagOpen = false; 
             } else {
                 openModal(null);
             }
        }
    });

    $('body').on('click', '#jay-lock-insert-shortcode', function() {
        const mode = $('input[name="jay_lock_mode"]:checked').val();
        let shortcode = `[jay_content_lock mode="${mode}"`;
        const addParam = (key, value, defaultVal) => { if (value && value !== defaultVal) shortcode += ` ${key}="${value}"`; };

        if (mode === 'redirect') {
            const title = $('#jay_redirect_title').val();
            const message = $('#jay_redirect_message').val();
            const btn = $('#jay_redirect_btn_text').val();
            const color = $('#jay_redirect_btn_color').val();
            const behavior = $('input[name="jay_redirect_behavior"]:checked').val();
            const customUrl = $('#jay_redirect_custom_url').val();
            addParam('title', title, 'محتوای ویژه اعضا');
            addParam('message', message, 'این بخش از محتوا برای اعضای سایت قابل مشاهده است.');
            addParam('button_text', btn, 'برای مشاهده کامل، وارد شوید یا عضو شوید');
            addParam('button_color', color, '#0073aa');
            if (behavior === 'custom' && customUrl) addParam('target_url', customUrl, '');
        } else if (mode === 'inline') {
            const style = $('input[name="jay_inline_style"]:checked').val();
            const title = $('#jay_inline_title').val();
            const message = $('#jay_inline_message').val();
            const btn = $('#jay_inline_btn_text').val();
            const color = $('#jay_inline_btn_color').val();
            const behavior = $('input[name="jay_inline_behavior"]:checked').val();
            const customUrl = $('#jay_inline_custom_url').val();
            if (style === 'button_only') {
                shortcode += ` style="button_only"`;
                addParam('button_text', btn, 'برای مشاهده کامل، وارد شوید یا عضو شوید');
                addParam('button_color', color, '#0073aa');
            } else {
                addParam('title', title, 'محتوای ویژه اعضا');
                addParam('message', message, 'این بخش از محتوا برای اعضای سایت قابل مشاهده است.');
                addParam('button_text', btn, 'برای مشاهده کامل، وارد شوید یا عضو شوید');
                addParam('button_color', color, '#0073aa');
            }
            if (behavior === 'custom' && customUrl) addParam('target_url', customUrl, '');
            if ($('#jay_inline_get_personal_info').is(':checked')) {
                if ($('#jay_inline_get_name').is(':checked')) {
                    addParam('get_name', 'yes', 'no');
                    if ($('#jay_inline_force_persian').is(':checked')) addParam('force_persian', 'yes', 'no');
                }
            }
if ($('#jay_inline_enable_custom_fields').is(':checked')) {
                const customFields = [];
                $('#jay_inline_fields_list .jay-custom-field-item').each(function() {
                    const item = $(this);
                    const type = item.find('.jay-cf-type').val();
                    
                    const fieldData = {
                        label: item.find('.jay-cf-label').val(),
                        key: item.find('.jay-cf-key').val(),
                        type: type,
                        is_required: item.find('.jay-cf-required').is(':checked') ? 1 : 0,
                        options: []
                    };
                    
                    // تنظیمات تاریخ
                    if (type === 'date') {
                        fieldData.is_jalali = item.find('.jay-cf-jalali').is(':checked') ? 1 : 0;
                    }
                    // تنظیمات شماره
                    if (type === 'number') {
                        fieldData.number_len = item.find('.jay-cf-num-len').val();
                        fieldData.number_start = item.find('.jay-cf-num-start').val();
                    }

                    if (['select', 'radio', 'checkbox'].includes(type)) {
                        item.find('.jay-cf-options-list .jay-cf-option-row').each(function() {
                            fieldData.options.push({ 
                                label: $(this).find('.jay-cf-opt-label').val(), 
                                value: $(this).find('.jay-cf-opt-value').val() 
                            });
                        });
                    }

                    if (fieldData.key && fieldData.label) {
                        customFields.push(fieldData);
                    }
                });

                if (customFields.length > 0) {
                    // انکد کردن ایمن برای شورت‌کد (Base64)
                    // استفاده از encodeURIComponent برای پشتیبانی کامل از UTF-8 (فارسی)
                    const jsonStr = JSON.stringify(customFields);
                    const encodedFields = btoa(unescape(encodeURIComponent(jsonStr)));
                    addParam('custom_fields', encodedFields, '');
                }
            }
            
            
        }
        shortcode += ']'; 

        if (gutenbergCallback) {
             gutenbergCallback(shortcode); 
        } else {
            if (currentEditorInstance) {
                if (detectedShortcodeData) {
                    // در حالت ویرایش هوشمند، شورت‌کد جدید را در محل نشانگر درج کن
                    // (بهترین کار برای جلوگیری از پیچیدگی‌های Range این است که در محل انتخاب یا نشانگر درج کنیم)
                    currentEditorInstance.execCommand('mceInsertContent', false, shortcode);
                } else {
                    currentEditorInstance.execCommand('mceInsertContent', false, shortcode);
                    const targetButton = $('.jay-add-content-lock-button');
                    if (targetButton.length) {
                         targetButton.addClass('active').html('<span class="dashicons dashicons-unlock jay-lock-button-icon" style="vertical-align: middle; margin-left: 5px;"></span> پایان بخش محافظت شده');
                    }
                    isLockTagOpen = true;
                }
            }
        }
        closeModal();
    });

    function closeModal() {
          const modal = $('#jay-lock-modal-backdrop');
          modal.removeClass('visible');
          $('body').removeClass('jay-lock-modal-open');
    }
    $('body').on('click', '#jay-lock-cancel, #jay-lock-modal-backdrop', function(e) {
        if (e.target === this || $(this).is('#jay-lock-cancel')) { e.preventDefault(); closeModal(); }
    });
    $('body').on('click', '#jay-lock-modal-content', function(e) { e.stopPropagation(); });

});
