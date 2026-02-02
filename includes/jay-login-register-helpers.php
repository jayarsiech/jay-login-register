<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// تابع تبدیل اعداد
function jay_login_register_normalize_numbers( $string ) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = range( 0, 9 );
    $string = str_replace( $persian, $english, $string );
    return str_replace( $arabic, $english, $string );
}
// تابع اعتبار پاسپورت
function is_valid_passport_format( $passport_string ) {
    $passport_string = jay_login_register_normalize_numbers( $passport_string );
        if ( ! preg_match( '/^[a-zA-Z0-9]+$/', $passport_string ) ) {
        return false;
    }
    
    return true;
}
// تابع اعتبارسنجی کد ملی
function is_valid_iranian_national_code( $code ) {
    $code = jay_login_register_normalize_numbers( $code );
    if ( ! preg_match( '/^[0-9]{10}$/', $code ) ) { return false; }
    for ( $i = 0; $i < 10; $i++ ) { if ( preg_match( '/^' . $i . '{10}$/', $code ) ) { return false; } }
    for ( $i = 0, $sum = 0; $i < 9; $i++ ) { $sum += ( (int) $code[$i] ) * ( 10 - $i ); }
    $ret    = $sum % 11;
    $parity = (int) $code[9];
    if ( ( $ret < 2 && $ret == $parity ) || ( $ret >= 2 && $ret == 11 - $parity ) ) { return true; }
    return false;
}
/**
 * تابع تبدیل تاریخ شمسی به میلادی
 * ورودی: 1402/01/01
 * خروجی: 2023-03-21
 */
function jay_login_register_convert_jalali_to_gregorian( $jalali_date ) {
    if ( empty($jalali_date) ) return '';

    // جدا کردن سال، ماه و روز
    $parts = explode('/', $jalali_date);
    if ( count($parts) !== 3 ) return $jalali_date; // فرمت نامعتبر

    $j_y = (int) jay_login_register_normalize_numbers($parts[0]);
    $j_m = (int) jay_login_register_normalize_numbers($parts[1]);
    $j_d = (int) jay_login_register_normalize_numbers($parts[2]);

    // الگوریتم تبدیل
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $jy = $j_y - 979;
    $jm = $j_m - 1;
    $jd = $j_d - 1;

    $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor(($jy % 33 + 3) / 4);
    for ($i = 0; $i < $jm; ++$i) $j_day_no += $j_days_in_month[$i];
    $j_day_no += $jd;
    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * floor($g_day_no / 146097);
    $g_day_no = $g_day_no % 146097;
    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * floor($g_day_no / 36524);
        $g_day_no = $g_day_no % 36524;
        if ($g_day_no >= 365) $g_day_no++;
        else $leap = false;
    }
    $gy += 4 * floor($g_day_no / 1461);
    $g_day_no %= 1461;
    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += floor($g_day_no / 365);
        $g_day_no = $g_day_no % 365;
    }
    for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++)
        $g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
    
    $gm = $i + 1;
    $gd = $g_day_no + 1;

    // فرمت دهی خروجی (YYYY-MM-DD)
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}



/**
 * تابع کمکی برای تعیین آدرس ریدایرکت هوشمند
 */

add_action( 'template_redirect', 'jay_login_register_access_gatekeeper' );
function jay_login_register_access_gatekeeper() {

    if ( ! is_singular() ) {
        return;
    }

    $post_id = get_the_ID();
    $requires_login = get_post_meta( $post_id, '_jay_login_register_requires_login', true );
    
    // اگر صفحه نیازی به ورود نداشت، هیچ کاری نکن
    if ( $requires_login !== 'yes' ) {
        return;
    }

    // سناریو ۱: کاربر اصلاً وارد نشده است -> به صفحه ورود هدایت کن
    if ( ! is_user_logged_in() ) {
        $options = get_option('jay_login_register_settings');
        $login_page_id = $options['login_page_id'] ?? 0;
        if ( $login_page_id ) {
            $login_page_url = get_permalink($login_page_id);
            $redirect_url = add_query_arg( 'redirect_to', urlencode( get_permalink( $post_id ) ), $login_page_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }
        return;
    }

    // سناریو ۲: کاربر وارد شده است، اما ممکن است دسترسی نداشته باشد
    $protection_method = get_post_meta( $post_id, '_jay_login_register_protection_method', true );

    // اگر روش "مسدود کردن کل صفحه" انتخاب شده بود، تمام دسترسی‌ها را همینجا چک می‌کنیم
    if ( $protection_method === 'block_page' ) {
        $access_check_result = jay_login_register_check_user_access($post_id);
        
        if ( ! $access_check_result['has_access'] ) {
            jay_login_register_custom_die_page($access_check_result['title'], $access_check_result['html']);
        }
    }
}
/**
 * فیلتر محتوا: اگر روش محافظت "جایگزینی محتوا" بود، اینجا عمل می‌کند.
 */
add_filter( 'the_content', 'jay_login_register_content_access_filter' );
function jay_login_register_content_access_filter( $content ) {
    if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;

    $post_id = get_the_ID();
    $requires_login = get_post_meta( $post_id, '_jay_login_register_requires_login', true );
    $protection_method = get_post_meta( $post_id, '_jay_login_register_protection_method', true );

    if ( $requires_login !== 'yes' || $protection_method !== 'replace_content' ) {
        return $content;
    }

    if ( ! is_user_logged_in() ) return '';

    $access_check_result = jay_login_register_check_user_access($post_id);

    if ( $access_check_result['has_access'] ) {
        return $content;
    } else {
        wp_enqueue_style( 'jay-login-register-global-fonts' );
        wp_enqueue_style( 'jay-login-register-access-denied-style' );
    
        return $access_check_result['html'];
    }
}
/**
 * تابع مرکزی برای بررسی دسترسی کاربر (برای جلوگیری از تکرار کد)
 */
function jay_login_register_check_user_access( $post_id ) {
    $user_id = get_current_user_id();
    
    // ۱. بررسی دسترسی بر اساس کلید متا
    $required_meta_key = get_post_meta( $post_id, '_jay_login_register_required_meta_key', true );
    if ( ! empty( $required_meta_key ) ) {
        if ( ! metadata_exists( 'user', $user_id, $required_meta_key ) ) {
            $redirect_page_id = get_post_meta( $post_id, '_jay_login_register_meta_key_redirect_page_id', true );
            $user_phone_raw = get_user_meta($user_id, 'digits_phone', true);
            $user_phone = '0' . substr($user_phone_raw, 3); 
            $redirect_link = $redirect_page_id ? get_permalink($redirect_page_id) : home_url('/');
            
            $title   = get_post_meta($post_id, '_jay_login_register_meta_error_title', true) ?: 'دسترسی ویژه';
            $button  = get_post_meta($post_id, '_jay_login_register_meta_error_button', true) ?: 'تکمیل اطلاعات';
            
            $prefix_message = 'کاربر گرامی با شماره عضویت <strong style="direction:ltr; display:inline-block;">' . esc_html($user_phone) . '</strong> <br> ';
            $custom_message = nl2br(esc_html(get_post_meta($post_id, '_jay_login_register_meta_error_message', true)));
             if ( empty($custom_message) ) {
                    $custom_message = 'برای مشاهده این محتوا، ابتدا باید اطلاعات خود را در صفحه زیر تکمیل نمایید.';
                }
            $final_message = $prefix_message . $custom_message;
            
            $error_html = '
           
            <div class="jay-login-register-access-denied">
              <div class="icon">
                   
                </div>
            <h3>' . esc_html($title) . '</h3>
            <p>' . wp_kses_post($final_message) . '</p>
            <a href="' . esc_url( $redirect_link ) . '">' . esc_html($button) . '</a>
            </div>';

            return ['has_access' => false, 'title' => $title, 'html' => $error_html];
        }
    }

    // ۲. بررسی دسترسی بر اساس نقش کاربری
    $allowed_roles = get_post_meta( $post_id, '_jay_login_register_allowed_roles', true );
    if ( ! empty($allowed_roles) && is_array($allowed_roles) ) {
        $user = wp_get_current_user();
        if ( count( array_intersect( (array) $user->roles, $allowed_roles ) ) === 0 ) {
            $title = 'دسترسی غیرمجاز';
        $error_html = '
      
            <div class="jay-login-register-access-denied">
                <div class="icon">
                  
                </div>
                <h3>دسترسی غیرمجاز</h3>
                <p>شما برای مشاهده این محتوا، نقش کاربری لازم را ندارید.</p>
                <a href="' . esc_url( home_url('/') ) . '">بازگشت به صفحه اصلی</a>
            </div>
        ';
        
        return ['has_access' => false, 'title' => $title, 'html' => $error_html];
        }
    }

    // اگر تمام بررسی‌ها موفق بود
    return ['has_access' => true];
}


/**
 * تابع جدید برای نمایش صفحه خطای کاملاً سفارشی
 */
function jay_login_register_custom_die_page( $title, $message ) {
        // **شروع بخش جدید:** خواندن تنظیمات برای پیدا کردن لوگو
    $options = get_option('jay_login_register_settings');
    $logo_id = isset($options['logo_id']) ? absint($options['logo_id']) : 0;
    ?>
    <!DOCTYPE html>
       <html <?php language_attributes(); ?>>
    <head>
        <meta charset="utf-8" />
        <meta charset="<?php bloginfo( 'charset' ); ?>" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name='robots' content='max-image-preview:large, noindex, follow' />
        <title><?php echo esc_html( $title ); ?></title>
         <?php
        if ( $logo_id ) {
            $favicon_url = wp_get_attachment_image_url($logo_id, 'thumbnail');
            if ( $favicon_url ) {
                printf( '<link rel="icon" href="%s" />', esc_url( $favicon_url ) );
            }
        }
        
        wp_print_styles('jay-login-register-access-denied-style');
        wp_print_styles('jay-login-register-global-fonts');
        ?>
       
    </head>
    <body id="error-page">
            <?php echo wp_kses_post( $message ); ?>   
    </body>
    </html>
    <?php
    exit;
}

/**
 * تابع کمکی برای تعیین آدرس ریدایرکت هوشمند (نسخه نهایی)
 */
function get_jay_login_register_redirect_url( $referrer_url ) {
    $options = get_option('jay_login_register_settings');
    $login_page_id = $options['login_page_id'] ?? 0;
    $redirect_page_id = $options['redirect_page_id'] ?? 0;

    // اگر هیچ برگه ورودی در تنظیمات مشخص نشده یا آدرس صفحه قبل موجود نبود
    if ( ! $login_page_id || ! $referrer_url ) {
        // چک کن آیا برگه خاصی برای ریدایرکت تنظیم شده؟
        return $redirect_page_id ? get_permalink($redirect_page_id) : home_url('/');
    }

    $login_page_url = get_permalink($login_page_id);
    
    // نرمال‌سازی URLها
    $normalized_login_url = rtrim($login_page_url, '/');
    $normalized_referrer_url = rtrim($referrer_url, '/');

    // اگر کاربر از خود صفحه لاگین آمده، او را به برگه مشخص شده (یا صفحه اصلی) بفرست
    if ($normalized_referrer_url === $normalized_login_url) {
        return $redirect_page_id ? get_permalink($redirect_page_id) : home_url('/');
    }
    
    return $referrer_url;
}

/**
 * جدید: IP واقعی کاربر را با در نظر گرفتن پروکسی‌ها و CDN ها دریافت می‌کند.
 */
function jay_login_register_get_user_ip() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ( $ip_keys as $key ) {
        if ( array_key_exists( $key, $_SERVER ) === true ) {
           $server_value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            foreach ( explode( ',', $server_value ) as $ip ) {
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
                    return $ip;
                }
            }
        }
    }
    return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'UNKNOWN';
}


/**
 * جدید: اعتبارسنجی داده‌های اولیه (initData) از برنامک ایتا
 *
 * @param string $init_data رشته خام initData از ایتا.
 * @param string $bot_token توکن برنامه (ربات) ایتا.
 * @return array|WP_Error آرایه‌ای از اطلاعات کاربر در صورت موفقیت، یا WP_Error در صورت شکست.
 */
function jay_login_register_validate_eitaa_data( $init_data, $bot_token ) {
    if ( empty( $init_data ) || empty( $bot_token ) ) {
        return new WP_Error( 'invalid_data', 'اطلاعات ورودی یا توکن نامعتبر است.' );
    }

    $data_params = [];
    parse_str( $init_data, $data_params );

    if ( ! isset( $data_params['hash'] ) ) {
        return new WP_Error( 'hash_missing', 'هش اعتبارسنجی یافت نشد.' );
    }

    $hash = $data_params['hash'];
    unset( $data_params['hash'] );

    // بررسی تاریخ انقضای درخواست برای جلوگیری از حملات تکرار (با اعتبار ۵ دقیقه‌ای)
    if ( ! isset( $data_params['auth_date'] ) || time() - (int) $data_params['auth_date'] > 300 ) {
        return new WP_Error( 'request_expired', 'درخواست منقضی شده است. لطفا دوباره تلاش کنید.' );
    }

    ksort( $data_params );

    $data_check_array = [];
    foreach ( $data_params as $key => $value ) {
        $data_check_array[] = $key . '=' . $value;
    }
 $data_check_string = implode( "\n", $data_check_array );

    // اصلاحیه: جایگاه توکن و کلید بر اساس داکیومنت جابجا شد
 $secret_key = hash_hmac( 'sha256', $bot_token, 'WebAppData', true );
 $calculated_hash = hash_hmac( 'sha256', $data_check_string, $secret_key );

 if ( ! hash_equals( $calculated_hash, $hash ) ) {
        return new WP_Error( 'validation_failed', 'اعتبارسنجی داده‌های ایتا ناموفق بود.' );
    }
    
    // اگر داده معتبر بود، اطلاعات کاربر را برمی‌گردانیم
  if ( isset( $data_params['user'] ) ) {
  $user_data = json_decode( urldecode( $data_params['user'] ), true );
        // جدید: بررسی می‌کنیم که آیا اطلاعات کاربر به درستی استخراج شده است
  if ( is_array($user_data) && isset($user_data['id']) ) {
  return $user_data; // در صورت موفقیت، آرایه را برمی‌گردانیم
  }
        // اگر استخراج ناموفق بود، به جای بازگرداندن null، به خطای بعدی می‌رود
 }

    return new WP_Error( 'user_data_missing', 'اطلاعات کاربر در داده‌های ایتا یافت نشد.' );
}

/**
 * جدید: اعتبارسنجی داده‌های تماس دریافتی از ایتا (نسخه نهایی)
 *
 * @param string $contact_response_string رشته خام response از آبجکت contactData ایتا.
 * @param string $bot_token توکن برنامه (ربات) ایتا.
 * @return string|WP_Error شماره تلفن در صورت موفقیت، یا WP_Error در صورت شکست.
 */
function jay_login_register_validate_eitaa_contact_data( $contact_response_string, $bot_token ) {
    $data_params = [];
    parse_str( $contact_response_string, $data_params );

    if ( ! isset( $data_params['hash'] ) ) {
        return new WP_Error( 'hash_missing', 'هش اعتبارسنجی شماره تماس یافت نشد.' );
    }

    $hash = $data_params['hash'];
    unset( $data_params['hash'] );

    ksort( $data_params );

    $data_check_array = [];
    foreach ( $data_params as $key => $value ) {
        $data_check_array[] = $key . '=' . $value;
    }
    $data_check_string = implode( "\n", $data_check_array );

    $secret_key = hash_hmac( 'sha256', $bot_token, 'WebAppData', true );
    $calculated_hash = hash_hmac( 'sha256', $data_check_string, $secret_key );

    if ( ! hash_equals( $calculated_hash, $hash ) ) {
        return new WP_Error( 'validation_failed', 'اعتبارسنجی داده‌های تماس ایتا ناموفق بود.' );
    }
    
    // حالا شماره تلفن را از پارامتر contact که یک JSON است استخراج می‌کنیم
    if ( isset( $data_params['contact'] ) ) {
        $contact_details = json_decode( $data_params['contact'], true );
        if ( is_array($contact_details) && isset($contact_details['phone']) ) {
            return $contact_details['phone']; // شماره تلفن با موفقیت استخراج شد
        }
    }

    return new WP_Error( 'phone_data_missing', 'شماره تلفن در داده‌های تایید شده ایتا یافت نشد.' );
}

/**
 * به هوک init وردپرس متصل می‌شود تا بازگشت کاربر از گوگل را مدیریت کند.
 */
add_action('init', 'jay_login_register_handle_google_oauth_callback');

/**
 * مدیریت فرآیند OAuth2 گوگل، دریافت اطلاعات کاربر، و ورود یا ثبت‌نام او.
 */
function jay_login_register_handle_google_oauth_callback() {
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is checked later via the 'state' parameter from the OAuth flow.
  if ( ! isset($_GET['jay-google-auth']) || intval($_GET['jay-google-auth']) !== 1 ) {
  return;
  }

  // ۲. بررسی امنیتی نانس برای جلوگیری از حملات CSRF
  if ( ! isset($_GET['state']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['state'])), 'jay_google_oauth_nonce') ) {
  wp_die('خطای امنیتی: درخواست نامعتبر است.');
  }

  // ۳. بررسی وجود خطا از طرف گوگل
 if ( isset($_GET['error']) ) {
    $error_description = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : 'خطای نامشخصی رخ داد.';
    wp_die('خطا در احراز هویت با گوگل: ' . esc_html($error_description));
 }

  // ۴. دریافت کد احراز هویت از گوگل
  if ( ! isset($_GET['code']) ) {
  wp_die('کد احراز هویت گوگل یافت نشد.');
  }
  $code = sanitize_text_field(wp_unslash($_GET['code']));
 
  $settings = get_option('jay_login_register_settings');
  $client_id = $settings['google_client_id'] ?? '';
  $client_secret = $settings['google_client_secret'] ?? '';
  $redirect_uri = home_url('/?jay-google-auth=1');

  // ۵. تبادل کد با توکن دسترسی (Access Token)
  $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
  'body' => [
  'code' => $code,
  'client_id' => $client_id,
  'client_secret' => $client_secret,
  'redirect_uri' => $redirect_uri,
  'grant_type' => 'authorization_code',
    ],
    ]);

  if ( is_wp_error($token_response) || wp_remote_retrieve_response_code($token_response) !== 200 ) {
  wp_die('خطا در دریافت توکن از گوگل.');
  }

  $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
  $access_token = $token_data['access_token'];

  // ۶. دریافت اطلاعات کاربر با استفاده از توکن دسترسی
  $user_info_response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
  'headers' => [
  'Authorization' => 'Bearer ' . $access_token,
 ],
   ]);

  if ( is_wp_error($user_info_response) || wp_remote_retrieve_response_code($user_info_response) !== 200 ) {
  wp_die('خطا در دریافت اطلاعات کاربر از گوگل.');
  }

  $user_data = json_decode(wp_remote_retrieve_body($user_info_response), true);
  $email = sanitize_email($user_data['email']);

 // ۷. ورود یا ثبت‌نام کاربر
  $user = get_user_by('email', $email);

  if ( $user ) {
  // کاربر وجود دارد، او را وارد کنید
  $user_id = $user->ID;
  } else {
  // کاربر وجود ندارد، او را ثبت‌نام کنید
  $username = sanitize_user(explode('@', $email)[0], true);
  $temp_username = $username;
  $counter = 1;
  while ( username_exists($temp_username) ) {
  $temp_username = $username . $counter;
 $counter++;
  }
  $username = $temp_username;

  $password = wp_generate_password(12, true);
  $user_id = wp_create_user($username, $password, $email);

  if ( is_wp_error($user_id) ) {
    wp_die('خطا در ساخت حساب کاربری: ' . esc_html($user_id->get_error_message()));
   }

 // به‌روزرسانی نام و نام خانوادگی
  wp_update_user([
  'ID' => $user_id,
  'first_name' => sanitize_text_field($user_data['given_name'] ?? ''),
  'last_name' => sanitize_text_field($user_data['family_name'] ?? ''),
  'display_name' => sanitize_text_field($user_data['name'] ?? $username),
    ]);
   }

  // ۸. تنظیم کوکی ورود و هدایت کاربر
  wp_set_auth_cookie($user_id, true);
    if ( ! session_id() ) @session_start();
     $redirect_url = home_url('/'); // پیش‌فرض صفحه اصلی
     if ( isset( $_SESSION['jay_google_redirect_url'] ) ) {
        $redirect_url = esc_url_raw( $_SESSION['jay_google_redirect_url'] ); 
        unset( $_SESSION['jay_google_redirect_url'] ); 
     }
     wp_safe_redirect( $redirect_url );
 exit;
}



/**
 * جدید: خطای دقیق ارسال ایمیل را برای عیب‌یابی ثبت می‌کند.
 */
add_action('wp_mail_failed', 'jay_login_register_capture_mail_error', 10, 1);
function jay_login_register_capture_mail_error( $wp_error ) {
    if ( is_wp_error($wp_error) ) {
        $GLOBALS['jay_relog_mail_error'] = $wp_error->get_error_message();
    }
}

/**
 * جدید: تابع کمکی برای ساخت HTML فرم تغییر شماره
 * این تابع به ما اجازه می‌دهد هم در بارگذاری اولیه و هم در AJAX از آن استفاده کنیم.
 */
function jay_login_register_get_change_phone_form_html( $new_phone_value = '', $change_password = false ) {
    $user_id = get_current_user_id();
    $current_phone = get_user_meta($user_id, 'jay_mobile', true);

    ob_start();
    ?>
    <p style="color:#000;" >شماره موبایل فعلی شما: <strong><?php echo esc_html($current_phone); ?></strong></p>
    <p style="color:#000;">شماره جدید خود را برای دریافت کد تایید وارد کنید.</p>
    <p style="color:#000;">
        اگر فقط میخواهید پسورد را تغییر دهید شماره موبایل اکنون را از دوباره وارد کنید و پس از تایید پسورد شما برای همین شماره ذخیره می شود
    </p>
    
    <div class="jay-login-register-field">
        <label class="jay-login-register-toggle-label" for="jay_login_register_new_phone">شماره موبایل جدید</label>
        <input type="tel" name="jay_login_register_new_phone" class="jay-login-register-input" placeholder="مثال: 09123456789" value="<?php echo esc_attr($new_phone_value); ?>" required>
    </div>

    <div class="jay-login-register-field">
    <label class="jay-login-register-toggle-label">
            <input type="checkbox" name="jay_login_register_change_password_toggle" id="jay_login_register_change_password_toggle" value="1" <?php checked($change_password); ?>>
            می‌خواهم رمز عبور خود را نیز تغییر دهم.
        </label>
    </div>

    <?php if ($change_password) : ?>
    <div class="jay-login-register-field" id="jay-login-register-new-password-field">
        <label for="jay_login_register_new_password">رمز عبور جدید</label>
        <input type="password" name="jay_login_register_new_password" class="jay-login-register-input" required>
    </div>
    <?php endif; ?>
    
    <button type="button" class="jay-login-register-button" data-action="send_change_phone_otp">ارسال کد تایید</button>
    <?php
    return ob_get_clean();
}

/**
 * به هوک phpmailer_init متصل می‌شود تا تنظیمات ارسال ایمیل را بر اساس
 * تنظیمات افزونه پیکربندی کند (برای ارسال با SMTP).
 *
 * @param PHPMailer $phpmailer آبجکت اصلی PHPMailer.
 */
function jay_login_register_configure_phpmailer( $phpmailer ) {
    $settings = get_option('jay_login_register_settings');

    // فقط زمانی ادامه بده که کاربر روش SMTP را انتخاب کرده باشد
    if ( ! isset($settings['email_send_method']) || $settings['email_send_method'] !== 'smtp' ) {
        return;
    }

    // بررسی می‌کنیم که آیا اطلاعات ضروری SMTP وارد شده است یا خیر
    if ( empty($settings['smtp_host']) || empty($settings['smtp_user']) || empty($settings['smtp_pass']) ) {
        return;
    }

    // مرحله ۱: به PHPMailer بگو که از SMTP استفاده کند
    $phpmailer->isSMTP();

    // مرحله ۲: تنظیمات سرور
    $phpmailer->Host       = $settings['smtp_host'];
    $phpmailer->SMTPAuth   = true; // احراز هویت همیشه برای SMTP لازم است
    $phpmailer->Port       = absint($settings['smtp_port'] ?? 587);
    $phpmailer->Username   = $settings['smtp_user'];
    $phpmailer->Password   = $settings['smtp_pass'];

    // مرحله ۳: تنظیمات امنیتی
    $encryption = $settings['smtp_encryption'] ?? 'tls';
    if ( in_array($encryption, ['ssl', 'tls'], true) ) {
        $phpmailer->SMTPSecure = $encryption;
    }

    // مرحله ۴: تنظیم اطلاعات فرستنده
    if ( ! empty($settings['email_from_address']) ) {
        $phpmailer->From = $settings['email_from_address'];
    }
    if ( ! empty($settings['email_from_name']) ) {
        $phpmailer->FromName = $settings['email_from_name'];
    }
}
add_action('phpmailer_init', 'jay_login_register_configure_phpmailer');
/**
 * جدید: فرم ایجاد رمز عبور را به صورت داینامیک می‌سازد
 */
function jay_login_register_get_create_password_form_html($user_input, $identity_data = []) {
    $settings = get_option('jay_login_register_settings');

    // بررسی فعال بودن فیلدها
    $show_name_fields = isset($settings['enable_name_fields']) && $settings['enable_name_fields'] === 'yes';
    $show_username = isset($settings['enable_username']) && $settings['enable_username'] === 'yes';

    $identity_fields_html = '';
    if (!empty($identity_data['national_code'])) {
        $identity_fields_html = '<input type="hidden" name="national_code" value="' . esc_attr($identity_data['national_code']) . '">';
    } elseif (!empty($identity_data['passport_number'])) {
        $identity_fields_html = '<input type="hidden" name="passport_number" value="' . esc_attr($identity_data['passport_number']) . '">';
    }
    $extra_fields_html = '';
    $username_field_html = '';

    if ($show_username) {
        $username_field_html = '
        <div class="jay-login-register-field jay-username-wrapper">
            <label for="jay_login_register_custom_username">نام کاربری (انگلیسی)</label>
            <input type="text" name="jay_login_register_custom_username" id="jay_login_register_custom_username" class="jay-login-register-input" autocomplete="username" dir="ltr" style="text-align:left;" placeholder="مثال: jay_user_2024">
            <small class="jay-username-status" style="display:block; margin-top:5px; font-size:11px; min-height:15px;"></small>
        </div>';
    }
    
  if ($show_name_fields) {
        $extra_fields_html .= '
        <div class="jay-login-register-field">
            <label for="jay_login_register_first_name">نام</label>
            <input type="text" name="jay_login_register_first_name" class="jay-login-register-input" autocomplete="given-name">
        </div>
        <div class="jay-login-register-field">
            <label for="jay_login_register_last_name">نام خانوادگی</label>
            <input type="text" name="jay_login_register_last_name" class="jay-login-register-input" autocomplete="family-name">
        </div>';
    } 
// --- بخش جدید: رندر فیلدهای سفارشی سراسری (کامل) ---
    $custom_fields_html = '';
    $is_custom_fields_enabled = isset($settings['enable_custom_fields_global']) && $settings['enable_custom_fields_global'] === 'yes';
    
    if ( $is_custom_fields_enabled && !empty($settings['custom_fields_global_json']) ) {
        $fields_config = json_decode( $settings['custom_fields_global_json'], true );
        
        if ( is_array($fields_config) ) {
            foreach ( $fields_config as $field ) {
                $key   = isset($field['key']) ? esc_attr($field['key']) : '';
                $label = isset($field['label']) ? esc_html($field['label']) : '';
                $desc  = isset($field['description']) ? $field['description'] : ''; // توضیحات
                $type  = isset($field['type']) ? $field['type'] : 'text';
                $options = isset($field['options']) ? $field['options'] : [];
                $is_req  = !empty($field['is_required']);
                
                if ( empty($key) ) continue;

                $req_attr = $is_req ? 'required' : '';
                $req_mark = $is_req ? '<span style="color:red">*</span>' : '';
                $input_name = 'meta_' . $key;

                $custom_fields_html .= '<div class="jay-login-register-field jay-custom-field-wrapper">';
                $custom_fields_html .= '<label for="' . $input_name . '">' . $label . $req_mark . '</label>';

                // 1. پاراگراف
                if ( $type === 'textarea' ) {
                    $custom_fields_html .= '<textarea name="' . $input_name . '" id="' . $input_name . '" class="jay-login-register-input jay-login-register-textarea" rows="3" ' . $req_attr . '></textarea>';
                }
                elseif ( $type === 'number' ) {
                    // اعمال محدودیت طول در فرانت‌اند
                    $max_len_attr = '';
                    if ( !empty($field['number_len']) ) {
                        $max_len_attr = 'maxlength="' . esc_attr($field['number_len']) . '"';
                    }
                    
                    $custom_fields_html .= '<input type="tel" name="' . $input_name . '" id="' . $input_name . '" class="jay-login-register-input" ' . $req_attr . ' ' . $max_len_attr . ' inputmode="numeric" placeholder="فقط عدد وارد کنید">';
                }
               // 2. تاریخ (شمسی/میلادی)
                elseif ( $type === 'date' ) {
                    $is_jalali = !empty($field['is_jalali']);
                    $jalali_attr = $is_jalali ? ' data-jalali="1"' : '';
                    // کلاس jay-datepicker را همیشه اضافه می‌کنیم تا در JS پیدایش کنیم
                    $class_extra = ' jay-datepicker'; 
                    
                    $custom_fields_html .= '<input type="text" name="' . $input_name . '" id="' . $input_name . '" class="jay-login-register-input' . $class_extra . '" ' . $jalali_attr . ' ' . $req_attr . ' autocomplete="off">';
                }
                // 3. لیست بازشو
                elseif ( $type === 'select' ) {
                    $custom_fields_html .= '<select name="' . $input_name . '" id="' . $input_name . '" class="jay-login-register-input" ' . $req_attr . '>';
                    $custom_fields_html .= '<option value="">انتخاب کنید...</option>';
                    foreach ( $options as $opt ) {
                        $custom_fields_html .= '<option value="' . esc_attr($opt['value']) . '">' . esc_html($opt['label']) . '</option>';
                    }
                    $custom_fields_html .= '</select>';
                }
                // 4. رادیو
                elseif ( $type === 'radio' ) {
                    $custom_fields_html .= '<div class="jay-radio-group">';
                    foreach ( $options as $opt ) {
                        $custom_fields_html .= '<label style="display:inline-block; margin-left:10px; font-weight:normal;"><input type="radio" name="' . $input_name . '" value="' . esc_attr($opt['value']) . '" ' . $req_attr . '> ' . esc_html($opt['label']) . '</label>';
                    }
                    $custom_fields_html .= '</div>';
                }
                // 5. چک باکس
                elseif ( $type === 'checkbox' ) {
                    $custom_fields_html .= '<div class="jay-checkbox-group">';
                    foreach ( $options as $opt ) {
                        // چک باکس‌ها required ندارند چون ممکن است چند انتخابی باشند (پیچیده است)، اما می‌توان برای تکی گذاشت
                        $custom_fields_html .= '<label style="display:inline-block; margin-left:10px; font-weight:normal;"><input type="checkbox" name="' . $input_name . '[]" value="' . esc_attr($opt['value']) . '"> ' . esc_html($opt['label']) . '</label>';
                    }
                    $custom_fields_html .= '</div>';
                }
                // 6. متن (پیش‌فرض)
                else {
                    $custom_fields_html .= '<input type="text" name="' . $input_name . '" id="' . $input_name . '" class="jay-login-register-input" ' . $req_attr . '>';
                }

                // نمایش توضیحات زیر فیلد
                if ( ! empty($desc) ) {
                    $custom_fields_html .= '<p class="jay-field-description" style="font-size: 11px; color: #ccc; margin-top: 4px;">' . esc_html($desc) . '</p>';
                }

                $custom_fields_html .= '</div>';
            }
        }
    }
    if ( $show_name_fields ) {
        $title = 'تکمیل ثبت‌نام';
        $description = 'لطفاً اطلاعات زیر را برای تکمیل حساب کاربری خود وارد کنید.';
    } else {
        // حالت پیش‌فرض (فقط رمز عبور)
        $title = 'ایجاد رمز عبور';
        $description = 'یک رمز عبور قوی برای حساب کاربری خود انتخاب کنید.';
    }
    
    return '<h3>' . esc_html($title) . '</h3>
    <p>' . esc_html($description) . '</p>
' . $username_field_html . '
' . $extra_fields_html . '
' . $custom_fields_html . '
    <div class="jay-login-register-field">
        <label for="jay_login_register_password">رمز عبور</label>
        <input type="password" name="jay_login_register_password" class="jay-login-register-input" autocomplete="new-password">
    </div>

    <input type="hidden" name="user_input" value="' . esc_attr($user_input) . '">
    ' . $identity_fields_html . '
    <button type="button" class="jay-login-register-button" data-action="create_final_user">عضویت نهایی</button>';
}
/**
 * جدید: بررسی آنلاین وضعیت نام کاربری (یکتا بودن و فرمت)
 */
add_action('wp_ajax_nopriv_jay_check_username_availability', 'jay_login_register_ajax_check_username_availability');
function jay_login_register_ajax_check_username_availability() {
    // چون این درخواست در حین تایپ ارسال می‌شود، نانس را چک نمی‌کنیم یا یک نانس عمومی چک می‌کنیم.
    // اما برای امنیت بهتر است نانس فرم اصلی را بفرستیم.
    // در JS نانس را میفرستیم.
    if (isset($_POST['_ajax_nonce']) && !wp_verify_nonce(sanitize_key($_POST['_ajax_nonce']), 'jay_login_register_nonce_action')) {
        wp_send_json_error(['message' => 'خطای امنیتی.']);
    }

    $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';

    // ۱. بررسی فرمت (فقط انگلیسی، عدد و آندرلاین)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        wp_send_json_error(['message' => 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و خط زیر (_) باشد.']);
    }

    // ۲. بررسی طول
    if (strlen($username) < 4) {
        wp_send_json_error(['message' => 'نام کاربری باید حداقل ۴ کاراکتر باشد.']);
    }

    // ۳. بررسی رزرو بودن در وردپرس
    if (username_exists($username)) {
        wp_send_json_error(['message' => 'این نام کاربری قبلاً گرفته شده است.']);
    }

    // ۴. بررسی لیست سیاه وردپرس (نام‌های غیرمجاز مثل admin)
    $illegal_names = ['admin', 'administrator', 'root', 'support', 'test'];
    if (in_array(strtolower($username), $illegal_names)) {
        wp_send_json_error(['message' => 'استفاده از این نام کاربری مجاز نیست.']);
    }

    wp_send_json_success(['message' => 'نام کاربری معتبر و آزاد است.']);
}
/**
 * جدید: فرم تایید کد OTP را به صورت داینامیک می‌سازد
 */
function jay_login_register_get_otp_verification_form_html($title, $instruction_text, $user_input, $input_name, $button_data_action, $resend_data_action, $resend_context) {

    $settings = get_option('jay_login_register_settings');
    $otp_style = $settings['otp_input_style'] ?? 'single';
    $otp_length = intval($settings['otp_length'] ?? 4);

    $fields_html = '';

    if ($otp_style === 'multiple') {
        $fields_html .= '<div class="jay-otp-fields-container" data-otp-length="' . esc_attr($otp_length) . '">';
        for ($i = 0; $i < $otp_length; $i++) {
            // از type="text" و inputmode="numeric" برای بهترین تجربه در موبایل استفاده می‌کنیم
            $fields_html .= '<input type="text" class="jay-otp-digit-input" maxlength="1" inputmode="numeric" autocomplete="one-time-code">';
        }
        $fields_html .= '</div>';
        // یک فیلد مخفی برای نگهداری کد نهایی که به سرور ارسال می‌شود
        $fields_html .= '<input type="hidden" name="' . esc_attr($input_name) . '" id="' . esc_attr($input_name) . '">';
    } else {
        // حالت تک فیلدی (کد قبلی)
        $fields_html = '<div class="jay-login-register-field">
            <label for="' . esc_attr($input_name) . '">کد تایید</label>
            <input type="text" name="' . esc_attr($input_name) . '" id="' . esc_attr($input_name) . '" class="jay-login-register-input" inputmode="numeric" required>
        </div>';
    }

    return '<h3>' . esc_html($title) . '</h3>
    <p>' . wp_kses_post($instruction_text) . '</p>
    ' . $fields_html . '
    <input type="hidden" name="user_input" value="' . esc_attr($user_input) . '">
    <button type="button" class="jay-login-register-button" data-action="' . esc_attr($button_data_action) . '">تایید و ادامه</button>
    <div class="jay-login-register-timer-wrapper">
        <a href="#" class="jay-login-register-resend-link" data-action="' . esc_attr($resend_data_action) . '" data-context="' . esc_attr($resend_context) . '" disabled>ارسال مجدد کد</a>
        <span class="jay-login-register-timer"></span>
    </div>';
}
/**
 * جدید: تابع کمکی برای ارسال OTP ایمیل در فرآیند عضویت و نمایش فرم تایید
 */
function jay_login_register_send_email_otp_and_show_form($email, $identity_data) {
    $settings = get_option('jay_login_register_settings');
    $user_ip = jay_login_register_get_user_ip();

    jay_login_register_check_and_handle_lockout( $email, $user_ip, $settings );

    $otp_length = intval($settings['otp_length'] ?? 4);
    $validity_period = intval($settings['otp_validity_period'] ?? 2);
    $otp = wp_rand(pow(10, $otp_length - 1), pow(10, $otp_length) - 1);

    // --- منطق ارسال ایمیل ---
    $subject_template = $settings['email_otp_subject'] ?? 'کد تایید: [otp_code]';
    $body_template = $settings['email_otp_body'] ?? "کد تایید شما برای [site_name]:\n\n[otp_code]";
    $replacements = [
        '[otp_code]'        => $otp,
        '[site_name]'       => get_bloginfo('name'),
        '[validity_period]' => $validity_period,
    ];
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $body = nl2br(str_replace(array_keys($replacements), array_values($replacements), $body_template));
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $sent = wp_mail($email, $subject, $body, $headers);

    if ( ! $sent ) {
        $error_message = 'خطا در ارسال ایمیل.';
        if ( isset($GLOBALS['jay_relog_mail_error']) ) {
            $error_message .= ' پیام سرور: ' . esc_html($GLOBALS['jay_relog_mail_error']);
            unset($GLOBALS['jay_relog_mail_error']);
        }
        wp_send_json_error(['message' => $error_message]);
    }
    // --- پایان منطق ارسال ایمیل ---

    // ذخیره اطلاعات در transient
    $transient_data = array_merge(['otp' => $otp, 'time' => time()], $identity_data);
    set_transient('jay_login_register_email_otp_register_' . $email, $transient_data, $validity_period * MINUTE_IN_SECONDS);

    $instruction = 'کد ' . esc_html($otp_length) . ' رقمی به ایمیل <strong>' . esc_html($email) . '</strong> ارسال شد.';
    $html = jay_login_register_get_otp_verification_form_html('تایید آدرس ایمیل', $instruction, $email, 'jay_login_register_email_otp', 'verify_email_otp_register', 'resend_email_otp_register', 'register');

    wp_send_json_success([
        'html'            => $html,
        'message'         => 'کد تایید به ایمیل شما ارسال شد.',
        'validity_period' => $validity_period * 60
    ]);
}

/**
 * جدید: ترجمه کدهای خطای وب‌سرویس OTP بله
 */
function jay_login_register_get_bale_otp_error_message( $error_code ) {
    $errors = [
        '8'  => 'شماره تلفن وارد شده نامعتبر است.',
        '17' => 'این شماره تلفن عضو پیام‌رسان بله نیست.',
        '20' => 'اعتبار سرویس OTP شما در بله به پایان رسیده است.',
        '18' => 'تعداد درخواست‌ها برای این شماره بیش از حد مجاز بوده است. لطفاً بعداً تلاش کنید.',
        '2'  => 'خطای داخلی در سرور بله رخ داده است.',
    ];
    $error_code_key = (string) $error_code;
    return $errors[$error_code_key] ?? 'خطای ناشناخته از سرور بله با کد ' . $error_code;
}

/**
 * جدید: تابع اصلی ارسال OTP از طریق سامانه سفیر بله
 */
function jay_login_register_send_otp_via_bale( $mobile_number, $otp_code ) {
    $settings = get_option('jay_login_register_settings');
    $client_id = $settings['bale_otp_client_id'] ?? '';
    $client_secret = $settings['bale_otp_client_secret'] ?? '';

    if ( empty($client_id) || empty($client_secret) ) {
        return new WP_Error('config_error', 'اطلاعات سرویس OTP بله در تنظیمات افزونه تکمیل نشده است.');
    }

    // 1. دریافت توکن دسترسی (با استفاده از کش)
    $token_transient_key = 'jay_relog_bale_otp_token';
    $access_token = get_transient($token_transient_key);

    if ( false === $access_token ) {
        $auth_response = wp_remote_post('https://safir.bale.ai/api/v2/auth/token', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'scope'         => 'read',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($auth_response) || wp_remote_retrieve_response_code($auth_response) !== 200) {
            return new WP_Error('auth_error', 'خطا در احراز هویت با سرور بله.');
        }

        $auth_body = json_decode(wp_remote_retrieve_body($auth_response), true);
        if ( ! empty($auth_body['access_token']) ) {
            $access_token = $auth_body['access_token'];
            $expires_in = isset($auth_body['expires_in']) ? absint($auth_body['expires_in']) : 3600;
            set_transient($token_transient_key, $access_token, $expires_in - 60); // 60 ثانیه زودتر منقضی می‌کنیم
        } else {
            return new WP_Error('auth_failed', 'احراز هویت با بله ناموفق بود. لطفاً اطلاعات کاربری را بررسی کنید.');
        }
    }

    // 2. ارسال کد OTP
    $normalized_phone = '98' . substr(jay_login_register_normalize_numbers($mobile_number), 1);

    $otp_response = wp_remote_post('https://safir.bale.ai/api/v2/send_otp', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode([
            'phone' => $normalized_phone,
            'otp'   => (int) $otp_code,
        ]),
        'timeout' => 20,
    ]);

    if (is_wp_error($otp_response)) {
        return new WP_Error('send_error', 'خطا در اتصال به سرور ارسال OTP بله.');
    }

    $response_code = wp_remote_retrieve_response_code($otp_response);
    $response_body = json_decode(wp_remote_retrieve_body($otp_response), true);

    if ($response_code === 200 && isset($response_body['balance'])) {
        return true; // ارسال موفق
    } else {
        $error_code = $response_body['code'] ?? 'unknown';
        $error_message = jay_login_register_get_bale_otp_error_message($error_code);
        return new WP_Error('bale_api_error', 'خطای API بله: ' . $error_message);
    }
}
