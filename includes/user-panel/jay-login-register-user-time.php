<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * محاسبه مدت زمان عضویت کاربر به زبان انسان
 * * منطق:
 * - زیر ۱ ماه: نمایش روز (امروز، دیروز، x روز پیش)
 * - زیر ۱ سال: نمایش ماه و روز
 * - بالای ۱ سال: نمایش سال و ماه (روز حذف می‌شود)
 */
function jay_login_register_get_user_membership_time( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return '';

    // دریافت زمان ثبت نام و زمان حال (با منطقه زمانی UTC برای دقت بالا)
    try {
        $registered = new DateTime( $user->user_registered, new DateTimeZone('UTC') );
        $current    = new DateTime( 'now', new DateTimeZone('UTC') );
        $interval   = $registered->diff( $current );
    } catch (Exception $e) {
        return ''; // در صورت خطای تاریخ
    }

    $y = $interval->y; // سال
    $m = $interval->m; // ماه
    $d = $interval->d; // روز

    // ۱. اگر بیشتر از یک سال بود (روز را نشان نده)
    if ( $y > 0 ) {
        $text = $y . ' سال';
        if ( $m > 0 ) {
            $text .= ' و ' . $m . ' ماه';
        }
        return $text . ' پیش';
    } 
    // ۲. اگر کمتر از سال ولی بیشتر از ماه بود
    elseif ( $m > 0 ) {
        $text = $m . ' ماه';
        if ( $d > 0 ) {
            $text .= ' و ' . $d . ' روز';
        }
        return $text . ' پیش';
    } 
    // ۳. اگر کمتر از ماه بود (فقط روز)
    else {
        if ( $d == 0 ) return 'عضویت: امروز';
        if ( $d == 1 ) return 'عضویت: دیروز';
        return $d . ' روز پیش';
    }
}
/**
 * هوک برای نمایش مدت زمان عضویت (برای استفاده در قالب)
 * نحوه استفاده: do_action( 'jay_show_user_time', $user_id );
 */
add_action( 'jay_show_user_time', 'jay_login_register_output_user_time_hook', 10, 1 );
function jay_login_register_output_user_time_hook( $user_id = 0 ) {
    if ( empty( $user_id ) ) {
        $user_id = get_current_user_id();
    }
    if ( empty( $user_id ) ) {
        return;
    }
    echo '<span class="jay-user-time-wrapper">';
    echo esc_html( jay_login_register_get_user_membership_time( $user_id ) );
    echo '</span>';
}
/**
 * شورت‌کد برای استفاده در المنتور یا ادیتور متن
 * [jay_user_time]
 */
add_shortcode( 'jay_user_time', 'jay_login_register_user_time_shortcode' );
function jay_login_register_user_time_shortcode( $atts ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return '';
    return jay_login_register_get_user_membership_time( $user_id );
}
