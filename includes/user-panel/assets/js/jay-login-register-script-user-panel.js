jQuery(document).ready(function($) {
    'use strict';
    // --- 0. بازیابی تب فعال از LocalStorage ---
    function activateTab(targetId) {
        const wrapper = $('.jay-login-register-instagram-panel');
        
        // غیرفعال کردن همه
        wrapper.find('.jay-menu-item').removeClass('active');
        wrapper.find('.jay-tab-content').removeClass('active').hide();
        
        // پیدا کردن آیتم منو و تب مربوطه
        // برای منوهای پیش‌فرض: data-target="profile"
        // برای منوهای سفارشی: data-target="custom-123"
        
        const menuItem = wrapper.find(`.jay-menu-item[data-target="${targetId}"]`);
        const tabContent = wrapper.find(`#jay-tab-${targetId}`);
        
        if (menuItem.length && tabContent.length) {
            menuItem.addClass('active');
            tabContent.addClass('active').fadeIn(300);
            return true;
        }
        return false;
    }
    // بررسی اینکه آیا تب ذخیره شده داریم؟
    const savedTab = localStorage.getItem('jay_active_panel_tab');
    let tabRestored = false;
    
    if (savedTab) {
        tabRestored = activateTab(savedTab);
    }
    
    // اگر تب ذخیره شده نبود یا نامعتبر بود، اولین تب موجود را فعال کن
    if (!tabRestored) {
        const firstTabTarget = $('.jay-instagram-menu .jay-menu-item').first().data('target');
        if (firstTabTarget) {
            activateTab(firstTabTarget);
        }
    }
    
    // --- 1. سوییچ کردن تب‌ها ---
 $('.jay-instagram-menu').on('click', '.jay-menu-item', function() {
        const item = $(this);
        if (item.hasClass('jay-logout-item')) return;

        const targetId = item.data('target');
        if (targetId) {
            activateTab(targetId);
            localStorage.setItem('jay_active_panel_tab', targetId);
        }
    });

    // --- 2. توابع تایمر ---
    function startPanelOtpTimer(seconds, timerElement, resendButton) {
        if (!timerElement.length) return;
        resendButton.addClass('disabled').attr('disabled', 'disabled');
        
        if (timerElement.data('interval')) clearInterval(timerElement.data('interval'));

        let duration = seconds;
        const updateTimerDisplay = () => {
            let minutes = parseInt(duration / 60, 10);
            let secs = parseInt(duration % 60, 10);
            minutes = minutes < 10 ? "0" + minutes : minutes;
            secs = secs < 10 ? "0" + secs : secs;
            timerElement.text(minutes + ":" + secs);
        };
        
        updateTimerDisplay(); // نمایش لحظه‌ای

        let timer = setInterval(function () {
            duration--;
            updateTimerDisplay();

            if (duration < 0) {
                clearInterval(timer);
                timerElement.text("");
                resendButton.removeClass('disabled').removeAttr('disabled');
            }
        }, 1000);
        timerElement.data('interval', timer);
    }

    function startPanelLockoutTimer(seconds, messageContainer) {
        let duration = seconds;
        if (messageContainer.find('.jay-login-register-lockout-timer').length === 0) {
             messageContainer.html('<div class="jay-lockout-alert">شما مسدود شده‌اید. زمان باقی‌مانده: <span class="jay-login-register-lockout-timer" dir="ltr"></span></div>');
        }
        const timerElement = messageContainer.find('.jay-login-register-lockout-timer');

        let timer = setInterval(function () {
            let minutes = parseInt(duration / 60, 10);
            let secs = parseInt(duration % 60, 10);
            minutes = minutes < 10 ? "0" + minutes : minutes;
            secs = secs < 10 ? "0" + secs : secs;
            timerElement.text(minutes + ":" + secs);

            if (--duration < 0) {
                clearInterval(timer);
                messageContainer.html('<span style="color:green">زمان مسدودیت تمام شد. لطفا دوباره تلاش کنید.</span>');
                const form = messageContainer.closest('form');
                form.find('button.jay-login-register-button').show().prop('disabled', false);
            }
        }, 1000);
    }

    // --- 3. تابع کمکی: پر کردن فیلد مخفی ---
    function updateHiddenOtpInput(container) {
        let otpCode = '';
        container.find('.jay-otp-digit-input').each(function() { 
            otpCode += $(this).val(); 
        });
        
        // پیدا کردن فیلد مخفی بر اساس name="jay_panel_otp_input" در همان فرم
        const form = container.closest('form');
        const hiddenInput = form.find('input[name="jay_panel_otp_input"]');
        
        if (hiddenInput.length) {
            hiddenInput.val(otpCode);
        }
        return otpCode;
    }

    // --- 4. هندلر دکمه‌های اصلی فرم (AJAX) ---
    $(document).on('click', '.jay-login-register-button', function(e) {
        const button = $(this);
        const action = button.data('action');
        
        // لیست تمام اکشن‌های مجاز برای پنل (موبایل و ایمیل)
        const panelActions = [
            'panel_send_old_mobile_otp', 
            'panel_verify_old_mobile', 
            'panel_send_new_mobile_otp', 
            'panel_verify_new_mobile',
            'panel_send_old_email_otp', 
            'panel_verify_old_email', 
            'panel_send_new_email_otp', 
            'panel_verify_new_email'
        ];

        if (!panelActions.includes(action)) return;

        e.preventDefault();
        const form = button.closest('form');
        const messages = form.find('.jay-login-register-messages');
        const originalText = button.text();

        // پر کردن فیلد مخفی اگر چند فیلدی است
        const otpContainer = form.find('.jay-otp-fields-container');
        if (otpContainer.length > 0) {
            updateHiddenOtpInput(otpContainer);
        }

        let formData = form.serialize();
        formData += '&action=jay_' + action;
        formData += '&nonce=' + jayUserPanelObj.nonce;

        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> پردازش...');
        if(messages.length) messages.removeClass('error success').text('');

        $.ajax({
            type: 'POST',
            url: jayUserPanelObj.ajax_url,
            data: formData,
            success: function(response) {
                button.prop('disabled', false).html(originalText);

                if (response.success) {
                    
                    // --- بخش هوشمند کانتینرها ---
                    // پیدا کردن تب فعلی که دکمه در آن کلیک شده
                    const currentTab = form.closest('.jay-tab-content');
                    // پیدا کردن کانتینر مرحله ۱ در همین تب (هر المنتی که آیدیش با -step-1 تمام شود)
                    const step1Container = currentTab.find('[id$="-step-1"]');
                    // پیدا کردن کانتینر داینامیک در همین تب (هر المنتی که آیدیش با -dynamic-container تمام شود)
                    const dynamicContainer = currentTab.find('[id$="-dynamic-container"]');

                    if (response.data.step === 'verify_old') {
                        step1Container.slideUp();
                        dynamicContainer.html(response.data.html).slideDown();
                    } 
                    else if (response.data.step === 'enter_new' || response.data.step === 'verify_new') {
                        dynamicContainer.html(response.data.html);
                    }
                    else if (response.data.step === 'completed') {
                        currentTab.html(response.data.html);
                    }
                    // تایمر
                    if (response.data.validity_period) {
                        const timerWrapper = dynamicContainer.find('.jay-login-register-timer-wrapper');
                        const timerEl = timerWrapper.find('.jay-login-register-timer');
                        const resendBtn = timerWrapper.find('.jay-login-register-resend-link');
                        startPanelOtpTimer(response.data.validity_period, timerEl, resendBtn);
                    }
                    // فوکوس روی اولین فیلد
                    setTimeout(function(){
                         dynamicContainer.find('input:visible, .jay-otp-digit-input:visible').first().focus();
                    }, 100);

                } else {
                    // خطا
                    if(messages.length) {
                        messages.addClass('error').html(response.data.message);
                        
                        if (response.data.lockout_timer) {
                            button.hide();
                            startPanelLockoutTimer(response.data.lockout_timer, messages);
                        }
                    } else {
                        alert(response.data.message);
                    }
                    
                    if (!response.data.lockout_timer && otpContainer.length) {
                         otpContainer.find('input').val('').first().focus();
                         // پاک کردن مقدار فیلد مخفی
                         form.find('input[name="jay_panel_otp_input"]').val(''); 
                    }
                }
            },
            error: function() {
                button.prop('disabled', false).html(originalText);
                if(messages.length) messages.addClass('error').text('خطای ارتباط با سرور.');
            }
        });
    });

// --- 5. هندلر دکمه ارسال مجدد ---
    $(document).on('click', '.jay-login-register-resend-link', function(e) {
        e.preventDefault();
        const link = $(this);
        if (link.attr('disabled') || link.hasClass('disabled')) return;

        const action = link.data('action');
        const timerWrapper = link.closest('.jay-login-register-timer-wrapper');
        const timerElement = timerWrapper.find('.jay-login-register-timer');
        
        // پیدا کردن فرم والد برای خواندن فیلد مخفی user_input
        const form = link.closest('form');
        let formData = form.serialize(); // تمام داده‌های فرم را می‌گیرد
        
        // اضافه کردن اکشن و نانس
        formData += '&action=jay_' + action;
        formData += '&nonce=' + jayUserPanelObj.nonce;
        
        link.text('در حال ارسال...').addClass('disabled');

        $.ajax({
            type: 'POST',
            url: jayUserPanelObj.ajax_url,
            data: formData, // ارسال داده‌های کامل فرم
            success: function(response) {
                if (response.success) {
                    link.text('ارسال مجدد کد');
                    if (response.data.validity_period) {
                        startPanelOtpTimer(response.data.validity_period, timerElement, link);
                    }
                } else {
                    link.text('تلاش مجدد').removeClass('disabled').removeAttr('disabled');
                    alert(response.data.message);
                }
            },
            error: function() {
                link.text('تلاش مجدد').removeClass('disabled').removeAttr('disabled');
                alert('خطا در ارسال مجدد.');
            }
        });
    });
    // --- 6. مدیریت اینپوت‌های چندگانه و ارسال خودکار ---
    $(document).on('input', '.jay-otp-digit-input', function() {
        const current = $(this);
        const container = current.closest('.jay-otp-fields-container');
        
        // حرکت به جلو
        if(current.val().length === 1) {
            const next = current.next('.jay-otp-digit-input');
            if (next.length) {
                next.focus();
            }
        }
        
        // آپدیت مقدار مخفی
        const otpCode = updateHiddenOtpInput(container);

        // بررسی تکمیل شدن برای ارسال خودکار
        const otpLength = parseInt(container.data('otp-length'), 10);
        
        if (otpCode.length === otpLength) {
            const form = container.closest('form');
            const submitBtn = form.find('.jay-login-register-button');
            setTimeout(function() {
                submitBtn.trigger('click');
            }, 300);
        }
    });
    
    // حرکت به عقب
    $(document).on('keydown', '.jay-otp-digit-input', function(e) {
        if(e.key === 'Backspace' && this.value.length === 0) {
            $(this).prev('.jay-otp-digit-input').focus();
        }
    });

    // پیست کردن
    $(document).on('paste', '.jay-otp-digit-input', function(e) {
        e.preventDefault();
        const pastedData = (e.originalEvent || e).clipboardData.getData('text/plain').trim();
        const inputs = $(this).closest('.jay-otp-fields-container').find('.jay-otp-digit-input');
        
        inputs.each(function(index) {
            if (index < pastedData.length) {
                $(this).val(pastedData[index]);
            }
        });
        
        const lastFilled = inputs.eq(Math.min(pastedData.length, inputs.length - 1));
        lastFilled.focus().trigger('input');
    });

    // --- 7. فعال‌سازی دکمه اینتر برای فرم‌های پنل ---
    $(document).on('keypress', '.jay-panel-form input', function(e) {
        if (e.which === 13) { 
            e.preventDefault(); 
            const submitBtn = $(this).closest('.jay-panel-form').find('.jay-login-register-button');
            if (!submitBtn.prop('disabled') && submitBtn.is(':visible')) {
                submitBtn.trigger('click');
            }
        }
    });
    
    // ==========================================
    // منطق تغییر رمز عبور (Password Logic)
    // ==========================================

    // 1. تابع Debounce (برای بهینه سازی جستجوی زنده)
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    // 2. بررسی زنده رمز عبور قدیمی
    const currentPassInput = $('#jay_current_password_input');
    const checkStatus = $('#jay-password-check-status');
    const step1 = $('#jay-panel-password-step-1');
    const step2 = $('#jay-panel-password-step-2');

    currentPassInput.on('input', debounce(function() {
        const password = $(this).val();
        
        if (password.length < 1) {
            checkStatus.text('').removeClass('loading success error');
            return;
        }

        checkStatus.text('در حال بررسی...').addClass('loading').removeClass('success error');

        $.ajax({
            type: 'POST',
            url: jayUserPanelObj.ajax_url,
            data: {
                action: 'jay_panel_check_current_password',
                password: password,
                nonce: jayUserPanelObj.nonce
            },
            success: function(response) {
                if (response.success) {
                    checkStatus.text('رمز عبور صحیح است. انتقال...').addClass('success').removeClass('loading error');
                    currentPassInput.prop('disabled', true); // قفل کردن فیلد
                    
                    // افکت انتقال به مرحله بعد
                    setTimeout(function() {
                        step1.slideUp(300, function() {
                            step2.slideDown(300);
                            // ارسال تنظیمات رمز قوی به JS (اگر نیاز باشد، فعلا فرانت‌اند هندل می‌کنیم)
                        });
                    }, 800);
                } else {
                    checkStatus.text('رمز عبور اشتباه است.').addClass('error').removeClass('loading success');
                }
            }
        });
    }, 800)); // 800 میلی ثانیه تاخیر بعد از آخرین تایپ

    // 3. سنجش قدرت رمز عبور (New Password)
    const newPassInput = $('#jay_new_password');
    const strengthBar = $('.jay-strength-bar');
    const strengthMeter = $('.jay-password-strength-meter');
    const strengthText = $('.jay-strength-text');

    newPassInput.on('input', function() {
        const val = $(this).val();
        strengthMeter.show();
        
        let strength = 0;
        if (val.length >= 8) strength += 1;
        if (val.match(/[a-z]/)) strength += 1;
        if (val.match(/[A-Z]/)) strength += 1;
        if (val.match(/[0-9]/)) strength += 1;
        if (val.match(/[^a-zA-Z0-9]/)) strength += 1;

        let width = 0;
        let color = '#dc3545'; // قرمز
        let text = 'خیلی ضعیف';

        if (val.length === 0) {
            width = 0;
            text = '';
        } else if (val.length < 8) {
            width = 20;
            text = 'کوتاه (حداقل ۸ کاراکتر)';
        } else {
            switch(strength) {
                case 1: width = 20; color = '#dc3545'; text = 'ضعیف'; break;
                case 2: width = 40; color = '#ffc107'; text = 'متوسط'; break;
                case 3: width = 60; color = '#ffc107'; text = 'خوب'; break;
                case 4: width = 80; color = '#28a745'; text = 'قوی'; break;
                case 5: width = 100; color = '#20c997'; text = 'بسیار قوی'; break;
            }
        }

        strengthBar.css({ 'width': width + '%', 'background-color': color });
        strengthText.text(text).css('color', color);
        
        // تریگر کردن بررسی تطابق
        $('#jay_confirm_password').trigger('input');
    });

    // 4. بررسی تطابق رمزها (Confirm Password)
    const confirmInput = $('#jay_confirm_password');
    const matchStatus = $('#jay-password-match-status');

    confirmInput.on('input', function() {
        const pass1 = newPassInput.val();
        const pass2 = $(this).val();

        if (pass2.length === 0) {
            matchStatus.text('');
            return;
        }

        if (pass1 === pass2) {
            matchStatus.text('رمز عبور مطابقت دارد.').css('color', '#28a745');
        } else {
            matchStatus.text('رمز عبور مطابقت ندارد.').css('color', '#dc3545');
        }
    });

    // 5. ارسال نهایی فرم تغییر رمز
    $(document).on('click', '.jay-login-register-button[data-action="panel_change_password_final"]', function(e) {
        e.preventDefault();
        const button = $(this);
        const form = button.closest('form');
        const messages = form.find('.jay-login-register-messages');
        const originalText = button.text();

        // بررسی کلاینت ساید تطابق
        if (newPassInput.val() !== confirmInput.val()) {
            messages.removeClass('success').addClass('error').text('رمز عبور و تکرار آن یکسان نیستند.');
            return;
        }

        button.prop('disabled', true).text('در حال ذخیره...');
        messages.text('').removeClass('error success');

        $.ajax({
            type: 'POST',
            url: jayUserPanelObj.ajax_url,
            data: form.serialize() + '&action=jay_panel_change_password_final&nonce=' + jayUserPanelObj.nonce,
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                if (response.success) {
                    $('#jay-tab-password').html(response.data.html); // نمایش پیام موفقیت نهایی
                } else {
                    messages.addClass('error').text(response.data.message);
                }
            },
            error: function() {
                button.prop('disabled', false).text(originalText);
                messages.addClass('error').text('خطای ارتباط با سرور.');
            }
        });
    });
    // ==========================================
    // منطق ویرایش پروفایل
    // ==========================================

    // 1. بررسی زنده نام کاربری
    const usernameInput = $('#jay_panel_username');
    const usernameStatus = $('#jay-username-status');

    if (usernameInput.length) {
        usernameInput.on('input', debounce(function() {
            const username = $(this).val();
            
            if (username.length < 3) {
                usernameStatus.text('').removeClass('loading success error');
                return;
            }

            usernameStatus.text('در حال بررسی...').addClass('loading').removeClass('success error');

            $.ajax({
                type: 'POST',
                url: jayUserPanelObj.ajax_url,
                data: {
                    action: 'jay_panel_check_username_live',
                    username: username,
                    nonce: jayUserPanelObj.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if(response.data.status === 'current') {
                             usernameStatus.text(response.data.message).css('color', '#666').removeClass('loading success error');
                        } else {
                             usernameStatus.text(response.data.message).addClass('success').removeClass('loading error').css('color', '');
                        }
                    } else {
                        usernameStatus.text(response.data.message).addClass('error').removeClass('loading success').css('color', '');
                    }
                }
            });
        }, 800));
    }

    // 2. ذخیره پروفایل
    $(document).on('click', '.jay-login-register-button[data-action="panel_update_profile"]', function(e) {
        e.preventDefault();
        const button = $(this);
        const form = button.closest('form');
        const messages = form.find('.jay-login-register-messages');
        const originalText = button.text();

        button.prop('disabled', true).text('در حال ذخیره...');
        messages.text('').removeClass('error success');

        // 1. تبدیل داده‌های فرم به آرایه
        let formDataArray = form.serializeArray();

        // 2. جستجو در آرایه و جایگزینی تاریخ شمسی با میلادی
        formDataArray.forEach(function(field) {
            // پیدا کردن اینپوت مربوطه در فرم
            const inputEl = form.find('[name="' + field.name + '"]');
            
            // اگر اینپوت تاریخ شمسی است و مقدار میلادی دارد
            if (inputEl.hasClass('jay-datepicker') && inputEl.data('jalali') == 1) {
                const gDate = inputEl.attr('data-gdate');
                // اگر gDate موجود بود، مقدار ارسالی (value) را عوض کن
                if (gDate) {
                    field.value = gDate; 
                }
            }
        });

        // 3. تبدیل آرایه اصلاح شده به رشته برای ارسال
        // اضافه کردن پارامترهای دستی (اکشن و نانس)
        formDataArray.push({name: 'action', value: 'jay_panel_update_profile'});
        formDataArray.push({name: 'nonce', value: jayUserPanelObj.nonce});
        
        const finalData = $.param(formDataArray);

        $.ajax({
            type: 'POST',
            url: jayUserPanelObj.ajax_url,
            data: finalData,
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                if (response.success) {
                    messages.addClass('success').text(response.data.message);
                    if(response.data.redirect) {
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    messages.addClass('error').html(response.data.message);
                }
            },
            error: function() {
                button.prop('disabled', false).text(originalText);
                messages.addClass('error').text('خطای سرور.');
            }
        });
    });
    
    // ==========================================
    // منطق فیلدهای پروفایل (نام، کد ملی، گذرنامه)
    // ==========================================

    // 1. بررسی فقط فارسی بودن نام و نام خانوادگی
    const nameInputs = $('#jay_panel_first_name, #jay_panel_last_name');
    nameInputs.on('input', function() {
        const input = $(this);
        const status = input.next('.jay-field-status');
        const forcePersian = input.data('force-persian');
        const val = input.val();

        if (forcePersian && val.length > 0) {
            // Regex برای حروف فارسی و فاصله
            const persianRegex = /^[\u0600-\u06FF\s]+$/;
            if (!persianRegex.test(val)) {
                status.text('لطفاً فقط حروف فارسی وارد کنید.').addClass('error').removeClass('success');
            } else {
                status.text('').removeClass('error');
            }
        } else {
            status.text('').removeClass('error');
        }
    });

    // 2. بررسی زنده کد ملی
    const ncInput = $('#jay_panel_national_code');
    const ncStatus = $('#jay-nationalcode-status');

    if (ncInput.length) {
        ncInput.on('input', debounce(function() {
            const val = $(this).val();
            
            if (val.length === 0) {
                ncStatus.text('').removeClass('loading error success');
                return;
            }
            // اگر طول کمتر از ۸ است هنوز چیزی نگو
            if (val.length < 8) {
                ncStatus.text('').removeClass('loading error success');
                return;
            }

            ncStatus.text('بررسی...').addClass('loading').removeClass('error success');

            $.ajax({
                type: 'POST',
                url: jayUserPanelObj.ajax_url,
                data: {
                    action: 'jay_panel_check_national_code_live',
                    national_code: val,
                    nonce: jayUserPanelObj.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if(response.data.status !== 'empty') {
                            ncStatus.text(response.data.message).addClass('success').removeClass('loading error');
                        }
                    } else {
                        ncStatus.text(response.data.message).addClass('error').removeClass('loading success');
                    }
                }
            });
        }, 800));
    }

    // 3. بررسی زنده گذرنامه
    const passInput = $('#jay_panel_passport');
    const passStatus = $('#jay-passport-status');

    if (passInput.length) {
        passInput.on('input', debounce(function() {
            const val = $(this).val();
            
            if (val.length < 5) {
                passStatus.text('').removeClass('loading error success');
                return;
            }

            passStatus.text('بررسی...').addClass('loading').removeClass('error success');

            $.ajax({
                type: 'POST',
                url: jayUserPanelObj.ajax_url,
                data: {
                    action: 'jay_panel_check_passport_live',
                    passport: val,
                    nonce: jayUserPanelObj.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if(response.data.status !== 'empty') {
                            passStatus.text(response.data.message).addClass('success').removeClass('loading error');
                        }
                    } else {
                        passStatus.text(response.data.message).addClass('error').removeClass('loading success');
                    }
                }
            });
        }, 800));
    }

    // ==========================================
    // 8. مدیریت تقویم شمسی (Jalali Datepicker)
    // ==========================================
    function initJalaliDatepickers() {
        $('.jay-datepicker[data-jalali="1"]').each(function() {
            const input = $(this);
            
            if (input.hasClass('has-pdatepicker')) return;
            
            // 1. تغییر تایپ به text
            input.attr('type', 'text');
            input.addClass('has-pdatepicker');
            
            // 2. تبدیل تاریخ میلادی دیتابیس به شمسی (برای نمایش اولیه)
            const gDateVal = input.val(); 
            
            if (gDateVal && gDateVal.indexOf('-') > 0) {
                try {
                    // ذخیره مقدار میلادی اصلی
                    input.attr('data-gdate', gDateVal);
                    
                    const parts = gDateVal.split('-');
                    const y = parseInt(parts[0]);
                    const m = parseInt(parts[1]);
                    const d = parseInt(parts[2]);
                    
                    const jDateFunc = new jDateFunctions();
                    const jDate = jDateFunc.gregorian_to_jalali(new Date(y, m - 1, d));
                    
                    // فرمت دهی دو رقمی برای ماه و روز
                    const jMonth = jDate.month < 10 ? '0' + jDate.month : jDate.month;
                    const jDay = jDate.date < 10 ? '0' + jDate.date : jDate.date;
                    
                    input.val(jDate.year + '/' + jMonth + '/' + jDay);
                    
                } catch(e) { console.log('Jalali load error:', e); }
            }

            // 3. فعال‌سازی تقویم
            if ($.fn.persianDatepicker) {
                input.persianDatepicker({
                    formatDate: "YYYY/MM/DD",
                    showGregorianDate: false,
                    persianNumbers: true, 
                    cellWidth: 30, 
                    cellHeight: 30,
                    fontSize: 14,
                    // *** مهم: وقتی کاربر تاریخ را انتخاب کرد ***
                    onSelect: function() {
                        // دریافت تاریخ شمسی انتخاب شده از اینپوت
                        const selectedJalali = input.val();
                        
                        // تبدیل دستی به میلادی برای ذخیره در data-gdate
                        if(selectedJalali) {
                            // تبدیل اعداد فارسی به انگلیسی (اگر وجود داشته باشد)
                            const cleanJalali = selectedJalali.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
                            const jParts = cleanJalali.split('/');
                            
                            if(jParts.length === 3) {
                                const jY = parseInt(jParts[0]);
                                const jM = parseInt(jParts[1]);
                                const jD = parseInt(jParts[2]);
                                
                                const jDateFunc = new jDateFunctions();
                                // تبدیل به میلادی
                                const gDateObj = jDateFunc.jalali_to_gregorian({year: jY, month: jM, date: jD});
                                
                                // فرمت دهی YYYY-MM-DD
                                const gY = gDateObj.getFullYear();
                                const gM = (gDateObj.getMonth() + 1).toString().padStart(2, '0');
                                const gD = gDateObj.getDate().toString().padStart(2, '0');
                                
                                // ست کردن اتریبیوت دیتا (این چیزی است که به سرور می‌فرستیم)
                                const finalGDate = gY + '-' + gM + '-' + gD;
                                input.attr('data-gdate', finalGDate);
                            }
                        }
                    }
                });
            }
        });
    }

    // اجرای تابع
    initJalaliDatepickers();
    // ==========================================
    // سوییچ بین کد ملی و گذرنامه (حالت تلفیقی)
    // ==========================================
    $(document).on('click', '.jay-panel-switch-identity', function(e) {
        e.preventDefault();
        const target = $(this).data('target');
         
        const ncGroup = $('#jay-panel-nc-group');
        const passGroup = $('#jay-panel-pass-group');
        const ncInput = $('#jay_panel_national_code');
        const passInput = $('#jay_panel_passport');

        if (target === 'passport') {
            // مخفی کردن کد ملی
            ncGroup.slideUp(200);
            ncInput.prop('disabled', true);
            
            // نمایش گذرنامه
            passGroup.slideDown(200);
            passInput.prop('disabled', false).focus();
        } 
        else if (target === 'nc') {
            // مخفی کردن گذرنامه
            passGroup.slideUp(200);
            passInput.prop('disabled', true);
            
            // نمایش کد ملی
            ncGroup.slideDown(200);
            ncInput.prop('disabled', false).focus();
        }
    });
    // ==========================================
    // مدیریت آپلود آواتار (Avatar Upload)
    // ==========================================
    
    // 1. کلیک روی کانتینر -> باز شدن پنجره انتخاب فایل
    $('.jay-avatar-container').on('click', function() {
        $('#jay_avatar_upload_input').click();
    });

    // 2. وقتی فایل انتخاب شد -> ارسال ایجکس
    $('#jay_avatar_upload_input').on('change', function() {
        const fileInput = this;
        const file = fileInput.files[0];
        
        if (!file) return;

        // بررسی نوع فایل در سمت کلاینت (UX)
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('لطفاً فقط فایل تصویری (JPG, PNG) انتخاب کنید.');
            return;
        }

        // نمایش لودر
        const container = $('.jay-avatar-container');
        const spinner = container.find('.jay-avatar-spinner');
        const img = container.find('img');
        
        spinner.show();
        container.addClass('loading');

        // ساخت فرم دیتا
        const formData = new FormData();
        formData.append('action', 'jay_panel_upload_avatar');
        formData.append('avatar_file', file);
        formData.append('nonce', jayUserPanelObj.nonce);

        $.ajax({
            url: jayUserPanelObj.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                spinner.hide();
                container.removeClass('loading');
                
                if (response.success) {
                    // تغییر آنی عکس (Force reload image)
                    img.attr('src', response.data.url);
                    // ریست کردن اینپوت برای انتخاب مجدد همان فایل
                    $(fileInput).val('');
                    // نمایش دکمه حذف
                    $('#jay-avatar-actions').show();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                spinner.hide();
                container.removeClass('loading');
                alert('خطا در ارتباط با سرور.');
            }
        });
    });

    // 3. حذف آواتار
    $('#jay-avatar-delete-btn').on('click', function() {
        if(!confirm('آیا از حذف عکس پروفایل مطمئن هستید؟')) return;

        const btn = $(this); 
        btn.text('در حال حذف...');

        $.ajax({
            url: jayUserPanelObj.ajax_url,
            type: 'POST',
            data: {
                action: 'jay_panel_delete_avatar',
                nonce: jayUserPanelObj.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.jay-avatar-container img').attr('src', response.data.url);
                    btn.closest('.jay-avatar-actions').hide();
                    btn.text('حذف عکس');
                }
            }
        });
    });
     // ==========================================
    // منطق شرطی فیلدها (Client Side Conditional Logic)
    // ==========================================
    
    function jayCheckConditionalLogic() {
        $('.jay-conditional-wrapper[data-conditional-logic]').each(function() {
            const wrapper = $(this);
            const logic = wrapper.data('conditional-logic');
            
            if (!logic || !logic.rules) return;

            let isVisible = (logic.relation === 'AND') ? true : false;

            // بررسی تک تک قوانین
            $.each(logic.rules, function(index, rule) {
                const targetKey = rule.target;
                const operator  = rule.operator; // = یا !=
                const expected  = rule.value;
                
                // نام فیلدها در فرم jay_panel_meta_KEY است
                const targetInputName = 'jay_panel_meta_' + targetKey;
                let actualValue = '';

                // گرفتن مقدار واقعی بر اساس نوع فیلد
                const targetEl = $('[name="' + targetInputName + '"], [name="' + targetInputName + '[]"]');
                
                if (targetEl.is(':radio')) {
                    actualValue = targetEl.filter(':checked').val() || '';
                } else if (targetEl.is(':checkbox')) {
                    // اگر چک‌باکس تکی مدنظر است:
                    actualValue = targetEl.filter(':checked').val() || '';
                } else {
                    actualValue = targetEl.val() || '';
                }
                // مقایسه
                let conditionMet = false;
                if (operator === '=') {
                    conditionMet = (actualValue == expected);
                } else if (operator === '!=') {
                    conditionMet = (actualValue != expected);
                }

                // اعمال منطق AND / OR
                if (logic.relation === 'AND') {
                    if (!conditionMet) { isVisible = false; return false; } // Break loop
                } else { // OR
                    if (conditionMet) { isVisible = true; return false; } // Break loop
                }
            });

            // اعمال نمایش/مخفی‌سازی
            const inputs = wrapper.find('input, select, textarea');
            
            if (isVisible) {
                wrapper.slideDown(200);
                // فعال کردن فیلدها تا ارسال شوند
                inputs.prop('disabled', false);
            } else {
                wrapper.slideUp(200);
                // غیرفعال کردن فیلدها تا ارسال نشوند (و ارور ندهند)
                inputs.prop('disabled', true);
            }
        });
    }

    // گوش دادن به تغییرات تمام فیلدهای ورودی فرم پروفایل
    $('#jay-profile-update-form').on('change input', 'input, select, textarea', function() {
        jayCheckConditionalLogic();
    });

    // اجرای اولیه هنگام لود صفحه (برای تنظیم وضعیت اولیه)
    jayCheckConditionalLogic();
    
    // --- سناریوی جدید: ثبت شماره موبایل (برای کاربرانی که موبایل ندارند) ---
    $(document).on('click', '[data-action="panel_send_new_mobile_otp_direct"]', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('form');
        var $msgBox = $form.find('.jay-login-register-messages');
        var new_mobile = $('#jay_new_mobile_direct').val();

        if(!new_mobile) {
            $msgBox.html('<div class="jay-message error">لطفاً شماره موبایل را وارد کنید.</div>');
            return;
        }

        $btn.addClass('loading').prop('disabled', true);
        $msgBox.html('');

        $.ajax({
            url: jayUserPanelObj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'jay_panel_send_new_mobile_otp',
                nonce: jayUserPanelObj.nonce,
                jay_panel_new_mobile: new_mobile,
                is_direct_add: 1 
            },
            success: function(res) {
                $btn.removeClass('loading').prop('disabled', false);
                if(res.success) {
                    $msgBox.html('<div class="jay-message success">' + res.data.message + '</div>');
                    // مخفی کردن فرم اول و نمایش فرم ورود کد تایید
                    $('#jay-panel-mobile-add-container').slideUp();
                    $('#jay-panel-mobile-change-dynamic-container').html(res.data.html).slideDown();
                } else {
                    $msgBox.html('<div class="jay-message error">' + (res.data.message || 'خطایی رخ داد') + '</div>');
                }
            },
            error: function() {
                $btn.removeClass('loading').prop('disabled', false);
                $msgBox.html('<div class="jay-message error">خطای ارتباط با سرور.</div>');
            }
        });
    });
    
});
