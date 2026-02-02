<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// نام کوکی برای ذخیره شناسه مدیر اصلی
define('jay_login_register_SWITCH_COOKIE', 'jay_login_register_switched_from_user');

/**
 * تابع کمکی برای ایجاد مقدار امن کوکی (ID + Hash)
 */
function jay_login_register_generate_secure_cookie( $user_id ) {
    $secret = wp_salt('auth'); // استفاده از کلیدهای امنیتی سایت
    $hash = hash_hmac( 'sha256', (string) $user_id, $secret );
    return $user_id . '|' . $hash;
}

/**
 * تابع کمکی برای اعتبارسنجی کوکی
 */
function jay_login_register_validate_secure_cookie( $cookie_value ) {
    if ( empty( $cookie_value ) || strpos( $cookie_value, '|' ) === false ) {
        return false;
    }

    list( $user_id, $hash ) = explode( '|', $cookie_value );
    
    $secret = wp_salt('auth');
    $expected_hash = hash_hmac( 'sha256', (string) $user_id, $secret );

    // مقایسه امن برای جلوگیری از حملات Timing Attacks
    if ( hash_equals( $expected_hash, $hash ) ) {
        return absint( $user_id );
    }

    return false;
}

/**
 * گام ۱: افزودن لینک "رفتن به حساب" به لیست کاربران در پیشخوان
 */
add_filter( 'user_row_actions', 'jay_login_register_add_switch_to_link', 10, 2 );
function jay_login_register_add_switch_to_link( $actions, $user_object ) {
    // اطمینان از اینکه کاربر فعلی مدیر است و می‌تواند کاربران را ویرایش کند
    if ( current_user_can( 'edit_users' ) && get_current_user_id() !== $user_object->ID ) {
        $switch_to_url = add_query_arg([
            'action'    => 'jay_login_register_switch_to',
            'user_id'   => $user_object->ID,
            '_wpnonce'  => wp_create_nonce( 'jay_login_register_switch_to_' . $user_object->ID ),
        ], admin_url('users.php')); 

        $actions['jay_login_register_switch_to'] = sprintf( '<a href="%s">رفتن به حساب</a>', esc_url( $switch_to_url ) );
    }
    return $actions;
}

/**
 * گام ۲: پردازش درخواست برای سوییچ کردن به حساب کاربر
 */
add_action( 'init', 'jay_login_register_process_user_switch' );
function jay_login_register_process_user_switch() {
    // استفاده از isset و sanitize برای $_GET
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( ! isset( $_GET['action'] ) || sanitize_key( $_GET['action'] ) !== 'jay_login_register_switch_to' ) {
        return;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // اعتبارسنجی اولیه
    if ( ! is_user_logged_in() ) return;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $user_id_to_switch = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    if ( !$user_id_to_switch ) return;

    check_admin_referer( 'jay_login_register_switch_to_' . $user_id_to_switch );
    
    if ( ! current_user_can( 'edit_users' ) ) {
        wp_die( 'شما اجازه انجام این کار را ندارید.' );
    }

    $current_user_id = get_current_user_id();
    $user_to_switch = get_userdata($user_id_to_switch);
    if ( !$user_to_switch ) return;

    // تولید کوکی رمزنگاری شده
    $cookie_value = jay_login_register_generate_secure_cookie( $current_user_id );
    
    // شناسه مدیر را در یک کوکی امن ذخیره می‌کنیم
    $expiration = time() + ( DAY_IN_SECONDS );
    setcookie( jay_login_register_SWITCH_COOKIE, $cookie_value, $expiration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    
    // به عنوان کاربر جدید وارد می‌شویم
    wp_set_current_user( $user_id_to_switch, $user_to_switch->user_login );
    wp_set_auth_cookie( $user_id_to_switch, true );
    
    // به صفحه اصلی سایت هدایت می‌شویم
    wp_safe_redirect( home_url('/') );
    exit;
}

/**
 * گام ۳: افزودن لینک "بازگشت به حساب" به صورت شناور در پایین صفحه
 */
add_action( 'wp_footer', 'jay_login_register_add_switch_back_footer_link' );
function jay_login_register_add_switch_back_footer_link() {
    
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    if ( ! isset( $_COOKIE[ jay_login_register_SWITCH_COOKIE ] ) ) {
        return;
    }
    
    // دریافت مقدار کوکی برای اعتبارسنجی (امنیت با HMAC تضمین شده است)
    $cookie_value = $_COOKIE[ jay_login_register_SWITCH_COOKIE ]; 
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

    // پاکسازی برای اطمینان بیشتر قبل از پردازش
    $cookie_sanitized = sanitize_text_field( wp_unslash( $cookie_value ) );

    $admin_id = jay_login_register_validate_secure_cookie( $cookie_sanitized );
    
    if ( ! $admin_id ) {
        // کوکی دستکاری شده است، آن را حذف می‌کنیم
        setcookie( jay_login_register_SWITCH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        return;
    }

    $admin_user = get_userdata($admin_id);
    if ( ! $admin_user ) return;

    // آدرس صفحه فعلی را به لینک اضافه می‌کنیم
    $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri;

    $switch_back_url = add_query_arg([
        'action'      => 'jay_login_register_switch_back',
        'redirect_to' => urlencode( $current_url ), 
        '_wpnonce'    => wp_create_nonce( 'jay_login_register_switch_back' ),
    ], admin_url('index.php')); 

    printf(
        '<a href="%s" id="jay-login-register-switch-back-footer">بازگشت به حساب %s</a>',
        esc_url( $switch_back_url ),
        esc_html( $admin_user->display_name )
    );
}

/**
 * گام ۴: پردازش درخواست برای بازگشت به حساب مدیر
 */
add_action( 'init', 'jay_login_register_process_switch_back' );
function jay_login_register_process_switch_back() {
    // استفاده از isset و sanitize برای $_GET
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( ! isset( $_GET['action'] ) || sanitize_key( $_GET['action'] ) !== 'jay_login_register_switch_back' ) {
        return;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // بررسی Nonce برای جلوگیری از CSRF
    check_admin_referer( 'jay_login_register_switch_back' );

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    if ( ! isset( $_COOKIE[ jay_login_register_SWITCH_COOKIE ] ) ) {
        return;
    }

    $cookie_value = $_COOKIE[ jay_login_register_SWITCH_COOKIE ];
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

    $cookie_sanitized = sanitize_text_field( wp_unslash( $cookie_value ) );
    $admin_id = jay_login_register_validate_secure_cookie( $cookie_sanitized );

    if ( ! $admin_id ) {
        // تلاش برای نفوذ تشخیص داده شد
        setcookie( jay_login_register_SWITCH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        wp_die( 'نشست نامعتبر است. لطفاً مجدداً وارد شوید.' );
    }

    $admin_user = get_userdata($admin_id);
    if ( ! $admin_user ) return;

    // به عنوان مدیر وارد می‌شویم
    wp_set_current_user( $admin_id, $admin_user->user_login );
    wp_set_auth_cookie( $admin_id, true );

    // کوکی را پاک می‌کنیم
    setcookie( jay_login_register_SWITCH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );

    // به جای هدایت به پیشخوان، به آدرس قبلی هدایت می‌کنیم
    $redirect_url = admin_url('/'); // آدرس پیش‌فرض
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['redirect_to'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $redirect_to_sanitized  = sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) );
        $redirect_url_decoded   = urldecode( $redirect_to_sanitized );
        $redirect_url           = esc_url_raw( $redirect_url_decoded );
    }    
    wp_safe_redirect( $redirect_url );
    exit;
}

/**
 * گام ۳ (بخش دوم): افزودن لینک "بازگشت به حساب" به نوار ابزار پیشخوان وردپرس
 */
add_action( 'admin_bar_menu', 'jay_login_register_add_switch_back_admin_bar_link', 999 );
function jay_login_register_add_switch_back_admin_bar_link( $wp_admin_bar ) {
    // این تابع فقط در محیط پیشخوان اجرا می‌شود
    
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    if ( ! is_admin() || ! isset( $_COOKIE[ jay_login_register_SWITCH_COOKIE ] ) ) {
        return;
    }

    $cookie_value = $_COOKIE[ jay_login_register_SWITCH_COOKIE ];
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

    $cookie_sanitized = sanitize_text_field( wp_unslash( $cookie_value ) );
    $admin_id = jay_login_register_validate_secure_cookie( $cookie_sanitized );
    
    if ( ! $admin_id ) {
        return;
    }

    $admin_user = get_userdata($admin_id);
    if ( ! $admin_user ) return;

    // آدرس صفحه فعلی در پیشخوان
    $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri;
      
    $switch_back_url = add_query_arg([
        'action'      => 'jay_login_register_switch_back',
        'redirect_to' => urlencode( $current_url ),
        '_wpnonce'    => wp_create_nonce( 'jay_login_register_switch_back' ),
    ], admin_url('index.php'));

    $wp_admin_bar->add_node([
        'id'    => 'jay_login_register_switch_back',
        'title' => sprintf( '<span class="ab-icon dashicons-admin-users" style="margin-top:2px;"></span><span class="ab-label">بازگشت به حساب %s</span>', esc_html($admin_user->display_name) ),
        'href'  => esc_url( $switch_back_url ),
        'meta'  => [
            'class' => 'jay-login-register-switch-back-button',
        ]
    ]);
}

/**
 * استایل‌های مربوط به دکمه‌های بازگشت به حساب را به روش استاندارد اضافه می‌کند.
 */
function jay_login_register_enqueue_switching_styles() {
    // این تابع فقط زمانی اجرا می‌شود که کوکی سوییچ کاربر وجود داشته باشد.
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    if ( ! isset( $_COOKIE[ jay_login_register_SWITCH_COOKIE ] ) ) {
        return;
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

    // استایل دکمه شناور در فوتر سایت (فرانت‌اند)
    $footer_style = "
        #jay-login-register-switch-back-footer {
            position: fixed; bottom: 20px; left: 20px; z-index: 99999;
            background-color: #d63638; color: #ffffff;
            padding: 10px 20px; border-radius: 5px; text-decoration: none;
            font-family: sans-serif; font-size: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: transform 0.2s ease-in-out;
        }
        #jay-login-register-switch-back-footer:hover {
            transform: scale(1.05); color: #ffffff;
        }";

    // استایل دکمه در نوار ابزار پیشخوان (ادمین)
    $admin_bar_style = "
        #wp-admin-bar-jay_login_register_switch_back > a {
            background-color: #d63638 !important; color: #fff !important;
        }
        #wp-admin-bar-jay_login_register_switch_back:hover > a {
            background-color: #a02022 !important; color: #fff !important;
        }";

    // اضافه کردن استایل‌ها به روش استاندارد وردپرس
    if ( is_admin() ) {
        wp_add_inline_style( 'admin-bar', $admin_bar_style );
    } else {
        $version = defined('JAY_LOGIN_REGISTER_VERSION') ? JAY_LOGIN_REGISTER_VERSION : '1.0.0';
        wp_register_style( 'jay-login-register-switching-handle', false, [], $version );
        wp_enqueue_style( 'jay-login-register-switching-handle' );
        wp_add_inline_style( 'jay-login-register-switching-handle', $footer_style );
    }
}
// این تابع را به دو هوک برای فرانت‌اند و بک‌اند متصل می‌کنیم
add_action( 'wp_enqueue_scripts', 'jay_login_register_enqueue_switching_styles' );
add_action( 'admin_enqueue_scripts', 'jay_login_register_enqueue_switching_styles' );
