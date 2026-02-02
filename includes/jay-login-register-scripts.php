<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue admin scripts and styles for settings page
add_action( 'admin_enqueue_scripts', 'jay_login_register_admin_enqueue_scripts' );
function jay_login_register_admin_enqueue_scripts( $hook_suffix ) {
    // ابتدا بررسی می‌کنیم که آیا در یکی از صفحات داخلی افزونه هستیم یا خیر
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $is_plugin_page = isset( $_GET['page'] ) && in_array( $_GET['page'], [ 'jay_login_register_settings_page', 'jay_login_register_instructions', 'jay_login_register_access_control', 'jay_login_register_style_customizer', 'jay_login_register_user_panel' ] );

     if ( $is_plugin_page || 'users.php' === $hook_suffix ) {
        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        wp_enqueue_script(
            'jay-login-register-admin-script',
            JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-admin-script.js',
            [ 'jquery' ],
            filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-admin-script.js' ),
            true
        );
        wp_localize_script(
         'jay-login-register-admin-script', 
         'jay_relog_admin_obj', 
             [
             'ajax_url' => admin_url('admin-ajax.php'),
             'test_email_nonce' => wp_create_nonce('jay_relog_test_email_nonce'),
             ]
        );
    
        wp_enqueue_style(
            'jay-login-register-admin-style',
            JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-admin-style.css',
            [],
            filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-admin-style.css' )
        );
    }
}

// Enqueue scripts and styles for the frontend shortcode
add_action( 'wp_footer', 'jay_login_register_frontend_enqueue_scripts' );
function jay_login_register_frontend_enqueue_scripts() {
        global $jay_login_register_edit_shortcode, $jay_login_register_has_shortcode, $jay_login_register_is_user_panel;
     if ( ! $jay_login_register_edit_shortcode && ! $jay_login_register_has_shortcode && ! $jay_login_register_is_user_panel ) {
            return;
        }

    $settings = get_option('jay_login_register_settings');

if ( $jay_login_register_edit_shortcode ) { 
        wp_enqueue_style(
            'jay-login-register-content-lock-style',
            JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-content-lock.css',
            [],
            filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-content-lock.css' )
        );

        wp_enqueue_script(
            'jay-login-register-content-lock-script',
            JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-content-lock.js',
            [ 'jquery' ],
            filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-content-lock.js' ),
            true
        );

        // ارسال آدرس صفحه ورود به جاوا اسکریپت قفل محتوا
        $login_page_id = ! empty( $settings['login_page_id'] ) ? absint( $settings['login_page_id'] ) : 0;
        $login_page_url = $login_page_id ? get_permalink($login_page_id) : home_url('/');
        wp_localize_script(
            'jay-login-register-content-lock-script',
            'jay_lock_obj_inline', // نام آبجکت در جاوا اسکریپت
            [
                'login_page_url' => esc_url($login_page_url),
            ]
        );
    }

if ( $jay_login_register_has_shortcode ) {
    // --- بخش ۱: بارگذاری استایل قالب اصلی ---
    $selected_style = $settings['form_style'] ?? 'glass';
    $style_handle = 'jay-login-register-form-style';
    $style_file_path = 'assets/css/jay-login-register-form-style-' . sanitize_key($selected_style) . '.css';

    if ( file_exists( JAY_LOGIN_REGISTER_PATH . $style_file_path ) ) {
        wp_enqueue_style(
            $style_handle,
            JAY_LOGIN_REGISTER_URL . $style_file_path,
            [],
            filemtime( JAY_LOGIN_REGISTER_PATH . $style_file_path )
        );
    }

    // --- بخش ۲: اعمال استایل‌های شخصی‌سازی شده ---
    $defaults = [
        'form_bg_color'              => 'linear-gradient(135deg, #667eea, #764ba2, #f093fb)',
        'form_container_bg'          => 'rgba(255, 255, 255, 0.1)',
        'form_border_radius'         => 24,
        'form_backdrop_blur'         => 20,
        'form_border'                => '1px solid rgba(255, 255, 255, 0.2)',
        'form_box_shadow'            => '0 8px 32px 0 rgba(0, 0, 0, 0.1)',
        'form_button_bg'             => 'linear-gradient(90deg, #0073aa, #00c6ff)',
        'form_button_text_color'     => '#fff',
        'form_label_color'           => '#fff',
        'form_error_bg'              => 'rgba(220, 53, 69, 0.5)',
        'form_error_border'          => 'rgba(220, 53, 69, 0.8)',
        'form_error_text'            => '#fff',
        'form_h_color'               => '#fff',
        'form_p_color'               => '#fff',
        'form_button_secondary_bg'   => 'rgba(255, 255, 255, 0.15)',
        'form_button_secondary_text' => '#fff',
        'form_input_bg'              => 'rgba(0, 0, 0, 0.2)',
        'form_input_border'          => '#888',
        'form_input_text'            => '#fff',
        'form_switcher_color'        => '#fff',
    ];

    $custom_css = '';

    $root_vars = ':root {';
    
    $page_bg = $settings['form_bg_color'] ?? $defaults['form_bg_color'];
    if ($page_bg !== $defaults['form_bg_color']) {
        $root_vars .= '--page-bg:' . esc_attr($page_bg) . ';';
    }

    $container_bg = $settings['form_container_bg'] ?? $defaults['form_container_bg'];
    if ($container_bg !== $defaults['form_container_bg']) $root_vars .= '--form-container-bg:' . esc_attr($container_bg) . ';';
    
    $btn_text = $settings['form_button_text_color'] ?? $defaults['form_button_text_color'];
    if ($btn_text !== $defaults['form_button_text_color']) $root_vars .= '--button-primary-text:' . esc_attr($btn_text) . ';';
   
    $switcher_color = $settings['form_switcher_color'] ?? $defaults['form_switcher_color'];
    if ($switcher_color !== $defaults['form_switcher_color']) $root_vars .= '--form-switcher-color:' . esc_attr($switcher_color) . ';';

    $border_radius = $settings['form_border_radius'] ?? $defaults['form_border_radius'];
    if ($border_radius != $defaults['form_border_radius']) $root_vars .= '--form-border-radius:' . absint($border_radius) . 'px;';
    
    $backdrop_blur = $settings['form_backdrop_blur'] ?? $defaults['form_backdrop_blur'];
    if ($backdrop_blur != $defaults['form_backdrop_blur']) $root_vars .= '--form-backdrop-blur:' . absint($backdrop_blur) . 'px;';
    
    $border = $settings['form_border'] ?? $defaults['form_border'];
    if ($border !== $defaults['form_border']) $root_vars .= '--form-border:' . esc_attr($border) . ';';

    $box_shadow = $settings['form_box_shadow'] ?? $defaults['form_box_shadow'];
    if ($box_shadow !== $defaults['form_box_shadow']) $root_vars .= '--form-box-shadow:' . esc_attr($box_shadow) . ';';
    
    $button_bg = $settings['form_button_bg'] ?? $defaults['form_button_bg'];
    if ($button_bg !== $defaults['form_button_bg']) $root_vars .= '--button-primary-bg:' . esc_attr($button_bg) . ';';

    $label_color = $settings['form_label_color'] ?? $defaults['form_label_color'];
    if ($label_color !== $defaults['form_label_color']) $root_vars .= '--form-label-color:' . esc_attr($label_color) . ';';

    $error_bg = $settings['form_error_bg'] ?? $defaults['form_error_bg'];
    if ($error_bg !== $defaults['form_error_bg']) $root_vars .= '--form-error-bg:' . esc_attr($error_bg) . ';';

    $error_border = $settings['form_error_border'] ?? $defaults['form_error_border'];
    if ($error_border !== $defaults['form_error_border']) $root_vars .= '--form-error-border-color:' . esc_attr($error_border) . ';';
    
    $error_text = $settings['form_error_text'] ?? $defaults['form_error_text'];
    if ($error_text !== $defaults['form_error_text']) $root_vars .= '--form-error-text-color:' . esc_attr($error_text) . ';';
   
    $h_color = $settings['form_h_color'] ?? $defaults['form_h_color'];
    if ($h_color !== $defaults['form_h_color']) $root_vars .= '--form-h-color:' . esc_attr($h_color) . ';';
    
    $p_color = $settings['form_p_color'] ?? $defaults['form_p_color'];
    if ($p_color !== $defaults['form_p_color']) $root_vars .= '--form-p-color:' . esc_attr($p_color) . ';';
    
    $btn_sec_bg = $settings['form_button_secondary_bg'] ?? $defaults['form_button_secondary_bg'];
    if ($btn_sec_bg !== $defaults['form_button_secondary_bg']) $root_vars .= '--button-secondary-bg:' . esc_attr($btn_sec_bg) . ';';
    
    $btn_sec_text = $settings['form_button_secondary_text'] ?? $defaults['form_button_secondary_text'];
    if ($btn_sec_text !== $defaults['form_button_secondary_text']) $root_vars .= '--button-secondary-text:' . esc_attr($btn_sec_text) . ';';
    
    $input_bg = $settings['form_input_bg'] ?? $defaults['form_input_bg'];
    if ($input_bg !== $defaults['form_input_bg']) $root_vars .= '--form-input-bg:' . esc_attr($input_bg) . ';';
     
    $input_border = $settings['form_input_border'] ?? $defaults['form_input_border'];
    if ($input_border !== $defaults['form_input_border']) $root_vars .= '--form-input-border:' . esc_attr($input_border) . ';';
    
    $input_text = $settings['form_input_text'] ?? $defaults['form_input_text'];
    if ($input_text !== $defaults['form_input_text']) $root_vars .= '--form-input-text:' . esc_attr($input_text) . ';';

    $root_vars .= '}';

    if (strlen($root_vars) > 8) { 
        $custom_css .= $root_vars;
    }

    if ( ! empty($custom_css) ) {
        wp_add_inline_style( $style_handle, $custom_css );
    }

    // --- بخش ۳: بارگذاری اسکریپت‌های JS ---
    wp_enqueue_script(
        'jay-login-register-form-script',
        JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-form-script.js',
        [ 'jquery' ],
        filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-form-script.js' ),
        true
    );

    $captcha_type = $settings['captcha_type'] ?? 'none';
    $site_key = $settings['recaptcha_site_key'] ?? '';

    $localized_data = [
        'ajax_url'           => admin_url( 'admin-ajax.php' ),
        'captcha_type'       => $captcha_type,
        'recaptcha_site_key' => $site_key,
    ];  
    
    wp_localize_script( 'jay-login-register-form-script', 'jay_login_register_ajax_obj', $localized_data );
    
        if ( $captcha_type === 'recaptcha_v3' && ! empty( $site_key ) ) {
            $recaptcha_url = 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $site_key );
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
            wp_enqueue_script( 'google-recaptcha', $recaptcha_url, [], null, true );
        }
    
    } // $jay_login_register_has_shortcode

    // --- بارگذاری اسکریپت‌ها و استایل‌های اختصاصی پنل کاربری ---
    global $jay_login_register_is_user_panel;
    if ( is_singular() || (isset($jay_login_register_is_user_panel) && $jay_login_register_is_user_panel) || $jay_login_register_has_shortcode ) {
        wp_enqueue_style(
            'jay-pdatepicker-style',
            JAY_LOGIN_REGISTER_URL . 'pcalendar/jay-login-register-persianDatepickerlist.css',
            [],
            '0.1.0'
        );
        wp_enqueue_script(
            'jay-pdatepicker-script',
            JAY_LOGIN_REGISTER_URL . 'pcalendar/jay-login-register-persianDatepickerlist.min.js',
            ['jquery'],
            '0.1.0',
            true
        );
    }
    
    if ( isset($jay_login_register_is_user_panel) && $jay_login_register_is_user_panel ) {
        // فایل CSS
        wp_enqueue_style(
            'jay-login-register-user-panel-style',
            JAY_LOGIN_REGISTER_URL . 'includes/user-panel/assets/css/jay-login-register-style-user-panel.css',
            [],
            filemtime( JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/assets/css/jay-login-register-style-user-panel.css' )
        );

        // فایل JS
        wp_enqueue_script(
            'jay-login-register-user-panel-script',
            JAY_LOGIN_REGISTER_URL . 'includes/user-panel/assets/js/jay-login-register-script-user-panel.js',
            ['jquery'],
            filemtime( JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/assets/js/jay-login-register-script-user-panel.js' ),
            true
        );

        // ارسال متغیرهای لازم به JS (اگر نیاز شد)
        wp_localize_script(
            'jay-login-register-user-panel-script',
            'jayUserPanelObj',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('jay_login_register_nonce_action')
            ]
        );
    }
    
}

/**
 * بارگذاری اسکریپت‌ها فقط برای متا باکس در صفحات ویرایش
 */
add_action( 'admin_enqueue_scripts', 'jay_login_register_metabox_scripts' );
function jay_login_register_metabox_scripts( $hook ) {
    // این شرط چک می‌کند که آیا در صفحه ویرایش یک پست یا برگه هستیم یا خیر
    if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
        wp_enqueue_style(
            'jay-login-register-meta-box-style',
            JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-meta-box.css',
            [],
            filemtime(JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-meta-box.css')
        );
        wp_enqueue_script(
            'jay-login-register-meta-box-script',
            JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-meta-box.js',
            ['jquery'],
            filemtime(JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-meta-box.js'),
            true
        );

        wp_enqueue_style(
            'jay-login-register-editor-style',
            JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-editor-style.css',
            [],
            filemtime(JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-editor-style.css')
        );
        
        wp_enqueue_script(
            'jay-login-register-editor-script',
            JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-editor-script.js',
            ['jquery', 'wp-util'], 
            filemtime(JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-editor-script.js'),
            true 
        );
    // --- ارسال تنظیمات لازم به اسکریپت ویرایشگر ---
        $settings = get_option('jay_login_register_settings');
        $editor_settings_data = [
            'email_login_enabled' => in_array('email', $settings['login_methods'] ?? [], true),
            'email_otp_enabled'   => isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes',
        ];
        wp_localize_script(
            'jay-login-register-editor-script', 
            'jayEditorSettings',              
            $editor_settings_data            
        );
        // --- پایان ارسال تنظیمات ---
        
    }
}

/**
 * جدید: بارگذاری اسکریپت دکمه برای گوتنبرگ
 */
add_action( 'enqueue_block_editor_assets', 'jay_login_register_enqueue_gutenberg_assets' );
function jay_login_register_enqueue_gutenberg_assets() {
    // بارگذاری فایل JS مخصوص گوتنبرگ
    wp_enqueue_script(
        'jay-login-register-gutenberg',
        JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-gutenberg.js',
        [ 'wp-blocks', 'wp-element', 'wp-rich-text', 'wp-editor', 'wp-components', 'wp-i18n', 'jay-login-register-editor-script' ], // وابسته به اسکریپت مودال
        filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-gutenberg.js' ),
        true
    );
}

/**
 * استایل‌ها را برای استفاده در کل سایت (از جمله صفحات خطا) ثبت می‌کند
 */ 
add_action( 'init', 'jay_login_register_register_global_styles' );
function jay_login_register_register_global_styles() {
    // این استایل روی هوک init ثبت (register) می‌شود تا در همه جا،
    // از جمله صفحات die سفارشی، قابل دسترس باشد.
    wp_register_style(
        'jay-login-register-access-denied-style',
        JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-access-denied.css',
        [],
        filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-access-denied.css' )
    );

    wp_register_style(
        'jay-login-register-inline-lock-style', // <-- نام هندل جدید
        JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-inline-lock.css',
        [], // وابستگی خاصی ندارد
        filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-inline-lock.css' )
    );
        wp_register_style(
        'jay-login-register-global-fonts',
        JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-global-fonts.css',
        [],
        filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-global-fonts.css' )
    );

}
/**
 * ثبت اسکریپت‌های فرانت‌اند که ممکنه لازم بشن
 */
add_action( 'wp_enqueue_scripts', 'jay_login_register_register_frontend_scripts', 5 ); 
function jay_login_register_register_frontend_scripts() {
    // ثبت اسکریپت فرم درون خطی
    wp_register_script(
        'jay-login-register-inline-lock-script', 
        JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-inline-lock.js',
        [ 'jquery' ], 
        filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-inline-lock.js' ),
        true 
    );

}

/**
 * جدید: اسکریپت مدیریت ایتا را در تمام صفحات فرانت‌اند بارگذاری می‌کند
 */
add_action( 'wp_enqueue_scripts', 'jay_login_register_enqueue_eitaa_handler_script' );
function jay_login_register_enqueue_eitaa_handler_script() {
    $settings = get_option('jay_login_register_settings');
    $login_page_id = ! empty( $settings['login_page_id'] ) ? absint( $settings['login_page_id'] ) : 0;

    // فقط در صورتی اسکریپت‌ها را بارگذاری کن که قابلیت ورود با ایتا فعال باشد
    if ( isset($settings['eitaa_login_enable']) && $settings['eitaa_login_enable'] === 'yes' && $login_page_id > 0 && is_page($login_page_id) ) {
        
        // ۱. بارگذاری اسکریپت SDK ایتا در هدر
       // phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_script(
            'eitaa-web-app-sdk',
            'https://developer.eitaa.com/eitaa-web-app.js',
            [],
            null, 
            false
        );
        // phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion

        // ۲. بارگذاری اسکریپت مدیریت‌کننده سفارشی ما
        wp_enqueue_script(
            'jay-login-register-eitaa-handler',
            JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-eitaa-handler.js',
            [ 'jquery', 'eitaa-web-app-sdk' ], 
            filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-eitaa-handler.js' ),
            true 
        );

        // ۳. ارسال داده‌های لازم از PHP به جاوااسکریپت
        $localized_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'jay_login_register_nonce_action' ),
            'is_user_logged_in' => is_user_logged_in() ? 'true' : 'false',
        ];
        wp_localize_script( 'jay-login-register-eitaa-handler', 'jay_eitaa_obj', $localized_data );
    }
}
