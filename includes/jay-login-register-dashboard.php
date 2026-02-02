<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ثبت ویجت در صفحه پیشخوان وردپرس
 */
add_action( 'wp_dashboard_setup', 'jay_login_register_add_dashboard_widget' );
function jay_login_register_add_dashboard_widget() {
    // ویجت را در ستون 'normal' ثبت می‌کنیم. JS آن را به بالا منتقل می‌کند.
    wp_add_dashboard_widget(
        'jay_relog_dashboard_widget',
        'افزونه JAY Login & Register',
        'jay_login_register_dashboard_widget_callback',
        null,
        null,
        'normal', // ستون اصلی (راست)
        'high'
    );
}

/**
 * تابع نمایش محتوای ویجت پیشخوان (شبیه‌سازی شده از پارسی‌دیت)
 */
function jay_login_register_dashboard_widget_callback() {
    ?>
    <div class="jay-relog-dashboard-widget"> <div class="navigation-wrapper">
            
            <div id="jay-relog-sponsor-slider" class="keen-slider">
                <div class="keen-slider__slide" style="background-color: #f0f0f1; min-height: 200px;"></div>
            </div>

            <div class="arrow arrow--left" id="jay-relog-slider-arrow-left"></div>
            <div class="arrow arrow--right" id="jay-relog-slider-arrow-right"></div>

        </div> <div class="dots" id="jay-relog-slider-dots">
            </div>
        
        <div id="sponsorship-guide">
            <div class="question">
                <span class="dashicons dashicons-info-outline"></span>
                <span>این چیست؟</span>
            </div>
            <ul>
                <li>
                    <a href="https://jayarsiech.ir/contact" target="_blank">
                        <span class="dashicons dashicons-external"></span>&nbsp;چرا این را به من نشان می‌دهید؟
                    </a>
                </li>
                <li>
                    <a href="https://jayarsiech.ir/contact" target="_blank">
                        <span class="dashicons dashicons-external"></span>&nbsp;چگونه می‌توانم اسپانسر شوم؟
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="wordpress-news hide-if-no-js">
            <div class="rss-widget">
                <ul>
                <?php 
                $news_items = jay_login_register_get_static_news_feed();
                foreach ( $news_items as $item ) : ?>
                    <li>
                        <a class="rsswidget" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank">
                            <?php echo esc_html( $item['title'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <p class="community-events-footer">
            <a href="https://instagram.com/jayarsiech" target="_blank">
                پشتیبانی <span class="dashicons dashicons-external"></span>
            </a>
            |
            <a href="https://jayarsiech.ir" target="_blank">
                وبسایت ما <span class="dashicons dashicons-external"></span>
            </a>
        </p>
    </div>
    <?php
}

/**
 * (جدید) داده‌های ثابت اسپانسرها (برای ارسال به JS)
 */
function jay_login_register_get_static_sponsors_data() {
    $ad_image_1 = JAY_LOGIN_REGISTER_URL . 'assets/images/jayrelog-sponser.jpg';
    $ad_image_2 = JAY_LOGIN_REGISTER_URL . 'assets/images/jayrelog.jpg'; 

    return [
        [
            'link'      => 'https://jayarsiech.ir/contact',
            'image_url' => $ad_image_1,
            'image_alt' => 'جایگاه تبلیغات شما'
        ],
        [
            'link'      => 'https://jayarsiech.ir/contact',
            'image_url' => $ad_image_2,
            'image_alt' => 'اسپانسر افزونه شوید'
        ],
    ];
}

/**
 * (جدید) داده‌های ثابت فید اخبار
 */
function jay_login_register_get_static_news_feed() {
    $email_settings_url = wp_nonce_url(
        admin_url( 'admin.php?page=jay_login_register_settings_page&tab=email_settings' ),
        'jay_relog_main_settings_tabs_nonce' 
    );
    
    return [
        [
            'url'   => admin_url( 'admin.php?page=jay_login_register_instructions' ),
            'title' => 'آموزش کار با افزونه و شورت‌کدها'
        ],
        [
            'url'   => admin_url( 'admin.php?page=jay_login_register_user_panel' ),
            'title' => 'اضافه شدن پنل کاربری با فرم ساز های قوی'
        ],
        [
            'url'   => $email_settings_url, 
            'title' => 'ارسال ایمیل و SMTP'
        ],
    ];
}

/**
 * (جدید) بارگذاری CSS و JS و ارسال داده‌ها به JS
 */
add_action( 'admin_enqueue_scripts', 'jay_login_register_enqueue_dashboard_assets' );
function jay_login_register_enqueue_dashboard_assets( $hook ) {
    if ( 'index.php' !== $hook ) {
        return;
    }
    
    // ۱. بارگذاری CSS اصلی keen-slider
    wp_enqueue_style(
        'keen-slider-css',
        JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-keen-slider.min.css',
        [],
        JAY_LOGIN_REGISTER_VERSION // فرض می‌کنیم فایل‌ها را در پوشه افزونه قرار داده‌اید
    );

    // ۲. بارگذاری CSS سفارشی ویجت ما
    $css_version = filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/css/jay-login-register-dashboard.css' );
    wp_enqueue_style(
        'jay-login-register-dashboard-style',
        JAY_LOGIN_REGISTER_URL . 'assets/css/jay-login-register-dashboard.css',
        ['keen-slider-css'], // وابسته به CSS اصلی
        $css_version
    );

    // ۳. بارگذاری JS اصلی keen-slider
    wp_enqueue_script(
        'keen-slider-js',
        JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-keen-slider.min.js',
        [], 
        JAY_LOGIN_REGISTER_VERSION,
        true
    );

    // ۴. بارگذاری JS سفارشی ما
    $js_version = filemtime( JAY_LOGIN_REGISTER_PATH . 'assets/js/jay-login-register-dashboard.js' );
    wp_enqueue_script(
        'jay-login-register-dashboard-script',
        JAY_LOGIN_REGISTER_URL . 'assets/js/jay-login-register-dashboard.js',
        [ 'jquery', 'keen-slider-js' ], // وابسته به JS اصلی
        $js_version,
        true
    );

    // ۵. ارسال داده‌های اسپانسرها به JS
    wp_localize_script(
        'jay-login-register-dashboard-script', // به اسکریپت خودمان متصل می‌کنیم
        'jayRelogDashboard', // نام آبجکت در JS
        [
            'sponsors' => jay_login_register_get_static_sponsors_data(), // داده‌های اسپانسر
            'is_rtl'   => is_rtl() // ارسال وضعیت RTL
        ]
    );
}
