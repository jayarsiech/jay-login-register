<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * افزودن دکمه "افزودن قفل محتوا" کنار دکمه "افزودن رسانه"
 */
add_action( 'media_buttons', 'jay_login_register_add_editor_lock_button', 20 );
function jay_login_register_add_editor_lock_button() {
    // اطمینان از اینکه در صفحه ویرایش پست هستیم
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'post' ) {
        return;
    }

    // HTML دکمه
    $button_html = '<button type="button" class="button jay-add-content-lock-button"> 
        <span class="dashicons dashicons-lock jay-lock-button-icon" style="vertical-align: middle; margin-left: 5px;"></span> 
        افزودن قفل محتوا        
    </button>';

    echo $button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * تابع کمکی برای بررسی اینکه آیا هشدار ایمیل باید در ویرایشگر نمایش داده شود یا خیر
 */
function jay_login_register_should_show_email_warning_for_editor() {
    $settings = get_option('jay_login_register_settings');
    $email_enabled = in_array('email', $settings['login_methods'] ?? [], true);
    $email_otp_enabled = isset($settings['email_otp_enable']) && $settings['email_otp_enable'] === 'yes';
    return ($email_enabled && !$email_otp_enabled);
}

/**
 * ثبت شورت‌کد [jay_content_lock]
 */
add_shortcode( 'jay_content_lock', 'jay_login_register_content_lock_shortcode_handler' );
function jay_login_register_content_lock_shortcode_handler( $atts, $content = null ) {
 wp_enqueue_style( 'jay-login-register-global-fonts' );
    global $jay_login_register_edit_shortcode;
    $jay_login_register_edit_shortcode = true;

    static $lock_counter = 0;
    $lock_counter++;
    $lock_id = 'jay-lock-' . $lock_counter;

    // ۱. تعریف متغیرها
    $attributes = shortcode_atts( [
        'mode'          => 'redirect',
        'style'         => 'default',
        'title'         => 'محتوای ویژه اعضا',
        'message'       => 'این بخش از محتوا برای اعضای سایت قابل مشاهده است.',
        'button_text'   => 'برای مشاهده کامل، وارد شوید یا عضو شوید',
        'button_color'  => '',
        'target_url'    => '', 
        'get_name'      => 'no',
        'force_persian' => 'no',
        'custom_fields' => '',
    ], $atts );
 
    $mode = sanitize_key( $attributes['mode'] );
    $style = sanitize_key( $attributes['style'] );
    $target_url = esc_url( $attributes['target_url'] );
    $btn_color = sanitize_hex_color( $attributes['button_color'] );
    $get_name_attr = sanitize_key( $attributes['get_name'] );
    $custom_fields_enc = $attributes['custom_fields'];
    $force_persian_attr = sanitize_key( $attributes['force_persian'] );
    $redirect_destination = $target_url ? $target_url : get_permalink();
    $is_pending_details = false;
    $missing_fields_list = []; // <-- (جدید) آرایه برای نگهداری فیلدهای ناقص

    // ۲. بررسی وضعیت کاربر
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $needs_info = false;

        // الف) بررسی نام (اصلاح شده)
        if ( $get_name_attr === 'yes' ) {
            $user_data = get_userdata($user_id);
            if ( empty( $user_data->first_name ) ) {
                $needs_info = true;
                $missing_fields_list[] = 'first_name'; // <-- (جدید) افزودن به لیست
            }
            if ( empty( $user_data->last_name ) ) {
                $needs_info = true;
                $missing_fields_list[] = 'last_name'; // <-- (جدید) افزودن به لیست
            }
        }

        // ب) بررسی فیلدهای سفارشی (اصلاح شده)
        if ( ! empty( $custom_fields_enc ) ) {
            $decoded_json = json_decode( base64_decode( $custom_fields_enc ), true );
            if ( is_array( $decoded_json ) ) {
                foreach ( $decoded_json as $field ) {
                    $key = isset($field['key']) ? $field['key'] : '';
                    if ( $key ) {
                        $meta_val = get_user_meta( $user_id, $key, true );
                        // اگر مقدار خالی بود و صفر هم نبود
                        if ( empty( $meta_val ) && $meta_val !== '0' ) {
                            $needs_info = true;
                            $missing_fields_list[] = $key; // <-- (جدید) افزودن به لیست
                            // نکته: اینجا دیگر break نمی‌کنیم تا لیست کامل پر شود
                        }
                    }
                }
            }
        }

        // ج) تصمیم نهایی
        if ( ! $needs_info ) {
            // اطلاعات کامل است -> نمایش محتوا
            return '<div id="' . esc_attr( $lock_id ) . '">' . do_shortcode( $content ) . '</div>';
        } else {
            // اطلاعات ناقص است -> وضعیت Pending
            $is_pending_details = true;
        }
    } 
    
    if ( $mode === 'inline' && ! empty( $content ) ) {
        if ( ! is_user_logged_in() || $is_pending_details ) {
            $transient_key = 'jay_lock_content_' . $lock_id;
            // این قسمت عالیه و باعث کاهش فشار سرور میشه
            set_transient( $transient_key, $content, 25 * MINUTE_IN_SECONDS ); 
        }
    }

    // ۳. لود کردن استایل‌ها و اسکریپت‌ها
    wp_enqueue_style( 'jay-login-register-content-lock-style' );
    
    if ( $mode === 'inline' || $is_pending_details ) {
        wp_enqueue_style( 'jay-login-register-inline-lock-style' );
        wp_enqueue_script( 'jay-login-register-inline-lock-script' );
    } else { 
        wp_enqueue_script( 'jay-login-register-content-lock-script' );
        $settings = get_option('jay_login_register_settings');
        $login_page_id = $settings['login_page_id'] ?? 0;
        $login_page_url = $login_page_id ? get_permalink($login_page_id) : home_url('/');
        
        wp_localize_script( 'jay-login-register-content-lock-script', 'jay_lock_obj_inline', [
            'login_page_url' => $login_page_url
        ]);
    }
 
    // ۴. تولید خروجی HTML
    ob_start();
    
    $wrapper_classes = 'jay-content-lock-wrapper';
    if ( $style === 'button_only' ) {
        $wrapper_classes .= ' jay-lock-button-only';
    }
    if ( $is_pending_details ) {
        $wrapper_classes .= ' jay-inline-locked-state';
    }
    ?>
    
    <div id="<?php echo esc_attr( $lock_id ); ?>" 
         class="<?php echo esc_attr( $wrapper_classes ); ?>" 
         data-get-name="<?php echo esc_attr($get_name_attr); ?>" 
         data-custom-fields="<?php echo esc_attr($custom_fields_enc); ?>"
         data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
         data-nonce="<?php echo esc_attr( wp_create_nonce( 'jay_inline_lock_nonce' ) ); ?>"
         data-post-id="<?php echo esc_attr( get_the_ID() ); ?>"
         data-lock-id="<?php echo esc_attr( $lock_id ); ?>"
         data-button-text="<?php echo esc_attr( $attributes['button_text'] ); ?>"
         data-button-color="<?php echo esc_attr( $btn_color ); ?>"
         data-redirect-url="<?php echo esc_attr($redirect_destination); ?>"
         data-has-custom-redirect="<?php echo !empty($target_url) ? 'true' : 'false'; ?>"
         data-force-persian="<?php echo esc_attr($force_persian_attr); ?>"
         <?php 
         // (جدید) اضافه کردن لیست فیلدهای ناقص به عنوان اتریبیوت دیتا
         if ( !empty($missing_fields_list) ) {
             echo " data-missing-fields='" . esc_attr( json_encode($missing_fields_list) ) . "'";
         }
         ?>
         >
         
        <?php if ( $style !== 'button_only' && ! $is_pending_details ) : ?>
            <div class="jay-locked-content-preview">
                <?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $content ), 40, '...' ) ); ?>
            </div>
        <?php endif; ?>

        <div class="jay-lock-overlay">
            <?php if ( $is_pending_details ) : ?>
                <div class="jay-inline-spinner"></div>
            <?php else : ?>
                <?php if ( $style !== 'button_only' ) : ?>
                    <span class="dashicons dashicons-lock jay-lock-overlay-icon"></span>
                    <h4><?php echo esc_html( $attributes['title'] ); ?></h4>
                    <p><?php echo esc_html( $attributes['message'] ); ?></p>
                <?php endif; ?>
                
               <a href="#" class="jay-lock-button" 
                  data-mode="<?php echo esc_attr( $mode ); ?>" 
                  data-redirect-url="<?php echo esc_attr($redirect_destination); ?>"
                  data-has-custom-redirect="<?php echo !empty($target_url) ? 'true' : 'false'; ?>"
                  style="<?php echo $btn_color ? 'background-color:' . esc_attr( $btn_color ) . ' !important;' : ''; ?>">
                <?php echo esc_html( $attributes['button_text'] ); ?>
               </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
return Jay_Login_Register_Minifier::html( ob_get_clean() );
    
}
