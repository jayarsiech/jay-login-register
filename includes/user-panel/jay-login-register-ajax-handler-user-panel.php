<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}
/**
 * تابع کمکی: مدیریت خطا و مسدودسازی در پنل کاربری
 */
function jay_panel_handle_verification_failure($phone, $user_ip, $settings) {
    $max_retries = intval($settings['otp_max_retries'] ?? 3);
    $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
    $block_methods = $settings['otp_block_method'] ?? ['phone'];

    $fail_count = 0;
    
    // بررسی بلاک موبایل
    if ( in_array('phone', $block_methods, true) ) {
        $fail_count = get_transient('jay_login_register_otp_fail_count_' . $phone) ?: 0;
        $fail_count++;
        if ($fail_count >= $max_retries) {
            set_transient('jay_login_register_otp_block_' . $phone, 'blocked', $lockout_duration * MINUTE_IN_SECONDS);
            delete_transient('jay_login_register_otp_fail_count_' . $phone);
        } else {
            set_transient('jay_login_register_otp_fail_count_' . $phone, $fail_count, $lockout_duration * MINUTE_IN_SECONDS);
        }
    }
    // بررسی بلاک IP
    if ( in_array('ip', $block_methods, true) ) {
        $ip_fail_count = get_transient('jay_login_register_otp_fail_count_' . $user_ip) ?: 0;
        $ip_fail_count++;
        if ($ip_fail_count >= $max_retries) {
            set_transient('jay_login_register_otp_block_' . $user_ip, 'blocked', $lockout_duration * MINUTE_IN_SECONDS);
            delete_transient('jay_login_register_otp_fail_count_' . $user_ip);
        } else {
            set_transient('jay_login_register_otp_fail_count_' . $user_ip, $ip_fail_count, $lockout_duration * MINUTE_IN_SECONDS);
        }
        $fail_count = max($fail_count, $ip_fail_count);
    }

    // اگر بلاک شد، پیام بلاک بفرست
    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );

    // اگر هنوز بلاک نشده، تعداد تلاش باقی‌مانده را بگو
    $remaining_tries = max(0, $max_retries - $fail_count);
    return "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید.";
}

/**
 * پنل کاربری: مرحله ۱ - ارسال کد به شماره قدیمی
 */
add_action('wp_ajax_jay_panel_send_old_mobile_otp', 'jay_panel_ajax_send_old_mobile_otp');
function jay_panel_ajax_send_old_mobile_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);

    $user_id = get_current_user_id();
    $current_phone = get_user_meta($user_id, 'jay_mobile', true);
    
    if (empty($current_phone)) wp_send_json_error(['message' => 'شماره موبایلی برای شما ثبت نشده است.']);

    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    
    // بررسی مسدودیت
    jay_login_register_check_and_handle_lockout( $current_phone, $user_ip, $settings );

    // تولید کد
    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    // ارسال
    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('error', 'خطا'), $current_phone, $otp);
    if (is_wp_error($send_result)) wp_send_json_error(['message' => 'خطا در ارسال: ' . $send_result->get_error_message()]);

    // ذخیره
    set_transient('jay_panel_old_otp_' . $user_id, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);

  // ساخت فرم
    $instruction = "کد تایید به شماره فعلی شما ($current_phone) ارسال شد.";
    
    // دریافت HTML خام از تابع اصلی (بدون دستکاری تابع اصلی)
    $raw_html = jay_login_register_get_otp_verification_form_html('تایید شماره فعلی', $instruction, $current_phone, 'jay_panel_otp_input', 'panel_verify_old_mobile', 'panel_send_old_mobile_otp', 'panel_old');
    
    // **تغییر:** ما اینجا دستی یک تگ فرم دورش می‌پیچیم تا JS بتواند دیتا را بخواند
    $html = '<form class="jay-panel-form">' . $raw_html . '</form>';
    
    wp_send_json_success([
        'html' => Jay_Login_Register_Minifier::html($html),
        'step' => 'verify_old',
        'validity_period' => $validity_period * 60
    ]);
}

/**
 * پنل کاربری: مرحله ۲ - بررسی کد شماره قدیمی
 */
add_action('wp_ajax_jay_panel_verify_old_mobile', 'jay_panel_ajax_verify_old_mobile');
function jay_panel_ajax_verify_old_mobile() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);

    $user_id = get_current_user_id();
    $current_phone = get_user_meta($user_id, 'jay_mobile', true);
    $otp_entered = isset($_POST['jay_panel_otp_input']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_otp_input'])) : '';
    
    // ۱. بررسی مسدودیت قبل از هر چیز
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    jay_login_register_check_and_handle_lockout( $current_phone, $user_ip, $settings );

    $transient_data = get_transient('jay_panel_old_otp_' . $user_id);
    if (!$transient_data) wp_send_json_error(['message' => 'کد منقضی شده است.']);

    if ( jay_login_register_normalize_numbers($otp_entered) !== (string)$transient_data['otp'] ) {
        // مدیریت شمارش خطا و مسدودسازی
        $error_msg = jay_panel_handle_verification_failure($current_phone, $user_ip, $settings);
        wp_send_json_error(['message' => $error_msg]);
    }

    // موفقیت
    delete_transient('jay_login_register_otp_fail_count_' . $current_phone);
    delete_transient('jay_login_register_otp_fail_count_' . $user_ip);
    delete_transient('jay_panel_old_otp_' . $user_id);
    
    set_transient('jay_panel_permit_new_num_' . $user_id, 'allowed', 15 * MINUTE_IN_SECONDS);

    // فرم دریافت شماره جدید
    $html = '<h3>وارد کردن شماره جدید</h3>
    <p>هویت شما تایید شد. لطفاً شماره جدید را وارد کنید.</p>
    <form class="jay-panel-form">
        <div class="jay-login-register-field">
            <label>شماره موبایل جدید</label>
            <input type="tel" name="jay_panel_new_mobile" class="jay-login-register-input" placeholder="09xxxxxxxxx" dir="ltr">
        </div>
        <button type="button" class="jay-login-register-button" data-action="panel_send_new_mobile_otp">ارسال کد به شماره جدید</button>
        <div class="jay-login-register-messages"></div>
    </form>';

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'step' => 'enter_new']);
}
/**
 * پنل کاربری: مرحله ۳ - ارسال کد به شماره جدید
 */
add_action('wp_ajax_jay_panel_send_new_mobile_otp', 'jay_panel_ajax_send_new_mobile_otp');
function jay_panel_ajax_send_new_mobile_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    $user_id = get_current_user_id();
 // دریافت وضعیت "ثبت مستقیم"
    $is_direct_add = isset($_POST['is_direct_add']) && $_POST['is_direct_add'] == 1;

      // --- منطق امنیتی جدید ---
    if ( $is_direct_add ) {
        // اگر کاربر می‌گوید شماره ندارم، چک می‌کنیم که واقعاً در دیتابیس نداشته باشد
        $db_phone = get_user_meta($user_id, 'jay_mobile', true);
        if ( ! empty($db_phone) ) {
             wp_send_json_error(['message' => 'شما شماره ثبت شده دارید. لطفاً صفحه را رفرش کنید.']);
        }
    } else {
        // اگر تغییر شماره است، باید مجوز مرحله قبل (تایید شماره قدیم) را داشته باشد
        if ( ! get_transient('jay_panel_permit_new_num_' . $user_id) ) {
            wp_send_json_error(['message' => 'نشست شما منقضی شده. لطفاً مراحل را از اول طی کنید.']);
        }
    }

    // دریافت شماره: اگر از فرم اصلی آمد jay_panel_new_mobile، اگر از ارسال مجدد آمد user_input
    $new_phone = isset($_POST['jay_panel_new_mobile']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_new_mobile'])) : '';
    if ( empty($new_phone) && isset($_POST['user_input']) ) {
        $new_phone = sanitize_text_field(wp_unslash($_POST['user_input']));
    }
    $new_phone = jay_login_register_normalize_numbers($new_phone);

    if ( ! preg_match('/^09[0-9]{9}$/', $new_phone) ) {
        wp_send_json_error(['message' => 'فرمت شماره موبایل نامعتبر است.']);
    }
    
    // بررسی شماره فعلی خودش نباشد
    $current_phone = get_user_meta($user_id, 'jay_mobile', true);
    if ($new_phone == $current_phone) {
        wp_send_json_error(['message' => 'شماره جدید نمی‌تواند همان شماره فعلی باشد.']);
    }

    // --- بررسی یکتا بودن شماره (که کس دیگری نگرفته باشد) ---
    $phone_plus_98 = '+98' . substr($new_phone, 1);
// phpcs:disable WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
        $query = new WP_User_Query([
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'digits_phone', 'value' => $phone_plus_98],
            ['key' => 'jay_mobile', 'value' => $new_phone]
        ],
        'exclude' => [$user_id], // خود کاربر را نادیده بگیر
        'number' => 1,
        'fields' => 'ID'
    ]);
    // phpcs:enable
    
    if ( ! empty($query->get_results()) ) {
        wp_send_json_error(['message' => 'این شماره موبایل قبلاً توسط کاربر دیگری ثبت شده است.']);
    }

    // ارسال OTP
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    
    jay_login_register_check_and_handle_lockout( $new_phone, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('error', 'خطا'), $new_phone, $otp);
    if (is_wp_error($send_result)) wp_send_json_error(['message' => 'خطا در ارسال: ' . $send_result->get_error_message()]);

    set_transient('jay_panel_new_otp_' . $user_id, ['otp' => $otp, 'phone' => $new_phone, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);

$instruction = "کد تایید به شماره جدید ($new_phone) ارسال شد.";
    
    // دریافت HTML خام
    $raw_html = jay_login_register_get_otp_verification_form_html('تایید شماره جدید', $instruction, $new_phone, 'jay_panel_otp_input', 'panel_verify_new_mobile', 'panel_send_new_mobile_otp', 'panel_new');

    // **تغییر:** اضافه کردن تگ فرم
    $html = '<form class="jay-panel-form">' . $raw_html . '</form>';

    wp_send_json_success([
        'html' => Jay_Login_Register_Minifier::html($html),
        'step' => 'verify_new',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * پنل کاربری: مرحله ۴ - تایید نهایی و ذخیره
 */
add_action('wp_ajax_jay_panel_verify_new_mobile', 'jay_panel_ajax_verify_new_mobile');
function jay_panel_ajax_verify_new_mobile() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    $user_id = get_current_user_id();

    $otp_entered = isset($_POST['jay_panel_otp_input']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_otp_input'])) : '';
    $transient_data = get_transient('jay_panel_new_otp_' . $user_id);

    if (!$transient_data) wp_send_json_error(['message' => 'کد منقضی شده است.']);
    
    $new_phone = $transient_data['phone'];
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $new_phone, $user_ip, $settings );

    if ( jay_login_register_normalize_numbers($otp_entered) !== (string)$transient_data['otp'] ) {
        // مدیریت خطا برای شماره جدید
        $error_msg = jay_panel_handle_verification_failure($new_phone, $user_ip, $settings);
        wp_send_json_error(['message' => $error_msg]);
    }

    // موفقیت: پاکسازی خطاها
    delete_transient('jay_login_register_otp_fail_count_' . $new_phone);
    delete_transient('jay_login_register_otp_fail_count_' . $user_ip);
    delete_transient('jay_panel_new_otp_' . $user_id);
    delete_transient('jay_panel_permit_new_num_' . $user_id);

    // آپدیت شماره
    update_user_meta($user_id, 'jay_mobile', $new_phone);
    update_user_meta($user_id, 'digits_phone', '+98' . substr($new_phone, 1));
    update_user_meta($user_id, 'digits_phone_no', substr($new_phone, 1));

    $html = '<div class="jay-login-register-message-box jay-login-register-success" style="text-align:center; padding: 30px;">
        <span class="dashicons dashicons-yes-alt" style="font-size:50px; color:#28a745; margin-bottom:15px; width:auto; height:auto;"></span>
        <h3 style="margin-top:0;">عملیات موفقیت‌آمیز بود</h3>
        <p>شماره موبایل شما به <strong>'.esc_html($new_phone).'</strong> تغییر یافت.</p>
        <br>
        <button onclick="location.reload();" class="jay-login-register-button">بازگشت به پنل</button>
    </div>';

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'step' => 'completed']);
}

/* ====================================================================
   بخش تغییر ایمیل
   ==================================================================== */

/**
 * پنل کاربری: مرحله ۱ - ارسال کد به ایمیل قدیمی
 */
add_action('wp_ajax_jay_panel_send_old_email_otp', 'jay_panel_ajax_send_old_email_otp');
function jay_panel_ajax_send_old_email_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $current_email = $user->user_email;

    if (empty($current_email)) wp_send_json_error(['message' => 'ایمیلی برای شما ثبت نشده است.']);

    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    
    // بررسی مسدودیت
    jay_login_register_check_and_handle_lockout( $current_email, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);

    // ارسال ایمیل
    $subject = 'کد تایید تغییر ایمیل: ' . $otp;
    $body = "کد تایید شما برای تغییر ایمیل:\n\n" . $otp;
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    $sent = wp_mail($current_email, $subject, nl2br($body), $headers);

    if (!$sent) {
        $error_msg = 'خطا در ارسال ایمیل.';
        if (isset($GLOBALS['jay_relog_mail_error'])) {
            $error_msg .= ' ' . $GLOBALS['jay_relog_mail_error'];
            unset($GLOBALS['jay_relog_mail_error']);
        }
        wp_send_json_error(['message' => $error_msg]);
    }

    set_transient('jay_panel_old_email_otp_' . $user_id, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);

    $instruction = "کد تایید به ایمیل فعلی شما ($current_email) ارسال شد.";
    $raw_html = jay_login_register_get_otp_verification_form_html('تایید ایمیل فعلی', $instruction, $current_email, 'jay_panel_otp_input', 'panel_verify_old_email', 'panel_send_old_email_otp', 'panel_email_old');
    
    // افزودن تگ فرم
    $html = '<form class="jay-panel-form">' . $raw_html . '</form>';

    wp_send_json_success([
        'html' => Jay_Login_Register_Minifier::html($html),
        'step' => 'verify_old',
        'validity_period' => $validity_period * 60
    ]);
}

/**
 * پنل کاربری: مرحله ۲ - بررسی کد ایمیل قدیمی
 */
add_action('wp_ajax_jay_panel_verify_old_email', 'jay_panel_ajax_verify_old_email');
function jay_panel_ajax_verify_old_email() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $current_email = $user->user_email;
    
    $otp_entered = isset($_POST['jay_panel_otp_input']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_otp_input'])) : '';
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $current_email, $user_ip, $settings );

    $transient_data = get_transient('jay_panel_old_email_otp_' . $user_id);
    if (!$transient_data) wp_send_json_error(['message' => 'کد منقضی شده است.']);

    if ( jay_login_register_normalize_numbers($otp_entered) !== (string)$transient_data['otp'] ) {
        // چون تابع هندلر خطا برای شماره نوشته شده بود، اینجا دستی هندل می‌کنیم یا تابع جدید می‌سازیم
        // برای سادگی از همان تابع استفاده می‌کنیم چون منطق شمارش یکی است (فقط نام ترنزینت فرق دارد)
        // اما چون کلید ترنزینت فرق دارد، باید منطق بلاک ایمیل را جدا بنویسیم یا از لاجیک عمومی استفاده کنیم.
        // اینجا از لاجیک ساده استفاده می‌کنیم:
        
        $fail_key = 'jay_login_register_otp_fail_count_' . $current_email;
        $fail_count = get_transient($fail_key) ?: 0;
        $fail_count++;
        $max_retries = intval($settings['otp_max_retries'] ?? 3);
        
        if ($fail_count >= $max_retries) {
            set_transient('jay_login_register_otp_block_' . $current_email, 'blocked', 15 * MINUTE_IN_SECONDS);
            delete_transient($fail_key);
            wp_send_json_error(['message' => 'شما مسدود شدید.', 'lockout_timer' => 15 * 60]);
        } else {
            set_transient($fail_key, $fail_count, 15 * MINUTE_IN_SECONDS);
        }
        $rem = $max_retries - $fail_count;
        wp_send_json_error(['message' => "کد اشتباه است. $rem تلاش باقی‌مانده."]);
    }

    delete_transient('jay_login_register_otp_fail_count_' . $current_email);
    delete_transient('jay_panel_old_email_otp_' . $user_id);
    
    set_transient('jay_panel_permit_new_email_' . $user_id, 'allowed', 15 * MINUTE_IN_SECONDS);

    $html = '<h3>وارد کردن ایمیل جدید</h3>
    <p>هویت شما تایید شد. لطفاً ایمیل جدید را وارد کنید.</p>
    <form class="jay-panel-form">
        <div class="jay-login-register-field">
            <label>آدرس ایمیل جدید</label>
            <input type="email" name="jay_panel_new_email" class="jay-login-register-input jay-input-icon-email" placeholder="example@mail.com" dir="ltr">
        </div>
        <button type="button" class="jay-login-register-button" data-action="panel_send_new_email_otp">ارسال کد به ایمیل جدید</button>
        <div class="jay-login-register-messages"></div>
    </form>';

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'step' => 'enter_new']);
}

/**
 * پنل کاربری: مرحله ۳ - ارسال کد به ایمیل جدید
 */
add_action('wp_ajax_jay_panel_send_new_email_otp', 'jay_panel_ajax_send_new_email_otp');
function jay_panel_ajax_send_new_email_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    $user_id = get_current_user_id();

if ( ! get_transient('jay_panel_permit_new_email_' . $user_id) ) {
        wp_send_json_error(['message' => 'نشست منقضی شده. لطفاً مجدد تلاش کنید.']);
    }

    // دریافت ایمیل: اگر از فرم اصلی آمد jay_panel_new_email، اگر از ارسال مجدد آمد user_input
    $new_email = isset($_POST['jay_panel_new_email']) ? sanitize_email(wp_unslash($_POST['jay_panel_new_email'])) : '';
    if ( empty($new_email) && isset($_POST['user_input']) ) {
        $new_email = sanitize_email(wp_unslash($_POST['user_input']));
    }
    if ( ! is_email($new_email) ) {
        wp_send_json_error(['message' => 'فرمت ایمیل نامعتبر است.']);
    }
    
    if ( email_exists($new_email) ) {
        wp_send_json_error(['message' => 'این ایمیل قبلاً ثبت شده است.']);
    }

    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    jay_login_register_check_and_handle_lockout( $new_email, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);

    $subject = 'تایید ایمیل جدید: ' . $otp;
    $body = "کد تایید برای ایمیل جدید:\n\n" . $otp;
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    if ( ! wp_mail($new_email, $subject, nl2br($body), $headers) ) {
        wp_send_json_error(['message' => 'خطا در ارسال ایمیل.']);
    }

    set_transient('jay_panel_new_email_otp_' . $user_id, ['otp' => $otp, 'email' => $new_email, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);

    $instruction = "کد تایید به ایمیل جدید ($new_email) ارسال شد.";
    $raw_html = jay_login_register_get_otp_verification_form_html('تایید ایمیل جدید', $instruction, $new_email, 'jay_panel_otp_input', 'panel_verify_new_email', 'panel_send_new_email_otp', 'panel_email_new');
    $html = '<form class="jay-panel-form">' . $raw_html . '</form>';

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'step' => 'verify_new', 'validity_period' => $validity_period * 60]);
}

/**
 * پنل کاربری: مرحله ۴ - تایید نهایی ایمیل
 */
add_action('wp_ajax_jay_panel_verify_new_email', 'jay_panel_ajax_verify_new_email');
function jay_panel_ajax_verify_new_email() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    $user_id = get_current_user_id();
    
    $otp_entered = isset($_POST['jay_panel_otp_input']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_otp_input'])) : '';
    $transient_data = get_transient('jay_panel_new_email_otp_' . $user_id);

    if (!$transient_data) wp_send_json_error(['message' => 'کد منقضی شده است.']);
    
    $new_email = $transient_data['email'];

    if ( jay_login_register_normalize_numbers($otp_entered) !== (string)$transient_data['otp'] ) {
        // (منطق بلاک ساده شده)
        wp_send_json_error(['message' => 'کد اشتباه است.']);
    }

    // آپدیت ایمیل
    $update = wp_update_user([ 'ID' => $user_id, 'user_email' => $new_email ]);
    
    if ( is_wp_error($update) ) {
        wp_send_json_error(['message' => 'خطا در ذخیره: ' . $update->get_error_message()]);
    }

    // پاکسازی
    delete_transient('jay_panel_new_email_otp_' . $user_id);
    delete_transient('jay_panel_permit_new_email_' . $user_id);

    $html = '<div class="jay-login-register-message-box jay-login-register-success" style="text-align:center; padding: 30px;">
        <span class="dashicons dashicons-yes-alt" style="font-size:50px; color:#28a745; margin-bottom:15px; width:auto; height:auto;"></span>
        <h3 style="margin-top:0;">تغییر ایمیل موفقیت‌آمیز بود</h3>
        <p>ایمیل شما به <strong>'.esc_html($new_email).'</strong> تغییر یافت.</p>
        <br>
        <button onclick="location.reload();" class="jay-login-register-button">بازگشت به پنل</button>
    </div>';

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'step' => 'completed']);
}
/* ====================================================================
   بخش تغییر رمز عبور
   ==================================================================== */

/**
 * بررسی زنده رمز عبور فعلی (AJAX Live Check)
 */
add_action('wp_ajax_jay_panel_check_current_password', 'jay_panel_ajax_check_current_password');
function jay_panel_ajax_check_current_password() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error();
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
    $user = wp_get_current_user();

    if ( wp_check_password( $password, $user->user_pass, $user->ID ) ) {
        // رمز درست است
        // یک مجوز موقت (Transient) ایجاد می‌کنیم تا کاربر بتواند در مرحله بعد رمز را عوض کند
        set_transient('jay_panel_pass_permit_' . $user->ID, 'allowed', 10 * MINUTE_IN_SECONDS);
        wp_send_json_success(['message' => 'رمز عبور صحیح است.']);
    } else {
        wp_send_json_error(['message' => 'رمز عبور اشتباه است.']);
    }
}

/**
 * ذخیره نهایی رمز عبور جدید
 */
add_action('wp_ajax_jay_panel_change_password_final', 'jay_panel_ajax_change_password_final');
function jay_panel_ajax_change_password_final() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    $user_id = get_current_user_id();

    // بررسی مجوز مرحله قبل
    if ( ! get_transient('jay_panel_pass_permit_' . $user_id) ) {
        wp_send_json_error(['message' => 'نشست امنیتی منقضی شده است. لطفاً صفحه را رفرش کنید و مجدد تلاش کنید.']);
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $new_pass = isset($_POST['jay_new_password']) ? wp_unslash($_POST['jay_new_password']) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $confirm_pass = isset($_POST['jay_confirm_password']) ? wp_unslash($_POST['jay_confirm_password']) : '';

    if ( empty($new_pass) || empty($confirm_pass) ) {
        wp_send_json_error(['message' => 'لطفاً تمام فیلدها را پر کنید.']);
    }

    if ( $new_pass !== $confirm_pass ) {
        wp_send_json_error(['message' => 'رمز عبور و تکرار آن مطابقت ندارند.']);
    }

    // بررسی تنظیمات "رمز قوی"
    $panel_settings = get_option( 'jay_login_register_user_panel_settings', [] );
    $strong_required = isset($panel_settings['enable_strong_password']) && $panel_settings['enable_strong_password'] === 'yes';

    if ( strlen($new_pass) < 8 ) {
        wp_send_json_error(['message' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.']);
    }

    if ( $strong_required ) {
        // حداقل یک حرف بزرگ، یک حرف کوچک و یک عدد
        if ( !preg_match('/[a-z]/', $new_pass) || !preg_match('/[A-Z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass) ) {
            wp_send_json_error(['message' => 'رمز عبور ضعیف است. (باید شامل حروف بزرگ، کوچک و عدد باشد).']);
        }
    }

    // تغییر رمز
    wp_set_password( $new_pass, $user_id );
    
    // پاکسازی مجوز
    delete_transient('jay_panel_pass_permit_' . $user_id);

    // لاگین مجدد کاربر (چون wp_set_password کاربر را لاگ‌اوت می‌کند)
    $user = get_userdata($user_id);
    wp_set_current_user($user_id, $user->user_login);
    wp_set_auth_cookie($user_id);

    $html = '<div class="jay-login-register-message-box jay-login-register-success" style="text-align:center; padding: 30px;">
        <span class="dashicons dashicons-lock" style="font-size:50px; color:#28a745; margin-bottom:15px; width:auto; height:auto;"></span>
        <h3 style="margin-top:0;">تغییر رمز موفقیت‌آمیز بود</h3>
        <p>رمز عبور شما با موفقیت تغییر کرد.</p>
    </div>';

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
}
/* ====================================================================
   بخش ویرایش پروفایل
   ==================================================================== */

/**
 * بررسی زنده نام کاربری (اختصاصی پنل - با نادیده گرفتن خود کاربر)
 */
add_action('wp_ajax_jay_panel_check_username_live', 'jay_panel_ajax_check_username_live');
function jay_panel_ajax_check_username_live() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
    $user_id = get_current_user_id();
    $current_user = get_userdata($user_id);

    // اگر نام کاربری تغییر نکرده
    if ($username === $current_user->user_login) {
        wp_send_json_success(['message' => 'نام کاربری فعلی شماست.', 'status' => 'current']);
    }

    // اعتبارسنجی‌ها
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        wp_send_json_error(['message' => 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و _ باشد.']);
    }
    if (strlen($username) < 4) {
        wp_send_json_error(['message' => 'حداقل ۴ کاراکتر باشد.']);
    }
    
    // بررسی تکراری بودن
    if (username_exists($username)) {
        wp_send_json_error(['message' => 'این نام کاربری قبلاً گرفته شده است.']);
    }

    $illegal_names = ['admin', 'administrator', 'root', 'support', 'test'];
    if (in_array(strtolower($username), $illegal_names)) {
        wp_send_json_error(['message' => 'نام کاربری غیرمجاز است.']);
    }

    wp_send_json_success(['message' => 'نام کاربری آزاد است.', 'status' => 'valid']);
}

/**
 * ذخیره تغییرات پروفایل
 */
/**
 * ذخیره تغییرات پروفایل (اصلاح شده برای پاک کردن فیلدها)
 */
add_action('wp_ajax_jay_panel_update_profile', 'jay_panel_ajax_update_profile');
function jay_panel_ajax_update_profile() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    $user_id = get_current_user_id();

    $can_edit = get_user_meta( $user_id, 'jay_can_edit_profile', true );
    if ( $can_edit === '0' ) {
        wp_send_json_error(['message' => 'شما اجازه ویرایش پروفایل خود را ندارید.']);
    }
    $user = get_userdata($user_id);
    $settings = get_option( 'jay_login_register_user_panel_settings', [] );
    
    $errors = [];
    $update_data = ['ID' => $user_id]; 

    // =======================================================
    // 1. اعتبارسنجی فیلدهای ضروری سفارشی (Validation Only)
    // =======================================================
    $custom_fields_json = isset($settings['profile_custom_fields_json']) ? $settings['profile_custom_fields_json'] : '[]';
    $custom_fields = json_decode($custom_fields_json, true);

if (is_array($custom_fields)) {
        foreach ($custom_fields as $field) {
            
            // --- 1. بررسی شرط متاکی (Server Side Security) ---
            // اگر کاربر متاکی لازم را نداشت، اصلا این فیلد برایش وجود ندارد -> رد کن
            if ( ! empty($field['logic_meta_rules']) && is_array($field['logic_meta_rules']) ) {
                $meta_relation = isset($field['logic_meta_relation']) ? $field['logic_meta_relation'] : 'AND';
                $meta_passed = ($meta_relation === 'AND') ? true : false;
                foreach ( $field['logic_meta_rules'] as $rule_key ) {
                    $has_meta = metadata_exists( 'user', $user_id, $rule_key );
                    if ( $meta_relation === 'AND' ) { if(!$has_meta){ $meta_passed=false; break; } }
                    else { if($has_meta){ $meta_passed=true; break; } }
                }
                if ( ! $meta_passed ) continue; 
            }

            // --- 2. بررسی شرط فیلد (Client Side Logic Validation in Server) ---
            // اگر شرط برقرار نبود، این فیلد مخفی بوده، پس نباید اعتبارسنجی شود
            if ( ! empty($field['logic_field_rules']) && is_array($field['logic_field_rules']) ) {
                $logic_relation = isset($field['logic_field_relation']) ? $field['logic_field_relation'] : 'AND';
                $logic_passed = ($logic_relation === 'AND') ? true : false;

                foreach ( $field['logic_field_rules'] as $rule ) {
                    $target_key = isset($rule['target']) ? $rule['target'] : '';
                    $operator   = isset($rule['operator']) ? $rule['operator'] : '=';
                    $expected   = isset($rule['value']) ? $rule['value'] : '';

                    // مقدار فیلد هدف را از $_POST می‌خوانیم
                    $target_post_name = 'jay_panel_meta_' . $target_key;
                    $actual_val = isset($_POST[$target_post_name]) ? sanitize_text_field(wp_unslash($_POST[$target_post_name])) : '';

                    $condition_met = false;
                    if ($operator === '=') { $condition_met = ($actual_val == $expected); }
                    elseif ($operator === '!=') { $condition_met = ($actual_val != $expected); }

                    if ( $logic_relation === 'AND' ) { if(!$condition_met){ $logic_passed=false; break; } }
                    else { if($condition_met){ $logic_passed=true; break; } }
                }

                // اگر شرط نمایش برقرار نبود، از بررسی و ذخیره این فیلد صرف نظر کن
                if ( ! $logic_passed ) continue;
            }

            // --- پایان بررسی شرط‌ها ---

            // چک می‌کنیم آیا فیلد ضروری است؟ (ادامه کد قبلی)
            if (isset($field['is_required']) && $field['is_required'] == 1) {
                // نام فیلد در POST با نامی که در شورت‌کد ساخته شده باید یکی باشد
                // شورت‌کد از sanitize_key استفاده می‌کند، پس اینجا هم باید استفاده کنیم
                $key = isset($field['key']) ? sanitize_key($field['key']) : '';
                $input_name = 'jay_panel_meta_' . $key;
                $label = $field['label'];

                if (empty($key)) continue;

                // بررسی وجود مقدار
                // برای چک‌باکس‌ها اگر تیک نخورند ارسال نمی‌شوند
                if (!isset($_POST[$input_name])) {
                    $errors[] = "لطفاً فیلد «{$label}» را پر کنید.";
                } else {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $val = isset($_POST[$input_name]) ? wp_unslash($_POST[$input_name]) : '';
                    if (is_array($val)) {
                        // آرایه خالی نباشد
                        if (empty($val)) $errors[] = "لطفاً فیلد «{$label}» را پر کنید.";
                    } else {
                        // رشته خالی نباشد
                        if (trim($val) === '') $errors[] = "لطفاً فیلد «{$label}» را پر کنید.";
                    }
                }
            }
            // اعتبارسنجی اختصاصی نوع شماره
            if ( isset($field['type']) && $field['type'] === 'number' ) {
                    $key = $field['key'];
                    $input_name = 'jay_panel_meta_' . $key;
                    
                    if ( !empty($_POST[$input_name]) ) {
                        $val = sanitize_text_field(wp_unslash($_POST[$input_name]));
                        // تبدیل اعداد فارسی به انگلیسی
                        $val = jay_login_register_normalize_numbers($val);
                        
                        // 1. بررسی عددی بودن
                        if ( !ctype_digit($val) ) {
                            $errors[] = "فیلد «{$field['label']}» فقط باید شامل عدد باشد.";
                        }
                        
                        // 2. بررسی طول دقیق
                        if ( !empty($field['number_len']) ) {
                            $len = (int)$field['number_len'];
                            if ( strlen($val) !== $len ) {
                                $errors[] = "فیلد «{$field['label']}» باید دقیقاً {$len} رقم باشد.";
                            }
                        }
                        
                        // 3. بررسی پیش‌شماره
                        if ( !empty($field['number_start']) ) {
                            $start = (string)$field['number_start'];
                            if ( strpos($val, $start) !== 0 ) {
                                $errors[] = "فیلد «{$field['label']}» باید با {$start} شروع شود.";
                            }
                        }
                    }
                }
        }
    }

    // =======================================================
    // 2. اعتبارسنجی نام کاربری
    // =======================================================
    if (isset($settings['enable_username_edit']) && $settings['enable_username_edit'] === 'yes') {
        if (isset($_POST['jay_panel_username'])) {
            $new_username = sanitize_text_field(wp_unslash($_POST['jay_panel_username']));
            if ($new_username !== $user->user_login && !empty($new_username)) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
                    $errors[] = 'فرمت نام کاربری نامعتبر است.';
                } elseif (username_exists($new_username)) {
                    $errors[] = 'نام کاربری تکراری است.';
                } else {
                    global $wpdb;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->update($wpdb->users, ['user_login' => $new_username], ['ID' => $user_id]);
                    clean_user_cache($user_id);
                    if ($user->display_name === $user->user_login) {
                        $update_data['display_name'] = $new_username;
                    }
                }
            }
        }
    }

    // =======================================================
    // 3. اعتبارسنجی نام و نام خانوادگی
    // =======================================================
    if (isset($settings['enable_name_edit']) && $settings['enable_name_edit'] === 'yes') {
        $fname = isset($_POST['jay_panel_first_name']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_first_name'])) : '';
        $lname = isset($_POST['jay_panel_last_name']) ? sanitize_text_field(wp_unslash($_POST['jay_panel_last_name'])) : '';
        $req_name = isset($settings['required_name_edit']) && $settings['required_name_edit'] === 'yes';

        if ($req_name && (empty($fname) || empty($lname))) {
            $errors[] = 'لطفاً نام و نام خانوادگی را وارد کنید.';
        }
        if (isset($settings['force_persian_name']) && $settings['force_persian_name'] === 'yes') {
            if (!empty($fname) && !preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $fname)) {
                $errors[] = 'نام باید فقط حروف فارسی باشد.';
            }
            if (!empty($lname) && !preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $lname)) {
                $errors[] = 'نام خانوادگی باید فقط حروف فارسی باشد.';
            }
        }

        if (empty($errors)) { 
            $update_data['first_name'] = $fname;
            $update_data['last_name'] = $lname;
            if (!empty($fname) || !empty($lname)) {
                $update_data['display_name'] = trim($fname . ' ' . $lname);
            }
        }
    }

    // =======================================================
    // 4. اعتبارسنجی کد ملی
    // =======================================================
    $nc_to_save = null;
    $should_delete_nc = false;

    if (isset($settings['enable_national_code_edit']) && $settings['enable_national_code_edit'] === 'yes') {
        $req_nc = isset($settings['required_national_code_edit']) && $settings['required_national_code_edit'] === 'yes';
        if (isset($_POST['jay_panel_national_code'])) {
            $nc = sanitize_text_field(wp_unslash($_POST['jay_panel_national_code']));
            $nc = jay_login_register_normalize_numbers($nc);
            
            if (empty($nc)) {
                if ($req_nc) {
                    $errors[] = 'لطفاً کد ملی را وارد کنید.';
                } else {
                    $should_delete_nc = true;
                }
                
                
            } else {
                if (!is_valid_iranian_national_code($nc)) {
                    $errors[] = 'کد ملی نامعتبر است.';
                } else {
                    // phpcs:disable WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                    $nc_check = new WP_User_Query([
                        'meta_key' => 'codemeli', 'meta_value' => $nc, 
                        'exclude' => [$user_id], 'number' => 1
                    ]);
                    // phpcs:enable
                    if (!empty($nc_check->get_results())) {
                        $errors[] = 'این کد ملی قبلاً ثبت شده است.';
                    } else {
                        $nc_to_save = $nc;
                    }
                }
            }
        }
    }

    // =======================================================
    // 5. اعتبارسنجی گذرنامه
    // =======================================================
    $pass_to_save = null;
    $should_delete_pass = false;

    if (isset($settings['enable_passport_edit']) && $settings['enable_passport_edit'] === 'yes') {
        $req_pass = isset($settings['required_passport_edit']) && $settings['required_passport_edit'] === 'yes';
        if (isset($_POST['jay_panel_passport'])) {
            $pass = sanitize_text_field(wp_unslash($_POST['jay_panel_passport']));
            $pass = jay_login_register_normalize_numbers($pass);
            
            if (empty($pass)) {
                if ($req_pass) {
                    $errors[] = 'لطفاً شماره گذرنامه را وارد کنید.';
                } else {
                    $should_delete_pass = true;
                }
                
                
            } else {
// phpcs:disable WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                $pass_check = new WP_User_Query([
                    'meta_key' => 'gozarname', 'meta_value' => $pass, 
                    'exclude' => [$user_id], 'number' => 1
                ]);
                // phpcs:enable
                if (!empty($pass_check->get_results())) {
                    $errors[] = 'این شماره گذرنامه تکراری است.';
                } else {
                    $pass_to_save = $pass;
                }
            }
        }
    }

    // ===========================================================
    // >>> نقطه توقف: اگر خطایی بود، همینجا قطع کن و ذخیره نکن <<<
    // ===========================================================
    if (!empty($errors)) {
        wp_send_json_error(['message' => implode('<br>', $errors)]);
        // دستورات بعد از این خط اجرا نمی‌شوند
    }

    // ===========================================================
    // عملیات ذخیره‌سازی (فقط زمانی اجرا می‌شود که خطایی نباشد)
    // ===========================================================

    // 1. ذخیره اطلاعات اصلی
    wp_update_user($update_data);

    // 2. ذخیره کد ملی
    if ($should_delete_nc) delete_user_meta($user_id, 'codemeli');
    elseif ($nc_to_save) update_user_meta($user_id, 'codemeli', $nc_to_save);

    // 3. ذخیره گذرنامه
    if ($should_delete_pass) delete_user_meta($user_id, 'gozarname');
    elseif ($pass_to_save) update_user_meta($user_id, 'gozarname', $pass_to_save);

    // 4. ذخیره فیلدهای سفارشی (این حلقه از بالا به اینجا منتقل شد) ✅
// ===========================================================
// 6. ذخیره فیلدهای سفارشی با لیست سفید امن
// ===========================================================

// 6.1 ایجاد لیست سفید از فیلدهای مجاز
$allowed_custom_fields = [];

// 6.2 فقط فیلدهایی که در تنظیمات پنل کاربری تعریف شده‌اند مجازند
if (is_array($custom_fields)) {
    foreach ($custom_fields as $field) {
        if (isset($field['key']) && !empty($field['key'])) {
            $key = sanitize_key($field['key']);
            $allowed_custom_fields[] = $key;
        }
    }
}

// 6.3 افزودن فیلدهای سیستمی مجاز (در صورت نیاز)
    $allowed_custom_fields = array_unique($allowed_custom_fields);

    // 6.4 هوک برای توسعه‌دهندگان - این رو اضافه کن
    $allowed_custom_fields = apply_filters('jay_login_register_allowed_profile_fields', $allowed_custom_fields, $user_id);
    
// 6.4 مسدود کردن کلیدهای حساس وردپرس (Blacklist مهم)
$disallowed_meta_keys = [
    'wp_capabilities',
    'wp_user_level',
    'admin_color',
    'rich_editing',
    'comment_shortcuts',
    'show_admin_bar_front',
    'session_tokens',
    'user-settings',
    'user-settings-time'
];
    // 6.6 هوک برای لیست غیرمجاز - این رو اضافه کن
    $disallowed_meta_keys = apply_filters('jay_login_register_disallowed_meta_keys', $disallowed_meta_keys, $user_id);
    
// 6.5 همچنین مسدود کردن هر کلیدی که با wp_ شروع می‌شود
foreach ($_POST as $key => $value) {
    if (strpos($key, 'jay_panel_meta_') === 0) {
        $meta_key = substr($key, 15);
        $meta_key = sanitize_key($meta_key);
        
        // بررسی 1: مسدود کردن کلیدهای حساس
        if (in_array($meta_key, $disallowed_meta_keys, true)) {
            continue; // رد کردن این فیلد
        }
        
        // بررسی 2: مسدود کردن کلیدهایی که با wp_ شروع می‌شوند
        if (strpos($meta_key, 'wp_') === 0) {
            continue; // رد کردن این فیلد
        }
        
        // بررسی 3: فقط کلیدهای موجود در لیست سفید مجازند
        if (!in_array($meta_key, $allowed_custom_fields, true)) {
            continue; // رد کردن این فیلد
        }
        
        // 6.6 اعتبارسنجی و ذخیره ایمن
        if (is_array($value)) {
            $sanitized_value = array_map('sanitize_textarea_field', wp_unslash($value));
        } else {
            $sanitized_value = sanitize_text_field(wp_unslash($value));
            
            // برای فیلدهای عددی، اعداد فارسی به انگلیسی تبدیل شوند
            $is_number_field = false;
            foreach ($custom_fields as $cf) {
                if (isset($cf['key']) && $cf['key'] === $meta_key && isset($cf['type']) && $cf['type'] === 'number') {
                    $is_number_field = true;
                    break;
                }
            }
            
            if ($is_number_field) {
                $sanitized_value = jay_login_register_normalize_numbers($sanitized_value);
            }
        }
        
        // 6.7 ذخیره نهایی
        update_user_meta($user_id, $meta_key, $sanitized_value);
    }
}     
    // --- Developer Hook: Action after profile update ---
    do_action( 'jay_login_register_after_profile_update', $user_id, $_POST );
    wp_send_json_success(['message' => 'تغییرات با موفقیت ذخیره شد.', 'redirect' => true]);
}
/**
 * بررسی زنده کد ملی
 */
add_action('wp_ajax_jay_panel_check_national_code_live', 'jay_panel_ajax_check_national_code_live');
function jay_panel_ajax_check_national_code_live() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $nc = isset($_POST['national_code']) ? sanitize_text_field(wp_unslash($_POST['national_code'])) : '';
    $nc = jay_login_register_normalize_numbers($nc);
    $user_id = get_current_user_id();

    // اگر خالی است
    if (empty($nc)) {
        wp_send_json_success(['message' => '', 'status' => 'empty']); // پیام خالی
    }

    // اعتبار سنجی فرمت
    if (!is_valid_iranian_national_code($nc)) {
        wp_send_json_error(['message' => 'کد ملی نامعتبر است.']);
    }

    // تکراری بودن
// phpcs:disable WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
    $query = new WP_User_Query([
        'meta_key' => 'codemeli', 
        'meta_value' => $nc, 
        'exclude' => [$user_id], 
        'number' => 1
    ]);
    // phpcs:enable

    if (!empty($query->get_results())) {
        wp_send_json_error(['message' => 'این کد ملی متعلق به شخص دیگری است.']);
    }

    wp_send_json_success(['message' => 'کد ملی معتبر است.', 'status' => 'valid']);
}

/**
 * بررسی زنده گذرنامه
 */
add_action('wp_ajax_jay_panel_check_passport_live', 'jay_panel_ajax_check_passport_live');
function jay_panel_ajax_check_passport_live() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $pass = isset($_POST['passport']) ? sanitize_text_field(wp_unslash($_POST['passport'])) : '';
    $pass = jay_login_register_normalize_numbers($pass);
    $user_id = get_current_user_id();

    if (empty($pass)) {
        wp_send_json_success(['message' => '', 'status' => 'empty']);
    }

    if ( ! is_valid_passport_format($pass) ) {
        wp_send_json_error(['message' => 'فرمت گذرنامه نامعتبر است.']);
    }

    // تکراری بودن
// phpcs:disable WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
    $query = new WP_User_Query([
        'meta_key' => 'gozarname', 
        'meta_value' => $pass, 
        'exclude' => [$user_id], 
        'number' => 1
    ]);
    // phpcs:enable

    if (!empty($query->get_results())) {
        wp_send_json_error(['message' => 'این گذرنامه قبلاً ثبت شده است.']);
    }

    wp_send_json_success(['message' => 'شماره گذرنامه معتبر است.', 'status' => 'valid']);
}
/**
 * آپلود عکس پروفایل (آواتار)
 */
add_action('wp_ajax_jay_panel_upload_avatar', 'jay_panel_ajax_upload_avatar');
function jay_panel_ajax_upload_avatar() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    if ( ! isset($_FILES['avatar_file']) || empty($_FILES['avatar_file']) ) {
        wp_send_json_error(['message' => 'هیچ فایلی ارسال نشد.']);
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $file = $_FILES['avatar_file'];
    
    // 1. اعتبارسنجی نوع فایل (Mime Type)
    $file_type = wp_check_filetype( $file['name'] );
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if ( ! in_array( $file_type['type'], $allowed_types ) ) {
        wp_send_json_error(['message' => 'فرمت فایل مجاز نیست. فقط JPG, PNG, GIF مجاز است.']);
    }

    // 2. محدودیت حجم (مثلاً 2 مگابایت)
    $max_size = 2 * 1024 * 1024; 
    if ( $file['size'] > $max_size ) {
        wp_send_json_error(['message' => 'حجم فایل نباید بیشتر از 2 مگابایت باشد.']);
    }

    // 3. استفاده از توابع وردپرس برای آپلود ایمن
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );

    $attachment_id = media_handle_sideload( $file, 0 );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error(['message' => 'خطا در آپلود: ' . $attachment_id->get_error_message()]);
    }

    // 4. دریافت متاکی از تنظیمات
    $settings = get_option( 'jay_login_register_user_panel_settings', [] );
    $meta_key = ! empty( $settings['custom_avatar_meta_key'] ) ? $settings['custom_avatar_meta_key'] : 'jay_login_register_custom_avatar';

    // 5. حذف عکس قبلی (اختیاری - برای جلوگیری از انباشت فایل‌ها)
    $old_avatar_id = get_user_meta( get_current_user_id(), $meta_key, true );
    if ( $old_avatar_id ) {
        wp_delete_attachment( $old_avatar_id, true );
    }

    // 6. ذخیره ID عکس جدید
    $user_id = get_current_user_id();
    update_user_meta( $user_id, $meta_key, $attachment_id );
    $full_image_url = wp_get_attachment_url( $attachment_id );
    /**
     * هوک اختصاصی بعد از آپلود موفقیت‌آمیز آواتار
     * * @param int    $user_id       شناسه کاربر
     * @param int    $attachment_id شناسه عکس در رسانه وردپرس
     * @param string $full_image_url آدرس کامل (لینک) عکس آپلود شده
     */
    do_action( 'jay_login_register_after_avatar_upload', $user_id, $attachment_id, $full_image_url );
    // --- Developer Hook End ---
    // 7. بازگرداندن لینک عکس برای نمایش آنی
    $new_avatar_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

    wp_send_json_success([
        'message' => 'عکس پروفایل با موفقیت تغییر کرد.',
        'url' => $new_avatar_url
    ]);
}

/**
 * حذف عکس پروفایل
 */
add_action('wp_ajax_jay_panel_delete_avatar', 'jay_panel_ajax_delete_avatar');
function jay_panel_ajax_delete_avatar() {
    check_ajax_referer('jay_login_register_nonce_action', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $settings = get_option( 'jay_login_register_user_panel_settings', [] );
    $meta_key = ! empty( $settings['custom_avatar_meta_key'] ) ? $settings['custom_avatar_meta_key'] : 'jay_login_register_custom_avatar';

    $old_avatar_id = get_user_meta( get_current_user_id(), $meta_key, true );
    
    if ( $old_avatar_id ) {
        wp_delete_attachment( $old_avatar_id, true );
        delete_user_meta( get_current_user_id(), $meta_key );
    } 

    // بازگرداندن آدرس گراواتار پیش‌فرض
    $default_avatar = get_avatar_url( get_current_user_id() );

    wp_send_json_success(['message' => 'عکس پروفایل حذف شد.', 'url' => $default_avatar]);
}
