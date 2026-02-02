<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ====================================================================
 * 1. AJAX Action: دریافت فرم اولیه
 * ====================================================================
 */
add_action( 'wp_ajax_nopriv_jay_get_inline_lock_form', 'jay_login_register_ajax_get_inline_lock_form_callback' );

function jay_login_register_ajax_get_inline_lock_form_callback() {
    check_ajax_referer( 'jay_inline_lock_nonce', 'nonce' );

    $settings = get_option('jay_login_register_settings');
    $captcha_handler = jay_relog_captcha_handler();
    $captcha_type = $captcha_handler->get_captcha_type();
    $google_login_enabled = isset($settings['google_login_enable']) && $settings['google_login_enable'] === 'yes' && !empty($settings['google_client_id']);
    $email_otp_enabled = isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes';
    $mobile_enabled = in_array('mobile', $settings['login_methods'] ?? ['mobile'], true);
    $email_enabled = in_array('email', $settings['login_methods'] ?? ['mobile'], true);
    $username_enabled = isset($settings['enable_username']) && $settings['enable_username'] === 'yes';
    $user_ip = jay_login_register_get_user_ip();

    if ( ! session_id() && ! headers_sent() ) { @session_start(); }

    // ذخیره ریدایرکت
    $current_page_url = isset($_POST['current_url']) ? esc_url_raw(wp_unslash($_POST['current_url'])) : home_url('/');
    $custom_redirect_url = isset($_POST['custom_redirect']) ? esc_url_raw(wp_unslash($_POST['custom_redirect'])) : '';
    $final_redirect_url = !empty($custom_redirect_url) ? $custom_redirect_url : $current_page_url;
    $_SESSION['jay_google_redirect_url'] = $final_redirect_url;

    // بررسی اولیه مسدودیت
    $lockout_error = jay_login_register_check_lockout_status(null, $user_ip, $settings);
    if (is_wp_error($lockout_error)) {
        wp_send_json_error([
            'message' => $lockout_error->get_error_message(),
            'lockout_timer' => $lockout_error->get_error_data()['lockout_timer'] ?? 0
        ]);
    }

 // ساخت HTML
    $form_html = '<div class="jay-inline-lock-form-wrapper">';
    $form_html .= '<h4>ورود یا عضویت سریع</h4>';

    $active_methods = [];
    if ($mobile_enabled) $active_methods[] = 'شماره موبایل';
    if ($email_enabled) $active_methods[] = 'ایمیل';
    if ($username_enabled) $active_methods[] = 'نام کاربری';

    if (empty($active_methods)) {
        wp_send_json_error(['message' => 'هیچ روش ورودی فعال نیست.']);
    }

    $placeholder = implode(' / ', $active_methods);
    $field_name = 'jay_inline_user_input';
    
    // تعیین نوع فیلد: اگر نام کاربری یا چند روشی باشد، باید text باشد
    if (count($active_methods) === 1 && $mobile_enabled) {
        $input_type = 'tel'; $input_mode = 'tel';
    } elseif (count($active_methods) === 1 && $email_enabled) {
        $input_type = 'email'; $input_mode = 'email';
    } else {
        $input_type = 'text'; $input_mode = 'text';
    }
    
    $show_email_registration_warning = ($mobile_enabled && $email_enabled && !$email_otp_enabled);

    if ($show_email_registration_warning) {
        $form_html .= '<p class="jay-inline-notice">توجه: ثبت‌نام با ایمیل در حال حاضر امکان‌پذیر نیست (فقط ورود).</p>';
    }

    $form_html .= '<div class="jay-inline-field"><label for="' . esc_attr($field_name) . '" class="screen-reader-text">' . esc_html($placeholder) . '</label>';
    $form_html .= '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($field_name) . '" id="' . esc_attr($field_name) . '" class="jay-inline-input" placeholder="' . esc_attr($placeholder) . '" inputmode="'. esc_attr($input_mode) .'" required></div>';

    ob_start();

        if ($captcha_type === 'math') {
           if ( ! session_id() && ! headers_sent() ) @session_start();
           $math_question = jay_relog_captcha_handler()->generate_math_captcha();
           $form_html .= '<div class="jay-inline-field jay-inline-captcha-math"><label for="jay_login_register_math_captcha">' . esc_html($math_question) . '</label>';
           $form_html .= '<input type="number" name="jay_login_register_math_captcha" id="jay_login_register_math_captcha" class="jay-inline-input jay-inline-input-small" inputmode="numeric" required></div>';
        } elseif ($captcha_type === 'honeypot') {
          $form_html .= '<input type="text" name="user_email_confirm_hp" style="opacity:0; position:absolute; top:0; left:0; height:0; width:0; z-index: -1;" tabindex="-1" autocomplete="off">';
          $form_html .= '<input type="hidden" name="form_load_time_hp" value="' . time() . '">';
        } elseif ($captcha_type === 'recaptcha_v3') {
          $form_html .= '<input type="hidden" name="recaptcha_v3_token" class="jay-recaptcha-v3-token" value="">';
        }

    $form_html .= '<button type="button" class="jay-inline-button jay-inline-check-input">بررسی</button>';
    
    // دکمه‌های سوشال
    $social_buttons_html = '';
    if ($google_login_enabled) {
        $google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $settings['google_client_id'],
            'redirect_uri' => home_url('/?jay-google-auth=1'),
            'scope' => 'openid email profile',
            'state' => wp_create_nonce('jay_google_oauth_nonce'),
            'prompt' => 'select_account'
        ]);
        $social_buttons_html .= '<a href="' . esc_url($google_auth_url) . '" class="jay-inline-social-button jay-inline-google"><span class="jay-social-icon google"></span>ورود با گوگل</a>';
    }
    if (!empty($social_buttons_html)) {
        $form_html .= '<div class="jay-inline-separator"><span>یا</span></div>';
        $form_html .= '<div class="jay-inline-social-buttons">' . $social_buttons_html . '</div>';
    }

    $form_html .= '</div>';

    wp_send_json_success([
        'html' => Jay_Login_Register_Minifier::html($form_html),
        'captcha_type' => $captcha_type,
        'recaptcha_site_key' => $settings['recaptcha_site_key'] ?? ''
    ]);
}

/**
 * ====================================================================
 * 2. AJAX Action: بررسی ورودی کاربر و کپچا
 * ====================================================================
 */
add_action( 'wp_ajax_nopriv_jay_check_inline_input', 'jay_login_register_ajax_check_inline_input_callback' );

function jay_login_register_ajax_check_inline_input_callback() {
    check_ajax_referer( 'jay_inline_lock_nonce', 'nonce' );
    if ( ! session_id() && ! headers_sent() ) { @session_start(); }
    
    $user_input_raw = isset($_POST['jay_inline_user_input']) ? sanitize_text_field(wp_unslash($_POST['jay_inline_user_input'])) : '';
    $captcha_type = isset($_POST['captcha_type']) ? sanitize_key($_POST['captcha_type']) : 'none';
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    $username_enabled = isset($settings['enable_username']) && $settings['enable_username'] === 'yes';
    // -----------------------------------------------------------
    // الف) بررسی دقیق کپچا (جایگزین do_action برای کنترل خطا)
    // -----------------------------------------------------------
    
    // ۱. بررسی کپچای ریاضی
// ۱. بررسی کپچای ریاضی
    if ($captcha_type === 'math') {
        $entered_sum = isset($_POST['jay_login_register_math_captcha']) ? intval($_POST['jay_login_register_math_captcha']) : 0;
        
        // اطمینان از اینکه سشن باز است
        if ( ! session_id() ) { @session_start(); }

        $correct_sum = isset($_SESSION['jay_math_captcha_ans']) ? intval($_SESSION['jay_math_captcha_ans']) : null;
        
        // اگر پاسخ اشتباه بود یا سشن به هر دلیلی خالی بود
        if ($correct_sum === null || $entered_sum !== $correct_sum) {
            
            // الف) مدیریت شمارنده خطا
            $fail_key = 'jay_login_register_math_fail_count_' . $user_ip;
            $block_key = 'jay_login_register_math_block_' . $user_ip;
            $max_retries = intval($settings['captcha_max_retries'] ?? 2);
            $lockout_time = intval($settings['otp_lockout_duration'] ?? 15);

            $fails = get_transient($fail_key) ?: 0;
            $fails++; // افزایش تعداد خطا
            
            // ب) تولید سوال جدید و ذخیره مستقیم در سشن (اصلاح مشکل خطای دوم)
            $n1 = wp_rand(1, 9);
            $n2 = wp_rand(1, 9);
            $new_ans = $n1 + $n2;
            $_SESSION['jay_math_captcha_ans'] = $new_ans; // آپدیت قطعی سشن
            $new_question_text = "$n1 + $n2 = ?"; // متن سوال جدید

            // ج) بررسی رسیدن به سقف مجاز
            if ($fails >= $max_retries) {
                // کاربر مسدود شد
                set_transient($block_key, 'blocked', $lockout_time * MINUTE_IN_SECONDS);
                delete_transient($fail_key);
                
                wp_send_json_error([
                    'message' => 'مسدودیت موقت کپچا. لطفاً صبر کنید.',
                    'lockout_timer' => $lockout_time * 60,
                    'new_math_question' => $new_question_text // سوال جدید برای وقتی تایمر تمام شد
                ]);
            } else {
                // هنوز فرصت دارد
                set_transient($fail_key, $fails, $lockout_time * MINUTE_IN_SECONDS);
                
                wp_send_json_error([
                    'message' => 'پاسخ امنیتی اشتباه است.',
                    'new_math_question' => $new_question_text // ارسال سوال جدید به JS برای نمایش
                ]);
            }
        } else {
            // د) اگر پاسخ درست بود
            delete_transient('jay_login_register_math_fail_count_' . $user_ip);
            // پاک کردن پاسخ از سشن (اختیاری، جهت امنیت بیشتر)
            unset($_SESSION['jay_math_captcha_ans']);
        }
    }    
    // ۲. بررسی Honeypot
    elseif ($captcha_type === 'honeypot') {
        $hp_val = isset($_POST['user_email_confirm_hp']) ? sanitize_text_field( wp_unslash( $_POST['user_email_confirm_hp'] ) ) : '';
        $load_time = isset($_POST['form_load_time_hp']) ? intval( $_POST['form_load_time_hp'] ) : 0;
        $min_time = 2; // حداقل زمان پر کردن فرم (ثانیه)

        if (!empty($hp_val) || (time() - $load_time) < $min_time) {
            // رفتار ربات‌گونه -> بلاک یا خطای عمومی
            wp_send_json_error(['message' => 'درخواست نامعتبر تشخیص داده شد (Honeypot).']);
        }
    }
    
    // ۳. بررسی reCAPTCHA v3
    elseif ($captcha_type === 'recaptcha_v3') {
            $token = isset($_POST['recaptcha_v3_token']) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_v3_token'] ) ) : '';
            $secret_key = $settings['recaptcha_secret_key'] ?? '';
        
        if (empty($token) || empty($secret_key)) {
            wp_send_json_error(['message' => 'خطای تنظیمات ریکپچا.']);
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $response = wp_remote_post($verify_url, [
            'body' => [
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $user_ip
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'خطا در اتصال به گوگل.']);
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (!$result['success'] || $result['score'] < 0.5) {
             wp_send_json_error(['message' => 'تشخیص امنیتی: احتمال ربات بودن شما بالاست.']);
        }
    }

    // -----------------------------------------------------------
    // ب) ادامه روال عادی (اگر کپچا درست بود)
    // -----------------------------------------------------------

// -----------------------------------------------------------
    // ب) ادامه روال عادی (اگر کپچا درست بود)
    // -----------------------------------------------------------

    jay_login_register_inline_check_lockout_and_die($user_input_raw, $user_ip, $settings);

 // 1. دریافت تنظیمات
    $login_methods = $settings['login_methods'] ?? ['mobile'];
    $mobile_svc = in_array('mobile', $login_methods, true);
    $email_svc = in_array('email', $login_methods, true);
    $username_svc = isset($settings['enable_username']) && $settings['enable_username'] === 'yes';
    $bale_svc = isset($settings['bale_otp_enable']) && $settings['bale_otp_enable'] === 'yes';

    // 2. تشخیص ورودی
    $input_type = ''; $sanitized_input = ''; $phone_plus_98 = '';
    
    if (is_email($user_input_raw)) { 
        $input_type = 'email'; $sanitized_input = sanitize_email($user_input_raw); 
    } elseif (preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($user_input_raw))) { 
        $input_type = 'mobile'; 
        $sanitized_input = jay_login_register_normalize_numbers($user_input_raw); 
        $phone_plus_98 = '+98' . substr($sanitized_input, 1); 
    } else {
        $input_type = 'username'; $sanitized_input = sanitize_user($user_input_raw);
    }

    // 3. لاجیک اصلی (سناریوها)

    // --- سناریو A: ورودی ایمیل است ---
    if ($input_type == 'email') {
        if (!$email_svc) wp_send_json_error(['message' => 'ورود با ایمیل غیرفعال است.']);
        
        // طبق خواسته شما: ایمیل مستقیم ارسال شود (بدون جستجوی موبایل و دکمه)
        jay_login_register_process_inline_send($sanitized_input, 'email', 'email');
        return;
    }

    // --- سناریو B: ورودی موبایل است ---
    if ($input_type == 'mobile') {
        if (!$mobile_svc) wp_send_json_error(['message' => 'ورود با موبایل غیرفعال است.']);
        
        // اگر بله فعال است -> انتخاب (SMS / Bale)
        if ($bale_svc) {
            $html = jay_login_register_get_inline_method_choice_html($sanitized_input, $sanitized_input, '', true, true, false);
            wp_send_json_success(['html' => $html, 'step' => 'choice']);
        } else {
            // مستقیم SMS
            jay_login_register_process_inline_send($sanitized_input, 'mobile', 'sms');
        }
        return;
    }

    // --- سناریو C: ورودی نام کاربری است ---
    if ($input_type == 'username') {
        if (!$username_svc) wp_send_json_error(['message' => 'فرمت ورودی نامعتبر است.']);

        $u = get_user_by('login', $sanitized_input);
        if (!$u) {
            $msg = 'نام کاربری موجود نیست.';
            if ($mobile_svc || $email_svc) $msg .= ' لطفاً با موبایل یا ایمیل تلاش کنید.';
            wp_send_json_error(['message' => $msg]);
        }

        // استخراج اطلاعات تماس
        $target_mobile = '';
        $target_email = '';
        
        if ($mobile_svc) {
            $m = get_user_meta($u->ID, 'jay_mobile', true);
            if ($m) $target_mobile = $m;
        }
        if ($email_svc) {
            $target_email = $u->user_email;
        }

        // ساخت لیست گزینه‌ها
        $has_sms = (!empty($target_mobile));
        $has_bale = ($has_sms && $bale_svc);
        $has_email = (!empty($target_email));

        // اگر هیچکدام نبود
        if (!$has_sms && !$has_email) {
            wp_send_json_error(['message' => 'هیچ شماره یا ایمیلی برای این حساب ثبت نشده است.']);
        }

        // اگر فقط یک گزینه بود (مثلاً فقط ایمیل، یا فقط موبایل بدون بله)
        // اما اینجا یک نکته است: اگر کاربر نام کاربری زده، بهتر است همیشه لیست را ببیند 
        // مگر اینکه واقعاً فقط یک راه باشد.
        
        $count = ($has_sms ? 1 : 0) + ($has_bale ? 1 : 0) + ($has_email ? 1 : 0);

        if ($count > 1) {
            $html = jay_login_register_get_inline_method_choice_html(
                $sanitized_input, 
                $target_mobile, 
                $target_email, 
                $has_sms, 
                $has_bale, 
                $has_email
            );
            wp_send_json_success(['html' => $html, 'step' => 'choice']);
        } else {
            // ارسال مستقیم (چون فقط یک راه دارد)
            if ($has_email) {
                jay_login_register_process_inline_send($target_email, 'email', 'email');
            } else {
                jay_login_register_process_inline_send($target_mobile, 'mobile', 'sms');
            }
        }
    }
}
/**
 * 3. ارسال OTP خاص (بله/SMS/Email) - دکمه‌های انتخاب
 */
add_action( 'wp_ajax_nopriv_jay_send_inline_otp_specific', 'jay_login_register_ajax_send_inline_otp_specific_callback' );
function jay_login_register_ajax_send_inline_otp_specific_callback() {
    check_ajax_referer( 'jay_inline_lock_nonce', 'nonce' );
    
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $method = isset($_POST['method']) ? sanitize_key($_POST['method']) : 'sms';
    
    if (empty($user_input)) wp_send_json_error(['message' => 'اطلاعات تماس نامعتبر است.']);

    // تشخیص دقیق نوع ورودی برای جلوگیری از ارسال ایمیل به سامانه پیامک
    $input_type = 'mobile';
    
    // اگر متد ایمیل است یا متن وارد شده فرمت ایمیل دارد -> نوع ایمیل است
    if ($method === 'email' || is_email($user_input)) {
        $input_type = 'email';
    }

    jay_login_register_process_inline_send($user_input, $input_type, $method);
}

/**
 * تابع پردازش ارسال
 */
function jay_login_register_process_inline_send($user_input, $input_type, $send_method) {
    $settings = get_option('jay_login_register_settings');
    $user_exists = false;
    if ($input_type === 'email') {
        $user = get_user_by('email', $user_input);
        if ($user) $user_exists = true;
    } else {
        $phone_plus_98 = '+98' . substr($user_input, 1);
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $user_query = new WP_User_Query(['meta_query' => ['relation' => 'OR',['key' => 'digits_phone', 'value' => $phone_plus_98],['key' => 'jay_mobile', 'value' => $user_input]], 'number' => 1, 'fields' => 'ID']);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $users = $user_query->get_results();
        if (!empty($users)) $user_exists = true;
    }

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $context = $user_exists ? 'login' : 'register';
    $transient_key = 'jay_inline_otp_' . $context . '_' . $user_input;
    $send_result = null;

    if ($input_type === 'email') {
        $subject = str_replace(['[otp_code]', '[site_name]', '[validity_period]'], [$otp, get_bloginfo('name'), $validity_period], $settings['email_otp_subject'] ?? 'کد تایید: [otp_code]');
        $body = nl2br(str_replace(['[otp_code]', '[site_name]', '[validity_period]'], [$otp, get_bloginfo('name'), $validity_period], $settings['email_otp_body'] ?? "کد: [otp_code]"));
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($user_input, $subject, $body, $headers);
if (!$sent) {
            $send_result = new WP_Error('email_error', 'خطا در ارسال ایمیل.'); 
        } else {
            $send_result = true;
            $send_method = 'email'; // <--- این خط حیاتی است!
        }
        
    } else {
        if ($send_method === 'bale') {
            $send_result = jay_login_register_send_otp_via_bale($user_input, $otp);
        } else {
            $send_result = jay_relog_sms_handler()->send_otp(null, $user_input, $otp);
        }
    }

    if (is_wp_error($send_result)) wp_send_json_error(['message' => 'خطا در ارسال: ' . $send_result->get_error_message()]);

    set_transient($transient_key, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);
    $otp_form_html = jay_login_register_get_inline_otp_form_html_callback($user_input, $context, $send_method, $settings);
    wp_send_json_success(['html' => $otp_form_html, 'validity_period' => $validity_period * 60]);
}

/**
 * HTML انتخاب روش
 */
/**
 * HTML انتخاب روش دریافت کد
 */
/**
 * HTML انتخاب روش دریافت کد
 */
/**
 * HTML انتخاب روش دریافت کد
 * این تابع با گرفتن اطلاعات کامل، دکمه‌های مناسب را می‌سازد
 */
function jay_login_register_get_inline_method_choice_html($display_name, $mobile_num, $email_addr, $show_sms, $show_bale, $show_email) {
    $html = '<div class="jay-inline-lock-form-wrapper jay-inline-choice-form">';
    $html .= '<h4>انتخاب روش دریافت کد</h4>';
    $html .= '<p>ارسال کد تایید برای <strong>' . esc_html($display_name) . '</strong>:</p>';
    
    // 1. دکمه پیامک
    if ($show_sms && !empty($mobile_num)) {
        $html .= '<button type="button" class="jay-inline-button jay-inline-send-method" data-method="sms" data-input="' . esc_attr($mobile_num) . '">ارسال پیامک</button>';
    }
        // 3. دکمه ایمیل (جدید)
    if ($show_email && !empty($email_addr)) {
        // ماسک کردن ایمیل (مثال: m***@gmail.com)
        $masked = substr($email_addr, 0, 1) . '***' . substr($email_addr, strpos($email_addr, '@'));
        
        $html .= '<button type="button" class="jay-inline-button jay-inline-button-secondary jay-inline-send-method" data-method="email" data-input="' . esc_attr($email_addr) . '" style="margin-top:10px; background:#f0f0f1; color:#333; border:1px solid #ccc;">ارسال به ایمیل (' . esc_html($masked) . ')</button>';
    }
    // 2. دکمه بله
    if ($show_bale && !empty($mobile_num)) {
        $html .= '<button type="button" class="jay-inline-button jay-inline-button-secondary jay-inline-send-method" data-method="bale" data-input="' . esc_attr($mobile_num) . '" style="margin-top:10px;">ارسال به پیام‌رسان بله</button>';
    }

    if ($show_bale) {
        $html .= '<p class="jay-inline-notice" style="margin-top:15px;">توجه: گزینه "بله" فقط برای کاربرانی است که اپلیکیشن بله را دارند.</p>';
    }

    $html .= '</div>';
    return Jay_Login_Register_Minifier::html($html);
    
}
/**
 * HTML فرم OTP
 */
function jay_login_register_get_inline_otp_form_html_callback($user_input, $context, $send_method, $settings) {
    $otp_style = $settings['otp_input_style'] ?? 'single';
    $otp_length = intval($settings['otp_length'] ?? 4);
    $title = ($context === 'login') ? 'ورود با کد تایید' : 'تایید عضویت';
    $method_text = 'به شماره موبایل شما'; // پیش‌فرض
    if ($send_method === 'email') {
        $method_text = 'به آدرس ایمیل شما';
    } elseif ($send_method === 'bale') {
        $method_text = 'به پیام‌رسان بله شما';
    }
    $instruction = "کد {$otp_length} رقمی ارسال شده {$method_text} را وارد کنید:";

    $html = '<div class="jay-inline-lock-form-wrapper jay-inline-otp-form">';
    $html .= '<h4>' . esc_html($title) . '</h4><p>' . wp_kses_post($instruction) . '</p>';
    $input_name = 'jay_inline_otp';

    if ($otp_style === 'multiple') {
        $html .= '<div class="jay-inline-otp-fields" data-otp-length="' . esc_attr($otp_length) . '">';
        for ($i = 0; $i < $otp_length; $i++) { $html .= '<input type="text" class="jay-inline-otp-digit" maxlength="1" inputmode="numeric" autocomplete="one-time-code">'; }
        $html .= '</div><input type="hidden" name="' . esc_attr($input_name) . '" id="' . esc_attr($input_name) . '">';
    } else {
        $html .= '<div class="jay-inline-field"><label for="' . esc_attr($input_name) . '" class="screen-reader-text">کد تایید</label>';
        $html .= '<input type="text" name="' . esc_attr($input_name) . '" id="' . esc_attr($input_name) . '" class="jay-inline-input jay-inline-otp-input-single" inputmode="numeric" autocomplete="one-time-code" required maxlength="' . esc_attr($otp_length) . '">';
        $html .= '</div>';
    }

    $html .= '<input type="hidden" name="jay_inline_context" value="' . esc_attr($context) . '">';
    $html .= '<input type="hidden" name="jay_inline_user_input_hidden" value="' . esc_attr($user_input) . '">';
    $html .= '<button type="button" class="jay-inline-button jay-inline-verify-otp">تایید کد</button>';
    $resend_action = 'jay_resend_inline_otp_' . $context;
    $html .= '<div class="jay-inline-timer-wrapper"><a href="#" class="jay-inline-resend-link" data-action="' . esc_attr($resend_action) . '" data-input="' . esc_attr($user_input) . '" disabled>ارسال مجدد کد</a>';
    $html .= '<span class="jay-inline-timer"></span></div>';
    $html .= '</div>';
    return Jay_Login_Register_Minifier::html($html);
    
}

/**
 * 4. تایید نهایی OTP (با ریلود)
 */
add_action( 'wp_ajax_nopriv_jay_verify_inline_otp', 'jay_login_register_ajax_verify_inline_otp_callback' );

function jay_login_register_ajax_verify_inline_otp_callback() {
    check_ajax_referer( 'jay_inline_lock_nonce', 'nonce' );
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $otp_entered = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';
    $context = isset($_POST['context']) ? sanitize_key($_POST['context']) : '';
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    $block_methods = $settings['otp_block_method'] ?? ['phone'];

    if (empty($user_input) || empty($otp_entered) || !in_array($context, ['login', 'register'])) { wp_send_json_error(['message' => 'اطلاعات ناقص است.']); }

    jay_login_register_inline_check_lockout_and_die($user_input, $user_ip, $settings);

    $transient_key = 'jay_inline_otp_' . $context . '_' . $user_input;
    $otp_correct_data = get_transient($transient_key);
    if ($otp_correct_data === false) { wp_send_json_error(['message' => 'کد تایید منقضی شده یا نامعتبر است.']); }

    if ((string) jay_login_register_normalize_numbers($otp_entered) !== (string) $otp_correct_data['otp']) {
        $max_retries = intval($settings['otp_max_retries'] ?? 3); $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
        $fail_targets = []; if (in_array('phone', $block_methods)) $fail_targets[] = $user_input; if (in_array('ip', $block_methods)) $fail_targets[] = $user_ip;
        $max_fail_count = 0;
        foreach ($fail_targets as $target) {
            if(empty($target)) continue;
            $fail_count_key = 'jay_login_register_otp_fail_count_' . $target; $block_key = 'jay_login_register_otp_block_' . $target;
            $fail_count = get_transient($fail_count_key) ?: 0; $fail_count++; $max_fail_count = max($max_fail_count, $fail_count);
            if ($fail_count >= $max_retries) { set_transient($block_key, 'blocked', $lockout_duration * MINUTE_IN_SECONDS); delete_transient($fail_count_key); }
            else { set_transient($fail_count_key, $fail_count, $lockout_duration * MINUTE_IN_SECONDS); }
        }
        jay_login_register_inline_check_lockout_and_die($user_input, $user_ip, $settings);
        $remaining_tries = max(0, $max_retries - $max_fail_count);
        wp_send_json_error(['message' => "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید."]);
    }

    delete_transient($transient_key);
    $fail_targets = []; if (in_array('phone', $block_methods)) $fail_targets[] = $user_input; if (in_array('ip', $block_methods)) $fail_targets[] = $user_ip;
    foreach ($fail_targets as $target) { if(!empty($target)) delete_transient('jay_login_register_otp_fail_count_' . $target); }

    $user_id = 0; $input_type = is_email($user_input) ? 'email' : 'mobile'; $user = null;
    if ($context === 'login') {
        if ($input_type === 'email') { $user = get_user_by('email', $user_input); }
        else { 
            // phpcs:ignore
            $phone_plus_98 = '+98' . substr($user_input, 1);
            
         // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
          $user_query = new WP_User_Query([
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'digits_phone', 'value' => $phone_plus_98],
                    ['key' => 'jay_mobile', 'value' => $user_input]
                ],
                'number' => 1,
                'fields' => 'ID'
            ]);
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $users = $user_query->get_results(); 
            if (!empty($users)) {
                $user = get_user_by('id', $users[0]); 
            }
            
        }
        if (!$user) wp_send_json_error(['message' => 'خطای داخلی: کاربر یافت نشد.']);
        $user_id = $user->ID;
    } else { 
        $username = ''; $email_for_user = ''; $display_name = '';
        if ($input_type === 'email') { 
            $email_for_user = $user_input; $username_base = sanitize_user(explode('@', $email_for_user)[0], true); $username = $username_base; $counter = 1; while (username_exists($username)) { $username = $username_base . $counter++; } $display_name = $username; 
        } else { 
            $username = $user_input; if(username_exists($username)){ $username = $username . '_' . wp_rand(100, 999); } $email_for_user = $username . '@' . wp_parse_url(home_url(), PHP_URL_HOST); $display_name = $user_input; 
        }
        $password = wp_generate_password(12, true);
        $user_id = wp_create_user($username, $password, $email_for_user);
        if (is_wp_error($user_id)) wp_send_json_error(['message' => 'خطا در ساخت حساب کاربری.']);
        if ($input_type === 'mobile') { $phone_plus_98 = '+98' . substr($user_input, 1); update_user_meta($user_id, 'jay_mobile', $user_input); update_user_meta($user_id, 'digits_phone', $phone_plus_98); update_user_meta($user_id, 'digits_phone_no', substr($user_input, 1)); }
        wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
    }

    clean_user_cache($user_id);
    wp_clear_auth_cookie();
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    $missing_fields = [];
$req_name = isset($_POST['req_name']) ? sanitize_key( $_POST['req_name'] ) : 'no';
$req_fields_enc = isset($_POST['req_fields']) ? sanitize_text_field( wp_unslash( $_POST['req_fields'] ) ) : '';
$user_data = get_userdata($user_id);

    if ( $req_name === 'yes' ) {
        if ( empty($user_data->first_name) ) $missing_fields[] = 'first_name';
        if ( empty($user_data->last_name) ) $missing_fields[] = 'last_name';
    }
    if ( ! empty($req_fields_enc) ) {
        $decoded_json = json_decode( base64_decode($req_fields_enc), true );
        if ( is_array($decoded_json) ) {
            foreach ( $decoded_json as $field ) {
                $key = isset($field['key']) ? $field['key'] : '';
                if ( $key ) {
                    $meta_val = get_user_meta( $user_id, $key, true );
                    if ( empty($meta_val) && $meta_val !== '0' ) $missing_fields[] = $key;
                }
            }
        }
    }

    if ( ! empty($missing_fields) ) {
        $temp_token = wp_generate_password( 20, false );
        set_transient( 'jay_onboarding_token_' . $user_id, $temp_token, 10 * MINUTE_IN_SECONDS );
        wp_send_json_success([
            'message'    => 'کد تایید شد. لطفاً اطلاعات تکمیلی را وارد کنید.',
            'new_nonce' => $temp_token,
            'status'    => 'needs_details',
            'missing_fields' => $missing_fields
        ]);
        return;
    }

    // بازگرداندن دستور ریلود
    wp_send_json_success(['reload' => true]);
}

/**
 * 5. ثبت اطلاعات تکمیلی (با ریلود)
 */
add_action( 'wp_ajax_jay_submit_inline_details', 'jay_login_register_ajax_submit_inline_details_callback' );
function jay_login_register_ajax_submit_inline_details_callback() {
    if ( ! is_user_logged_in() ) { wp_send_json_error(['message' => 'نشست کاربری منقضی شده است.']); }
    
    $user_id = get_current_user_id();
    $received_nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    $is_valid = false;
    $temp_token_key = 'jay_onboarding_token_' . $user_id;
    $valid_temp_token = get_transient( $temp_token_key );
    $used_temp_token = false;
    
    if ( ! empty($valid_temp_token) && $received_nonce === $valid_temp_token ) { 
        
        $is_valid = true; 
        $used_temp_token = true;        
    } 
    else { if ( wp_verify_nonce( $received_nonce, 'jay_inline_lock_nonce' ) ) { $is_valid = true; } }

    if ( ! $is_valid ) { wp_send_json_error(['message' => 'خطای امنیتی.']); }
    
    $errors = [];

// --- الف) اعتبارسنجی نام و نام خانوادگی ---
    if ( isset($_POST['first_name']) || isset($_POST['last_name']) ) {
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        
        // دریافت تنظیمات اجبار فارسی
        $force_persian = isset($_POST['force_persian']) && $_POST['force_persian'] === 'yes';

        if ( empty($first_name) || empty($last_name) ) { 
            $errors[] = 'نام و نام خانوادگی الزامی است.'; 
        } else {
            // سناریوی ۱: تیک "فقط فارسی" زده شده است
            if ( $force_persian ) {
                // فقط حروف فارسی و فاصله مجاز است (انگلیسی و عدد ممنوع)
                if (!preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $first_name) || !preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $last_name)) {
                    $errors[] = 'نام و نام خانوادگی باید فقط شامل حروف فارسی باشد.';
                }
            } 
            // سناریوی ۲: تیک زده نشده (حالت عادی)
            else {
                // حروف فارسی یا انگلیسی و فاصله مجاز است (عدد و علامت ممنوع)
                // اگر عدد یا علامت وارد شود، خطا می‌دهد
                if (!preg_match('/^[a-zA-Z\x{0600}-\x{06FF}\s]+$/u', $first_name) || !preg_match('/^[a-zA-Z\x{0600}-\x{06FF}\s]+$/u', $last_name)) {
                    $errors[] = 'نام و نام خانوادگی فقط می‌تواند شامل حروف باشد (عدد مجاز نیست).';
                }
            }
        }
    }
    
// --- دریافت تنظیمات فیلدها ---
    $fields_config = [];
    if ( !empty($_POST['fields_config_enc']) ) {
        // رفع ارور امنیتی: پاک‌سازی رشته Base64 قبل از پردازش
        $enc_string = sanitize_text_field( wp_unslash( $_POST['fields_config_enc'] ) );
        
        // دیکد کردن ایمن
        $json_str = base64_decode($enc_string);
        $decoded = json_decode( urldecode($json_str), true );
        // پشتیبانی از حالت‌های مختلف دیکد
        $fields_config = is_array($decoded) ? $decoded : json_decode($json_str, true);
    }
    
    $fields_map = [];
    if(is_array($fields_config)) { 
        foreach($fields_config as $f) { $fields_map[$f['key']] = $f; }
    }

    // ======================================================
    // مرحله ۱: فقط اعتبارسنجی (هیچ چیزی ذخیره نکن)
    // ======================================================
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'meta_') === 0) {
            $meta_key = sanitize_key(substr($key, 5));
            $field_cfg = $fields_map[$meta_key] ?? null;
            
            // اگر تنظیمی برای این فیلد پیدا نشد، رد شو
            if (!$field_cfg) continue;

            $val_to_check = is_array($value) ? $value : trim(wp_unslash($value));

            // 1. بررسی ضروری بودن
            if ( !empty($field_cfg['is_required']) && $field_cfg['is_required'] == 1 ) {
                 if ( empty($val_to_check) && $val_to_check !== '0' ) {
                     $errors[] = "فیلد «{$field_cfg['label']}» الزامی است.";
                     continue;
                 }
            }

            // اگر خالی بود و ضروری نبود، ادامه نده (چون پر نکرده که چک کنیم)
            if ( empty($val_to_check) && $val_to_check !== '0' ) continue;

            // 2. بررسی‌های خاص برای فیلدهای متنی (غیر آرایه)
            if ( !is_array($val_to_check) ) {
                $raw_val = $val_to_check;
                // استاندارد سازی اعداد برای بررسی
                $normalized_val = jay_login_register_normalize_numbers($raw_val);

                // بررسی نوع شماره
                if ($field_cfg['type'] === 'number') {
                    if (!ctype_digit($normalized_val)) {
                         $errors[] = "فیلد «{$field_cfg['label']}» باید فقط شامل عدد باشد.";
                    } else {
                        // فقط اگر عدد بود طول و شروع را چک کن
                        if (!empty($field_cfg['number_len']) && mb_strlen($normalized_val) != $field_cfg['number_len']) {
                            $errors[] = "فیلد «{$field_cfg['label']}» باید دقیقاً {$field_cfg['number_len']} رقم باشد.";
                        }
                        if (!empty($field_cfg['number_start']) && strpos($normalized_val, (string)$field_cfg['number_start']) !== 0) {
                             $errors[] = "فیلد «{$field_cfg['label']}» باید با {$field_cfg['number_start']} شروع شود.";
                        }
                    }
                }
            }
        }
    }

    // اگر خطایی وجود دارد، همین‌جا متوقف شو و به کاربر بگو (هیچ دیتایی ذخیره نمی‌شود)
    if (!empty($errors)) { 
        wp_send_json_error(['message' => implode('<br>', $errors)]); 
    }

    // ======================================================
    // مرحله ۲: ذخیره‌سازی (فقط وقتی به اینجا می‌رسیم که خطایی نباشد)
    // ======================================================
    
// 1. آپدیت نام
    if ( isset($first_name) && isset($last_name) ) {
        wp_update_user([ 
            'ID' => $user_id, 
            'first_name' => $first_name, 
            'last_name' => $last_name, 
            'display_name' => "$first_name $last_name" 
        ]); 
    }

    // ب) ذخیره متاها
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'meta_') === 0) {
            $meta_key = sanitize_key(substr($key, 5));
            $field_cfg = $fields_map[$meta_key] ?? null;

            if (is_array($value)) { 
                $sanitized_values = array_map('sanitize_text_field', wp_unslash($value)); 
                update_user_meta($user_id, $meta_key, $sanitized_values); 
            }
            else { 
                $raw_val = wp_unslash($value);
                // برای پاراگراف اینترها حفظ شود
                $sanitized_value = sanitize_textarea_field($raw_val);

                if ($field_cfg) {
                    // تبدیل شماره به انگلیسی
                    if ($field_cfg['type'] === 'number') {
                        $sanitized_value = jay_login_register_normalize_numbers($sanitized_value);
                    }
                    // تبدیل تاریخ شمسی به میلادی
                    if ($field_cfg['type'] === 'date' && !empty($field_cfg['is_jalali'])) {
                         $sanitized_value = jay_login_register_convert_jalali_to_gregorian($sanitized_value);
                    }
                }
                update_user_meta($user_id, $meta_key, $sanitized_value); 
            }
        }
    }
    // 3. پاک کردن توکن امنیتی موقت (حالا که کار تمام شد)
    if ( $used_temp_token ) {
        delete_transient( $temp_token_key );
    }
    $redirect_url = '';
    if ( !empty($_POST['redirect_to']) ) {
        $redirect_url = esc_url_raw( wp_unslash($_POST['redirect_to']) );
    }

    if ( !empty($redirect_url) ) {
        // اگر آدرس ریدایرکت وجود داشت، آن را برگردان
        wp_send_json_success(['redirect_url' => $redirect_url]);
    } else {
        // در غیر این صورت ریلود کن
        wp_send_json_success(['reload' => true]);
    }

}

/* ارسال مجدد */
add_action( 'wp_ajax_nopriv_jay_resend_inline_otp_login', 'jay_login_register_ajax_resend_inline_otp_login_callback' );
add_action( 'wp_ajax_nopriv_jay_resend_inline_otp_register', 'jay_login_register_ajax_resend_inline_otp_register_callback' );
function jay_login_register_ajax_resend_inline_otp_login_callback() { jay_login_register_handle_inline_resend_callback('login'); }
function jay_login_register_ajax_resend_inline_otp_register_callback() { jay_login_register_handle_inline_resend_callback('register'); }

function jay_login_register_handle_inline_resend_callback( $context ) { 
    check_ajax_referer( 'jay_inline_lock_nonce', 'nonce' );
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    if (empty($user_input) || !in_array($context, ['login', 'register'])) { wp_send_json_error(['message' => 'اطلاعات ناقص است.']); }
    jay_login_register_inline_check_lockout_and_die($user_input, $user_ip, $settings);

    $transient_key = 'jay_inline_otp_' . $context . '_' . $user_input;
    $old_transient_data = get_transient($transient_key);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    if ($old_transient_data && isset($old_transient_data['time']) && (time() - $old_transient_data['time']) < ($validity_period * 60)) { $remaining_seconds = ($validity_period * 60) - (time() - $old_transient_data['time']); wp_send_json_error(['message' => 'لطفاً ' . $remaining_seconds . ' ثانیه دیگر صبر کنید.']); }

    $otp_length = intval($settings['otp_length'] ?? 4);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    $input_type = is_email($user_input) ? 'email' : 'mobile'; $send_method = ''; $send_result = null;

    if ($input_type === 'email') {
        $send_method = 'email';
        $subject = str_replace(['[otp_code]', '[site_name]', '[validity_period]'], [$otp, get_bloginfo('name'), $validity_period], $settings['email_otp_subject'] ?? 'کد تایید: [otp_code]');
        $body = nl2br(str_replace(['[otp_code]', '[site_name]', '[validity_period]'], [$otp, get_bloginfo('name'), $validity_period], $settings['email_otp_body'] ?? "کد: [otp_code]"));
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($user_input, $subject, $body, $headers);
        if (!$sent) $send_result = new WP_Error('email_send_error', 'خطا در ارسال ایمیل'); else $send_result = true;
    } else {
        $send_method = 'sms';
        $send_result = jay_relog_sms_handler()->send_otp(null, $user_input, $otp);
    }

    if (is_wp_error($send_result)) { wp_send_json_error(['message' => 'خطا در ارسال مجدد کد: ' . $send_result->get_error_message()]); }
    set_transient($transient_key, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);
    wp_send_json_success(['message' => 'کد جدید با موفقیت ارسال شد.', 'validity_period' => $validity_period * 60]);
}

/* توابع Lockout */
function jay_login_register_check_lockout_status($input, $ip, $settings) {
    $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
    $block_methods = $settings['otp_block_method'] ?? ['phone'];
    $otp_lockout_targets = [];
    if (in_array('phone', $block_methods) && !empty($input)) $otp_lockout_targets[] = $input;
    if (in_array('ip', $block_methods) && !empty($ip)) $otp_lockout_targets[] = $ip;
    foreach ($otp_lockout_targets as $target) {
        $transient_name = 'jay_login_register_otp_block_' . $target;
        if (get_transient($transient_name)) {
            $expiration_time = get_option('_transient_timeout_' . $transient_name);
            $remaining_seconds = $expiration_time ? max(0, $expiration_time - time()) : 0;
            return new WP_Error('otp_lockout', 'شما به دلیل تلاش‌های ناموفق زیاد مسدود شده‌اید.', ['lockout_timer' => $remaining_seconds]);
        }
    }
    $math_block_transient = 'jay_login_register_math_block_' . $ip;
    if (get_transient($math_block_transient)) {
        $expiration_time = get_option('_transient_timeout_' . $math_block_transient);
        $remaining_seconds = $expiration_time ? max(0, $expiration_time - time()) : 0;
        return new WP_Error('math_lockout', 'مسدودیت موقت کپچا.', ['lockout_timer' => $remaining_seconds]);
    }
    return null; 
}

function jay_login_register_inline_check_lockout_and_die($input, $ip, $settings) {
     $lockout_error = jay_login_register_check_lockout_status($input, $ip, $settings);
     if (is_wp_error($lockout_error)) {
          wp_send_json_error([ 'message' => $lockout_error->get_error_message(), 'lockout_timer' => $lockout_error->get_error_data()['lockout_timer'] ?? 0 ]);
     }
}

function jay_login_register_recover_content_from_post( $post_id, $lock_id ) {
    if ( empty( $post_id ) || empty( $lock_id ) ) return false;
    $post = get_post( $post_id );
    if ( ! $post ) return false;
    $pattern = get_shortcode_regex( [ 'jay_content_lock' ] );
    if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches ) ) {
        $index = intval( str_replace( 'jay-lock-', '', $lock_id ) ) - 1;
        if ( isset( $matches[5][ $index ] ) ) return $matches[5][ $index ];
    }
    return false;
} 
