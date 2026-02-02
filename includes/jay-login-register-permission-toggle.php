<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. هندلر ایجکس امن برای تغییر وضعیت
 */
add_action( 'wp_ajax_jay_toggle_edit_access', 'jay_ajax_toggle_edit_access' );
function jay_ajax_toggle_edit_access() {
    check_ajax_referer( 'jay_permission_nonce_action', 'nonce' );

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $state   = isset($_POST['state']) ? intval($_POST['state']) : 0;

    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        wp_send_json_error(['message' => 'شما اجازه ویرایش این کاربر را ندارید.']);
    }
    
    if ( $user_id === get_current_user_id() && $state === 0 ) {
         wp_send_json_error(['message' => 'شما نمی‌توانید دسترسی خودتان را ببندید.']);
    }

    update_user_meta( $user_id, 'jay_can_edit_profile', $state );
    do_action( 'jay_login_register_after_toggle_edit_access', $user_id, $state );
    
    wp_send_json_success(['message' => 'وضعیت تغییر کرد.']);
}

/**
 * 2. تابع کمکی برای لود کردن اسکریپت‌ها (مشترک)
 * این تابع را هر جا صدا بزنیم، فایل‌ها را لود می‌کند.
 */
function jay_enqueue_permission_assets() {
    wp_enqueue_style(
        'jay-login-register-permission-toggle-style',
        JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-permission-toggle.css',
        [],
        defined('JAY_LOGIN_REGISTER_VERSION') ? JAY_LOGIN_REGISTER_VERSION : '1.0.0'
    );

    wp_enqueue_script(
        'jay-login-register-permission-toggle-script',
        JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-permission-toggle.js',
        [ 'jquery' ],
        defined('JAY_LOGIN_REGISTER_VERSION') ? JAY_LOGIN_REGISTER_VERSION : '1.0.0',
        true
    );
    
    // اصلاح نام متغیر به jayPermissionObj (بدون خط تیره برای جلوگیری از ارور JS)
    wp_localize_script(
        'jay-login-register-permission-toggle-script', 
        'jayPermissionObj', 
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('jay_permission_nonce_action'), 
        ]
    );
}

/**
 * 3. لود کردن خودکار اسکریپت‌ها در صفحه لیست کاربران (Admin)
 */
add_action( 'admin_enqueue_scripts', 'jay_load_permission_assets_on_users_page' );
function jay_load_permission_assets_on_users_page( $hook ) {
    // فقط در صفحه users.php اجرا شود
    if ( 'users.php' === $hook ) {
        jay_enqueue_permission_assets();
    }
}

/**
 * 4. تابع ساخت HTML سوییچ
 */
function jay_get_edit_toggle_html( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return '<span style="color:#ccc; font-size:11px;">غیرقابل ویرایش</span>';
    }

    $can_edit = get_user_meta( $user_id, 'jay_can_edit_profile', true );
    $checked = ( $can_edit === '' || $can_edit === '1' ) ? 'checked' : '';

    return '<label class="jay-switch" style="vertical-align: middle;">
        <input type="checkbox" class="jay-user-edit-toggle" data-user-id="' . esc_attr($user_id) . '" ' . $checked . '>
        <span class="jay-slider round"></span>
    </label>';
}

/**
 * 5. هوک هوشمند نمایشی (برای استفاده در فرانت‌اند یا جاهای دیگر)
 */
add_action( 'jay_render_user_edit_toggle', 'jay_print_edit_toggle_hook' );
function jay_print_edit_toggle_hook( $user_id ) {
    // لود کردن فایل‌ها در لحظه (اگر هنوز لود نشده‌اند)
    jay_enqueue_permission_assets();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo jay_get_edit_toggle_html( $user_id );
}

// ================================================================
// بخش‌های زیر فقط مخصوص پیشخوان وردپرس هستند و در فرانت‌اند لود نمی‌شوند
// ================================================================
if ( is_admin() ) {

    /**
     * 6. اضافه کردن لینک‌های فیلتر (مجوز ویرایش) به بالای لیست کاربران
     */
    add_filter( 'views_users', 'jay_login_register_add_permission_views' );
    function jay_login_register_add_permission_views( $views ) {
        
        // دریافت تعداد کاربرانی که مسدود شده‌اند (متاکی 0 دارند)
        $args_blocked = array(
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_key'   => 'jay_can_edit_profile',
            'meta_value' => '0',
            // phpcs:enable
            'fields'     => 'ID',
            'number'     => 1, // فقط تعداد مهم است (بهینه برای سرعت)
            'count_total'=> true
        );
        $blocked_query = new WP_User_Query( $args_blocked );
        $blocked_count = $blocked_query->get_total();

        // محاسبه تعداد کل کاربران
        $result_counts = count_users();
        $total_users = $result_counts['total_users'];

        // تعداد مجازها = کل کاربران - مسدود شده‌ها
        $allowed_count = $total_users - $blocked_count;

        // تشخیص وضعیت فعلی لینک
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current = isset( $_GET['jay_perm_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['jay_perm_filter'] ) ) : '';

        // لینک "مجوز ویرایش دارد"
        $class_allowed = ( 'allowed' === $current ) ? 'current' : '';
        $url_allowed = add_query_arg( 'jay_perm_filter', 'allowed', 'users.php' );
        $views['jay_allowed'] = sprintf(
            '<a href="%s" class="%s">مجوز ویرایش دارد <span class="count">(%d)</span></a>',
            esc_url( $url_allowed ),
            esc_attr( $class_allowed ),
            $allowed_count
        );

        // لینک "مجوز ویرایش ندارد"
        $class_blocked = ( 'blocked' === $current ) ? 'current' : '';
        $url_blocked = add_query_arg( 'jay_perm_filter', 'blocked', 'users.php' );
        $views['jay_blocked'] = sprintf(
            '<a href="%s" class="%s" style="color:#d63638;">مجوز ویرایش ندارد <span class="count">(%d)</span></a>',
            esc_url( $url_blocked ),
            esc_attr( $class_blocked ),
            $blocked_count
        );

        return $views;
    }

    /**
     * 7. اعمال فیلتر روی کوئری کاربران هنگام کلیک روی لینک‌ها
     */
    add_action( 'pre_get_users', 'jay_login_register_filter_users_by_permission_query' );
    function jay_login_register_filter_users_by_permission_query( $query ) {
        global $pagenow;

        // فقط در صفحه کاربران و زمانی که فیلتر ما ست شده باشد اجرا شود
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'users.php' === $pagenow && isset( $_GET['jay_perm_filter'] ) ) {
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $filter = sanitize_text_field( wp_unslash( $_GET['jay_perm_filter'] ) );

            // اگر روی "مجوز ویرایش ندارد" کلیک شده بود
            if ( 'blocked' === $filter ) {
                $meta_query = array(
                    array(
                        'key'     => 'jay_can_edit_profile',
                        'value'   => '0',
                        'compare' => '='
                    )
                );
                $query->set( 'meta_query', $meta_query );
            }
            
            // اگر روی "مجوز ویرایش دارد" کلیک شده بود
            elseif ( 'allowed' === $filter ) {
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'jay_can_edit_profile',
                        'value'   => '1',
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'jay_can_edit_profile',
                        'compare' => 'NOT EXISTS' // کسانی که هنوز تنظیمی ندارند (پیش‌فرض مجاز)
                    )
                );
                $query->set( 'meta_query', $meta_query );
            }
        }
    }

} // پایان شرط is_admin
