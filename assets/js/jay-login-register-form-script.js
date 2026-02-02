// تابع جدید برای مدیریت تایمر شمارش معکوس
function startOtpTimer(seconds, timerElement, resendButton) {
    resendButton.attr('disabled', true);
    let duration = seconds;
    let timer = setInterval(function () {
        let minutes = parseInt(duration / 60, 10);
        let secs = parseInt(duration % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        secs = secs < 10 ? "0" + secs : secs;

        timerElement.text(minutes + ":" + secs);

        if (--duration < 0) {
            clearInterval(timer);
            timerElement.text("");
            resendButton.attr('disabled', false).text('ارسال مجدد کد');
        }
    }, 1000);
}
// تابع جدید برای تایمر مسدودیت
function startLockoutTimer(seconds, timerElement) {
    let duration = seconds;
    let timer = setInterval(function () {
        let minutes = parseInt(duration / 60, 10);
        let secs = parseInt(duration % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        secs = secs < 10 ? "0" + secs : secs;

        timerElement.text(minutes + ":" + secs);

        if (--duration < 0) {
            clearInterval(timer);
            timerElement.closest('.jay-login-register-lockout-message').text('اکنون می‌توانید دوباره تلاش کنید یا صفحه را رفرش کنید.');
        }
    }, 1000);
}
jQuery(function ($) {
    'use strict';

    $('#jay-login-register-container').find('input.jay-login-register-input:visible').first().focus();
// --- فعال‌سازی تقویم شمسی ---
    if ($.fn.persianDatepicker) {
        $('.jay-datepicker[data-jalali="1"]').persianDatepicker({
            formatDate: "YYYY/MM/DD",
            showGregorianDate: false,
            persianNumbers: true,
            cellWidth: 35,
            cellHeight: 35,
            fontSize: 16
        });
    }
// رویداد کلیک جدید که منطق کپچا را مدیریت می‌کند
$('#jay-login-register-container').on('click', '.jay-login-register-button, .jay-login-register-button-secondary', function (e) {
    e.preventDefault();
    var button = $(this);
    var action = button.data('action');
    if (!action) return;
    var originalButtonText = button.html();
    button.prop('disabled', true).html('<span class="jay-spinner"></span>در حال پردازش...');
    
    // اگر نوع کپچا v3 بود و کاربر روی دکمه اولیه کلیک کرده بود، ابتدا توکن را دریافت کن
    if (jay_login_register_ajax_obj.captcha_type === 'recaptcha_v3' && action === 'check_user_input') {
        grecaptcha.ready(function() {
            grecaptcha.execute(jay_login_register_ajax_obj.recaptcha_site_key, {action: 'login_form_submit'}).then(function(token) {
                $('#recaptcha_v3_token').val(token);
                    sendMainAjaxRequest(button, originalButtonText);
            });
        });
    } else {
 
     sendMainAjaxRequest(button, originalButtonText);
    }
});

    // این کد به تمام اینپوت‌های داخل فرم ما گوش می‌دهد تا با فشردن Enter عمل کند
    $('#jay-login-register-container').on('keypress', 'input.jay-login-register-input', function (e) {
        if (e.which === 13) {
            e.preventDefault(); 
            // حالا کد، فقط اولین دکمه اصلی "قابل مشاهده" را پیدا و کلیک می‌کند.
            $(this).closest('form').find('.jay-login-register-button:visible').first().click();
        }
    });
    
    // مدیریت سوییچ بین فیلد کد ملی و گذرنامه
    // مدیریت سوییچ بین فیلد کد ملی و گذرنامه
    $('#jay-login-register-container').on('click', '.jay-login-register-switcher', function(e){
        e.preventDefault();
        var switchTo = $(this).data('switch-to');
        
        // فقط المنتی که متن "کد ملی/گذرنامه" دارد را انتخاب می‌کنیم
        var idTypeTextEl = $('#jay-login-register-id-type-text');

        if (switchTo === 'passport') {
            // فقط کلمه را عوض کن، به بقیه جمله دست نزن
            idTypeTextEl.text('شماره گذرنامه');

            $('#jay-login-register-national-code-field').hide();
            $('#jay-login-register-passport-field').show();
            $('#jay-login-register-passport-field').find('input.jay-login-register-input').focus();
        } else {
            // فقط کلمه را عوض کن، به بقیه جمله دست نزن
            idTypeTextEl.text('کد ملی');

            $('#jay-login-register-passport-field').hide();
            $('#jay-login-register-national-code-field').show();
            $('#jay-login-register-national-code-field').find('input.jay-login-register-input').focus();
        }
    });
    
$('#jay-login-register-container').on('click', '.jay-login-register-resend-link', function (e) {
        e.preventDefault();
        var button = $(this);
        if (button.attr('disabled')) {
            return;
        }
        
        // جدید: نام اکشن را از خود دکمه می‌خوانیم
        var action = button.data('action');
        if (!action) {
            console.error('Resend link is missing data-action attribute.');
            return;
        }

        var form = button.closest('form');
        var messages = $('#jay-login-register-messages');
        var container = $('#jay-login-register-step-container');

        // در فرم تغییر شماره، phone در فیلد مخفی نیست، پس آن را اضافه نمی‌کنیم
        var formData = form.serialize();
        var context = button.data('context') || 'register'; 
        formData += '&context=' + context;
        formData += '&action=jay_login_register_' + action;

        button.attr('disabled', true).text('در حال ارسال...');
        messages.empty().removeClass('error success');

        $.ajax({
            type: 'POST',
            url: jay_login_register_ajax_obj.ajax_url,
            data: formData,
            success: function (response) {
                if (response.success) {
                    messages.html(response.data.message).addClass('success');
                    if (response.data.validity_period) {
                        button.text('ارسال مجدد کد');
                        const timerWrapper = container.find('.jay-login-register-timer-wrapper');
                        const timerElement = timerWrapper.find('.jay-login-register-timer');
                        startOtpTimer(response.data.validity_period, timerElement, button);
                    }
                } else {
                    button.attr('disabled', false).text('ارسال مجدد کد');
                    messages.html(response.data.message).addClass('error');
                    // منطق نمایش تایمر مسدودیت
                    if (response.data.lockout_timer && response.data.lockout_timer > 0) {
                        messages.append('<div class="jay-login-register-lockout-message">زمان باقی‌مانده تا تلاش مجدد: <span class="jay-login-register-lockout-timer"></span></div>');
                        const timerElement = messages.find('.jay-login-register-lockout-timer');
                        startLockoutTimer(response.data.lockout_timer, timerElement);
                    }
                }
            },
            error: function () {
                messages.html('خطای سرور رخ داده است.').addClass('error');
                button.attr('disabled', false).text('ارسال مجدد کد');
            }
        });
    });
  
  // کد جدید برای مدیریت داینامیک فرم تغییر شماره
$('#jay-login-register-container').on('change', '#jay_login_register_change_password_toggle', function() {
    var form = $(this).closest('form');
    var wrapper = $('#jay-login-register-change-phone-form-wrapper');
    var nonce = form.find('#jay_login_register_nonce').val();

    // نمایش یک لودر ساده
    wrapper.css('opacity', 0.5);

    $.ajax({
        type: 'POST',
        url: jay_login_register_ajax_obj.ajax_url,
        data: {
            action: 'jay_login_register_render_change_phone_form',
            _ajax_nonce: nonce,
            jay_login_register_new_phone: form.find('input[name="jay_login_register_new_phone"]').val(),
            jay_login_register_change_password_toggle: $(this).is(':checked')
        },
        success: function(response) {
            if (response.success) {
                wrapper.html(response.data.html);
            }
            wrapper.css('opacity', 1);
        },
        error: function() {
            wrapper.css('opacity', 1);
            alert('خطایی رخ داد. لطفاً صفحه را رفرش کنید.');
        }
    });
 });

 const countdownElement = $('#jay-login-register-countdown');
    if (countdownElement.length > 0) {
        let countdown = parseInt(countdownElement.data('seconds'), 10);
        const redirectButton = $('#jay-login-register-redirect-button');
        const redirectUrl = redirectButton.attr('href');

        const timer = setInterval(function() {
            countdown--;
            countdownElement.text(countdown); 

            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = redirectUrl;
            }
        }, 1000);
    }

const mathCaptchaWrapper = $('#math-captcha-wrapper');
    if (mathCaptchaWrapper.length > 0) {
        $.ajax({
            type: 'POST',
            url: jay_login_register_ajax_obj.ajax_url,
            data: {
                action: 'jay_login_register_get_math_captcha'
            },
            success: function(response) {
                if (response.success) {
                    mathCaptchaWrapper.find('label').text(response.data.question);
                }
            }
        });
    }
  // --- جدید: مدیریت فیلدهای چندگانه OTP ---
const container = $('#jay-login-register-container');

// تابع برای جمع‌آوری کد و ارسال فرم
function handleOtpCompletion() {
    const otpContainer = $('.jay-otp-fields-container');
    if (otpContainer.length === 0) return;

    let otp = '';
    const inputs = otpContainer.find('.jay-otp-digit-input');
    inputs.each(function() {
        otp += $(this).val();
    });

    // قرار دادن کد کامل شده در فیلد مخفی اصلی
    const mainOtpInputId = '#' + otpContainer.next('input[type="hidden"]').attr('id');
    $(mainOtpInputId).val(otp);

    // اگر کد کامل بود، فرم را ارسال کن
    const otpLength = parseInt(otpContainer.data('otp-length'), 10);
    if (otp.length === otpLength) {
        // دکمه تایید را پیدا کرده و کلیک کن
        otpContainer.closest('form').find('.jay-login-register-button[data-action*="verify"]').click();
    }
}

// رویداد برای حرکت به جلو هنگام تایپ
container.on('input', '.jay-otp-digit-input', function(e) {
    const currentInput = $(this);
    const nextInput = currentInput.next('.jay-otp-digit-input');

    if (currentInput.val().length === 1 && nextInput.length > 0) {
        nextInput.focus();
    }

    handleOtpCompletion();
});

// رویداد برای حرکت به عقب با Backspace
container.on('keydown', '.jay-otp-digit-input', function(e) {
    if (e.key === 'Backspace' && $(this).val() === '') {
        const prevInput = $(this).prev('.jay-otp-digit-input');
        if (prevInput.length > 0) {
            e.preventDefault();
            prevInput.focus().val('');
        }
    }
});

// رویداد برای پیست کردن کد
container.on('paste', '.jay-otp-digit-input', function(e) {
    e.preventDefault();
    const pastedData = (e.originalEvent || e).clipboardData.getData('text/plain').trim();
    const inputs = $(this).closest('.jay-otp-fields-container').find('.jay-otp-digit-input');
    let currentInput = $(this);

    for (let i = 0; i < pastedData.length; i++) {
        if (currentInput.length > 0) {
            currentInput.val(pastedData[i]);
            const next = currentInput.next('.jay-otp-digit-input');
            if(next.length > 0) {
                currentInput = next;
            }
        }
    }
    currentInput.focus();
    handleOtpCompletion();
});

// فوکوس خودکار روی اولین فیلد OTP وقتی فرم لود می‌شود
$(document).ajaxComplete(function() {
    const firstOtpInput = $('.jay-otp-fields-container .jay-otp-digit-input').first();
    if (firstOtpInput.length > 0) {
        firstOtpInput.focus();
    }
});  
    // --- جدید: این تابع کمکی منطق تکراری AJAX را در خود دارد ---
function sendMainAjaxRequest(button, originalButtonText) {
    var form = button.closest('form');
    var container = $('#jay-login-register-step-container');
    var messages = $('#jay-login-register-messages');
    var action = button.data('action');
    
    var formData = form.serialize();
    formData += '&action=jay_login_register_' + action;
    formData += '&referrer_url=' + encodeURIComponent(window.location.href);

    messages.empty().removeClass('error success').html('');

    $.ajax({
        type: 'POST',
        url: jay_login_register_ajax_obj.ajax_url, 
        data: formData,
        success: function (response) {
            if (response.success) {
                if (response.data.message) {
                    messages.html(response.data.message).addClass('success');
                }
                if (response.data.redirect_url) {
                    button.prop('disabled', true).html('<span class="jay-spinner"></span>در حال انتقال...');
                    setTimeout(function () { window.location.href = response.data.redirect_url; }, 800);
                    return;
                }
               if (response.data.html) {
                    container.html(response.data.html);
                    jayInitDatepickers(); // <--- تقویم‌ها را روی فرم جدید فعال کن
                    container.find('input.jay-login-register-input:visible').first().focus();
                }
                if (response.data.validity_period) {
                    const timerWrapper = container.find('.jay-login-register-timer-wrapper');
                    const timerElement = timerWrapper.find('.jay-login-register-timer');
                    const resendButton = timerWrapper.find('.jay-login-register-resend-link');
                    startOtpTimer(response.data.validity_period, timerElement, resendButton);
                }
                button.prop('disabled', false).html(originalButtonText);
            } else {
                button.prop('disabled', false).html(originalButtonText);
                messages.html(response.data.message).addClass('error');
                if (response.data.new_math_question) {
                    $('#math-captcha-wrapper').find('label').text(response.data.new_math_question);
                    $('input[name="jay_login_register_math_captcha"]').val('');
                }
                if (response.data.lockout_timer && response.data.lockout_timer > 0) {
                    messages.append('<div class="jay-login-register-lockout-message">زمان باقی‌مانده تا تلاش مجدد: <span class="jay-login-register-lockout-timer"></span></div>');
                    const timerElement = messages.find('.jay-login-register-lockout-timer');
                    startLockoutTimer(response.data.lockout_timer, timerElement);
                }
                if (messages.length > 0) {
                    $('html, body').animate({ scrollTop: messages.offset().top - 100 }, 500);
                }
            }
        },
        error: function () {
            messages.html('خطای سرور رخ داده است. لطفاً دوباره تلاش کنید.').addClass('error');
            button.prop('disabled', false).html(originalButtonText);
        }
    });
}
 
// --- بررسی زنده نام کاربری (Live Check) ---
    let usernameTimeout = null;
    const usernameInput = '#jay_login_register_custom_username';
    const statusEl = '.jay-username-status';
    const registerBtn = 'button[data-action="create_final_user"]';

    $('#jay-login-register-container').on('input', usernameInput, function() {
        const input = $(this);
        const val = input.val().trim();
        const status = input.siblings(statusEl);
        const btn = input.closest('form').find(registerBtn);

        // پاک کردن تایمر قبلی (Debounce)
        clearTimeout(usernameTimeout);
        
        // ریست وضعیت
        status.text('').removeClass('text-success text-error');
        btn.prop('disabled', true); // غیرفعال کردن دکمه تا زمان تایید
        input.css('border-color', '');

        if (val.length === 0) {
            // اگر خالی شد، کاری نکن (یا اگر اجباری نیست دکمه را فعال کن)
            // فرض می‌کنیم اگر فیلد هست، پر کردنش اجباریست اگر بخواهد یوزر خاص داشته باشد
            // اما اگر خالی رها کرد، سمت سرور یوزر اتوماتیک می‌سازد؟ 
            // طبق سناریوی شما، اگر فیلد باشد باید پر شود.
            return;
        }

        // بررسی اولیه Regex در سمت کلاینت (برای سرعت)
        const regex = /^[a-zA-Z0-9_]+$/;
        if (!regex.test(val)) {
            status.text('فقط حروف انگلیسی، اعداد و _ مجاز است.').addClass('text-error').css('color', '#d63638');
            input.css('border-color', '#d63638');
            return;
        }
        
        if (val.length < 4) {
            status.text('حداقل ۴ کاراکتر وارد کنید.').addClass('text-error').css('color', '#d63638');
            return;
        }

        // نمایش وضعیت "در حال بررسی..."
        status.text('در حال بررسی...').css('color', '#666');

        // شروع تایمر ۵۰۰ میلی‌ثانیه
        usernameTimeout = setTimeout(function() {
            $.ajax({
                type: 'POST',
                url: jay_login_register_ajax_obj.ajax_url,
                data: {
                    action: 'jay_check_username_availability',
                    username: val,
                    _ajax_nonce: $('#jay_login_register_nonce').val() // استفاده از نانس موجود در فرم
                },
                success: function(response) {
                    if (response.success) {
                        status.text(response.data.message).css('color', '#28a745'); // سبز
                        input.css('border-color', '#28a745');
                        btn.prop('disabled', false); // فعال کردن دکمه ثبت نام
                    } else {
                        status.text(response.data.message).css('color', '#d63638'); // قرمز
                        input.css('border-color', '#d63638');
                        btn.prop('disabled', true);
                    }
                },
                error: function() {
                    status.text('خطا در ارتباط با سرور.').css('color', '#d63638');
                }
            });
        }, 500); // 500ms تاخیر
    });

// تابع کمکی برای فعال‌سازی مجدد تقویم‌ها (بعد از لود Ajax)
function jayInitDatepickers() {
    // 1. تقویم شمسی
    if ($.fn.persianDatepicker) {
        $('.jay-datepicker[data-jalali="1"]').each(function() {
            // جلوگیری از اعمال تکراری
            if ($(this).hasClass('has-pdatepicker')) return;
            
            $(this).persianDatepicker({
                formatDate: "YYYY/MM/DD",
                showGregorianDate: false,
                persianNumbers: true,
                cellWidth: 35,
                cellHeight: 35,
                fontSize: 16
            });
        });
    }

    // 2. تقویم میلادی (تبدیل به type=date واقعی)
    // ما در PHP تایپ همه را text گذاشتیم. اینجا اگر شمسی نبود، تایپ را date می‌کنیم تا مرورگر تقویم باز کند
    $('.jay-datepicker:not([data-jalali="1"])').each(function() {
        $(this).attr('type', 'date');
    });
}
    
});
