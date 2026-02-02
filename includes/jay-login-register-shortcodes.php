<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// شورت‌کد اصلی فرم
add_shortcode( 'jay_login_register_form', 'jay_login_register_render_form' );
function jay_login_register_render_form() {
    wp_enqueue_style( 'jay-login-register-global-fonts' );
        global $jay_login_register_has_shortcode;
        $jay_login_register_has_shortcode = true;
        
        $settings = get_option('jay_login_register_settings');
        $logo_id = !empty($settings['logo_id']) ? absint($settings['logo_id']) : 0;

      if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        $redirect_page_id = !empty($settings['redirect_page_id']) ? absint($settings['redirect_page_id']) : 0;
        $redirect_url = $redirect_page_id ? get_permalink($redirect_page_id) : home_url('/');

        ob_start();
        ?>
        <div class="jay-login-register-loggedin-container">
            <?php if ($logo_id): ?>
                <div class="jay-login-register-logo-wrapper">
                    <?php echo wp_get_attachment_image($logo_id, 'medium', false, ['alt' => get_bloginfo('name') . ' Logo']); ?>
                </div>
            <?php endif; ?>
            
            <h3>سلام <?php echo esc_html( $current_user->display_name ); ?> عزیز</h3>
            <p>شما از قبل وارد حساب کاربری خود شده‌اید.</p>

            <a href="<?php echo esc_url( $redirect_url ); ?>" id="jay-login-register-redirect-button" class="jay-login-register-button-login">ورود به پنل کاربری</a>
            
            <p class="jay-login-register-redirect-timer">
            تا <span id="jay-login-register-countdown" data-seconds="5">5</span> ثانیه دیگر به صورت خودکار منتقل می‌شوید...
            </p>

            <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="jay-login-register-resend-link-login">خروج از حساب کاربری</a>
        </div>
        <?php
        return Jay_Login_Register_Minifier::html( ob_get_clean() );
    }
// --- منطق جدید و هوشمند برای نمایش فرم ---
     $login_methods = $settings['login_methods'] ?? ['mobile'];
     $mobile_enabled = in_array('mobile', $login_methods, true);
     $email_enabled = in_array('email', $login_methods, true);
    $username_enabled = isset($settings['enable_username']) && $settings['enable_username'] === 'yes';
    
   $active_labels = [];
    if ($mobile_enabled) $active_labels[] = 'شماره همراه';
    if ($email_enabled) $active_labels[] = 'ایمیل';
    if ($username_enabled) $active_labels[] = 'نام کاربری';

    // ۲. تعیین نوع اینپوت و آیکون
    // نکته: اگر نام کاربری فعال باشد یا بیش از یک روش داشته باشیم، باید text باشد
    if ( count($active_labels) > 1 || $username_enabled ) {
        $input_type = 'text';
        $icon_class = 'jay-input-icon-user';
    } elseif ($email_enabled) {
        $input_type = 'email'; // فقط ایمیل
        $icon_class = 'jay-input-icon-email';
    } else {
        $input_type = 'tel'; // فقط موبایل
        $icon_class = 'jay-input-icon-phone';
    }

    // ۳. تولید متن‌ها
    $label_text = implode(' / ', $active_labels);
    
    // اگر بیش از یک روش فعال بود (حالت ترکیبی)
    if ( count($active_labels) > 1 ) {
        $button_text = 'بررسی حساب';
        $p_text = 'برای ورود یا عضویت، اطلاعات خود را وارد کنید.';
        // پلیس‌هلدر هوشمند
        $placeholder_text = implode(' یا ', $active_labels) . '...'; 
    } 
    // اگر فقط یک روش فعال بود (حالت تکی)
    else {
        if ($mobile_enabled) {
            $button_text = 'بررسی شماره';
            $p_text = 'برای ورود یا عضویت، شماره تلفن همراه خود را وارد کنید.';
            $placeholder_text = 'مثال: 09123456789';
        } elseif ($email_enabled) {
            $button_text = 'بررسی ایمیل';
            $p_text = 'برای ورود یا عضویت، ایمیل خود را وارد کنید.';
            $placeholder_text = 'مثال: user@example.com';
        } else { // فقط نام کاربری (حالت خاص)
            $button_text = 'بررسی نام کاربری';
            $p_text = 'برای ورود یا عضویت، نام کاربری خود را وارد کنید.';
            $placeholder_text = 'نام کاربری خود را وارد کنید';
        }
    }
    ob_start();
    ?>
    <div class="floating-icons"><div class="floating-icon">👤</div><div class="floating-icon">📱</div><div class="floating-icon">🔐</div><div class="floating-icon">✨</div><div class="floating-icon">🌟</div><div class="floating-icon">🎯</div>
</div>
<div class="page-template-default"><div id="jay-login-register-container" class="jay-login-register-container"><?php if ($logo_id): ?><div class="jay-login-register-logo-wrapper"><?php echo wp_get_attachment_image($logo_id, 'medium', false, ['alt' => get_bloginfo('name') . ' Logo']); ?></div><?php endif; ?><div id="jay-login-register-form-content"><form id="jay-login-register-form" method="post">
                    <?php 
                   // phpcs:disable WordPress.Security.NonceVerification.Recommended
                   if ( isset( $_GET['redirect_to'] ) ) {
                        $redirect_url = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
                        printf( '<input type="hidden" name="redirect_to" value="%s">', esc_url( $redirect_url ) );
                    }
                   // phpcs:enable WordPress.Security.NonceVerification.Recommended
                    wp_nonce_field( 'jay_login_register_nonce_action', 'jay_login_register_nonce' );  ?>
                <div id="jay-login-register-step-container"><div class="jay-login-register-step" id="step-phone"><h2>ورود | عضویت</h2><p><?php echo esc_html($p_text); ?></p><div class="jay-login-register-field"><label for="jay_login_register_user_input"><?php echo esc_html($label_text); ?></label><input type="<?php echo esc_attr($input_type); ?>" id="jay_login_register_user_input" name="jay_login_register_user_input" class="jay-login-register-input <?php echo esc_attr($icon_class); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>" required></div><?php do_action('jay_relog_display_captcha'); ?><button type="button" class="jay-login-register-button" data-action="check_user_input"><?php echo esc_html($button_text); ?></button>
                             <?php
                              $is_google_enabled = !empty($settings['google_login_enable']) && $settings['google_login_enable'] === 'yes';
                              $google_client_id = $settings['google_client_id'] ?? '';
                              if ($is_google_enabled && !empty($google_client_id)) {
                              $google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
                              $redirect_uri = home_url('/?jay-google-auth=1');
                              $params = [
                              'client_id' => $google_client_id,
                              'redirect_uri'  => $redirect_uri,
                              'response_type' => 'code',
                              'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
                              'access_type' => 'offline',
                              'state' => wp_create_nonce('jay_google_oauth_nonce'),
                             ];
                              $login_url = add_query_arg($params, $google_auth_url);
                              ?><div class="jay-login-register-social-divider"><span>یا</span></div><a href="<?php echo esc_url($login_url); ?>" class="jay-login-register-button-social google"><span class="social-icon"></span>ورود با گوگل</a>
                         <?php } ?></div></div><div id="jay-login-register-messages" class="jay-login-register-messages"></div></form></div></div></div>
    <?php
return Jay_Login_Register_Minifier::html( ob_get_clean() );
    
}
