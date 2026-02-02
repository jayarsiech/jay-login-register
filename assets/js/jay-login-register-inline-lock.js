jQuery(function($) {
    'use strict';

    let currentCaptchaType = 'none'; 
    let currentRecaptchaSiteKey = ''; 
    let inlineTimerInterval = null; 
    let currentCustomRedirectUrl = '';

    // --- ۱. لاجیک اسکرول خودکار پس از رفرش ---
    // اگر نشانه اسکرول در سشن بود، به آن نقطه برو
    const savedScrollPos = sessionStorage.getItem('jay_lock_scroll_pos');
    if (savedScrollPos) {
        $('html, body').animate({
            scrollTop: savedScrollPos
        }, 500); // انیمیشن نرم نیم ثانیه‌ای
        sessionStorage.removeItem('jay_lock_scroll_pos'); // پاک کردن تا در رفرش‌های بعدی مزاحم نشود
    }

    // --- رویداد کلیک روی دکمه ورود/عضویت قفل inline ---
    $('body').on('click', '.jay-content-lock-wrapper .jay-lock-button[data-mode="inline"]', function(e) {
        e.preventDefault();
        const button = $(this);
        const wrapper = button.closest('.jay-content-lock-wrapper');
        const overlay = wrapper.find('.jay-lock-overlay');
        const hasCustomRedirect = button.data('has-custom-redirect');
        
        if (hasCustomRedirect) {
            currentCustomRedirectUrl = button.data('redirect-url');
        } else {
            currentCustomRedirectUrl = '';
        }

        // نمایش لودر
        overlay.html('<div class="jay-inline-spinner"></div>');

        // درخواست فرم اولیه
        $.ajax({
            url: wrapper.data('ajax-url'), 
            type: 'POST',
            data: {
                action: 'jay_get_inline_lock_form',
                nonce: wrapper.data('nonce'),
                current_url: window.location.href,
                custom_redirect: currentCustomRedirectUrl 
            },
            success: function(response) {
                if (response.success) {
                    overlay.html(response.data.html);
                    
                    // ذخیره تنظیمات کپچا
                    currentCaptchaType = response.data.captcha_type || 'none';
                    currentRecaptchaSiteKey = response.data.recaptcha_site_key || '';

                    // راه‌اندازی کپچا
                    if (currentCaptchaType === 'recaptcha_v3' && currentRecaptchaSiteKey) {
                        loadRecaptchaV3Script(currentRecaptchaSiteKey);
                    } else if (currentCaptchaType === 'honeypot') {
                        const loadTimeInput = overlay.find('input[name="form_load_time_hp"]');
                        if (loadTimeInput.length) {
                            loadTimeInput.val(Math.floor(Date.now() / 1000));
                        }
                    }
                    // فوکوس
                    overlay.find('.jay-inline-input').first().focus();
                } else {
                    displayInlineError(overlay, response.data.message || 'خطایی رخ داد.');
                    // اگر مسدود بود
                    if (response.data.lockout_timer && response.data.lockout_timer > 0) {
                        startInlineTimer(overlay, response.data.lockout_timer, true);
                    }
                }
            },
            error: function() {
                displayInlineError(overlay, 'خطا در برقراری ارتباط با سرور.');
            }
        });
    });

    // --- رویدادهای اینتر و سابمیت فرم اولیه ---
    $('body').on('click', '.jay-inline-check-input', handleInlineFormSubmit);
    $('body').on('keypress', '.jay-inline-input[name="jay_inline_user_input"]', function(e) {
        if (e.which === 13) { e.preventDefault(); handleInlineFormSubmit.call(this); }
    });
     $('body').on('keypress', '.jay-inline-captcha-math input[name="jay_login_register_math_captcha"]', function(e) {
         if (e.which === 13) {
             e.preventDefault();
             $(this).closest('.jay-inline-lock-form-wrapper').find('.jay-inline-check-input').trigger('click');
         }
     });

    // --- تابع هندل سابمیت فرم اولیه ---
    function handleInlineFormSubmit() {
        const button = $(this);
        const overlay = button.closest('.jay-lock-overlay');
        const formWrapper = overlay.find('.jay-inline-lock-form-wrapper');
        const userInputField = formWrapper.find('input[name="jay_inline_user_input"]');
        const userInput = userInputField.val().trim();

        if (!userInput) {
            displayInlineError(overlay, 'لطفاً اطلاعات ورودی را کامل کنید.', true);
            userInputField.focus();
            return;
        }

        overlay.html('<div class="jay-inline-spinner"></div>');

        const sendAjaxRequest = (recaptchaToken = '') => {
            const formData = formWrapper.find('input, select, textarea').serializeArray();
            const ajaxData = {};
            $.each(formData, function(i, field){ ajaxData[field.name] = field.value; });
            const mainWrapper = overlay.closest('.jay-content-lock-wrapper');
            
            ajaxData.action = 'jay_check_inline_input';
            ajaxData.nonce = mainWrapper.data('nonce');
            ajaxData.captcha_type = currentCaptchaType;
            if (currentCaptchaType === 'recaptcha_v3' && recaptchaToken) {
                 ajaxData.recaptcha_v3_token = recaptchaToken;
            }

            $.ajax({
                url: mainWrapper.data('ajax-url'),
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        if (response.data.step === 'choice') {
                             // نمایش فرم انتخاب (SMS یا Bale)
                             overlay.html(response.data.html);
                        } else {
                             // نمایش فرم OTP
                             overlay.html(response.data.html);
                             if (response.data.validity_period) {
                                 startInlineTimer(overlay, response.data.validity_period, false);
                             }
                             if (overlay.find('.jay-inline-otp-fields').length) {
                                 setupInlineOtpFields(overlay);
                             }
                             const firstOtpInput = overlay.find('.jay-inline-otp-input-single, .jay-inline-otp-digit').first();
                             if (firstOtpInput.length) firstOtpInput.focus();
                        }
                    } else {
                        // خطا (مثلاً فرمت اشتباه یا کپچا غلط) -> لود مجدد فرم اولیه
                        loadInitialInlineForm(overlay, function() {
                            displayInlineError(overlay, response.data.message || 'خطایی رخ داد.', true);
                            if (response.data.new_math_question) {
                                const mathLabel = overlay.find('.jay-inline-captcha-math label');
                                if(mathLabel.length) mathLabel.text(response.data.new_math_question);
                            }
                            else if (response.data.lockout_timer && response.data.lockout_timer > 0) {
                                 overlay.find('.jay-inline-lock-form-wrapper').hide();
                                 startInlineTimer(overlay, response.data.lockout_timer, true);
                            }
                        });
                    }
                },
                error: function() {
                    loadInitialInlineForm(overlay, function() {
                        displayInlineError(overlay, 'خطا در ارتباط با سرور.', true);
                    });
                }
            });
        };

        // هندل کردن کپچا V3
        if (currentCaptchaType === 'recaptcha_v3' && typeof grecaptcha !== 'undefined' && currentRecaptchaSiteKey) {
             grecaptcha.ready(function() {
                 grecaptcha.execute(currentRecaptchaSiteKey, {action: 'jay_inline_check'})
                 .then(function(token) { sendAjaxRequest(token); })
                 .catch(function(error){
                     loadInitialInlineForm(overlay, function() {
                         displayInlineError(overlay, 'خطا در کپچا.', true);
                     });
                 });
             });
        } else {
            sendAjaxRequest();
        }
    }

    // --- رویدادهای اینتر در OTP ---
    $('body').on('keypress', '.jay-inline-otp-input-single', function(e) {
        if (e.which === 13) { e.preventDefault(); $(this).closest('.jay-inline-otp-form').find('.jay-inline-verify-otp').trigger('click'); }
    });

    // --- تایید OTP (مهم: ریلود صفحه در صورت موفقیت) ---
    $('body').on('click', '.jay-inline-verify-otp', function() {
        const button = $(this);
        const wrapper = button.closest('.jay-content-lock-wrapper');
        const overlay = wrapper.find('.jay-lock-overlay');
        const formWrapper = button.closest('.jay-inline-otp-form');
        const otpInput = formWrapper.find('input[name="jay_inline_otp"]');
        const context = formWrapper.find('input[name="jay_inline_context"]').val();
        const userInput = formWrapper.find('input[name="jay_inline_user_input_hidden"]').val();
        let otpCode = '';

        const otpFieldsContainer = formWrapper.find('.jay-inline-otp-fields');
        let expectedLength = 0;

        if (otpFieldsContainer.length) {
            updateHiddenOtp(formWrapper.find('.jay-inline-otp-digit'), otpInput);
            otpCode = otpInput.val();
            expectedLength = parseInt(otpFieldsContainer.data('otp-length'), 10);
        } else {
            const singleOtpInput = formWrapper.find('.jay-inline-otp-input-single');
            otpCode = singleOtpInput.val().trim();
            expectedLength = parseInt(singleOtpInput.attr('maxlength'), 10);
        }

        if (!otpCode || (expectedLength > 0 && otpCode.length !== expectedLength)) {
             displayInlineError(overlay, 'کد تایید نامعتبر است.', true);
             return;
        }

        button.prop('disabled', true).html('<span class="jay-inline-spinner" style="width:1em;height:1em;border-width:2px;"></span>درحال بررسی...');

        $.ajax({
            url: wrapper.data('ajax-url'),
            type: 'POST',
            data: {
                action: 'jay_verify_inline_otp',
                nonce: wrapper.data('nonce'), 
                user_input: userInput,
                otp_code: otpCode,
                context: context,
                post_id: wrapper.data('post-id'), 
                lock_id: wrapper.attr('id'),
                req_name: wrapper.data('get-name'), 
                req_fields: wrapper.data('custom-fields')
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.new_nonce) {
                        wrapper.data('nonce', response.data.new_nonce);
                        wrapper.attr('data-nonce', response.data.new_nonce);
                    }

                    // سناریو ۱: نیاز به اطلاعات تکمیلی -> نمایش فرم
                    if (response.data.status === 'needs_details') {
                        window.jayRenderDetailsForm(wrapper, overlay, response.data.new_nonce, response.data.missing_fields);
                        clearInterval(inlineTimerInterval);
                    }
                    // سناریو ۲: موفقیت نهایی -> ریلود صفحه
                    else if (response.data.reload) {
                        if (currentCustomRedirectUrl) {
                            window.location.href = currentCustomRedirectUrl;
                        } else {
                            // ذخیره اسکرول
                            const scrollPos = wrapper.offset().top - 100; // کمی بالاتر از المان
                            sessionStorage.setItem('jay_lock_scroll_pos', scrollPos);
                            
                            // ریلود
                            window.location.reload();
                        }
                    }
                } else {
                    formWrapper.find('.jay-inline-error').remove();
                    displayInlineError(overlay, response.data.message || 'خطایی رخ داد.', true);
                    button.prop('disabled', false).html('تایید کد');
                    
                    if (response.data.lockout_timer && response.data.lockout_timer > 0) {
                        startInlineTimer(overlay, response.data.lockout_timer, true);
                        button.hide();
                        overlay.find('.jay-inline-timer-wrapper').hide();
                    } else {
                         if (otpFieldsContainer.length) {
                             formWrapper.find('.jay-inline-otp-digit').val('').first().focus();
                             otpInput.val('');
                         } else {
                             formWrapper.find('.jay-inline-otp-input-single').val('').focus();
                         }
                    }
                }
            },
            error: function() {
                displayInlineError(overlay, 'خطا در ارتباط با سرور.');
                button.prop('disabled', false).html('تایید کد');
            }
        });
    });

    // --- ارسال مجدد کد (کد قبلی شما حفظ شد) ---
    $('body').on('click', '.jay-inline-resend-link', function(e) {
        e.preventDefault();
        const link = $(this);
        if (link.attr('disabled')) return;
        const overlay = link.closest('.jay-lock-overlay');
        
        link.attr('disabled', 'disabled').text('درحال ارسال...');
        overlay.find('.jay-inline-timer').text('');
        
        $.ajax({
            url: overlay.closest('.jay-content-lock-wrapper').data('ajax-url'),
            type: 'POST',
            data: {
                action: link.data('action'),
                nonce: overlay.closest('.jay-content-lock-wrapper').data('nonce'),
                user_input: link.data('input')
            },
            success: function(response) {
                if (response.success) {
                    link.text('ارسال مجدد کد ');
                    startInlineTimer(overlay, response.data.validity_period, false);
                } else {
                    displayInlineError(overlay, response.data.message || 'خطا.', true);
                    link.removeAttr('disabled').text('ارسال مجدد کد');
                    if (response.data.lockout_timer > 0) {
                        startInlineTimer(overlay, response.data.lockout_timer, true);
                        overlay.find('.jay-inline-verify-otp').hide();
                        link.parent().hide();
                    }
                }
            },
            error: function() {
                displayInlineError(overlay, 'خطا در ارتباط با سرور.');
                link.removeAttr('disabled').text('ارسال مجدد کد');
            }
        });
    });

    // --- انتخاب متد ارسال (پیامک/بله) ---
    $('body').on('click', '.jay-inline-send-method', function(e) {
       e.preventDefault();
       const button = $(this);
       const overlay = button.closest('.jay-lock-overlay');
       const wrapper = overlay.closest('.jay-content-lock-wrapper');
       overlay.html('<div class="jay-inline-spinner"></div>');
       
       $.ajax({
           url: wrapper.data('ajax-url'),
           type: 'POST',
           data: {
               action: 'jay_send_inline_otp_specific',
               nonce: wrapper.data('nonce'),
               user_input: button.data('input'),
               method: button.data('method')
           },
           success: function(response) {
               if (response.success) {
                   overlay.html(response.data.html);
                   if (response.data.validity_period) startInlineTimer(overlay, response.data.validity_period, false);
                   if (overlay.find('.jay-inline-otp-fields').length) setupInlineOtpFields(overlay);
                   overlay.find('input').first().focus();
               } else {
                   displayInlineError(overlay, response.data.message || 'خطا.');
               }
           }
       });
    });

    // --- سابمیت فرم اطلاعات تکمیلی ---
    $('body').on('submit', '.jay-details-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const button = form.find('.jay-inline-submit-details');
        const wrapper = button.closest('.jay-content-lock-wrapper');
        const overlay = wrapper.find('.jay-lock-overlay');
        const formNonce = form.find('input[name="security_nonce"]').val();
        
        const formData = form.serializeArray();
        const ajaxData = {
            action: 'jay_submit_inline_details',
            nonce: formNonce || wrapper.attr('data-nonce'),
            post_id: wrapper.data('post-id'),
            lock_id: wrapper.data('lock-id') || wrapper.attr('id')
        };
        
        $.each(formData, function(i, field){
            if (ajaxData[field.name]) {
                if (!Array.isArray(ajaxData[field.name])) ajaxData[field.name] = [ajaxData[field.name]];
                ajaxData[field.name].push(field.value);
            } else {
                ajaxData[field.name] = field.value;
            }
        });

        button.prop('disabled', true).html('<span class="jay-inline-spinner" style="width:1em;height:1em;border-width:2px;"></span> در حال ثبت...');

        $.ajax({
            url: wrapper.data('ajax-url'),
            type: 'POST',
            data: ajaxData,
            success: function(response) { 
                if (response.success) {
                    // اولویت اول: ریدایرکت سمت سرور
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } 
                    // اولویت دوم: متغیر گلوبال (برای اطمینان)
                    else if (currentCustomRedirectUrl) {
                        window.location.href = currentCustomRedirectUrl;
                    }
                    // اولویت سوم: ریلود صفحه
                    else if (response.data.reload) {
                        const scrollPos = wrapper.offset().top - 100;
                        sessionStorage.setItem('jay_lock_scroll_pos', scrollPos);
                        window.location.reload();
                    }
                } else {
                    displayInlineError(overlay, response.data.message || 'خطا.', true);
                    button.prop('disabled', false).html('ثبت و ورود');
                }
            },
            error: function() {
                displayInlineError(overlay, 'خطا.', true);
                button.prop('disabled', false).html('ثبت و ورود');
            }
        });
    });

    // --- توابع کمکی (نمایش خطا) ---
    function displayInlineError(overlayElement, message, prepend = false) {
        overlayElement.find('.jay-inline-error').remove();
        const errorHtml = '<p class="jay-inline-error">' + message + '</p>';
        if (prepend) {
            const formWrapper = overlayElement.find('.jay-inline-lock-form-wrapper, .jay-inline-otp-form');
            if(formWrapper.length){ formWrapper.prepend(errorHtml); overlayElement.find('.jay-inline-spinner').remove(); }
            else { overlayElement.html(errorHtml); }
        } else { overlayElement.html(errorHtml); }
    }

    // --- تابع بارگذاری مجدد فرم اولیه ---
    function loadInitialInlineForm(overlayElement, callback = null) {
        const wrapper = overlayElement.closest('.jay-content-lock-wrapper');
        overlayElement.html('<div class="jay-inline-spinner"></div>');
         $.ajax({
             url: wrapper.data('ajax-url'), type: 'POST',
             data: { action: 'jay_get_inline_lock_form', nonce: wrapper.data('nonce'), current_url: window.location.href },
             success: function(response) {
                 if (response.success) {
                      overlayElement.html(response.data.html);
                      currentCaptchaType = response.data.captcha_type || 'none';
                      if (currentCaptchaType === 'recaptcha_v3') loadRecaptchaV3Script(response.data.recaptcha_site_key);
                      if (typeof callback === 'function') callback();
                      overlayElement.find('.jay-inline-input').first().focus();
                 } else { displayInlineError(overlayElement, 'خطا.'); }
             }
         });
    }

    // --- تایمر ---
    function startInlineTimer(overlayElement, seconds, isLockout = false) {
        clearInterval(inlineTimerInterval);
        let timerSpan;
        const resendWrapper = overlayElement.find('.jay-inline-timer-wrapper');
        let remainingSeconds = parseInt(seconds, 10);
        if (remainingSeconds <= 0) return;

        if (isLockout) {
            resendWrapper.hide();
            const errorEl = overlayElement.find('.jay-inline-error');
            errorEl.find('.jay-lockout-timer-span').remove();
            timerSpan = $('<span class="jay-lockout-timer-span" style="margin-right:5px; font-weight:bold;"></span>');
            errorEl.append(timerSpan);
        } else {
            resendWrapper.show();
            timerSpan = overlayElement.find('span.jay-inline-timer');
            overlayElement.find('.jay-inline-resend-link').attr('disabled', 'disabled').text('ارسال مجدد کد');
        }

        const updateTimer = () => {
            const minutes = Math.floor(remainingSeconds / 60);
            const secs = remainingSeconds % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            timerSpan.text(` (${timeString})`);
            if (remainingSeconds <= 0) {
                clearInterval(inlineTimerInterval);
                timerSpan.text('');
                if (isLockout) { loadInitialInlineForm(overlayElement); }
                else { overlayElement.find('.jay-inline-resend-link').removeAttr('disabled'); }
            }
            remainingSeconds--;
        };
        updateTimer();
        inlineTimerInterval = setInterval(updateTimer, 1000);
    }

    // --- تنظیم فیلدهای OTP ---
    function setupInlineOtpFields(overlayElement) {
        const otpDigits = overlayElement.find('.jay-inline-otp-digit');
        const hiddenOtpInput = overlayElement.find('input[name="jay_inline_otp"]');
        const submitButton = overlayElement.find('.jay-inline-verify-otp');
        otpDigits.off('input.otp keydown.otp paste.otp');

        otpDigits.on('input.otp', function(e) {
            const current = $(this); const next = current.next();
            let val = current.val();
            if (!/^\d?$/.test(val)) { current.val(''); return; }
            if (val.length > 1) { current.val(val[0]); }
            updateHiddenOtp(otpDigits, hiddenOtpInput);
            if (next.length && val) next.focus().select();
            else if (!next.length && isOtpComplete(otpDigits)) submitButton.trigger('click');
        });

        otpDigits.on('keydown.otp', function(e) {
            const current = $(this); const prev = current.prev();
            if (e.key === 'Backspace' && !current.val() && prev.length) { e.preventDefault(); prev.focus().val('').select(); updateHiddenOtp(otpDigits, hiddenOtpInput); }
            else if (e.key === 'ArrowLeft' && prev.length) { e.preventDefault(); prev.focus().select(); }
            else if (e.key === 'ArrowRight' && current.next().length) { e.preventDefault(); current.next().focus().select(); }
        });

        otpDigits.on('paste.otp', function(e){
             e.preventDefault();
             const data = (e.originalEvent.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
             let idx = otpDigits.index(this);
             for(let i=0; i<data.length && idx<otpDigits.length; i++){ $(otpDigits[idx]).val(data[i]); idx++; }
             updateHiddenOtp(otpDigits, hiddenOtpInput);
             const focusIdx = Math.min(otpDigits.index(this) + data.length, otpDigits.length - 1);
             $(otpDigits[focusIdx]).focus().select();
             if (isOtpComplete(otpDigits)) submitButton.trigger('click');
        });
    }

    function updateHiddenOtp(otpDigitInputs, hiddenInput) {
        let otpValue = ''; otpDigitInputs.each(function() { otpValue += $(this).val(); }); hiddenInput.val(otpValue);
    }
    function isOtpComplete(otpDigitInputs) {
        let complete = true; otpDigitInputs.each(function() { if ($(this).val() === '') { complete = false; return false; } }); return complete;
    }
    function loadRecaptchaV3Script(siteKey) {
        if (typeof grecaptcha === 'undefined') {
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
            script.async = true; document.body.appendChild(script);
        }
    }
 
    // --- تابع جهانی رندر فرم (نسخه اصلاح شده و نهایی) ---
    window.jayRenderDetailsForm = function(wrapper, overlay, newNonce, missingFields = []) {
       const getName = wrapper.data('get-name');
       const getForcePersian = wrapper.attr('data-force-persian');
       const redirectUrl = wrapper.data('redirect-url');      // دریافت لینک ریدایرکت
       
       const customFieldsEnc = wrapper.data('custom-fields');
       const btnText = wrapper.data('button-text') || 'ثبت و ورود';
       const btnColor = wrapper.data('button-color') || '#0073aa';
       
       let fieldsHTML = `<input type="hidden" name="security_nonce" value="${newNonce || wrapper.attr('data-nonce')}">`;
       
       // انتقال تنظیمات به فرم برای ارسال به سرور
       if (getForcePersian === 'yes') {
           fieldsHTML += `<input type="hidden" name="force_persian" value="yes">`;
       }
       if (redirectUrl) {
           fieldsHTML += `<input type="hidden" name="redirect_to" value="${redirectUrl}">`;
       }
        // ارسال کانفیگ فیلدها برای اعتبارسنجی سمت سرور
        if (customFieldsEnc) {
            fieldsHTML += `<input type="hidden" name="fields_config_enc" value="${customFieldsEnc}">`;
        }

        // فیلدهای نام
        if (getName === 'yes') {
            if (missingFields.length === 0 || missingFields.includes('first_name')) {
                fieldsHTML += `<div class="jay-inline-field"><label>نام <span style="color:red">*</span></label><input type="text" name="first_name" class="jay-inline-input" required></div>`;
            }
            if (missingFields.length === 0 || missingFields.includes('last_name')) {
                fieldsHTML += `<div class="jay-inline-field"><label>نام خانوادگی <span style="color:red">*</span></label><input type="text" name="last_name" class="jay-inline-input" required></div>`;
            }
        }

        // فیلدهای سفارشی
        if (customFieldsEnc) {
            try {
                // دیکد کردن ایمن UTF-8
                const fields = JSON.parse(decodeURIComponent(escape(atob(customFieldsEnc))));
                
                fields.forEach(field => {
                    // اگر این فیلد قبلاً پر شده است، نمایش نده
                    if (missingFields.length > 0 && !missingFields.includes(field.key)) return;

                    const reqAttr = (field.is_required && field.is_required == 1) ? 'required' : '';
                    const reqMark = (field.is_required && field.is_required == 1) ? '<span style="color:red">*</span>' : '';
                    
                    // شروع کانتینر فیلد (فقط یک بار!)
                    fieldsHTML += `<div class="jay-inline-field jay-custom-meta-field"><label>${field.label} ${reqMark}</label>`;

                    if (field.type === 'text') {
                        fieldsHTML += `<input type="text" name="meta_${field.key}" class="jay-inline-input" ${reqAttr}>`;
                    } 
                    else if (field.type === 'textarea') {
                        fieldsHTML += `<textarea name="meta_${field.key}" class="jay-inline-input" rows="3" ${reqAttr}></textarea>`;
                    }
                    else if (field.type === 'number') {
                        const maxLen = field.number_len ? `maxlength="${field.number_len}"` : '';
                        fieldsHTML += `<input type="tel" name="meta_${field.key}" class="jay-inline-input" inputmode="numeric" placeholder="فقط عدد" ${reqAttr} ${maxLen}>`;
                    }
                    else if (field.type === 'date') {
                        const isJalali = (field.is_jalali && field.is_jalali == 1);
                        // کلاس jay-datepicker را همیشه اضافه می‌کنیم
                        // اگر شمسی بود data-jalali، اگر میلادی بود type=date (توسط JS تبدیل می‌شود)
                        const dateAttr = isJalali ? 'data-jalali="1"' : 'data-gregorian="1"';
                        fieldsHTML += `<input type="text" name="meta_${field.key}" class="jay-inline-input jay-datepicker" ${dateAttr} ${reqAttr} autocomplete="off">`;
                    }
                    else if (field.type === 'select') {
                        fieldsHTML += `<select name="meta_${field.key}" class="jay-inline-input" ${reqAttr}>`;
                        fieldsHTML += `<option value="">انتخاب کنید...</option>`;
                        if (field.options) {
                            field.options.forEach(opt => {
                                fieldsHTML += `<option value="${opt.value}">${opt.label}</option>`;
                            });
                        }
                        fieldsHTML += `</select>`;
                    }
                    else if (field.type === 'radio' || field.type === 'checkbox') {
                        fieldsHTML += `<div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:5px;">`;
                        if (field.options) {
                            field.options.forEach(opt => {
                                const inputName = `meta_${field.key}${field.type==='checkbox'?'[]':''}`;
                                fieldsHTML += `<label style="display:flex; align-items:center; gap:5px;"><input type="${field.type}" name="${inputName}" value="${opt.value}"> ${opt.label}</label>`;
                            });
                        }
                        fieldsHTML += `</div>`;
                    }

                    fieldsHTML += `</div>`; // پایان کانتینر فیلد
                });
            } catch (e) { 
                console.error('Error parsing inline fields:', e); 
            }
        }

        // اگر هیچ فیلدی برای نمایش نبود، ریلود کن
        if (fieldsHTML.indexOf('<div') === -1 && fieldsHTML.indexOf('input type="text"') === -1) { 
            window.location.reload(); 
            return; 
        }

        // رندر فرم در صفحه
        overlay.html(`
            <div class="jay-inline-lock-form-wrapper jay-inline-name-form">
                <h4>تکمیل اطلاعات</h4>
                <p>لطفاً موارد زیر را تکمیل کنید.</p>
                <form class="jay-details-form">
                    ${fieldsHTML}
                    <button type="submit" class="jay-inline-button jay-inline-submit-details" style="background-color: ${btnColor} !important;">${btnText}</button>
                </form>
            </div>
        `);

        // --- فعال‌سازی تقویم‌ها (بعد از رندر شدن فرم) ---
        setTimeout(function() {
            // 1. تقویم شمسی
            if ($.fn.persianDatepicker) {
                overlay.find('.jay-datepicker[data-jalali="1"]').persianDatepicker({
                    formatDate: "YYYY/MM/DD",
                    showGregorianDate: false,
                    persianNumbers: true,
                    cellWidth: 35,
                    cellHeight: 35,
                    fontSize: 14
                });
            }

            // 2. تقویم میلادی
            // فیلدهای میلادی را پیدا کرده و به type="date" تبدیل می‌کنیم تا مرورگر هندل کند
            overlay.find('.jay-datepicker[data-gregorian="1"]').attr('type', 'date');
        }, 100);
    };
    // اجرا برای حالت‌هایی که از قبل باز شده‌اند
    $('.jay-inline-locked-state').each(function() {
        const wrapper = $(this);
        const missingData = wrapper.data('missing-fields');
        let missingFields = [];
        if (missingData) missingFields = (typeof missingData === 'object') ? missingData : JSON.parse(missingData);
        setTimeout(function(){ 
            if(typeof window.jayRenderDetailsForm === 'function') {
                window.jayRenderDetailsForm(wrapper, wrapper.find('.jay-lock-overlay'), null, missingFields); 
            }
        }, 500);
    });

});
