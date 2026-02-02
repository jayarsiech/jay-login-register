<?php
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * ۱. افزودن و بازچینی ستون‌های جدول کاربران
 */
add_filter( 'manage_users_columns', 'jay_login_register_add_registration_date_column' );
function jay_login_register_add_registration_date_column( $columns ) {
    $new_columns = [];

    // کپی کردن ستون‌های پیش‌فرض تا قبل از ستون "نقش" (role) یا ایمیل
    foreach ( $columns as $key => $title ) {
        $new_columns[ $key ] = $title;
        
        // می‌خواهیم ستون‌های ما بعد از "ایمیل" یا "نقش" باشند
        if ( $key === 'email' ) {
            $new_columns['jay_mobile'] = 'شماره موبایل';
            $new_columns['registration_date'] = 'تاریخ عضویت';
            $new_columns['jay_edit_access'] = 'دسترسی ویرایش'; // <--- ستون جدید اینجا اضافه شد
        }
    }
    
    // اگر به هر دلیلی ستون‌ها اضافه نشدند (مثلا کلید email نبود)، تهش اضافه کن
    if ( ! isset( $new_columns['jay_edit_access'] ) ) {
        $new_columns['jay_mobile'] = 'شماره موبایل';
        $new_columns['registration_date'] = 'تاریخ عضویت';
        $new_columns['jay_edit_access'] = 'دسترسی ویرایش';
    }

    // اضافه کردن ستون‌های سفارشی (از تنظیمات)
    $custom_columns_options = get_option( 'jay_login_register_custom_columns_settings' );
    if ( ! empty( $custom_columns_options['columns'] ) ) {
        foreach ( $custom_columns_options['columns'] as $column ) {
            $new_columns[ $column['key'] ] = $column['name'];
        }
    }
    
    return $new_columns;
}

/**
 * ۲. نمایش محتوای ستون "تاریخ عضویت" برای هر کاربر
 */
add_action( 'manage_users_custom_column', 'jay_login_register_render_registration_date_column', 10, 3 );
function jay_login_register_render_registration_date_column( $value, $column_name, $user_id ) {
switch ( $column_name ) {
        case 'registration_date':
            $user = get_userdata( $user_id );
            return jay_login_register_to_jalali_date( $user->user_registered );
        
        case 'jay_mobile':
            $mobile = get_user_meta( $user_id, 'jay_mobile', true );
            return ! empty( $mobile ) ? '<span class="jay-login-register-ltr-text">' . esc_html( $mobile ) . '</span>' : '—';
        case 'jay_edit_access':
            // استفاده از تابع متمرکز که در مرحله ۱ ساختیم
            if ( function_exists('jay_get_edit_toggle_html') ) {
                return jay_get_edit_toggle_html( $user_id );
            }
            return '';
            
    }

// مدیریت ستون‌های سفارشی داینامیک
    $custom_columns_options = get_option( 'jay_login_register_custom_columns_settings' );
    if ( ! empty( $custom_columns_options['columns'] ) ) {
        foreach ( $custom_columns_options['columns'] as $column ) {
            if ( $column['key'] === $column_name ) {
                $meta_value = get_user_meta( $user_id, $column['key'], true );

                // اگر نوع نمایش "آیکون" انتخاب شده بود
                if ( isset( $column['display'] ) && $column['display'] === 'icon' ) {
                    return jay_login_register_get_status_icon( $meta_value );
                } 
                // در غیر این صورت (یا اگر نوع نمایش مقدار بود)
                else {
                    return empty( $meta_value ) ? '—' : esc_html( $meta_value );
                }
            }
        }
    }
    return $value;
}

/**
 * ۳. افزودن قابلیت مرتب‌سازی (Sort) به ستون جدید
 */
add_filter( 'manage_users_sortable_columns', 'jay_login_register_make_registration_date_sortable' );
function jay_login_register_make_registration_date_sortable( $columns ) {
    $columns['registration_date'] = 'registered';
    return $columns;
}

/**
 * ۴. مرتب‌سازی پیش‌فرض لیست کاربران بر اساس تاریخ ثبت‌نام
 */
add_action( 'pre_get_users', 'jay_login_register_default_sort_users_by_registration', 99 );
function jay_login_register_default_sort_users_by_registration( $query ) {
    $screen = get_current_screen();
 if ( ! is_admin() || ! is_a( $screen, 'WP_Screen' ) || 'users' !== $screen->id ) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( empty( $_GET['orderby'] ) ) {
        $query->set('orderby', 'registered');
        $query->set('order', 'DESC');
    }
}
/**
 * تابع اصلی برای تبدیل تاریخ میلادی به شمسی
 * @param string $gregorian_date_time تاریخ میلادی مثل: 2025-08-03 14:30:00
 * @return string تاریخ شمسی مثل: ۱۴۰۴/۰۵/۱۲ – ۱۴:۳۰
 */
function jay_login_register_to_jalali_date( $gregorian_date_time ) {
    $timestamp = strtotime( $gregorian_date_time );
    
    // جدا کردن تاریخ و زمان
    list($gy, $gm, $gd) = explode('-', gmdate('Y-m-d', $timestamp));
    $time = gmdate('H:i', $timestamp);

    // اجرای الگوریتم تبدیل
    list($jy, $jm, $jd) = jay_login_register_gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);

    // افزودن صفر به اول ماه و روزهای تک رقمی
    $jm = sprintf('%02d', $jm);
    $jd = sprintf('%02d', $jd);
    
    // تبدیل اعداد به فارسی
    $persian_numerals = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numerals = range(0, 9);
    
    $jalali_date = str_replace($english_numerals, $persian_numerals, "$jy/$jm/$jd");
    $jalali_time = str_replace($english_numerals, $persian_numerals, $time);

    return $jalali_date . ',' . $jalali_time;
}

/**
 * الگوریتم کمکی برای محاسبات تبدیل تاریخ
 */
function jay_login_register_gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

/**
 * استایل ستون تاریخ عضویت را به روش استاندارد اضافه می‌کند.
 */
add_action( 'admin_enqueue_scripts', 'jay_login_register_user_column_styles' );
function jay_login_register_user_column_styles( $hook ) {
    // این استایل فقط در صفحه کاربران (users.php) بارگذاری می‌شود
    if ( 'users.php' !== $hook ) {
        return;
    }

    $css = "
        .column-registration_date {
            direction: ltr;
            font-family: Tahoma, sans-serif;
            font-size: 12px;
            text-align: left !important;
        }
        .sorting-indicator {
            width: inherit;
        }
    ";

    // یک هندل استایل پایه وردپرس را برای اتصال انتخاب می‌کنیم
    wp_add_inline_style( 'list-tables', $css );
}

// این کد را به انتهای فایل jay-relog-user-columns.php اضافه کنید

/**
 * جدید: ستون‌های جدول کاربران را بر اساس تنظیمات ذخیره شده فیلتر می‌کند
 */
add_filter( 'manage_users_columns', 'jay_login_register_filter_user_columns', 20 );
function jay_login_register_filter_user_columns( $columns ) {
    $options = get_option( 'jay_login_register_user_columns_settings' );

    // اگر گزینه‌ها وجود داشت و آرایه ستون‌های پنهان خالی نبود
    if ( ! empty( $options ) && ! empty( $options['hidden_columns'] ) && is_array( $options['hidden_columns'] ) ) {
        $hidden_columns = $options['hidden_columns'];
        foreach ( $hidden_columns as $column_slug ) {
            // اگر ستون مورد نظر در لیست ستون‌ها وجود داشت، آن را حذف کن
            if ( isset( $columns[ $column_slug ] ) ) {
                unset( $columns[ $column_slug ] );
            }
        }
    }

    return $columns;
}

/**
 * جدید: آیکون وضعیت را بر اساس مقدار متا برمی‌گرداند
 */
function jay_login_register_get_status_icon( $meta_value ) {
    if ( ! empty( $meta_value ) ) {
        return '<svg class="jay-login-register-status-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg>';
    }

    return '—';
}
