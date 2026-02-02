<?php
/**
 * Plugin Name:       JAY Login & Register
 * Description:       All-in-One Mobile OTP Login, Registration & Content Restriction plugin. Supports SMS, Email, Google, Digits & WooCommerce with Inline Forms.
 * Version:           2.6.03
 * Author:            Jayarsiech
 * Author URI:        https://instagram.com/jayarsiech
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jay-login-register
 * Plugin Slug:       jay-login-register
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JAY_LOGIN_REGISTER_VERSION', '2.6.03' );
define( 'JAY_LOGIN_REGISTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'JAY_LOGIN_REGISTER_URL', plugin_dir_url( __FILE__ ) );
 
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-helpers.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-minifier.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-captcha-handler.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-sms-handler.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-ajax-handler.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/jay-login-register-ajax-handler-user-panel.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/jay-login-register-avatar-handler.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/jay-login-register-user-time.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-editor.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-inline-handler.php'; 
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-install.php';
add_filter( 'template_include', 'jay_login_register_template_include', 99 );

require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-permission-toggle.php';

// این فیلتر باید همیشه اجرا شود، چه ادمین باشد چه نباشد

if ( is_admin() ) {
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-settings.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/jay-login-register-user-panel-settings.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-meta-box.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-user-columns.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-learn.php'; 
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-dashboard.php'; 

      add_action( 'admin_notices', 'jay_login_register_setup_notice' );
      add_action( 'admin_init', 'jay_login_register_notice_actions' );
      add_filter( 'display_post_states', 'jay_login_register_add_post_states', 10, 2 );
      
    add_action( 'admin_init', 'jay_login_register_check_admin_access' );
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jay_login_register_add_settings_link' );

} else {

require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-shortcodes.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/jay-login-register-user-panel-shortcode.php';

    add_action('template_redirect', 'jay_login_register_custom_logout_url_handler');
}

require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-user-switching.php';
require_once JAY_LOGIN_REGISTER_PATH . 'includes/jay-login-register-scripts.php';
 
register_activation_hook( __FILE__, 'jay_login_register_activate' );
function jay_login_register_activate() {
    // Set default options if they don't exist
    $options = get_option('jay_login_register_settings');
    if ( empty($options) ) {
        $defaults = [
            'sms_panel_username' => '',
            'sms_panel_password' => '',
            'sms_sender_line' => '',
            'sms_panel_domain' => 'ippanel.com',
            'send_method' => 'pattern',
            'pattern_code' => '',
            'logo_url' => ''
        ];
        update_option('jay_login_register_settings', $defaults);
    }
    // --- جدید: افزودن قابلیت‌های سفارشی به نقش مدیرکل ---
     jay_login_register_run_upgrade_tasks();

}
add_action('template_redirect', 'jay_login_register_custom_logout_url_handler');

function jay_login_register_custom_logout_url_handler() {
    if ( is_page('logout') ) {
        wp_logout();
        wp_safe_redirect( home_url('/') );
        exit();
    }
}
// hide wp-login.php
$access_options = get_option('jay_login_register_access_settings', ['hide_wp_login' => 'no']);
if ( isset( $access_options['hide_wp_login'] ) && $access_options['hide_wp_login'] === 'yes' ) {
  add_action('login_init', 'jay_login_register_redirect_wp_login');
}
function jay_login_register_redirect_wp_login() {

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset($_GET['action']) && 'logout' === $_GET['action'] ) {
        return;
    }

    $options = get_option('jay_login_register_settings');
    $login_page_id = $options['login_page_id'] ?? 0;

    if ( $login_page_id ) {
        $redirect_url = get_permalink($login_page_id);
    } else {
        $redirect_url = home_url('/');
    }

    wp_safe_redirect($redirect_url);
    exit;
}
/**
 * دسترسی کاربران به پیشخوان وردپرس را بر اساس تنظیمات کنترل می‌کند.
 */
function jay_login_register_check_admin_access() {
    if ( wp_doing_ajax() ) {
        return;
    }
    $user = wp_get_current_user();
    if ( ! $user->exists() ) {
        return;
    }

    if ( in_array( 'administrator', $user->roles, true ) ) {
        return;
    }
    // گزینه‌های ذخیره شده را می‌خوانیم
    $options = get_option('jay_login_register_access_settings', ['allow_admin_access' => []]);
    $allowed_roles = $options['allow_admin_access'];

    $common_roles = array_intersect( $user->roles, $allowed_roles );

    if ( ! empty( $common_roles ) ) {
        return;
    }

        wp_safe_redirect( home_url('/') );
        exit;
}
add_action( 'admin_init', 'jay_login_register_check_admin_access' );

/**
 *
 * @param bool $show  وضعیت فعلی نمایش نوار ابزار.
 * @return bool       True برای نمایش، False برای مخفی کردن.
 */
function jay_login_register_hide_admin_bar_for_restricted_roles( $show ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user = wp_get_current_user();
    
    if ( in_array( 'administrator', $user->roles, true ) ) {
        return true;
    }

    // گزینه‌های ذخیره شده را از دیتابیس می‌خوانیم
    $options = get_option('jay_login_register_access_settings', ['allow_admin_access' => []]);
    $allowed_roles = $options['allow_admin_access'];
    
    $common_roles = array_intersect( $user->roles, $allowed_roles );
    if ( ! empty( $common_roles ) ) {
        return true;
    }
    return false;
}
add_filter( 'show_admin_bar', 'jay_login_register_hide_admin_bar_for_restricted_roles' );

/**
 * پاک کردن کش پس از ذخیره تنظیمات
 */
add_action( 'update_option_jay_login_register_settings', 'jay_login_register_clear_cache_on_settings_save', 10, 2 );
function jay_login_register_clear_cache_on_settings_save( $old_value, $new_value ) {
    
    $login_page_id = isset( $new_value['login_page_id'] ) ? absint( $new_value['login_page_id'] ) : 0;
    if ( ! $login_page_id > 0 ) {
        return;
    }

    // ۱. سازگاری با WP Rocket
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $login_page_id );
    }
    // ۲. سازگاری با W3 Total Cache
    if ( function_exists( 'w3tc_flush_post' ) ) {
        w3tc_flush_post( $login_page_id );
    }
    // ۳. سازگاری با WP Super Cache
    if ( function_exists( 'wp_cache_post_change' ) ) {
        wp_cache_post_change( $login_page_id );
    }
    // ۴. سازگاری با LiteSpeed Cache
    // این افزونه از طریق یک action hook کار می‌کند
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        do_action( 'litespeed_purge_post', $login_page_id );
    }

} 
/**
 * یک لینک "تنظیمات" به لیست اقدامات افزونه در صفحه افزونه‌ها اضافه می‌کند.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jay_login_register_add_settings_link' );
function jay_login_register_add_settings_link( $links ) {
    
    $settings_url = admin_url( 'admin.php?page=jay_login_register_settings_page' );
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . __( 'تنظیمات', 'jay-login-register' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * جدید: بررسی نسخه افزونه و اجرای کدهای بروزرسانی در صورت نیاز
 */
add_action( 'admin_init', 'jay_login_register_check_version' );
function jay_login_register_check_version() {
    $current_version = JAY_LOGIN_REGISTER_VERSION;
    $saved_version = get_option( 'jay_login_register_version' );

    // اگر نسخه ذخیره شده با نسخه فعلی متفاوت بود یا اصلاً وجود نداشت
    if ( $saved_version !== $current_version ) {
        
        // اجرای کدهای مربوط به بروزرسانی یا نصب اولیه
        jay_login_register_run_upgrade_tasks();

        // به‌روزرسانی نسخه ذخیره شده در دیتابیس به نسخه فعلی
        update_option( 'jay_login_register_version', $current_version );
    }
}

/**
 * جدید: تابع مرکزی برای اجرای وظایف نصب و بروزرسانی
 * این تابع هم در زمان فعال‌سازی و هم در زمان بروزرسانی فراخوانی می‌شود.
 */
function jay_login_register_run_upgrade_tasks() {
    // افزودن قابلیت‌های سفارشی به نقش مدیرکل
    $administrator_role = get_role( 'administrator' );
    if ( $administrator_role ) {
        $administrator_role->add_cap( 'jay_login_register_manage_settings' );
        $administrator_role->add_cap( 'jay_login_register_manage_access_control' );
    }
}

/**
 * Hook captcha display method to the display action.
 */
add_action('jay_relog_display_captcha', function() {
    jay_relog_captcha_handler()->display_field();
});

/**
 * Hook captcha verification method to the verification action.
 * This wrapper handles sending JSON errors in AJAX context if verification fails.
 *
 * @param array $post_data The $_POST data.
 * @param string $nonce_action The expected nonce action for the form.
 * @param string $nonce_name The expected nonce field name for the form.
 */ 
add_action('jay_relog_verify_captcha', function($post_data, $nonce_action, $nonce_name) {
    $result = jay_relog_captcha_handler()->verify_submission($post_data, $nonce_action, $nonce_name);

    if (is_wp_error($result)) {
        // Prepare error data, including potential extra info like new math question or lockout timer
        $error_data = [
            'message' => $result->get_error_message(),
        ];
        $extra_data = $result->get_error_data();
        if (is_array($extra_data)) {
            // Include specific data keys we know about
            if (isset($extra_data['new_math_question'])) {
                $error_data['new_math_question'] = $extra_data['new_math_question'];
            }
            if (isset($extra_data['lockout_timer'])) {
                $error_data['lockout_timer'] = $extra_data['lockout_timer'];
            }
        }

        wp_send_json_error($error_data);
        // wp_send_json_error will exit, so no need for explicit exit/die here.
    }
    // If verification was successful (returned true), do nothing and let the original handler continue.

}, 10, 3);

/**
 * AJAX action to get a new math captcha question.
 * (Kept for compatibility with existing JS, now uses the class method)
 */
add_action('wp_ajax_nopriv_jay_login_register_get_math_captcha', 'jay_login_register_ajax_get_math_captcha_new');
add_action('wp_ajax_jay_login_register_get_math_captcha', 'jay_login_register_ajax_get_math_captcha_new'); 
function jay_login_register_ajax_get_math_captcha_new() {
     // Nonce not strictly needed here as it only reveals a public question
     // Reflecting the class structure, the generation method is private.
     // We need a public method to expose question generation if needed via AJAX.
     // Let's modify the class slightly.
     // OR: We can just regenerate within the error response of verify_math_captcha.
     // Let's stick to the latter for now, less complexity.
     // This AJAX action might become obsolete if JS handles the update based on error response.
     // For now, let's keep it but it might need adjustment based on how generate_math_captcha is called.
     // ***Correction:*** generate_math_captcha needs to be callable externally for the initial load too.
     // Let's make generate_math_captcha public in the class or create a public wrapper.

     // Let's add a public method in the class:
     // public function get_new_math_question() { return $this->generate_math_captcha(); }
     // Assuming that method exists now:
    // $question = jay_relog_captcha_handler()->get_new_math_question(); // Needs the new method in class
    // wp_send_json_success(['question' => $question]);

     // ***Alternative/Simpler for now:*** Let's make generate_math_captcha public.
     // Make sure you change 'private function generate_math_captcha()'
     // to 'public function generate_math_captcha()' in the class definition above.
     $question = jay_relog_captcha_handler()->generate_math_captcha();
     wp_send_json_success(['question' => $question]);
}
/**
 * Hook the SMS sending method to the 'jay_relog_send_otp' filter.
 *
 * @param mixed $default_result Default value (usually an error or null).
 * @param string $mobile_number Recipient mobile number.
 * @param string|int $otp_code OTP code.
 * @return bool|WP_Error Result of the SMS sending attempt.
 */
add_filter('jay_relog_send_otp', function($default_result, $mobile_number, $otp_code) {
    // Call the send_otp method of the SmsHandler instance
    return jay_relog_sms_handler()->send_otp($default_result, $mobile_number, $otp_code);
}, 10, 3);

global $jay_login_register_edit_shortcode; 
$jay_login_register_edit_shortcode = false;

global $jay_login_register_has_shortcode;
$jay_login_register_has_shortcode = false;

global $jay_login_register_is_user_panel;
$jay_login_register_is_user_panel = false;
