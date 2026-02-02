<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاس فشرده‌سازی هوشمند HTML
 * نسخه ساده و امن برای جلوگیری از خطاهای Regex
 */
class Jay_Login_Register_Minifier {

    /**
     * فشرده‌سازی رشته HTML
     *
     * @param string $html محتوای HTML ورودی.
     * @return string محتوای فشرده شده.
     */
    public static function html( $html ) {
        if ( empty( $html ) ) {
            return $html;
        }

        // حذف فاصله‌های خالی، تب‌ها و خط‌های جدید بین تگ‌های HTML
        // تبدیل ( >   < ) به ( >< )
        // از علامت ~ به عنوان جداکننده استفاده می‌کنیم که با HTML تداخل ندارد
        $html = preg_replace( '~>\s+<~', '><', $html );

        return trim( $html );
    }
}
