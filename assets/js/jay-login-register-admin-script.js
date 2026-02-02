jQuery(document).ready(function($) {
    'use strict';

// منطق نمایش سوییچ‌های خطی (برای تنظیمات اصلی)
    $(document).on('change', '.jay-main-trigger', function() {
        const wrapper = $(this).closest('.jay-setting-wrapper');
        const subs = wrapper.find('.jay-inline-subs');
        
        if ($(this).is(':checked')) {
            subs.addClass('active');
        } else {
            subs.removeClass('active');
        }
    });

    // --- بخش آپلود لوگو  ---
  $(document).on('click', '#jay_login_register_upload_logo_button', function(e) {
        e.preventDefault();

        var mediaUploader = wp.media({
            title: 'انتخاب لوگو',
            button: {
                text: 'استفاده از این لوگو'
            },
            multiple: false,
            library: {
                type: 'image',
            },
        });
        
        // Limit file size (client-side check)
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            if (attachment.filesizeInBytes > 3145728) { // 3MB
                alert('حجم فایل نباید بیشتر از 3 مگابایت باشد.');
                return;
            }
            $('#jay_login_register_logo_id').val(attachment.id);
            // Hide the upload button and show the preview (optional, for better UX)
            $('#jay_login_register_upload_logo_button').hide();
            // We rely on server-side logic to hide it permanently after saving.
        });

        mediaUploader.open();
    });

    // Handle logo removal
    $(document).on('click', '#jay_login_register_remove_logo_button', function(e) {
        e.preventDefault();
        if (confirm('آیا از حذف لوگو مطمئن هستید؟')) {
            $('#jay_login_register_logo_id').val(0);
            $('#jay_login_register_logo_preview_wrapper').remove();
            // To make the upload button reappear, we might need to save and reload,
            // or add it back with JS. The safest is to save.
            $('form').submit(); // Automatically submit the form to save the empty value
        }
    });
 
  // ---  مدیریت Repeater برای ستون‌های سفارشی ---
    function reindexRows() {
        $('#custom-columns-repeater .repeater-rows .repeater-row').each(function(index) {
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    }

    $('#custom-columns-repeater').on('click', '.add-row', function() {
        var template = $('#repeater-template').html();
        var newIndex = $('#custom-columns-repeater .repeater-rows .repeater-row').length;
        var newRow = template.replace(/__INDEX__/g, newIndex);
        $('#custom-columns-repeater .repeater-rows').append(newRow);
    });

    $('#custom-columns-repeater').on('click', '.remove-row', function() {
        $(this).closest('.repeater-row').remove();
        reindexRows();
    });
    
     // --- مدیریت نمایش شرطی فیلدهای reCAPTCHA ---
    const captchaTypeRadios = $('input[name="jay_login_register_settings[captcha_type]"]');
    const recaptchaFields = $('#jay-login-register-recaptcha-fields');
    
    function toggleRecaptchaFields() {
        if ($('input[name="jay_login_register_settings[captcha_type]"]:checked').val() === 'recaptcha_v3') {
            recaptchaFields.slideDown();
        } else {
            recaptchaFields.slideUp();
        }
    }

    captchaTypeRadios.on('change', toggleRecaptchaFields);
    toggleRecaptchaFields();

    $('#toggle-recaptcha-instructions').on('click', function() {
        $('#recaptcha-instructions-panel').slideToggle();
    });
    
    
    // --- مدیریت نمایش شرطی فیلدهای پنل پیامک ---
    const smsProviderSelect = $('#jay_login_register_sms_provider_select');
     
function updateSmsFieldsVisibility() {
    const provider = smsProviderSelect.val();
    const kavenegarVoiceCheckbox = $('input[name="jay_login_register_settings[kavenegar_use_voice]"]');
    const useVoice = kavenegarVoiceCheckbox.is(':checked');

    // Selectors for all provider-specific fields (rows)
    const iPanelFields = $('.jay-login-register-ipanel-field').closest('tr');
    const farazFields = $('.jay-login-register-farazsms-field').closest('tr'); // Selects ALL FarazSMS fields now
    const kavenegarFields = $('.jay-login-register-kavenegar-field').closest('tr');
    const kavenegarVoiceTemplateField = $('.jay-login-register-kavenegar-voice-template-field').closest('tr');
    const smsirFields = $('.jay-login-register-smsir-field').closest('tr');
    const raygansmsFields = $('.jay-login-register-raygansms-field').closest('tr');
    const melipayamakFields = $('.jay-login-register-melipayamak-field').closest('tr');

    // Hide all fields first
    iPanelFields.hide();
    farazFields.hide(); 
    kavenegarFields.hide();
    smsirFields.hide();
    raygansmsFields.hide();
    melipayamakFields.hide();

    // Show fields based on selected provider
    if (provider === 'ipanel' || provider === 'modirpayamak' || provider === 'tabansms') {
        // Show iPanel specific fields
        iPanelFields.show();
    } else if (provider === 'farazsms') {
        farazFields.show();
    } else if (provider === 'kavenegar') {
        kavenegarFields.show();
        if (!useVoice) {
            kavenegarVoiceTemplateField.hide();
        }
    } else if (provider === 'smsir') {
        smsirFields.show();
    } else if (provider === 'raygansms') {
        raygansmsFields.show();
    } else if (provider === 'melipayamak') {
        melipayamakFields.show();
    }
}

smsProviderSelect.on('change', updateSmsFieldsVisibility);
    $(document).on('change', 'input[name="jay_login_register_settings[kavenegar_use_voice]"]', updateSmsFieldsVisibility);
    
    updateSmsFieldsVisibility();
    // پنل کاوه نگار پایان


    // مدیریت رویداد کلیک آکاردیون
    $('.accordion-title').on('click', function() {
        $(this).next('.accordion-content').slideToggle(200); // 200ms animation
        $(this).toggleClass('active');
    });

// --- مدیریت Repeater برای توکن‌های ایتا ---
 function reindexEitaaRows() {
$('#eitaa-tokens-repeater .repeater-rows .repeater-row').each(function(index) {
 $(this).find('input').each(function() {
 var name = $(this).attr('name');
 if (name) {
 var newName = name.replace(/\[\d+\]/, '[' + index + ']');
 $(this).attr('name', newName);
 }
 });
 });
 }

 $('#eitaa-tokens-repeater').on('click', '.add-row', function() {
 var template = $('#eitaa-repeater-template').html();
 var newIndex = $('#eitaa-tokens-repeater .repeater-rows .repeater-row').length;
 var newRow = template.replace(/__INDEX__/g, newIndex);
 $('#eitaa-tokens-repeater .repeater-rows').append(newRow);
 });

 $('#eitaa-tokens-repeater').on('click', '.remove-row', function() {
 $(this).closest('.repeater-row').remove();
 reindexEitaaRows();
 });

// --- مدیریت نمایش شرطی فیلدهای ورود با گوگل ---
 const googleEnableCheckbox = $('#jay_login_register_google_login_enable');
 const googleFields = $('#jay-login-register-google-fields');

 function toggleGoogleFields() {
 if (googleEnableCheckbox.is(':checked')) {
 googleFields.slideDown();
 } else {
 googleFields.slideUp();
}
 }

 googleEnableCheckbox.on('change', toggleGoogleFields);

 $('#toggle-google-instructions').on('click', function() {
 $('#google-instructions-panel').slideToggle();
 });
// --- جدید: مدیریت نمایش شرطی فیلدهای ورود با بله (OTP) ---
const baleOtpEnableCheckbox = $('#jay_login_register_bale_otp_enable');
const baleOtpFields = $('#jay-login-register-bale-otp-fields');

function toggleBaleOtpFields() {
    if (baleOtpEnableCheckbox.is(':checked')) {
        baleOtpFields.slideDown();
    } else {
        baleOtpFields.slideUp();
    }
}

baleOtpEnableCheckbox.on('change', toggleBaleOtpFields);
toggleBaleOtpFields(); // اجرا در زمان بارگذاری صفحه

$('#toggle-bale-otp-instructions').on('click', function() {
    $('#bale-otp-instructions-panel').slideToggle();
});
// --- مدیریت بخش استایل و شخصی‌سازی ---
    if (typeof $.fn.wpColorPicker === 'function') {
     $('.jay-color-picker-input').each(function() {
     var $input = $(this);

     // تابع برای بررسی اینکه آیا مقدار یک گرادینت است یا خیر
    function checkGradientValue() {
     var value = $input.val();
     if (value && value.includes('gradient')) {
     // اگر گرادینت بود، خطای iris را حذف کن و رنگ پیش‌نمایش را خاکستری کن
     $input.removeClass('iris-error');
     $input.closest('.wp-picker-container').find('.wp-color-result').css('background-color', '#f0f0f1');
     }
     }

     // مقداردهی اولیه color picker
     $input.wpColorPicker({
     // وقتی رنگ از پالت انتخاب می‌شود یا تغییر می‌کند
     change: function(event, ui) {
     // برای جلوگیری از اجرای دوباره، کمی تاخیر می‌دهیم
    setTimeout(checkGradientValue, 100);
    }
     });

     // با هر بار تایپ کردن در فیلد، مقدار را بررسی کن
     $input.on('keyup', function() {
     checkGradientValue();
     });
    
     // در زمان بارگذاری صفحه هم یک بار بررسی کن تا مقادیر ذخیره شده بدرستی نمایش داده شوند
     checkGradientValue();
     });
     }
 
 const styleCardsWrapper = $('.jay-login-register-style-cards-wrapper');
 
  // کلیک روی کل کارت برای انتخاب رادیو باتن
 styleCardsWrapper.on('click', '.style-card', function() {
  $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
  });

  // جلوگیری از انتخاب دوباره با کلیک روی خود رادیو باتن
  styleCardsWrapper.on('click', 'input[type="radio"]', function(e) {
  e.stopPropagation();
  });

  // به‌روزرسانی ظاهر کارت‌ها هنگام تغییر انتخاب
  styleCardsWrapper.on('change', 'input[type="radio"]', function() {
  styleCardsWrapper.find('.style-card').removeClass('selected');
  styleCardsWrapper.find('.edit-style-button').prop('disabled', true);

  const selectedCard = $(this).closest('.style-card');
  selectedCard.addClass('selected');
  selectedCard.find('.edit-style-button').prop('disabled', false);
  });

// --- مدیریت دکمه بازگشت به پیش‌فرض استایل ---
     $('#jay-relog-reset-styles-button').on('click', function(e) {
     e.preventDefault();
     if (confirm('آیا مطمئن هستید که می‌خواهید تمام تنظیمات ظاهری را به حالت اولیه بازگردانید؟ این عمل غیرقابل بازگشت است.')) {
     window.location.href = $(this).attr('href');
     }
     });
     
$('.jay-login-register-accordion').first().find('.accordion-title').addClass('active').next('.accordion-content').show();

// --- مدیریت نمایش شرطی تنظیمات ایمیل ---

    const emailEnableCheckbox = $('#jay_login_register_email_otp_enable');
    const emailOptionsWrapper = $('#jay-login-register-email-options-wrapper');
    const emailMethodRadios = $('input[name="jay_login_register_settings[email_send_method]"]');
    const smtpWrapper = $('#jay-login-register-smtp-wrapper');
    const sendTestBtn = $('#jay-relog-send-test-email');
    const testStatus = $('#jay-relog-test-email-status');
    const spinner = sendTestBtn.next('.spinner');

    function toggleEmailOptions() {
        if (emailEnableCheckbox.is(':checked')) {
            emailOptionsWrapper.slideDown();
        } else {
            emailOptionsWrapper.slideUp();
        }
    }

    function toggleMailerFields() {
        if ($('input[name="jay_login_register_settings[email_send_method]"]:checked').val() === 'smtp') {
            smtpWrapper.slideDown();
        } else {
            smtpWrapper.slideUp();
        }
    }

    emailEnableCheckbox.on('change', toggleEmailOptions);
    emailMethodRadios.on('change', toggleMailerFields);

    // اجرای توابع در زمان بارگذاری صفحه
    toggleEmailOptions();
    toggleMailerFields();

    // مدیریت دکمه راهنمای cPanel (بدون تغییر)
    $('#toggle-cpanel-instructions').on('click', function(e) {
        e.preventDefault();
        $('#cpanel-instructions-panel').slideToggle();
    });

    // مدیریت ارسال ایمیل تستی
    sendTestBtn.on('click', function() {
        const toEmail = $('#jay_login_register_test_email').val();
        if (!toEmail) {
            alert('لطفاً یک آدرس ایمیل برای تست وارد کنید.');
            return;
        }

        spinner.addClass('is-active');
        sendTestBtn.prop('disabled', true);
        testStatus.text('در حال ارسال...').css('color', '');

        $.ajax({
            type: 'POST',
            url: jay_relog_admin_obj.ajax_url, 
            data: {
                action: 'jay_login_register_send_test_email',
                _ajax_nonce: jay_relog_admin_obj.test_email_nonce, 
                to_email: toEmail
            },
            success: function(response) {
                if (response.success) {
                    testStatus.text(response.data.message).css('color', 'green');
                } else {
                    testStatus.html('<b>خطا:</b> ' + response.data.message).css('color', 'red');
                }
            },
            error: function() {
                testStatus.text('خطای ناشناخته AJAX رخ داد.').css('color', 'red');
            },
            complete: function() {
                spinner.removeClass('is-active');
                sendTestBtn.prop('disabled', false);
            }
        });
    });
    
    // --- مدیریت فیلدهای سفارشی سراسری (Global Custom Fields) ---

    const globalFieldsWrapper = $('#jay_global_fields_wrapper');
    const globalFieldsList = $('#jay_global_fields_list');
    const globalJsonInput = $('#jay_custom_fields_global_json');
    const enableGlobalCheckbox = $('input[name="jay_login_register_settings[enable_custom_fields_global]"]');

    // 1. نمایش/مخفی کردن بیلدر
    enableGlobalCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            globalFieldsWrapper.slideDown();
        } else {
            globalFieldsWrapper.slideUp();
        }
    }); 

// 2. تابع ذخیره وضعیت فعلی در JSON
    function updateGlobalFieldsJson() {
        const fields = [];
        globalFieldsList.find('.jay-admin-field-item').each(function() {
            const item = $(this);
            const type = item.find('.jay-gf-type').val();
            
            const fieldData = {
                label: item.find('.jay-gf-label').val(),
                key: item.find('.jay-gf-key').val(),
                description: item.find('.jay-gf-description').val(), // ذخیره توضیحات
                type: type,
                is_required: item.find('.jay-gf-required').is(':checked') ? 1 : 0, 
                options: []
            };

            if (['select', 'radio', 'checkbox'].includes(type)) {
                item.find('.jay-gf-option-row').each(function() {
                    const optLabel = $(this).find('.jay-gf-opt-label').val();
                    const optValue = $(this).find('.jay-gf-opt-value').val();
                    if (optLabel && optValue) {
                        fieldData.options.push({ label: optLabel, value: optValue });
                    }
                });
            }
            
            // ذخیره تنظیمات تاریخ
            if (type === 'date') {
                fieldData.is_jalali = item.find('.jay-gf-jalali').is(':checked') ? 1 : 0;
            }
            // ذخیره تنظیمات شماره
            if (type === 'number') {
                if (item.find('.jay-gf-has-len').is(':checked')) {
                    fieldData.number_len = item.find('.jay-gf-number-len').val();
                }
                if (item.find('.jay-gf-has-start').is(':checked')) {
                    fieldData.number_start = item.find('.jay-gf-number-start').val();
                }
            }
            // فقط فیلدهایی که کلید دارند ذخیره شوند
            if (fieldData.key) {
                fields.push(fieldData);
            }
        });
        
        globalJsonInput.val(JSON.stringify(fields));
    }
// 3. تابع ساخت HTML یک فیلد (نسخه پیشرفته - آکاردئونی)
    function renderGlobalFieldItem(data = {}) {
        const fieldId = Date.now() + Math.floor(Math.random() * 1000);
        const label = data.label || '';
        const key = data.key || '';
        const type = data.type || 'text';
        const description = data.description || ''; // دریافت توضیحات
        
        const isJalali = data.is_jalali ? 'checked' : '';
        const isRequired = data.is_required ? 'checked' : ''; 
        // تنظیمات فیلد شماره
        const numberLen = data.number_len || ''; 
        const numberStart = data.number_start || '';
        const hasLen = numberLen ? 'checked' : '';
        const hasStart = numberStart ? 'checked' : '';
        
        let optionsHtml = '';
        if (data.options && data.options.length) {
            data.options.forEach(opt => {
                optionsHtml += `
                    <div class="jay-admin-field-row jay-gf-option-row" style="display:flex; gap:10px; align-items:center; margin-bottom:5px;">
                        <input type="text" class="jay-gf-opt-label" placeholder="عنوان گزینه" value="${opt.label}" style="flex:1;">
                        <input type="text" class="jay-gf-opt-value" placeholder="مقدار (Value)" value="${opt.value}" style="flex:1;">
                        <span class="dashicons dashicons-no-alt jay-remove-option-btn" style="cursor:pointer; color:#888;"></span>
                    </div>`;
            });
        }

        const html = `
            <div class="jay-admin-field-item" data-id="${fieldId}">
                
                <div class="jay-admin-field-header jay-gf-accordion-toggle" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <span class="dashicons dashicons-arrow-down-alt2 jay-gf-accordion-icon" style="margin-left:5px;"></span>
                        <strong class="jay-gf-header-title">${label ? label : 'تنظیمات فیلد (جدید)'}</strong>
                    </div>
                    <span class="dashicons dashicons-trash jay-remove-field-btn" title="حذف فیلد"></span>
                </div>

                <div class="jay-admin-field-body" style="display: none;">
                    
                    <div class="jay-admin-field-row">
                        <input type="text" class="jay-gf-label widefat" placeholder="عنوان نمایشی (مثال: جنسیت)" value="${label}">
                        <input type="text" class="jay-gf-key widefat" placeholder="متا کی (مثال: gender)" value="${key}">
                    </div>
                    
                    <div class="jay-admin-field-row">
                        <input type="text" class="jay-gf-description widefat" placeholder="توضیحات راهنما (زیر فیلد نمایش داده می‌شود)" value="${description}">
                    </div>

                    <div class="jay-admin-field-row">
                        <select class="jay-gf-type widefat">
                            <option value="text" ${type === 'text' ? 'selected' : ''}>متن (Text)</option>
                            <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>پاراگراف (Textarea)</option>
                            <option value="select" ${type === 'select' ? 'selected' : ''}>لیست بازشو (Select)</option>
                            <option value="radio" ${type === 'radio' ? 'selected' : ''}>رادیو باتن (Radio)</option>
                            <option value="checkbox" ${type === 'checkbox' ? 'selected' : ''}>چک باکس (Checkbox)</option>
                            <option value="date" ${type === 'date' ? 'selected' : ''}>تاریخ (Date)</option>
                            <option value="number" ${type === 'number' ? 'selected' : ''}>شماره (Number)</option>
                        </select>
                    </div>
                    <div class="jay-admin-field-row" style="background:#fff8e5; padding:10px; border:1px solid #eee; border-radius:4px; margin-bottom:10px;">
                        <label>
                            <input type="checkbox" class="jay-gf-required" value="1" ${isRequired}> 
                            <strong>این فیلد ضروری است </strong>
                        </label>
                    </div>

                    <div class="jay-admin-date-options" style="background:#f0f6fc; padding:10px; border-radius:4px; margin-bottom:10px; display: ${type === 'date' ? 'block' : 'none'};">
                        <label>
                            <input type="checkbox" class="jay-gf-jalali" value="1" ${isJalali}> 
                            استفاده از تقویم شمسی (Jalali)
                        </label>
                    </div>
                    <div class="jay-admin-number-options" style="background:#f0f6fc; padding:10px; border-radius:4px; margin-bottom:10px; border:1px solid #cce5ff; display: ${type === 'number' ? 'block' : 'none'};">
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

                    <div class="jay-admin-options-wrapper" style="display:${['select', 'radio', 'checkbox'].includes(type) ? 'block' : 'none'};">
                        <p style="margin:0 0 10px;">گزینه‌ها:</p>
                        <div class="jay-gf-options-list">${optionsHtml}</div>
                        <button type="button" class="button-link jay-add-gf-option-btn" style="margin-top:10px;">+ افزودن گزینه</button>
                    </div>
                </div>
            </div>
        `;
        
        globalFieldsList.append(html);
    }
    // 4. بارگذاری اولیه از JSON ذخیره شده
    if (globalJsonInput.length > 0) {
        try {
            const savedData = JSON.parse(globalJsonInput.val());
            if (Array.isArray(savedData)) {
                savedData.forEach(field => renderGlobalFieldItem(field));
            }
        } catch (e) {
            console.error('Error parsing saved fields JSON', e);
        }
    }

    // 5. رویدادهای کلیک و تغییر (Events)
    $('#jay_add_global_field_btn').on('click', function() {
        renderGlobalFieldItem();
    });

    globalFieldsList.on('click', '.jay-remove-field-btn', function() {
        if(confirm('آیا از حذف این فیلد مطمئن هستید؟')) {
            $(this).closest('.jay-admin-field-item').remove();
            updateGlobalFieldsJson();
        }
    });

// --- رویداد تغییر نوع فیلد (اصلاح شده) ---
    globalFieldsList.on('change', '.jay-gf-type', function() {
        const type = $(this).val();
        const item = $(this).closest('.jay-admin-field-item');
        const optsWrapper = item.find('.jay-admin-options-wrapper');
        const dateWrapper = item.find('.jay-admin-date-options');
        const numWrapper = item.find('.jay-admin-number-options'); // <--- جدید

        // مخفی کردن همه در ابتدا
        optsWrapper.slideUp(200);
        dateWrapper.slideUp(200);
        numWrapper.slideUp(200);

        if (['select', 'radio', 'checkbox'].includes(type)) {
            optsWrapper.slideDown(200);
        } 
        else if (type === 'date') {
            dateWrapper.slideDown(200);
        } 
        else if (type === 'number') { // <--- جدید
            numWrapper.slideDown(200);
        }
        
        updateGlobalFieldsJson();
    });
    // --- فعال‌سازی Sortable (کشیدن و رها کردن) ---
    if (globalFieldsList.length && $.fn.sortable) {
        globalFieldsList.sortable({
            handle: '.jay-admin-field-header', // دستگیره درگ
            placeholder: 'jay-up-sortable-placeholder', // استایل جای خالی
            axis: 'y',
            opacity: 0.8,
            update: function() { updateGlobalFieldsJson(); } // ذخیره پس از جابجایی
        });
    }

    // --- منطق آکاردئون (باز و بسته کردن) ---
    globalFieldsList.on('click', '.jay-gf-accordion-toggle', function(e) {
        // جلوگیری از باز شدن اگر روی دکمه حذف کلیک شد
        if ($(e.target).hasClass('jay-remove-field-btn')) return;

        const body = $(this).siblings('.jay-admin-field-body');
        const icon = $(this).find('.jay-gf-accordion-icon');
        
        body.slideToggle(200);
        
        // چرخش فلش
        if (icon.hasClass('dashicons-arrow-down-alt2')) {
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        } else {
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });

    // --- سینک شدن نام فیلد با هدر ---
    globalFieldsList.on('input', '.jay-gf-label', function() {
        const val = $(this).val();
        const title = $(this).closest('.jay-admin-field-item').find('.jay-gf-header-title');
        title.text(val ? val : 'تنظیمات فیلد (بدون نام)');
    });

    globalFieldsList.on('click', '.jay-add-gf-option-btn', function() {
        const row = `
            <div class="jay-admin-field-row jay-gf-option-row">
                <input type="text" class="jay-gf-opt-label" placeholder="عنوان گزینه">
                <input type="text" class="jay-gf-opt-value" placeholder="مقدار (Value)">
                <span class="dashicons dashicons-no-alt jay-remove-option-btn" style="cursor:pointer; color:#888; margin-top:5px;"></span>
            </div>`;
        $(this).siblings('.jay-gf-options-list').append(row);
    });

    globalFieldsList.on('click', '.jay-remove-option-btn', function() {
        $(this).closest('.jay-gf-option-row').remove();
        updateGlobalFieldsJson();
    });

    // آپدیت JSON با هر تغییری در اینپوت‌ها
    globalFieldsList.on('change input', 'input, select', function() {
        updateGlobalFieldsJson();
    });
    
// جلوگیری از فاصله در کلید متا (Meta Key) - اصلاح شده
    globalFieldsList.on('input', '.jay-gf-key', function() {
        let val = $(this).val();
        let clean = val.replace(/[^a-zA-Z0-9_]/g, '_'); 
        if (val !== clean) {
            $(this).val(clean);
        }
        updateGlobalFieldsJson();
    });
    
    // مدیریت تنظیمات فیلد شماره - اصلاح شده
    globalFieldsList.on('change', '.jay-gf-has-len', function() {
        const input = $(this).closest('div').find('.jay-gf-number-len');
        if($(this).is(':checked')) {
            input.slideDown(200).focus();
        } else {
            input.slideUp(200);
        }
        updateGlobalFieldsJson();
    });
    
    globalFieldsList.on('change', '.jay-gf-has-start', function() {
        const input = $(this).closest('div').find('.jay-gf-number-start');
        if($(this).is(':checked')) {
            input.slideDown(200).focus();
        } else {
            input.slideUp(200);
        }
        updateGlobalFieldsJson();
    });
});
