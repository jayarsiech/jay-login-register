<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * جدید: وضعیت مسدودیت کاربر را بررسی و در صورت نیاز، خطا را به همراه تایمر ارسال می‌کند.
 */
function jay_login_register_check_and_handle_lockout( $phone, $ip, $settings ) {
    $block_methods    = $settings['otp_block_method'] ?? ['phone'];
    $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
    // بررسی مسدودیت بر اساس شماره موبایل
    if ( in_array('phone', $block_methods, true) ) {
        $transient_name = 'jay_login_register_otp_block_' . $phone;
        if ( get_transient( $transient_name ) ) {
            $expiration_time   = get_option( '_transient_timeout_' . $transient_name );
            $remaining_seconds = $expiration_time ? $expiration_time - time() : 0;
            wp_send_json_error([
                'message'       => "شما به دلیل تلاش‌های ناموفق زیاد، به طور موقت مسدود شده‌اید.",
                'lockout_timer' => max(0, $remaining_seconds)
            ]);
        }
    }
    // بررسی مسدودیت بر اساس IP
    if ( in_array('ip', $block_methods, true) ) {
        $transient_name = 'jay_login_register_otp_block_' . $ip;
        if ( get_transient( $transient_name ) ) {
            $expiration_time   = get_option( '_transient_timeout_' . $transient_name );
            $remaining_seconds = $expiration_time ? $expiration_time - time() : 0;
            wp_send_json_error([
                'message'       => "شما به دلیل تلاش‌های ناموفق زیاد، به طور موقت مسدود شده‌اید.",
                'lockout_timer' => max(0, $remaining_seconds)
            ]);
        }
    }
}
/**
 * این تابع شماره تلفن را به درستی ماسک می‌کند
 */
function jay_login_register_get_duplicate_id_error_message( $other_user_id, $id_type_string = 'کد ملی' ) {
    $other_user_phone = get_user_meta( $other_user_id, 'digits_phone', true );
    if ( ! $other_user_phone ) {
        return "این {$id_type_string} قبلاً توسط کاربر دیگری ثبت شده است.";
    }
    $display_phone = '0' . substr( $other_user_phone, 3 ); // تبدیل +98 به 0
    // ماسک کردن شماره: ۴ رقم اول و ۴ رقم آخر نمایش داده می‌شود
    $masked_phone = substr( $display_phone, 0, 4 ) . '*****' . substr( $display_phone, -4 );
    
    $styled_masked_phone = '<strong style="direction: ltr; display: inline-block; background-color: rgba(255,255,255,0.1); color: #ffdd57; padding: 2px 8px; border-radius: 4px;">' . $masked_phone . '</strong>';
    $try_again_link = '<div class="yekshomaredigar">
<a href="#" onclick="location.reload(); return false;" class="jay-login-register-resend-link">بررسی یک شماره دیگر</a>
</div>
';    
    return "این {$id_type_string} برای شماره {$styled_masked_phone} ثبت شده است. لطفاً با همان شماره وارد شوید.{$try_again_link}";
}
/**
 * فرم ورود html
 *
 * @param string $phone شماره تلفن کاربر.
 * @return string HTML کامل فرم.
 */
function jay_login_register_get_login_form_html($user_input) {
// --- منطق هوشمند جدید برای دکمه ورود با کد یکبار مصرف ---
    $settings = get_option('jay_login_register_settings');
    $email_otp_enabled = isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes';
    $is_bale_otp_enabled = isset($settings['bale_otp_enable']) && $settings['bale_otp_enable'] === 'yes';
    $is_email_input = is_email($user_input);
    $is_mobile_input = preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($user_input));
    
    $user_email_for_otp = '';
    $user_mobile_for_otp = '';
    
    if ( ! $is_email_input && ! $is_mobile_input ) {
        // پس نام کاربری است (چون قبلاً در مرحله قبل اعتبار سنجی شده که کاربر وجود دارد)
        $user = get_user_by('login', $user_input);
        if ($user) {
            $user_email_for_otp = $user->user_email;
            $user_mobile_for_otp = get_user_meta($user->ID, 'jay_mobile', true);
        }
    }
  $otp_buttons_html = '';

    // ۱. دکمه ورود با ایمیل
    // اگر ورودی ایمیل بود، یا اگر نام کاربری بود و آن کاربر ایمیل داشت
    if ( ($is_email_input || (!empty($user_email_for_otp))) && $email_otp_enabled ) {
        $otp_buttons_html .= '<button type="button" class="jay-login-register-button-secondary" data-action="send_email_otp">دریافت کد با ایمیل</button>';
    }
    // ۲. دکمه ورود با پیامک
    // اگر ورودی موبایل بود، یا اگر نام کاربری بود و آن کاربر موبایل داشت
    if ( $is_mobile_input || (!empty($user_mobile_for_otp)) ) {
        $otp_buttons_html .= '<button type="button" class="jay-login-register-button-secondary" data-action="send_otp_for_login">ورود با رمز یکبار مصرف (پیامک)</button>';
        // ۳. دکمه بله (فقط برای موبایل)
        if ($is_bale_otp_enabled) {
            $otp_buttons_html .= '<button type="button" class="jay-login-register-button-secondary jay-login-register-button-bale" data-action="send_otp_bale_login"><span class="social-icon"></span>دریافت کد از طریق بله</button>';
        }
    }
    $html = '
    <h3>ورود به حساب</h3><p>لطفاً رمز عبور خود را وارد کنید یا با رمز یکبار مصرف وارد شوید.</p><div class="jay-login-register-field"><label for="jay_login_register_password">رمز عبور</label><input type="password" name="jay_login_register_password" class="jay-login-register-input"></div><input type="hidden" name="user_input" value="' . esc_attr($user_input) . '"><button type="button" class="jay-login-register-button" data-action="login_with_password">ورود</button>
        ' . $otp_buttons_html;
        
    if ($is_bale_otp_enabled && ($is_mobile_input || !empty($user_mobile_for_otp))) {
        $html .= '<p class="jay-login-register-bale-notice">توجه: گزینه "بله" فقط در صورت نصب بودن اپلیکیشن کار می‌کند.</p>';
    }
    return $html;
}
/**
 * مرحله ۱: بررسی شماره تلفن و ارسال OTP
 */
add_action( 'wp_ajax_nopriv_jay_login_register_check_user_input', 'jay_login_register_ajax_check_user_input' );
add_action( 'wp_ajax_jay_login_register_check_user_input', 'jay_login_register_ajax_check_user_input' ); 
function jay_login_register_ajax_check_user_input() {
    // check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    // Verify captcha using the central handler via hook
    // This will also check the nonce internally again, but it's okay.
    // Or we can remove the check_ajax_referer above if the hook handles it.
    // Let's keep both for now, belt and suspenders.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled within the hooked function for 'jay_relog_verify_captcha'.
    do_action('jay_relog_verify_captcha', $_POST, 'jay_login_register_nonce_action', 'jay_login_register_nonce');
    // If captcha fails, the hooked function will wp_send_json_error and stop execution.
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    $captcha_type = $settings['captcha_type'] ?? 'none';
    if ( $captcha_type === 'math' ) {
        $user_ip = jay_login_register_get_user_ip();
        $math_block_transient = 'jay_login_register_math_block_' . $user_ip;
        if ( get_transient( $math_block_transient ) ) {
            // If blocked, send error immediately and stop.
            $expiration_time = get_option( '_transient_timeout_' . $math_block_transient );
            $remaining_seconds = $expiration_time ? max(0, $expiration_time - time()) : 0;
            wp_send_json_error([
                'message'        => "شما به دلیل تلاش‌های ناموفق زیاد در حل کپچا، به طور موقت مسدود شده‌اید.",
                'lockout_timer' => $remaining_seconds
            ]);
        }
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified within the 'jay_relog_verify_captcha' action hook called earlier.
    $user_input = isset($_POST['jay_login_register_user_input']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_user_input'])) : '';
     if (empty($user_input)) {
     wp_send_json_error(['message' => 'لطفاً فیلد ورودی را پر کنید.']);
     }
  // تشخیص نوع ورودی و اعتبارسنجی بر اساس تنظیمات
    $login_methods = $settings['login_methods'] ?? ['mobile'];
    $mobile_enabled = in_array('mobile', $login_methods, true);
    $email_enabled = in_array('email', $login_methods, true);
    // دریافت تنظیمات نام کاربری
    $username_enabled = isset($settings['enable_username']) && $settings['enable_username'] === 'yes';
    $input_type = '';
    $phone = '';
    $users = [];

    if ( is_email($user_input) ) {
        // سناریو ۱: ایمیل
        if ( ! $email_enabled ) {
            wp_send_json_error(['message' => 'ورود با ایمیل غیرفعال است.']);
        }
        $input_type = 'email';
        $user = get_user_by('email', $user_input);
        if ($user) $users[] = $user->ID;

    } elseif ( preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($user_input)) ) {
        // سناریو ۲: موبایل
        if ( ! $mobile_enabled ) {
            wp_send_json_error(['message' => 'ورود با موبایل غیرفعال است.']);
        }
        $input_type = 'mobile';
        $phone = jay_login_register_normalize_numbers($user_input);
        
        $phone_plus_98 = '+98' . substr($phone, 1);
        // phpcs:disable WordPress.DB.SlowDBQuery
        $user_query = new WP_User_Query([
            'number' => 1,
            'fields' => 'ID',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'digits_phone', 'value' => $phone_plus_98],
                ['key' => 'jay_mobile', 'value' => $phone],
            ],
        ]);
        // phpcs:enable
        $users = $user_query->get_results();
    } else {
        // سناریو ۳: احتمالاً نام کاربری
        if ( $username_enabled ) {
            $input_type = 'username';
            $user = get_user_by('login', $user_input);
            
            if ($user) {
                // نام کاربری وجود دارد -> ادامه به سمت ورود
                $users[] = $user->ID;
            } else {
                // نام کاربری وجود ندارد -> خطا و راهنمایی ثبت‌نام
                $reg_message = 'این نام کاربری وجود ندارد.';
                // راهنمایی هوشمند برای ثبت نام
                $methods_msg = [];
                if ($mobile_enabled) $methods_msg[] = 'شماره موبایل';
                if ($email_enabled) $methods_msg[] = 'ایمیل';
                
                if (!empty($methods_msg)) {
                    $reg_message .= ' برای ثبت‌نام لطفاً ' . implode(' یا ', $methods_msg) . ' خود را وارد کنید.';
                }
                
                wp_send_json_error(['message' => $reg_message]);
            }
        } else {
            // اگر قابلیت نام کاربری غیرفعال بود و ورودی هم ایمیل/موبایل نبود
            $error_message = 'فرمت ورودی نامعتبر است.';
            if ($mobile_enabled && $email_enabled) $error_message = 'لطفاً یک ایمیل یا شماره موبایل صحیح وارد کنید.';
            elseif ($mobile_enabled) $error_message = 'لطفاً شماره موبایل صحیح وارد کنید.';
            elseif ($email_enabled) $error_message = 'لطفاً ایمیل صحیح وارد کنید.';
            
            wp_send_json_error(['message' => $error_message]);
        }
    }

    // (قبلاً اینجا کدی بود که دوباره سعی می‌کرد $users را پر کند و باعث باگ می‌شد)
    $settings = get_option('jay_login_register_settings');
    $id_methods = $settings['id_methods'] ?? [];
     
    if ( empty($id_methods) ) {
        if (!empty($users)) {
            $user_id = $users[0];
            set_transient('jay_login_register_user_id_' . $user_input, $user_id, 5 * MINUTE_IN_SECONDS);
            $html = jay_login_register_get_login_form_html($user_input);
            wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
        } else {
            if ($input_type === 'email') {
         // --- جدید: بررسی فعال بودن OTP ایمیل ---
        $email_otp_enabled = isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes';
        if ($email_otp_enabled) {
            set_transient('jay_login_register_new_user_email_' . $user_input, $user_input, 10 * MINUTE_IN_SECONDS);
            jay_login_register_send_email_otp_and_show_form($user_input, []); // ارسال OTP
        } else {
            // حالت قبلی: مستقیم به ساخت رمز عبور می‌رود
            set_transient('jay_login_register_new_user_email_' . $user_input, $user_input, 10 * MINUTE_IN_SECONDS);
            $html = jay_login_register_get_create_password_form_html($user_input);
            wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
        }
      
    } else { 
            // --- جدید: منطق انتخاب روش ارسال برای کاربر جدید ---
            $is_bale_otp_enabled = isset($settings['bale_otp_enable']) && $settings['bale_otp_enable'] === 'yes';
            if ($is_bale_otp_enabled) {
                // نمایش صفحه انتخاب
                $html = '<h3>انتخاب روش دریافت کد</h3><p>شماره <strong>' . esc_html($phone) . '</strong> برای عضویت تایید شد. کد تایید را چگونه دریافت می‌کنید؟</p><input type="hidden" name="user_input" value="' . esc_attr($phone) . '"><button type="button" class="jay-login-register-button" data-action="send_otp_sms">ارسال کد با پیامک</button><button type="button" class="jay-login-register-button-secondary" data-action="send_otp_bale">ارسال کد با بله</button>';
                wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);

            } else {
                // حالت قبلی: ارسال مستقیم پیامک
                jay_login_register_handle_otp_sending_choice($phone, []);
            }
    }
        }
        return;
    }
    
    $codemeli_enabled = in_array('codemeli', $id_methods, true);
    $passport_enabled = in_array('passport', $id_methods, true);

    if (!empty($users)) {
        // --- کاربر وجود دارد (مرحله ورود) ---
    $user_id = $users[0];
    set_transient('jay_login_register_user_id_' . $user_input, $user_id, 5 * MINUTE_IN_SECONDS);
    $stored_national_code = get_user_meta($user_id, 'codemeli', true);
    $stored_passport    = get_user_meta($user_id, 'gozarname', true);

        if ( ! empty($stored_national_code) || ! empty($stored_passport) ) {
            // **اگر کد ملی داشت:** مستقیم به مرحله ورود با رمز می‌رویم
            $html = jay_login_register_get_login_form_html($user_input);
        } else {
        $html = '<h3>تکمیل اطلاعات ورود</h3>';
            $form_fields_html = '';

            if ( $codemeli_enabled ) {
                $form_fields_html .= '
            <div id="jay-login-register-national-code-field"><div class="jay-login-register-field"><label for="jay_login_register_national_code">کد ملی</label><input type="text" name="jay_login_register_national_code" class="jay-login-register-input" inputmode="numeric"></div><button type="button" class="jay-login-register-button" data-action="check_national_code_login">تایید کد ملی</button>';
                if ($passport_enabled) $form_fields_html .= '<a href="#" class="jay-login-register-switcher" data-switch-to="passport">ورود با گذرنامه</a>';
                $form_fields_html .= '</div>';
            }

            if ( $passport_enabled ) {
                $display_style = $codemeli_enabled ? 'style="display:none;"' : '';
                $form_fields_html .= '
                <div id="jay-login-register-passport-field" ' . $display_style . '>
                    <div class="jay-login-register-field">
                        <label for="jay_login_register_passport">شماره گذرنامه</label>
                        <input type="text" name="jay_login_register_passport" class="jay-login-register-input">
                    </div>
                    <button type="button" class="jay-login-register-button" data-action="check_passport_login">تایید گذرنامه</button>';
                if ($codemeli_enabled) $form_fields_html .= '<a href="#" class="jay-login-register-switcher" data-switch-to="national-code">ورود با کد ملی</a>';
                $form_fields_html .= '</div>';
            }
            
            // --- اصلاح منطق نمایش متن پیام ---
            // متغیر $input_type در ابتدای تابع به درستی پر شده است (username, mobile, email)
            if ($input_type === 'username') {
                $input_type_string = 'نام کاربری';
            } elseif ($input_type === 'email') {
                $input_type_string = 'ایمیل';
            } else {
                $input_type_string = 'شماره';
            }
            
            $id_type_string = ($codemeli_enabled) ? 'کد ملی' : 'شماره گذرنامه';
            
            // تغییر مهم: نمایش مقدار ورودی (user_input) در پیام برای اطمینان کاربر
            $html .= '<p id="jay-login-register-instruction-text" data-input-type-string="' . esc_attr($input_type_string) . '">کاربری با این ' . $input_type_string . ' (<strong>' . esc_html($user_input) . '</strong>) یافت شد. لطفاً برای ادامه، <span id="jay-login-register-id-type-text">' . $id_type_string . '</span> خود را وارد کنید.</p>';
            $html .= $form_fields_html;
            $html .= '<input type="hidden" name="user_input" value="' . esc_attr($user_input) . '">';
            
            // اضافه کردن لینک "اصلاح اطلاعات" برای مواقعی که کاربر اشتباه تایپ کرده است
            $html .= '<div class="yekshomaredigar"><a href="#" onclick="location.reload(); return false;" class="jay-login-register-resend-link">اطلاعات را اشتباه وارد کرده‌اید؟</a></div>';
            
           
        }
        wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);

} else {
 
         if ( $input_type === 'email' ) {
     
     // بررسی می‌کنیم که آیا احراز هویت با کد ملی/گذرنامه فعال است یا خیر
            if ( !empty($id_methods) ) {
                // اگر فعال بود، ابتدا فرم درخواست کد ملی/گذرنامه را نشان بده
                set_transient('jay_login_register_new_user_email_' . $user_input, $user_input, 10 * MINUTE_IN_SECONDS);

                $html = '<h3>عضویت در سایت</h3>';
                $form_fields_html = '';

                if ($codemeli_enabled) {
                    $form_fields_html .= '<div id="jay-login-register-national-code-field"><div class="jay-login-register-field"><label for="jay_login_register_national_code">کد ملی</label><input type="text" name="jay_login_register_national_code" class="jay-login-register-input" inputmode="numeric"></div><button type="button" class="jay-login-register-button" data-action="register_with_national_code">بررسی و ادامه (کد ملی)</button>';
                    if ($passport_enabled) $form_fields_html .= '<a href="#" class="jay-login-register-switcher" data-switch-to="passport">عضویت با گذرنامه</a>';
                    $form_fields_html .= '</div>';
                }

                if ($passport_enabled) {
                    $display_style = $codemeli_enabled ? 'style="display:none;"' : '';
                    $form_fields_html .= '<div id="jay-login-register-passport-field" ' . $display_style . '><div class="jay-login-register-field"><label for="jay_login_register_passport">شماره گذرنامه</label><input type="text" name="jay_login_register_passport" class="jay-login-register-input"></div><button type="button" class="jay-login-register-button" data-action="register_with_passport">بررسی و ادامه (گذرنامه)</button>';
                    if ($codemeli_enabled) $form_fields_html .= '<a href="#" class="jay-login-register-switcher" data-switch-to="national-code">عضویت با کد ملی</a>';
                    $form_fields_html .= '</div>';
                }

                $id_type_string = ($codemeli_enabled) ? 'کد ملی' : 'شماره گذرنامه';
                // **تغییر کلیدی:** متن پیام برای ایمیل تغییر کرده است
                $html .= '<p>برای شروع عضویت با ایمیل <strong>' . esc_html($user_input) . '</strong>، لطفاً <span id="jay-login-register-id-type-text">' . $id_type_string . '</span> خود را وارد کنید.</p>' . $form_fields_html;
                // **تغییر کلیدی:** نام فیلد مخفی به user_input تغییر کرده است
                $html .= '<input type="hidden" name="user_input" value="' . esc_attr($user_input) . '"><a href="#" onclick="location.reload(); return false;" class="jay-login-register-resend-link">ایمیل را اشتباه وارد کرده‌اید؟</a>';
                wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);

            } else {
                // اگر احراز هویت غیرفعال بود، مستقیم به مرحله ساخت رمز عبور برو
                set_transient('jay_login_register_new_user_email_' . $user_input, $user_input, 10 * MINUTE_IN_SECONDS);
                $html = jay_login_register_get_create_password_form_html($user_input);
                wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
            }
            
         } else {
             
        $html = '<h3>عضویت در سایت</h3>';
        $form_fields_html = '';

        if ($codemeli_enabled) {
            $form_fields_html .= '<div id="jay-login-register-national-code-field"><div class="jay-login-register-field"><label for="jay_login_register_national_code">کد ملی</label><input type="text" name="jay_login_register_national_code" class="jay-login-register-input" inputmode="numeric"></div><button type="button" class="jay-login-register-button" data-action="register_with_national_code">بررسی و ادامه (کد ملی)</button>';
            if ($passport_enabled) $form_fields_html .= '<a href="#" class="jay-login-register-switcher" data-switch-to="passport">عضویت با گذرنامه</a>';
            $form_fields_html .= '</div>';
        }

        if ($passport_enabled) {
            $display_style = $codemeli_enabled ? 'style="display:none;"' : '';
            $form_fields_html .= '<div id="jay-login-register-passport-field" ' . $display_style . '><div class="jay-login-register-field"><label for="jay_login_register_passport">شماره گذرنامه</label><input type="text" name="jay_login_register_passport" class="jay-login-register-input"></div><button type="button" class="jay-login-register-button" data-action="register_with_passport">بررسی و ادامه (گذرنامه)</button>';
            if ($codemeli_enabled) $form_fields_html .= '<a href="#" class="jay-login-register-switcher" data-switch-to="national-code">عضویت با کد ملی</a>';
            $form_fields_html .= '</div>';
        }

        $id_type_string = ($codemeli_enabled) ? 'کد ملی' : 'شماره گذرنامه';
        $html .= '<p>برای شروع عضویت با شماره <strong>' . esc_html($phone) . '</strong>، لطفاً <span id="jay-login-register-id-type-text">' . $id_type_string . '</span> خود را وارد کنید.</p>' . $form_fields_html;
        $html .= '<input type="hidden" name="user_input" value="' . esc_attr($phone) . '">
        <a href="#" onclick="location.reload(); return false;" class="jay-login-register-resend-link">شماره را اشتباه وارد کرده‌اید؟</a>';
        wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
             
         }
    }
}

/**
 * مرحله ۲ (عضویت): تایید کد OTP
 */
add_action('wp_ajax_nopriv_jay_login_register_verify_otp_register', 'jay_login_register_ajax_verify_otp_register');
function jay_login_register_ajax_verify_otp_register() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $phone = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $otp_entered = isset($_POST['jay_login_register_otp']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_otp'])) : '';
    $settings    = get_option('jay_login_register_settings');
    $user_ip     = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );

    if (empty($phone) || empty($otp_entered)) wp_send_json_error(['message' => 'اطلاعات ناقص است.']);

    $otp_correct_data = get_transient('jay_login_register_otp_' . $phone);
    if ($otp_correct_data === false) wp_send_json_error(['message' => 'کد تایید منقضی شده است.']);
    
    if ((string) jay_login_register_normalize_numbers($otp_entered) !== (string) $otp_correct_data['otp']) {
        $max_retries = intval($settings['otp_max_retries'] ?? 3);
        $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
        $block_methods = $settings['otp_block_method'] ?? ['phone'];

        $fail_count = 0;
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
        
        jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );
        $remaining_tries = $max_retries - $fail_count;
        wp_send_json_error(['message' => "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید."]);
    }
    delete_transient('jay_login_register_otp_fail_count_' . $phone);
    delete_transient('jay_login_register_otp_fail_count_' . $user_ip);
    delete_transient('jay_login_register_otp_' . $phone);
    
    $identity_field = '';
    if (!empty($otp_correct_data['national_code'])) {
        $identity_field = '<input type="hidden" name="national_code" value="' . esc_attr($otp_correct_data['national_code']) . '">';
    } elseif (!empty($otp_correct_data['passport'])) {
        $identity_field = '<input type="hidden" name="passport_number" value="' . esc_attr($otp_correct_data['passport']) . '">';
    }

    $identity_data_for_form = [];
    if (!empty($otp_correct_data['national_code'])) {
        $identity_data_for_form['national_code'] = $otp_correct_data['national_code'];
    } elseif (!empty($otp_correct_data['passport'])) {
         // نام کلید را برای سازگاری با تابع جدید تغییر می‌دهیم
        $identity_data_for_form['passport_number'] = $otp_correct_data['passport'];
    }
    
    $html = jay_login_register_get_create_password_form_html($phone, $identity_data_for_form);
    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'message' => 'شماره موبایل با موفقیت تایید شد.']);
        
}
/**
 * مرحله ۲ (ورود): بررسی کد ملی کاربر موجود (نسخه اصلاح شده)
 */
add_action('wp_ajax_nopriv_jay_login_register_check_national_code_login', 'jay_login_register_ajax_check_national_code_login');
function jay_login_register_ajax_check_national_code_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $national_code = isset($_POST['jay_login_register_national_code']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_national_code'])) : '';
    $national_code = jay_login_register_normalize_numbers($national_code);
    // ۱. اولین گام: بررسی فرمت و اعتبار الگوریتمی کد ملی
    if (!is_valid_iranian_national_code($national_code)) {
        wp_send_json_error(['message' => 'کد ملی وارد شده معتبر نیست.']);
    }
    $user_id = get_transient('jay_login_register_user_id_' . $user_input);
    if ($user_id === false) {
        wp_send_json_error(['message' => 'نشست شما منقضی شده است. لطفاً از مرحله اول شروع کنید.']);
    }
    // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
    $user_query = new WP_User_Query([
        'meta_key'   => 'codemeli',
        'meta_value' => $national_code,
        'exclude'    => [$user_id],
        'number'     => 1,
        'fields'     => 'ID',
    ]);
    // phpcs:enable
    
    $other_users = $user_query->get_results();
    if (!empty($other_users)) {
        $error_message = jay_login_register_get_duplicate_id_error_message( $other_users[0], 'کد ملی' );
        wp_send_json_error(['message' => $error_message]);
    }
    set_transient('jay_login_register_new_nc_' . $user_input, $national_code, 5 * MINUTE_IN_SECONDS);
    
    // نمایش فرم ورود
    $html = jay_login_register_get_login_form_html($user_input);
    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
}
// ====================================================================
// جدید: مرحله ۲ (ورود): بررسی گذرنامه کاربر موجود
// ====================================================================
add_action('wp_ajax_nopriv_jay_login_register_check_passport_login', 'jay_login_register_ajax_check_passport_login');
function jay_login_register_ajax_check_passport_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $passport = isset($_POST['jay_login_register_passport']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_passport'])) : '';
    
    if (empty($passport)) {
        wp_send_json_error(['message' => 'شماره گذرنامه نمی‌تواند خالی باشد.']);
    }
    if ( ! is_valid_passport_format($passport) ) {
        wp_send_json_error(['message' => 'فرمت شماره گذرنامه نامعتبر است. لطفاً فقط از حروف انگلیسی و اعداد استفاده کنید.']);
    }

    $user_id = get_transient('jay_login_register_user_id_' . $user_input);
    if ($user_id === false) {
        wp_send_json_error(['message' => 'نشست شما منقضی شده است. لطفاً از مرحله اول شروع کنید.']);
    }
    // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
    $user_query = new WP_User_Query([
        'meta_key'   => 'gozarname',
        'meta_value' => $passport,
        'exclude'    => [$user_id], // به جز خود کاربر فعلی
        'number'     => 1,
        'fields'     => 'ID',
    ]);
    // phpcs:enable

    $other_users = $user_query->get_results();

    if (!empty($other_users)) {
        $error_message = jay_login_register_get_duplicate_id_error_message( $other_users[0], 'گذرنامه' );
        wp_send_json_error(['message' => $error_message]);
    }
    // اگر همه چیز درست بود، گذرنامه جدید را موقتا ذخیره می‌کنیم تا بعد از ورود ثبت شود
    set_transient('jay_login_register_new_pn_' . $user_input, $passport, 5 * MINUTE_IN_SECONDS);

    // نمایش فرم ورود
    $html = jay_login_register_get_login_form_html($user_input);
    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
}
/**
 * مرحله ۳ (عضویت): بررسی کد ملی و نمایش فرم رمز
 */
add_action('wp_ajax_nopriv_jay_login_register_register_with_national_code', 'jay_login_register_ajax_register_with_national_code');
function jay_login_register_ajax_register_with_national_code() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $national_code = isset($_POST['jay_login_register_national_code']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_national_code'])) : '';
    $national_code = jay_login_register_normalize_numbers($national_code);

    // ۱. اعتبارسنجی فرمت کد ملی
    if (!is_valid_iranian_national_code($national_code)) {
        wp_send_json_error(['message' => 'کد ملی وارد شده معتبر نیست.']);
    }
    // ۲. بررسی تکراری بودن کد ملی
    // phpcs:disable WordPress.DB.SlowDBQuery
    $user_query = new WP_User_Query(['meta_key' => 'codemeli', 'meta_value' => $national_code, 'number' => 1, 'fields' => 'ID']);
    // phpcs:enable
    $existing_user = $user_query->get_results();

   if (!empty($existing_user)) {
        // تغییر: استفاده از تابع کمکی برای نمایش خطا با ماسک صحیح
        $error_message = jay_login_register_get_duplicate_id_error_message( $existing_user[0], 'کد ملی' );
        wp_send_json_error(['message' => $error_message]);
    }

    $transient_data = ['national_code' => $national_code];
    if ( is_email($user_input) ) {
       // اگر ورودی ایمیل بود، به مرحله تایید ایمیل با OTP برو
        $settings = get_option('jay_login_register_settings');
        $email_otp_enabled = isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes';
        if ($email_otp_enabled) {
            jay_login_register_send_email_otp_and_show_form($user_input, $transient_data);
        } else {
              $html = jay_login_register_get_create_password_form_html($user_input, ['national_code' => $national_code]);
                wp_send_json_success([
                    'html' => Jay_Login_Register_Minifier::html($html),
                    'message' => 'کد ملی معتبر است. لطفاً رمز عبور خود را ایجاد کنید.'
                ]);
      
        }
        } else {
            // اگر ورودی موبایل بود، طبق روال قبل OTP ارسال کن
            $phone = $user_input;
            jay_login_register_handle_otp_sending_choice($phone, $transient_data);
        }
}
/**
 * جدید: تابع کمکی برای نمایش صفحه انتخاب روش ارسال OTP یا ارسال مستقیم پیامک
 */
function jay_login_register_handle_otp_sending_choice($phone, $identity_data = []) {
    $settings = get_option('jay_login_register_settings');
    $is_bale_otp_enabled = isset($settings['bale_otp_enable']) && $settings['bale_otp_enable'] === 'yes';

    if ($is_bale_otp_enabled) {
        // نمایش صفحه انتخاب

        // اگر اطلاعات هویتی وجود دارد، فیلدهای مخفی آن‌ها را هم بساز
        $identity_fields_html = '';
        if (!empty($identity_data['national_code'])) {
        $identity_fields_html .= '<input type="hidden" name="national_code" value="' . esc_attr($identity_data['national_code']) . '">';
        } elseif (!empty($identity_data['passport'])) { // از کلید 'passport' که در transient ذخیره می‌شود استفاده می‌کنیم
        $identity_fields_html .= '<input type="hidden" name="passport_number" value="' . esc_attr($identity_data['passport']) . '">';
        }
$html = '<h3>انتخاب روش دریافت کد</h3>
<p>شماره <strong>' . esc_html($phone) . '</strong> برای عضویت تایید شد. کد تایید را چگونه دریافت می‌کنید؟</p>
<input type="hidden" name="user_input" value="' . esc_attr($phone) . '">'
. $identity_fields_html . // <-- اطمینان از وجود این خط
'<button type="button" class="jay-login-register-button" data-action="send_otp_sms">ارسال کد با پیامک</button>
<button type="button" class="jay-login-register-button-secondary jay-login-register-button-bale" data-action="send_otp_bale"><span class="social-icon"></span>ارسال کد با بله</button>
<p class="jay-login-register-bale-notice">توجه: گزینه "ارسال با بله" فقط برای کاربرانی است که اپلیکیشن بله را با این شماره تلفن نصب کرده‌اند.</p>';
        wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);

    } else {
        // حالت قبلی: ارسال مستقیم پیامک
        jay_login_register_send_otp_and_show_form($phone, $identity_data);
    }
}
// ====================================================================
// جدید: تابع کمکی برای ارسال OTP و نمایش فرم (برای جلوگیری از تکرار کد)
// ====================================================================
function jay_login_register_send_otp_and_show_form($phone, $identity_data) {
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    
    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );
    
    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('unhandled_sms_filter', 'فیلتر jay_relog_send_otp اجرا نشد.'), $phone, $otp);
    if (is_wp_error($send_result)) {
        wp_send_json_error(['message' => 'خطا در ارسال کد: ' . $send_result->get_error_message()]);
    }

    $transient_data = array_merge(['otp' => $otp, 'time' => time()], $identity_data);
    set_transient('jay_login_register_otp_' . $phone, $transient_data, $validity_period * MINUTE_IN_SECONDS);
   
    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به شماره <strong>' . esc_html($phone) . '</strong> ارسال شد.';
    $html = jay_login_register_get_otp_verification_form_html('تایید شماره موبایل', $instruction, $phone, 'jay_login_register_otp', 'verify_otp_register', 'resend_otp', 'register');

    wp_send_json_success([
            'html' => Jay_Login_Register_Minifier::html($html),
            'message' => 'شناسه معتبر است. کد تایید ارسال شد.',
            'validity_period' => $validity_period * 60
        ]);
}
add_action('wp_ajax_nopriv_jay_login_register_register_with_passport', 'jay_login_register_ajax_register_with_passport');
function jay_login_register_ajax_register_with_passport() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $passport = isset($_POST['jay_login_register_passport']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_passport'])) : '';

    if (empty($passport)) {
        wp_send_json_error(['message' => 'شماره گذرنامه نمی‌تواند خالی باشد.']);
    }
    if ( ! is_valid_passport_format($passport) ) {
        wp_send_json_error(['message' => 'فرمت شماره گذرنامه نامعتبر است. لطفاً فقط از حروف انگلیسی و اعداد استفاده کنید.']);
    }
    // بررسی تکراری بودن گذرنامه
    // phpcs:disable WordPress.DB.SlowDBQuery
    $user_query = new WP_User_Query(['meta_key' => 'gozarname', 'meta_value' => $passport, 'number' => 1, 'fields' => 'ID']);
    // phpcs:enable
    $existing_user = $user_query->get_results();

   if (!empty($existing_user)) {
        // تغییر: استفاده از تابع کمکی برای نمایش خطا با ماسک صحیح
        $error_message = jay_login_register_get_duplicate_id_error_message( $existing_user[0], 'گذرنامه' );
        wp_send_json_error(['message' => $error_message]);
    }

    $transient_data = ['passport' => $passport];
    if ( is_email($user_input) ) {
    // اگر ورودی ایمیل بود، به مرحله تایید ایمیل با OTP برو
    $settings = get_option('jay_login_register_settings');
    $email_otp_enabled = isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes';
    if ($email_otp_enabled) {
        jay_login_register_send_email_otp_and_show_form($user_input, $transient_data);
    } else {

        $html = jay_login_register_get_create_password_form_html($user_input, ['passport_number' => $passport]);
        wp_send_json_success([
            'html' => Jay_Login_Register_Minifier::html($html),
            'message' => 'گذرنامه معتبر است. لطفاً رمز عبور خود را ایجاد کنید.'
        ]);
    }
        } else {
            // اگر ورودی موبایل بود، طبق روال قبل OTP ارسال کن
            $phone = $user_input;
            jay_login_register_handle_otp_sending_choice($phone, $transient_data);
            
        }
}
/**
 * جدید: مرحله تایید OTP ایمیل برای عضویت
 */
add_action('wp_ajax_nopriv_jay_login_register_verify_email_otp_register', 'jay_login_register_ajax_verify_email_otp_register');
function jay_login_register_ajax_verify_email_otp_register() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    $email = isset($_POST['user_input']) ? sanitize_email(wp_unslash($_POST['user_input'])) : '';
    $otp_entered = isset($_POST['jay_login_register_email_otp']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_email_otp'])) : '';
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $email, $user_ip, $settings );

    if (empty($email) || empty($otp_entered)) wp_send_json_error(['message' => 'اطلاعات ناقص است.']);

    $otp_correct_data = get_transient('jay_login_register_email_otp_register_' . $email);
    if ($otp_correct_data === false) wp_send_json_error(['message' => 'کد تایید منقضی شده است.']);

    if ((string) jay_login_register_normalize_numbers($otp_entered) !== (string) $otp_correct_data['otp']) {
        // منطق شمارش خطا و مسدودسازی
        $max_retries = intval($settings['otp_max_retries'] ?? 3);
        $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);

        $fail_count = get_transient('jay_login_register_otp_fail_count_' . $email) ?: 0;
        $fail_count++;
        if ($fail_count >= $max_retries) {
            set_transient('jay_login_register_otp_block_' . $email, 'blocked', $lockout_duration * MINUTE_IN_SECONDS);
            delete_transient('jay_login_register_otp_fail_count_' . $email);
        } else {
            set_transient('jay_login_register_otp_fail_count_' . $email, $fail_count, $lockout_duration * MINUTE_IN_SECONDS);
        }

        jay_login_register_check_and_handle_lockout( $email, $user_ip, $settings );
        $remaining_tries = $max_retries - $fail_count;
        wp_send_json_error(['message' => "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید."]);
    }

    // پاک کردن ترنزینت‌های خطا و OTP
    delete_transient('jay_login_register_otp_fail_count_' . $email);
    delete_transient('jay_login_register_email_otp_register_' . $email);

    // آماده‌سازی فیلدهای مخفی برای فرم بعدی
    $identity_field = '';
    if (!empty($otp_correct_data['national_code'])) {
        $identity_field = '<input type="hidden" name="national_code" value="' . esc_attr($otp_correct_data['national_code']) . '">';
    } elseif (!empty($otp_correct_data['passport'])) {
        $identity_field = '<input type="hidden" name="passport_number" value="' . esc_attr($otp_correct_data['passport']) . '">';
    }

    $identity_data_for_form = [];
    if (!empty($otp_correct_data['national_code'])) {
        $identity_data_for_form['national_code'] = $otp_correct_data['national_code'];
    } elseif (!empty($otp_correct_data['passport'])) {
        // نام کلید را برای سازگاری با تابع جدید تغییر می‌دهیم
        $identity_data_for_form['passport_number'] = $otp_correct_data['passport'];
    }
    
    $html = jay_login_register_get_create_password_form_html($email, $identity_data_for_form);
    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'message' => 'ایمیل با موفقیت تایید شد.']);
    
}

/**
 * مرحله نهایی (عضویت): ساخت کاربر
 */
add_action('wp_ajax_nopriv_jay_login_register_create_final_user', 'jay_login_register_ajax_create_final_user');
function jay_login_register_ajax_create_final_user() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    // دریافت تنظیمات
    $settings = get_option('jay_login_register_settings');

    // 1. دریافت داده‌های ورودی
    $first_name = isset($_POST['jay_login_register_first_name']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_first_name'])) : '';
    $last_name = isset($_POST['jay_login_register_last_name']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_last_name'])) : '';
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $national_code = isset($_POST['national_code']) ? sanitize_text_field(wp_unslash($_POST['national_code'])) : '';
    $passport_number = isset($_POST['passport_number']) ? sanitize_text_field(wp_unslash($_POST['passport_number'])) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $password = ( isset( $_POST['jay_login_register_password'] ) && is_string( $_POST['jay_login_register_password'] ) ) ? wp_unslash( $_POST['jay_login_register_password'] ) : '';

    // 2. بررسی ناقص بودن اطلاعات پایه
    $id_methods = $settings['id_methods'] ?? [];
    $id_is_missing = !empty($id_methods) && empty($national_code) && empty($passport_number);
    
    if (empty($user_input) || $id_is_missing || empty($password)) {
        wp_send_json_error(['message' => 'اطلاعات ارسالی ناقص است.']);
    }

    // 3. اعتبارسنجی نام کاربری (اگر فعال باشد)
    $final_user_login = '';
    if ( isset($settings['enable_username']) && $settings['enable_username'] === 'yes' ) {
        $custom_username = isset($_POST['jay_login_register_custom_username']) ? trim(sanitize_text_field(wp_unslash($_POST['jay_login_register_custom_username']))) : '';

        // اگر الزامی است و خالی فرستاده
        if ( isset($settings['required_username']) && $settings['required_username'] === 'yes' && empty($custom_username) ) {
            wp_send_json_error(['message' => 'وارد کردن نام کاربری الزامی است.']);
        }

        if ( ! empty($custom_username) ) {
            if ( ! preg_match('/^[a-zA-Z0-9_]+$/', $custom_username) ) wp_send_json_error(['message' => 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و _ باشد.']);
            if ( strlen($custom_username) < 4 ) wp_send_json_error(['message' => 'نام کاربری باید حداقل ۴ کاراکتر باشد.']);
            if ( username_exists($custom_username) ) wp_send_json_error(['message' => 'این نام کاربری قبلاً گرفته شده است.']);
            
            $final_user_login = $custom_username;
        }
    }

    // 4. اعتبارسنجی نام و نام خانوادگی (اگر فعال باشد)
    if ( isset($settings['enable_name_fields']) && $settings['enable_name_fields'] === 'yes' ) {
        // الزامی بودن
        if ( isset($settings['required_name_fields']) && $settings['required_name_fields'] === 'yes' ) {
            if ( empty($first_name) || empty($last_name) ) {
                wp_send_json_error(['message' => 'وارد کردن نام و نام خانوادگی الزامی است.']);
            }
        }
        
        // فقط فارسی
        if ( isset($settings['force_persian_name_fields']) && $settings['force_persian_name_fields'] === 'yes' ) {
            if ( !empty($first_name) && !preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $first_name) ) {
                wp_send_json_error(['message' => 'نام باید فقط شامل حروف فارسی باشد.']);
            }
            if ( !empty($last_name) && !preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $last_name) ) {
                wp_send_json_error(['message' => 'نام خانوادگی باید فقط شامل حروف فارسی باشد.']);
            }
        }
    }

    // 5. اعتبارسنجی فیلدهای سفارشی (Global Fields) - بررسی ضروری بودن
    if ( !empty($settings['enable_custom_fields_global']) && $settings['enable_custom_fields_global'] === 'yes' ) {
        $custom_fields = json_decode( $settings['custom_fields_global_json'] ?? '[]', true );
        
        if ( is_array($custom_fields) ) {
            foreach ( $custom_fields as $field ) {
                if ( !empty($field['is_required']) && $field['is_required'] == 1 ) {
                    $key = $field['key'];
                    $label = $field['label'];
                    // نام فیلد در فرم html به صورت meta_KEY است
                    $input_name = 'meta_' . $key;
                    
                    // رفع باگ مهم: PHP فاصله‌ها در نام کلید را به _ تبدیل می‌کند
                    // اگر کاربر کلید را "my key" گذاشته باشد، PHP آن را "my_key" می‌خواند.
                    // ما هم اینجا تبدیل می‌کنیم تا هماهنگ شود.
                    $input_name_fixed = str_replace(' ', '_', $input_name);

                    // بررسی وجود مقدار
                    if ( !isset($_POST[$input_name_fixed]) ) {
                        wp_send_json_error(['message' => "لطفاً فیلد «{$label}» را تکمیل کنید."]);
                    } 
                    
                    // رفع ارور امنیتی: Unslash و Sanitize کردن ورودی بر اساس نوع آن
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $raw_val = wp_unslash( $_POST[$input_name_fixed] );
                    
                    if ( is_array( $raw_val ) ) {
                        // اگر آرایه است (مثل چک‌باکس)، تمام مقادیر داخلی را تمیز می‌کنیم
                        $val = array_map( 'sanitize_text_field', $raw_val );
                    } else {
                        // اگر پاراگراف است از sanitize_textarea_field استفاده می‌کنیم تا اینترها حفظ شوند
                        // در غیر این صورت از sanitize_text_field معمولی استفاده می‌کنیم
                        $val = ( isset($field['type']) && $field['type'] === 'textarea' ) ? sanitize_textarea_field( $raw_val ) : sanitize_text_field( $raw_val );
                    }
                    
                    // بررسی خالی بودن مقدار
                    if ( is_array($val) ) {
                        // برای چک‌باکس: آرایه خالی یعنی انتخاب نشده
                        if ( empty($val) ) {
                            wp_send_json_error(['message' => "لطفاً فیلد «{$label}» را تکمیل کنید."]);
                        }
                    } 
                    elseif ( is_string($val) && trim($val) === '' ) {
                        wp_send_json_error(['message' => "لطفاً فیلد «{$label}» را تکمیل کنید."]);
                    }
                }
                // --- اعتبارسنجی اختصاصی نوع شماره (جدید) ---
                if ( isset($field['type']) && $field['type'] === 'number' ) {
                    $key = $field['key'];
                    $input_name = 'meta_' . $key;
                    // رفع باگ فاصله در نام
                    $input_name_fixed = str_replace(' ', '_', $input_name);
                    
                    if ( !empty($_POST[$input_name_fixed]) ) {
                        $val = sanitize_text_field(wp_unslash($_POST[$input_name_fixed]));
                        // تبدیل اعداد فارسی به انگلیسی برای بررسی دقیق
                        $val = jay_login_register_normalize_numbers($val);
                        
                        // 1. بررسی اینکه فقط عدد باشد
                        if ( !ctype_digit($val) ) {
                            wp_send_json_error(['message' => "فیلد «{$field['label']}» فقط باید شامل عدد باشد."]);
                        }
                        
                        // 2. بررسی طول دقیق
                        if ( !empty($field['number_len']) ) {
                            $len = (int)$field['number_len'];
                            if ( strlen($val) !== $len ) {
                                wp_send_json_error(['message' => "فیلد «{$field['label']}» باید دقیقاً {$len} رقم باشد."]);
                            }
                        }
                        
                        // 3. بررسی پیش‌شماره
                        if ( !empty($field['number_start']) ) {
                            $start = (string)$field['number_start'];
                            if ( strpos($val, $start) !== 0 ) {
                                wp_send_json_error(['message' => "فیلد «{$field['label']}» باید با {$start} شروع شود."]);
                            }
                        }
                    }
                }
            }
        }
    }
    // 6. آماده‌سازی داده‌های کاربر برای ساخت
    $new_user_email_transient = get_transient('jay_login_register_new_user_email_' . $user_input);

    if ($new_user_email_transient) { // ثبت‌نام با ایمیل
        $email = $new_user_email_transient;
        
        if ( empty($final_user_login) ) {
            $username_base = sanitize_user(explode('@', $email)[0], true);
            $final_user_login = $username_base;
            $counter = 1;
            while ( username_exists($final_user_login) ) {
                $final_user_login = $username_base . $counter++;
            }
        }

        $user_data = [
            'user_login'    => $final_user_login,
            'user_pass'     => $password,
            'user_email'    => $email,
            'display_name'  => $final_user_login,
            'role'          => 'subscriber',
            'first_name'    => $first_name,
            'last_name'     => $last_name,
        ];

    } else { // ثبت‌نام با موبایل
        $phone = $user_input;
        
        if ( empty($final_user_login) ) {
            $final_user_login = $phone;
        }

        $user_data = [
            'user_login'    => $final_user_login,
            'user_pass'     => $password,
            'user_email'    => $phone . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'display_name'  => $final_user_login,
            'user_nicename' => $final_user_login,
            'nickname'      => $final_user_login,
            'role'          => 'subscriber',
            'first_name'    => $first_name,
            'last_name'     => $last_name,
        ];
    }
    
    // اگر نام واقعی وارد شده، display_name را آپدیت کن
    if ( !empty($first_name) || !empty($last_name) ) {
        $user_data['display_name'] = trim($first_name . ' ' . $last_name);
    }

    // 7. ساخت کاربر
    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'خطا در ساخت حساب کاربری: ' . $user_id->get_error_message()]);
    }

    // 8. ذخیره اطلاعات تکمیلی
    if (!empty($national_code)) update_user_meta($user_id, 'codemeli', $national_code);
    if (!empty($passport_number)) update_user_meta($user_id, 'gozarname', $passport_number);

    if ($new_user_email_transient) {
        delete_transient('jay_login_register_new_user_email_' . $user_input);
    } else {
        $phone = $user_input;
        update_user_meta($user_id, 'jay_mobile', $phone);
        update_user_meta($user_id, 'digits_phone', '+98' . substr($phone, 1));
        update_user_meta($user_id, 'digits_phone_no', substr($phone, 1));
    }

// 9. ذخیره فیلدهای سفارشی (با تشخیص نوع فیلد و تبدیل تاریخ)
    
    // الف) خواندن تنظیمات فیلدها برای تشخیص نوع آن‌ها
    $custom_fields_config = json_decode( $settings['custom_fields_global_json'] ?? '[]', true );
    $fields_map = [];
    if ( is_array($custom_fields_config) ) {
        foreach ($custom_fields_config as $f) {
            $fields_map[ $f['key'] ] = $f; // آرایه‌ای برای دسترسی سریع: 'birthdate' => {config}
        }
    }

    foreach ($_POST as $post_key => $post_value) {
        if ( strpos($post_key, 'meta_') === 0 ) {
            $real_meta_key = substr($post_key, 5); 
            // باگ‌گیری: تبدیل فاصله به آندرلاین چون در POST فضاها _ می‌شوند
            $real_meta_key = str_replace(' ', '_', $real_meta_key); // اطمینان حاصل می‌کنیم
            $real_meta_key = sanitize_key($real_meta_key);
            
            if ( is_array($post_value) ) {
                $sanitized_values = array_map('sanitize_text_field', wp_unslash($post_value));
                update_user_meta($user_id, $real_meta_key, $sanitized_values);
            } else {
                $raw_value = wp_unslash($post_value);
                // استفاده از sanitize_textarea_field برای حفظ اینترها در پاراگراف
                $final_value = sanitize_textarea_field($raw_value);

                // بررسی تنظیمات این فیلد خاص
                if ( isset($fields_map[$real_meta_key]) ) {
                    $field_config = $fields_map[$real_meta_key];
                    
                    // --- الف: اگر تاریخ شمسی بود -> تبدیل به میلادی ---
                    if ( isset($field_config['type']) && $field_config['type'] === 'date' ) {
                        if ( !empty($field_config['is_jalali']) && $field_config['is_jalali'] == 1 ) {
                            $final_value = jay_login_register_convert_jalali_to_gregorian($final_value);
                        }
                    }

                    // --- ب: اگر شماره بود -> تبدیل به انگلیسی ---
                    if ( isset($field_config['type']) && $field_config['type'] === 'number' ) {
                        $final_value = jay_login_register_normalize_numbers($final_value);
                    }
                }

                update_user_meta($user_id, $real_meta_key, $final_value);
            }
        }
    }

    // 10. ورود و ریدایرکت
    wp_set_auth_cookie($user_id, true);

    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
    $referrer    = isset($_POST['referrer_url']) ? esc_url_raw(wp_unslash($_POST['referrer_url'])) : home_url('/');
    // phpcs:enable

    if ( ! empty( $redirect_to ) ) {
        $redirect_url = $redirect_to;
    } else {
        $redirect_url = get_jay_login_register_redirect_url($referrer);
    }

    wp_send_json_success(['message' => 'ثبت نام با موفقیت انجام شد در حال ورود ...', 'redirect_url' => $redirect_url]);
}

/**
 * مرحله نهایی (ورود): ورود با رمز عبور
 */
add_action('wp_ajax_nopriv_jay_login_register_login_with_password', 'jay_login_register_ajax_login_with_password');
function jay_login_register_ajax_login_with_password() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $user_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';

     // Password should not be sanitized in a way that alters it.
    // We validate it's a string and unslash it. wp_check_password handles security.
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $password = ( isset( $_POST['jay_login_register_password'] ) && is_string( $_POST['jay_login_register_password'] ) ) ? wp_unslash( $_POST['jay_login_register_password'] ) : '';
    
    $user_id = get_transient('jay_login_register_user_id_' . $user_input);
    if ($user_id === false) {
        wp_send_json_error(['message' => 'نشست شما منقضی شده است.']);
    }
    $user = get_user_by('id', $user_id);
    if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
        wp_send_json_error(['message' => 'رمز عبور وارد شده صحیح نیست.']);
    }
    if ( preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($user_input)) ) {
        update_user_meta( $user_id, 'jay_mobile', $user_input );
    }

    // اگر کاربر کد ملی جدیدی وارد کرده بود، آن را برایش ثبت می‌کنیم
    $new_national_code = get_transient('jay_login_register_new_nc_' . $user_input);
    if ($new_national_code !== false) {
    update_user_meta($user_id, 'codemeli', $new_national_code);
    delete_transient('jay_login_register_new_nc_' . $user_input);
    }
    $new_passport = get_transient('jay_login_register_new_pn_' . $user_input);
    if ($new_passport !== false) {
    update_user_meta($user_id, 'gozarname', $new_passport);
    delete_transient('jay_login_register_new_pn_' . $user_input);
    }
    wp_set_auth_cookie($user_id, true);
    delete_transient('jay_login_register_user_id_' . $user_input);
    

// تعیین آدرس ریدایرکت هوشمند
  $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
    $referrer    = isset($_POST['referrer_url']) ? esc_url_raw(wp_unslash($_POST['referrer_url'])) : home_url('/');

    if ( ! empty( $redirect_to ) ) {
        $redirect_url = $redirect_to;
    } else {
        $redirect_url = get_jay_login_register_redirect_url($referrer);
    }

wp_send_json_success(['message' => 'ورود با موفقیت انجام شد در حال انتقال ...', 'redirect_url' => $redirect_url]);
    
}
/**
 * جدید: تابع AJAX برای ارسال OTP از طریق پیامک (وقتی کاربر انتخاب می‌کند)
 */
add_action('wp_ajax_nopriv_jay_login_register_send_otp_sms', 'jay_login_register_ajax_send_otp_sms');
function jay_login_register_ajax_send_otp_sms() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    $phone = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    if (empty($phone)) wp_send_json_error(['message' => 'شماره تلفن یافت نشد.']);
    $identity_data = [];
    if (isset($_POST['national_code'])) {
        $identity_data['national_code'] = sanitize_text_field(wp_unslash($_POST['national_code']));
    } elseif (isset($_POST['passport_number'])) {
        $identity_data['passport'] = sanitize_text_field(wp_unslash($_POST['passport_number'])); // کلید passport را برای سازگاری ذخیره می‌کنیم
    }
    // از تابع موجود برای ارسال پیامک استفاده می‌کنیم
    jay_login_register_send_otp_and_show_form($phone, $identity_data);
    
}
/**
 * جدید: تابع AJAX برای ارسال OTP از طریق بله
 */
add_action('wp_ajax_nopriv_jay_login_register_send_otp_bale', 'jay_login_register_ajax_send_otp_bale');
function jay_login_register_ajax_send_otp_bale() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    $phone = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    if (empty($phone)) wp_send_json_error(['message' => 'شماره تلفن یافت نشد.']);

    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);

    // فراخوانی تابع جدید برای ارسال از طریق بله
    $send_result = jay_login_register_send_otp_via_bale($phone, $otp);
    if (is_wp_error($send_result)) {
        wp_send_json_error(['message' => $send_result->get_error_message()]);
    }
    $identity_data = [];
    if (isset($_POST['national_code'])) {
        $identity_data['national_code'] = sanitize_text_field(wp_unslash($_POST['national_code']));
    } elseif (isset($_POST['passport_number'])) {
        $identity_data['passport'] = sanitize_text_field(wp_unslash($_POST['passport_number']));
    }

    // ذخیره OTP و اطلاعات هویتی در transient
    $transient_data_to_save = array_merge(['otp' => $otp, 'time' => time()], $identity_data);
    set_transient('jay_login_register_otp_' . $phone, $transient_data_to_save, $validity_period * MINUTE_IN_SECONDS);
    // نمایش فرم ورود کد
    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به اپلیکیشن بله شما ارسال شد.';
    $html = jay_login_register_get_otp_verification_form_html('تایید شماره موبایل', $instruction, $phone, 'jay_login_register_otp', 'verify_otp_register', 'resend_otp', 'register');

    wp_send_json_success([
        'html'            => Jay_Login_Register_Minifier::html($html),
        'message'         => 'کد تایید به بله ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * تابع جدید: ارسال OTP برای ورود
 */
add_action('wp_ajax_nopriv_jay_login_register_send_otp_for_login', 'jay_login_register_ajax_send_otp_for_login');
function jay_login_register_ajax_send_otp_for_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $raw_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $phone = '';

  if ( preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($raw_input)) ) {
        $phone = $raw_input;
    } else {
        // اگر ورودی موبایل نبود، فرض می‌کنیم نام کاربری است
        $user = get_user_by('login', $raw_input);
        if ($user) {
            $phone = get_user_meta($user->ID, 'jay_mobile', true);
            // **مهم:** نشست را برای شماره موبایل هم ست می‌کنیم تا در مرحله تایید خطا ندهد
            if ($phone) {
                set_transient('jay_login_register_user_id_' . $phone, $user->ID, 5 * MINUTE_IN_SECONDS);
            }
        }
    }

    if (empty($phone)) wp_send_json_error(['message' => 'شماره تلفن برای این حساب یافت نشد.']);
    
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);

    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('unhandled_sms_filter', 'فیلتر jay_relog_send_otp اجرا نشد.'), $phone, $otp);
    if (is_wp_error($send_result)) wp_send_json_error(['message' => 'خطا در ارسال کد: ' . $send_result->get_error_message()]);

    set_transient('jay_login_register_otp_login_' . $phone, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);
    
    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به شماره <strong>' . esc_html($phone) . '</strong> ارسال شد. آن را وارد کنید.';
    $html = jay_login_register_get_otp_verification_form_html('ورود با رمز یکبار مصرف', $instruction, $phone, 'jay_login_register_otp_login', 'verify_otp_for_login', 'resend_otp', 'login');

    wp_send_json_success([
            'html' => Jay_Login_Register_Minifier::html($html),
            'message' => 'کد ورود با موفقیت ارسال شد.',
            'validity_period' => $validity_period * 60
        ]);
}
/**
 * تابع جدید: تایید OTP برای ورود
 */
add_action('wp_ajax_nopriv_jay_login_register_verify_otp_for_login', 'jay_login_register_ajax_verify_otp_for_login');
function jay_login_register_ajax_verify_otp_for_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    $phone = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $otp_entered = isset($_POST['jay_login_register_otp_login']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_otp_login'])) : '';
    
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );
    
    $otp_correct_data = get_transient('jay_login_register_otp_login_' . $phone);
    if ($otp_correct_data === false) wp_send_json_error(['message' => 'کد تایید منقضی شده است.']);

    if ((string) jay_login_register_normalize_numbers($otp_entered) !== (string) $otp_correct_data['otp']) {
        // منطق شمارش خطا و مسدودسازی
        $max_retries = intval($settings['otp_max_retries'] ?? 3);
        $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
        $block_methods = $settings['otp_block_method'] ?? ['phone'];

        $fail_count = 0;
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
        
        jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );
        $remaining_tries = $max_retries - $fail_count;
        wp_send_json_error(['message' => "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید."]);
    }
    
    delete_transient('jay_login_register_otp_fail_count_' . $phone);
    delete_transient('jay_login_register_otp_fail_count_' . $user_ip);
    delete_transient('jay_login_register_otp_login_' . $phone);

    $user_id = get_transient('jay_login_register_user_id_' . $phone);
    if (!$user_id) {
        wp_send_json_error(['message' => 'خطای نشست. لطفاً مجدداً تلاش کنید.']);
    }
    
    update_user_meta( $user_id, 'jay_mobile', $phone );

    $new_national_code = get_transient('jay_login_register_new_nc_' . $phone);
    if ($new_national_code !== false) {
        update_user_meta($user_id, 'codemeli', $new_national_code);
        delete_transient('jay_login_register_new_nc_' . $phone);
    }
    $new_passport = get_transient('jay_login_register_new_pn_' . $phone);
    if ($new_passport !== false) {
        update_user_meta($user_id, 'gozarname', $new_passport);
        delete_transient('jay_login_register_new_pn_' . $phone);
    }
    // پاک کردن تمام Transient ها
    delete_transient('jay_login_register_otp_login_' . $phone);
    delete_transient('jay_login_register_user_id_' . $phone);
    
    wp_set_auth_cookie($user_id, true);
    
// تعیین آدرس ریدایرکت هوشمند
    $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
    $referrer    = isset($_POST['referrer_url']) ? esc_url_raw(wp_unslash($_POST['referrer_url'])) : home_url('/');

    if ( ! empty( $redirect_to ) ) {
        $redirect_url = $redirect_to;
    } else {
        $redirect_url = get_jay_login_register_redirect_url($referrer);
    }

wp_send_json_success(['message' => 'کد تاییدیه صحیح بود درحال ورود و انتقال ...', 'redirect_url' => $redirect_url]);
    
}
/**
 * ارسال OTP برای تغییر شماره موبایل
 */
add_action('wp_ajax_jay_login_register_send_change_phone_otp', 'jay_login_register_ajax_send_change_phone_otp');
function jay_login_register_ajax_send_change_phone_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'شما اجازه دسترسی به این بخش را ندارید.']);
    }
    $new_phone       = isset($_POST['jay_login_register_new_phone']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_new_phone'])) : '';
    $change_password = isset($_POST['jay_login_register_change_password_toggle']);
    // The password field is a special case. We validate that it's a string and unslash it.
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $new_password = ( isset( $_POST['jay_login_register_new_password'] ) && is_string( $_POST['jay_login_register_new_password'] ) ) ? wp_unslash( $_POST['jay_login_register_new_password'] ) : '';
    if (!preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($new_phone))) {
        wp_send_json_error(['message' => 'فرمت شماره موبایل جدید نامعتبر است.']);
    }
    if ($change_password && empty($new_password)) {
        wp_send_json_error(['message' => 'لطفاً رمز عبور جدید را وارد کنید.']);
    }

    // بررسی اینکه شماره جدید برای کاربر دیگری نباشد
    $user_id = get_current_user_id();
    $new_phone_plus_98 = '+98' . substr($new_phone, 1);
// phpcs:disable WordPress.DB.SlowDBQuery, WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
    $existing_user_query = new WP_User_Query([
        'meta_key'   => 'digits_phone',
        'meta_value' => $new_phone_plus_98,
        'exclude'    => [$user_id],
    ]);
// phpcs:enable
    if ( ! empty($existing_user_query->get_results()) ) {
        wp_send_json_error(['message' => 'این شماره موبایل قبلاً توسط کاربر دیگری ثبت شده است.']);
    }

    // ارسال OTP
    $settings = get_option('jay_login_register_settings');
    $user_ip  = jay_login_register_get_user_ip();
    
    jay_login_register_check_and_handle_lockout( $new_phone, $user_ip, $settings );

    // ارسال OTP
    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('unhandled_sms_filter', 'فیلتر jay_relog_send_otp اجرا نشد.'), $new_phone, $otp);
    if (is_wp_error($send_result)) {
        wp_send_json_error(['message' => 'خطا در ارسال کد: ' . $send_result->get_error_message()]);
    }

    // ذخیره کد، شماره جدید و زمان ارسال در Transient
    $transient_data = [
        'otp'          => $otp, 
        'new_phone'    => $new_phone, 
        'time'         => time(),
        'new_password' => $change_password ? $new_password : null, // فقط در صورت تیک خوردن ذخیره می‌شود
    ];
    set_transient('jay_login_register_change_phone_data_' . $user_id, $transient_data, $validity_period * MINUTE_IN_SECONDS);

    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به شماره <strong>' . esc_html($new_phone) . '</strong> ارسال شد.';
    $html = jay_login_register_get_otp_verification_form_html('تایید شماره جدید', $instruction, $new_phone, 'jay_login_register_change_otp', 'verify_change_phone_otp', 'resend_change_phone_otp', 'change_phone');
            
    wp_send_json_success([
        'html' => Jay_Login_Register_Minifier::html($html),
        'message' => 'کد تایید ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * تایید OTP و تغییر نهایی شماره
 */

add_action('wp_ajax_jay_login_register_verify_change_phone_otp', 'jay_login_register_ajax_verify_change_phone_otp');
function jay_login_register_ajax_verify_change_phone_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'شما اجازه دسترسی به این بخش را ندارید.']);
    }
    $user_id = get_current_user_id();
    $otp_entered = isset($_POST['jay_login_register_change_otp']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_change_otp'])) : '';
    $transient_data = get_transient('jay_login_register_change_phone_data_' . $user_id);

    if ($transient_data === false) wp_send_json_error(['message' => 'کد تایید منقضی شده است.']);

    $new_phone = $transient_data['new_phone'];
    $settings  = get_option('jay_login_register_settings');
    $user_ip   = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $new_phone, $user_ip, $settings );

    if ((string) jay_login_register_normalize_numbers($otp_entered) !== (string) $transient_data['otp']) {
        // منطق شمارش خطا و مسدودسازی
        $max_retries = intval($settings['otp_max_retries'] ?? 3);
        $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
        $block_methods = $settings['otp_block_method'] ?? ['phone'];

        $fail_count = 0;
        if ( in_array('phone', $block_methods, true) ) {
            $fail_count = get_transient('jay_login_register_otp_fail_count_' . $new_phone) ?: 0;
            $fail_count++;
            if ($fail_count >= $max_retries) {
                set_transient('jay_login_register_otp_block_' . $new_phone, 'blocked', $lockout_duration * MINUTE_IN_SECONDS);
                delete_transient('jay_login_register_otp_fail_count_' . $new_phone);
            } else {
                set_transient('jay_login_register_otp_fail_count_' . $new_phone, $fail_count, $lockout_duration * MINUTE_IN_SECONDS);
            }
        }
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
        
        jay_login_register_check_and_handle_lockout( $new_phone, $user_ip, $settings );
        $remaining_tries = $max_retries - $fail_count;
        wp_send_json_error(['message' => "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید."]);
    }

    // در صورت موفقیت، شمارنده‌ها را پاک می‌کنیم
    delete_transient('jay_login_register_otp_fail_count_' . $new_phone);
    delete_transient('jay_login_register_otp_fail_count_' . $user_ip);
    
    // بروزرسانی متاهای کاربر با شماره جدید
    update_user_meta($user_id, 'jay_mobile', $new_phone);
    update_user_meta($user_id, 'digits_phone', '+98' . substr($new_phone, 1));
    update_user_meta($user_id, 'digits_phone_no', substr($new_phone, 1));
    if ( ! empty( $transient_data['new_password'] ) ) {
        wp_set_password( $transient_data['new_password'], $user_id );
    }
    delete_transient('jay_login_register_change_phone_data_' . $user_id);
    $message = 'شماره موبایل شما با موفقیت به <strong>' . esc_html($new_phone) . '</strong> تغییر یافت.';
    if ( ! empty( $transient_data['new_password'] ) ) {
        $message .= ' همچنین رمز عبور شما نیز با موفقیت به‌روز شد.';
    }
    $html = '<h3>عملیات موفق</h3><p>' . $message . '</p>';
    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html), 'message' => 'تغییرات با موفقیت اعمال شد.']);
}
/**
 * جدید: ارسال مجدد کد تایید برای تغییر شماره
 */
add_action('wp_ajax_jay_login_register_resend_change_phone_otp', 'jay_login_register_ajax_resend_change_phone_otp');
function jay_login_register_ajax_resend_change_phone_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $user_id = get_current_user_id();
    $transient_data = get_transient('jay_login_register_change_phone_data_' . $user_id);

    if ( empty($transient_data['new_phone']) ) {
        wp_send_json_error(['message' => 'خطای نشست. لطفاً از ابتدا تلاش کنید.']);
    }
    
    $new_phone = $transient_data['new_phone'];
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $new_phone, $user_ip, $settings );
    
    $validity_period = intval($settings['otp_validity_period'] ?? 2);

    if ( ! empty($transient_data['time']) && (time() - $transient_data['time']) < ($validity_period * 60) ) {
        wp_send_json_error(['message' => 'لطفاً تا پایان زمان‌سنج منتظر بمانید.']);
    }

    $otp_length = intval($settings['otp_length'] ?? 4);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('unhandled_sms_filter', 'فیلتر jay_relog_send_otp اجرا نشد.'), $new_phone, $otp);
    if (is_wp_error($send_result)) wp_send_json_error(['message' => 'خطا در ارسال کد: ' . $send_result->get_error_message()]);

     $new_transient_data = [
      'otp'=> $otp,
      'new_phone'=> $new_phone,
      'time' => time(),
      'new_password' => $transient_data['new_password'] ?? null, 
     ];
     set_transient('jay_login_register_change_phone_data_' . $user_id, $new_transient_data, $validity_period * MINUTE_IN_SECONDS);

    wp_send_json_success([
        'message' => 'کد جدید با موفقیت ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * جدید: ارسال مجدد کد تایید
 */
add_action('wp_ajax_nopriv_jay_login_register_resend_otp', 'jay_login_register_ajax_resend_otp');
function jay_login_register_ajax_resend_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $phone = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $context = isset($_POST['context']) ? sanitize_key($_POST['context']) : 'register'; // 'login' or 'register'

    if (empty($phone)) wp_send_json_error(['message' => 'شماره تلفن یافت نشد.']);

    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    
    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );
    
    // تعیین نام transient بر اساس context
    $transient_name = ($context === 'login') 
        ? 'jay_login_register_otp_login_' . $phone 
        : 'jay_login_register_otp_' . $phone;

    $old_transient_data = get_transient($transient_name);
    
    $validity_period = intval($settings['otp_validity_period'] ?? 2);

    // بررسی تایمر
    if ($old_transient_data && isset($old_transient_data['time']) && (time() - $old_transient_data['time']) < ($validity_period * 60)) {
        wp_send_json_error(['message' => 'لطفاً تا پایان زمان‌سنج منتظر بمانید.']);
    }

    $otp_length = intval($settings['otp_length'] ?? 4);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $send_result = apply_filters('jay_relog_send_otp', new WP_Error('unhandled_sms_filter', 'فیلتر jay_relog_send_otp اجرا نشد.'), $phone, $otp);
    if (is_wp_error($send_result)) {
        wp_send_json_error(['message' => 'خطا در ارسال کد: ' . $send_result->get_error_message()]);
    }

    // بازسازی transient با حفظ اطلاعات قبلی (مانند کد ملی)
    $new_transient_data = $old_transient_data ? $old_transient_data : [];
    $new_transient_data['otp'] = $otp;
    $new_transient_data['time'] = time();

    set_transient($transient_name, $new_transient_data, $validity_period * MINUTE_IN_SECONDS);

    wp_send_json_success([
        'message' => 'کد جدید با موفقیت ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * جدید: فرم تغییر شماره را بر اساس انتخاب کاربر، دوباره‌سازی می‌کند.
 */
add_action('wp_ajax_jay_login_register_render_change_phone_form', 'jay_login_register_ajax_render_change_phone_form');
function jay_login_register_ajax_render_change_phone_form() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error();
    }

    $new_phone = isset($_POST['jay_login_register_new_phone']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_new_phone'])) : '';
    $change_password = isset($_POST['jay_login_register_change_password_toggle']) && $_POST['jay_login_register_change_password_toggle'] === 'true';

    $html = jay_login_register_get_change_phone_form_html( $new_phone, $change_password );

    wp_send_json_success(['html' => Jay_Login_Register_Minifier::html($html)]);
}
/**
 * جدید: مدیریت ورود/عضویت خودکار از طریق برنامک ایتا (نسخه نهایی)
 */
add_action('wp_ajax_nopriv_jay_login_register_handle_eitaa_login', 'jay_login_register_ajax_handle_eitaa_login');
function jay_login_register_ajax_handle_eitaa_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    $settings = get_option('jay_login_register_settings');

    if ( ! isset($settings['eitaa_login_enable']) || $settings['eitaa_login_enable'] !== 'yes' ) {
        wp_send_json_error(['message' => 'ورود با ایتا در سایت فعال نیست.']);
    }

     $eitaa_tokens = $settings['eitaa_tokens'] ?? [];
    if ( empty($eitaa_tokens) ) {
        wp_send_json_error(['message' => 'هیچ توکن برنامه‌ای برای ایتا در تنظیمات افزونه ثبت نشده است.']);
    }
    /*
     * phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
     * Justification: The raw data from Eitaa API is needed for cryptographic hash validation.
     * Sanitizing this string would corrupt the data and cause the hash verification to fail.
     * The security is handled by the jay_login_register_validate_eitaa_* functions which perform
     * a hash_hmac check against the secret bot token. Any tampered data will be rejected.
     */
     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $init_data_raw = isset($_POST['initData']) ? wp_unslash($_POST['initData']) : '';
    /*
     * phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
     * Justification: Same reason as above. The raw contact data string is required for hash validation.
     * Security is ensured by the cryptographic check.
     */
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $contact_response_raw = isset($_POST['contactData']['response']) ? wp_unslash($_POST['contactData']['response']) : '';
    $validation_successful = false;
    $user_validation_result = null;
    $contact_validation_result = null;

    // شروع حلقه برای تست کردن همه توکن‌ها
    foreach ($eitaa_tokens as $token_data) {
        $current_token = $token_data['token'];

        // تلاش برای اعتبارسنجی با توکن فعلی
        $user_validation_result_attempt = jay_login_register_validate_eitaa_data($init_data_raw, $current_token);
        
        // اگر اعتبارسنجی اولیه موفق بود، اعتبارسنجی تماس را هم با همان توکن انجام بده
        if ( ! is_wp_error($user_validation_result_attempt) ) {
            $contact_validation_result_attempt = jay_login_register_validate_eitaa_contact_data($contact_response_raw, $current_token);
            
            if ( ! is_wp_error($contact_validation_result_attempt) ) {
                // هر دو اعتبارسنجی موفق بودند! توکن صحیح پیدا شد.
                $validation_successful = true;
                $user_validation_result = $user_validation_result_attempt;
                $contact_validation_result = $contact_validation_result_attempt;
                break; 
            }
        }
    }

    if ( ! $validation_successful ) {
        wp_send_json_error(['message' => 'اعتبارسنجی داده‌های ایتا ناموفق بود. توکن معتبری یافت نشد.']);
    }
    
    $phone_eitaa = $contact_validation_result; 

    // گام ۳: نرمال‌سازی شماره تلفن و جستجو یا ساخت کاربر
    $phone_plus_98 = '+' . $phone_eitaa;
    $phone_zero = '0' . substr($phone_eitaa, 2);
    // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    $user_query = new WP_User_Query([
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'digits_phone', 'value' => $phone_plus_98, 'compare' => '='],
            ['key' => 'jay_mobile', 'value' => $phone_zero, 'compare' => '=']
        ],
        'number' => 1,
        'fields' => 'ID',
    ]);
    $users = $user_query->get_results();
    $user_id = 0;

    if ( ! empty($users) ) {
        $user_id = $users[0];
    } else {
        $eitaa_user_info = $user_validation_result;
        $display_name = $phone_zero;
        if ( ! empty($eitaa_user_info['first_name']) ) {
            $display_name = trim($eitaa_user_info['first_name'] . ' ' . ($eitaa_user_info['last_name'] ?? ''));
        }

        $user_data = [
            'user_login'    => $phone_zero,
            'user_pass'     => wp_generate_password(),
            'user_email'    => $phone_zero . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'display_name'  => $display_name,
            'user_nicename' => $phone_zero,
            'nickname'      => $phone_zero,
            'role'          => 'subscriber',
        ];
        $user_id = wp_insert_user($user_data);
        if ( is_wp_error($user_id) ) {
            wp_send_json_error(['message' => 'خطا در ساخت حساب کاربری: ' . $user_id->get_error_message()]);
        }
        update_user_meta($user_id, 'jay_mobile', $phone_zero);
        update_user_meta($user_id, 'digits_phone', $phone_plus_98);
        update_user_meta($user_id, 'digits_phone_no', substr($phone_zero, 1));
    }

    $redirect_url = isset($_POST['current_url']) ? esc_url_raw(wp_unslash($_POST['current_url'])) : home_url('/');
    wp_set_auth_cookie($user_id, true);
    
    wp_send_json_success([
        'message' => 'احراز هویت با موفقیت انجام شد.',
        'redirect_url' => $redirect_url 
    ]);
}

/**
 * جدید: ارسال کد تایید (OTP) به ایمیل کاربر (نسخه اصلاح شده با فیلترهای وردپرس)
 */
add_action('wp_ajax_nopriv_jay_login_register_send_email_otp', 'jay_login_register_ajax_send_email_otp');
function jay_login_register_ajax_send_email_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

   $raw_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $email = '';

   if ( is_email($raw_input) ) {
        $email = $raw_input;
    } else {
        // اگر ورودی ایمیل نبود، فرض می‌کنیم نام کاربری است
        $user = get_user_by('login', $raw_input);
        if ($user) {
            $email = $user->user_email;
            // **مهم:** نشست را برای ایمیل هم ست می‌کنیم تا در مرحله تایید خطا ندهد
            set_transient('jay_login_register_user_id_' . $email, $user->ID, 5 * MINUTE_IN_SECONDS);
        }
    }

    if ( empty($email) || !is_email($email) ) {
        wp_send_json_error(['message' => 'آدرس ایمیل برای این حساب یافت نشد.']);
    }

    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $email, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    $subject_template = $settings['email_otp_subject'] ?? 'کد تایید ورود: [otp_code]';
    $body_template = $settings['email_otp_body'] ?? "کد تایید شما برای ورود به [site_name]:\n\n[otp_code]";
    
    $replacements = [
        '[otp_code]'        => $otp,
        '[site_name]'       => get_bloginfo('name'),
        '[validity_period]' => $validity_period,
    ];
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $body = nl2br(str_replace(array_keys($replacements), array_values($replacements), $body_template));

    // تنظیم هدر برای ارسال ایمیل HTML
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    // ارسال ایمیل. هوک phpmailer_init بقیه کارها را به صورت خودکار انجام می‌دهد.
    $sent = wp_mail($email, $subject, $body, $headers);
       
//jayemail
   if ( ! $sent ) {
        // بررسی می‌کنیم آیا تابع عیب‌یابی ما پیام خطای دقیقی را ثبت کرده است یا خیر
        if ( isset($GLOBALS['jay_relog_mail_error']) ) {
            $error_message = 'خطای سرور ایمیل: ' . esc_html($GLOBALS['jay_relog_mail_error']);
            // متغیر را پاک می‌کنیم تا در درخواست‌های بعدی تداخل ایجاد نکند
            unset($GLOBALS['jay_relog_mail_error']);
            wp_send_json_error(['message' => $error_message]);
        } else {
            // اگر خطای خاصی ثبت نشده بود، همان پیام عمومی را نمایش بده
            wp_send_json_error(['message' => 'خطا در ارسال ایمیل. لطفاً با مدیریت سایت تماس بگیرید.']);
        }
    }

    set_transient('jay_login_register_email_otp_' . $email, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);

    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به ایمیل <strong>' . esc_html($email) . '</strong> ارسال شد.';
    $html = jay_login_register_get_otp_verification_form_html('تایید ایمیل', $instruction, $email, 'jay_login_register_email_otp', 'verify_email_otp', 'resend_email_otp', 'email_login');
    
    wp_send_json_success([
        'html' => Jay_Login_Register_Minifier::html($html),
        'message' => 'کد تایید با موفقیت به ایمیل شما ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * جدید: تایید کد OTP دریافت شده از ایمیل.
 */
add_action('wp_ajax_nopriv_jay_login_register_verify_email_otp', 'jay_login_register_ajax_verify_email_otp');
function jay_login_register_ajax_verify_email_otp() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $email = isset($_POST['user_input']) ? sanitize_email(wp_unslash($_POST['user_input'])) : '';
    $otp_entered = isset($_POST['jay_login_register_email_otp']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_email_otp'])) : '';
    
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $email, $user_ip, $settings );
    
    $otp_correct_data = get_transient('jay_login_register_email_otp_' . $email);
    if ($otp_correct_data === false) {
        wp_send_json_error(['message' => 'کد تایید منقضی شده است.']);
    }

    if ((string) jay_login_register_normalize_numbers($otp_entered) !== (string) $otp_correct_data['otp']) {
        // منطق شمارش خطا و مسدودسازی برای ایمیل
        $max_retries = intval($settings['otp_max_retries'] ?? 3);
        $lockout_duration = intval($settings['otp_lockout_duration'] ?? 15);
        
        $fail_count = get_transient('jay_login_register_otp_fail_count_' . $email) ?: 0;
        $fail_count++;
        if ($fail_count >= $max_retries) {
            set_transient('jay_login_register_otp_block_' . $email, 'blocked', $lockout_duration * MINUTE_IN_SECONDS);
            delete_transient('jay_login_register_otp_fail_count_' . $email);
        } else {
            set_transient('jay_login_register_otp_fail_count_' . $email, $fail_count, $lockout_duration * MINUTE_IN_SECONDS);
        }
        
        jay_login_register_check_and_handle_lockout( $email, $user_ip, $settings );
        $remaining_tries = $max_retries - $fail_count;
        wp_send_json_error(['message' => "کد تایید اشتباه است. شما {$remaining_tries} تلاش دیگر دارید."]);
    }
    
    // پاک کردن ترنزینت‌های خطا
    delete_transient('jay_login_register_otp_fail_count_' . $email);
    delete_transient('jay_login_register_email_otp_' . $email);

    $user_id = get_transient('jay_login_register_user_id_' . $email);
    if (!$user_id) {
        wp_send_json_error(['message' => 'خطای نشست. لطفاً مجدداً تلاش کنید.']);
    }

    wp_set_auth_cookie($user_id, true);
    delete_transient('jay_login_register_user_id_' . $email);

    $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
    $referrer    = isset($_POST['referrer_url']) ? esc_url_raw(wp_unslash($_POST['referrer_url'])) : home_url('/');
    $redirect_url = !empty($redirect_to) ? $redirect_to : get_jay_login_register_redirect_url($referrer);

    wp_send_json_success(['message' => 'ورود با موفقیت انجام شد. در حال انتقال...', 'redirect_url' => $redirect_url]);
}

/**
 * جدید: ارسال مجدد کد تایید (OTP) به ایمیل.
 */ 
add_action('wp_ajax_nopriv_jay_login_register_resend_email_otp', 'jay_login_register_ajax_resend_email_otp');
function jay_login_register_ajax_resend_email_otp() {
    // این تابع به سادگی، تابع اصلی ارسال ایمیل را دوباره فراخوانی می‌کند
    jay_login_register_ajax_send_email_otp();
}
/**
 * جدید: ارسال مجدد کد تایید (OTP) به ایمیل برای فرآیند عضویت.
 */
add_action('wp_ajax_nopriv_jay_login_register_resend_email_otp_register', 'jay_login_register_ajax_resend_email_otp_register');
function jay_login_register_ajax_resend_email_otp_register() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');

    $email = isset($_POST['user_input']) ? sanitize_email(wp_unslash($_POST['user_input'])) : '';
    if (empty($email)) wp_send_json_error(['message' => 'ایمیل یافت نشد.']);

    // اطلاعات قبلی (مثل کد ملی) را از transient قدیمی می‌خوانیم تا از دست نرود
    $old_transient_data = get_transient('jay_login_register_email_otp_register_' . $email);
    $identity_data = [];
    if ($old_transient_data) {
        if (isset($old_transient_data['national_code'])) {
            $identity_data['national_code'] = $old_transient_data['national_code'];
        }
        if (isset($old_transient_data['passport'])) {
            $identity_data['passport'] = $old_transient_data['passport'];
        }
    }

    // تابع کمکی اصلی را برای ارسال مجدد فراخوانی می‌کنیم
    jay_login_register_send_email_otp_and_show_form($email, $identity_data);
}
/**
 * جدید: ارسال یک ایمیل تستی برای بررسی تنظیمات SMTP.
 */
add_action('wp_ajax_jay_login_register_send_test_email', 'jay_login_register_ajax_send_test_email');
function jay_login_register_ajax_send_test_email() {
    check_ajax_referer('jay_relog_test_email_nonce');

    if ( ! current_user_can('jay_login_register_manage_settings') ) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    $to_email = isset($_POST['to_email']) ? sanitize_email(wp_unslash($_POST['to_email'])) : '';
    if ( ! is_email($to_email) ) {
        wp_send_json_error(['message' => 'آدرس ایمیل وارد شده نامعتبر است.']);
    }
    
    $subject = 'ایمیل تستی از افزونه JAY Login & Register';
    $body = '<p>سلام،</p><p>این یک ایمیل تستی برای اطمینان از صحت تنظیمات ارسال ایمیل شماست.</p><p>اگر این ایمیل را دریافت کرده‌اید، یعنی تنظیمات شما به درستی کار می‌کند.</p><p>موفق باشید!</p>';
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // ما نیازی به بازنویسی کد نداریم، چون هوک phpmailer_init تمام کارها را انجام خواهد داد.
    $sent = wp_mail($to_email, $subject, $body, $headers);

    if ($sent) {
        wp_send_json_success(['message' => 'ایمیل تستی با موفقیت به ' . $to_email . ' ارسال شد. لطفاً صندوق ورودی و پوشه اسپم خود را بررسی کنید.']);
    } else {
        $error_message = 'ارسال ایمیل با شکست مواجه شد.';
        if (isset($GLOBALS['jay_relog_mail_error'])) {
            $error_message .= '<br><strong>پیام خطای سرور:</strong> ' . esc_html($GLOBALS['jay_relog_mail_error']);
            unset($GLOBALS['jay_relog_mail_error']);
        }
        wp_send_json_error(['message' => $error_message]);
    }
}

/**
 * جدید: تابع AJAX برای ارسال OTP از طریق بله (مخصوص فرآیند ورود)
 */
add_action('wp_ajax_nopriv_jay_login_register_send_otp_bale_login', 'jay_login_register_ajax_send_otp_bale_login');
function jay_login_register_ajax_send_otp_bale_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $raw_input = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    $phone = '';

    if ( preg_match('/^09[0-9]{9}$/', jay_login_register_normalize_numbers($raw_input)) ) {
        $phone = $raw_input;
    } else {
        $user = get_user_by('login', $raw_input);
        if ($user) {
            $phone = get_user_meta($user->ID, 'jay_mobile', true);
            // **مهم:** نشست را برای شماره موبایل هم ست می‌کنیم
            if ($phone) {
                set_transient('jay_login_register_user_id_' . $phone, $user->ID, 5 * MINUTE_IN_SECONDS);
            }
        }
    }

    if (empty($phone)) wp_send_json_error(['message' => 'شماره تلفن برای این حساب یافت نشد.']);
    
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();
    
    jay_login_register_check_and_handle_lockout( $phone, $user_ip, $settings );
    
    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);
    
    // فراخوانی تابع جدید برای ارسال از طریق بله
    $send_result = jay_login_register_send_otp_via_bale($phone, $otp);
    if (is_wp_error($send_result)) {
        wp_send_json_error(['message' => $send_result->get_error_message()]);
    }
    // ذخیره OTP در transient برای تایید بعدی
    set_transient('jay_login_register_otp_login_' . $phone, ['otp' => $otp, 'time' => time()], $validity_period * MINUTE_IN_SECONDS);

    // نمایش فرم ورود کد
    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به اپلیکیشن بله شما ارسال شد.';
    // از همان فرم قبلی استفاده می‌کنیم، فقط اکشن ارسال مجدد فرق می‌کند
    $html = jay_login_register_get_otp_verification_form_html('ورود با کد یکبار مصرف', $instruction, $phone, 'jay_login_register_otp_login', 'verify_otp_for_login', 'resend_otp_bale_login', 'login_bale');

    wp_send_json_success([
        'html'            => Jay_Login_Register_Minifier::html($html),
        'message'         => 'کد تایید به بله ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}
/**
 * جدید: تابع AJAX برای ارسال مجدد OTP از طریق بله (مخصوص فرآیند ورود)
 */
add_action('wp_ajax_nopriv_jay_login_register_resend_otp_bale_login', 'jay_login_register_ajax_resend_otp_bale_login');
function jay_login_register_ajax_resend_otp_bale_login() {
    check_ajax_referer('jay_login_register_nonce_action', 'jay_login_register_nonce');
    
    $phone = isset($_POST['user_input']) ? sanitize_text_field(wp_unslash($_POST['user_input'])) : '';
    if (empty($phone)) wp_send_json_error(['message' => 'شماره تلفن یافت نشد.']);

    jay_login_register_ajax_send_otp_bale_login();
}
