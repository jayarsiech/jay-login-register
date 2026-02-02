<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاس مدیریت آواتار محلی
 */
class Jay_Login_Register_Avatar_Handler {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // فیلتر اصلی جایگزینی آواتار
        add_filter( 'pre_get_avatar', [ $this, 'jay_login_register_override_avatar' ], 10, 3 );
    }

    /**
     * تابع جایگزینی آواتار وردپرس با آواتار آپلودی کاربر
     */
    public function jay_login_register_override_avatar( $avatar, $id_or_email, $args ) {
        $user_id = 0;

        // 1. استخراج شناسه کاربر
        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) ) {
            $user_id = $user->ID;
        } elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
            $user_id = (int) $id_or_email->user_id;
        }

        if ( ! $user_id ) {
            return $avatar;
        }

        // 2. دریافت متاکی از تنظیمات (با کشینگ برای پرفرمنس)
        $meta_key = $this->jay_login_register_get_avatar_meta_key();

        // 3. دریافت ID عکس از متای کاربر
        $avatar_id = get_user_meta( $user_id, $meta_key, true );

        if ( ! $avatar_id ) {
            return $avatar; // اگر عکسی آپلود نکرده، همان گراواتار پیش‌فرض را برگردان
        }

        // 4. دریافت آدرس عکس آپلود شده
        $img_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' ); // سایز thumbnail معمولا مربعی و سبک است

        if ( ! $img_url ) {
            return $avatar;
        }

        // 5. ساخت تگ img جایگزین
        $class = [ 'avatar', 'avatar-' . (int) $args['size'], 'photo', 'jay-custom-avatar' ];
        if ( $args['class'] ) {
            if ( is_array( $args['class'] ) ) {
                $class = array_merge( $class, $args['class'] );
            } else {
                $class[] = $args['class'];
            }
        }

        $avatar = sprintf(
            '<img alt="%s" src="%s" class="%s" height="%d" width="%d" %s/>',
            esc_attr( $args['alt'] ),
            esc_url( $img_url ),
            esc_attr( implode( ' ', $class ) ),
            (int) $args['height'],
            (int) $args['width'],
            $args['extra_attr']
        );

        return $avatar;
    }
 
    /**
     * دریافت متاکی تنظیم شده توسط ادمین
     */
    public function jay_login_register_get_avatar_meta_key() {
        $settings = get_option( 'jay_login_register_user_panel_settings', [] );
        // پیش‌فرض: jay_login_register_custom_avatar
        return ! empty( $settings['custom_avatar_meta_key'] ) ? $settings['custom_avatar_meta_key'] : 'jay_login_register_custom_avatar';
    }
}

// راه‌اندازی کلاس
Jay_Login_Register_Avatar_Handler::get_instance();
