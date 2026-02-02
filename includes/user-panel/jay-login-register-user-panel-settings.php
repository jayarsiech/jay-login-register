<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ثبت تنظیمات (Settings API)
 */
add_action( 'admin_init', 'jay_login_register_user_panel_settings_init' );
function jay_login_register_user_panel_settings_init() {
    // ثبت گروه تنظیمات جدید با پیشوند صحیح
    register_setting( 
        'jay_login_register_user_panel_group', 
        'jay_login_register_user_panel_settings', // نام آپشن در دیتابیس
        'jay_login_register_user_panel_sanitize_settings' 
    );

    // --- بخش ۱: تنظیمات عمومی ---
    add_settings_section(
        'jay_login_register_up_general_section',
        'تنظیمات عمومی پنل',
        'jay_login_register_user_panel_general_section_callback',
        'jay_login_register_user_panel_general_tab'
    );
    // --- تنظیمات آواتار ---
    add_settings_field(
        'custom_avatar_meta_key',
        'متاکی (شناسه) آواتار',
        'jay_login_register_user_panel_textfield_render', // یک تابع رندر متنی ساده نیاز داریم
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'name' => 'custom_avatar_meta_key', 
            'default' => 'jay_login_register_custom_avatar',
            'desc' => '<span style="color:#d63638;">هشدار: تغییر این فیلد باعث می‌شود تصاویر پروفایل کاربرانی که قبلاً عکس آپلود کرده‌اند نمایش داده نشود. فقط در صورت نیاز تغییر دهید.</span>'
        ]
    );
    // --- منوهای سفارشی ---
    add_settings_field(
        'enable_custom_menus',
        'منوهای اضافه',
        'jay_login_register_user_panel_checkbox_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        ['name' => 'enable_custom_menus', 'label' => 'فعال‌سازی منوهای سفارشی در پنل']
    );

    add_settings_field(
        'custom_menus_json',
        '',
        'jay_login_register_user_panel_menus_builder_callback', // تابع جدید
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'label_for' => 'custom_menus_json',
            'class' => 'jay-custom-menus-sub' // کلاس برای مدیریت نمایش شرطی (اختیاری)
        ]
    );
// 1. اطلاعات تکمیلی (سرفصل اصلی)
    add_settings_field(
        'enable_profile_info',
        'ویرایش مشخصات',
        'jay_login_register_user_panel_checkbox_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        ['name' => 'enable_profile_info', 'label' => 'فعال‌سازی تب ویرایش مشخصات (نام، کد ملی و...)']
    );

    // 1-1. نام کاربری
    add_settings_field(
        'enable_username_edit',
        '— تغییر نام کاربری',
        'jay_login_register_user_panel_checkbox_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        ['name' => 'enable_username_edit', 'label' => 'اجازه تغییر نام کاربری به کاربر', 'class' => 'jay-profile-sub']
    );

    // 1-2. نام و نام خانوادگی
    add_settings_field(
        'enable_name_edit',
        '— نام و نام خانوادگی',
        'jay_login_register_user_panel_switch_group_render', // استفاده از تابع رندر جدید
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'name' => 'enable_name_edit', 
            'label' => 'دریافت نام و نام خانوادگی',
            'class' => 'jay-profile-sub', // کلاس برای مدیریت نمایش با JS (وقتی پروفایل تیک خورد این بیاید)
            'sub_toggles' => [
                [ 'name' => 'force_persian_name', 'label' => 'فقط فارسی' ],
                [ 'name' => 'required_name_edit', 'label' => 'ضروری' ]
            ]
        ]
    );

// 1-3. کد ملی (با زیرمجموعه ضروری)
    add_settings_field(
        'enable_national_code_edit',
        '— کد ملی',
        'jay_login_register_user_panel_switch_group_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'name' => 'enable_national_code_edit', 
            'label' => 'دریافت کد ملی',
            'class' => 'jay-profile-sub',
            'sub_toggles' => [
                [ 'name' => 'required_national_code_edit', 'label' => 'ضروری' ]
            ]
        ]
    );

// 1-4. گذرنامه (با زیرمجموعه ضروری)
    add_settings_field(
        'enable_passport_edit',
        '— گذرنامه',
        'jay_login_register_user_panel_switch_group_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'name' => 'enable_passport_edit', 
            'label' => 'دریافت شماره گذرنامه',
            'class' => 'jay-profile-sub',
            'sub_toggles' => [
                [ 'name' => 'required_passport_edit', 'label' => 'ضروری' ]
            ]
        ]
    );
    // 1-5. فیلد ساز پروفایل (زیرمجموعه ویرایش مشخصات)
    add_settings_field(
        'profile_custom_fields_json',
        'فیلدهای دلخواه پروفایل',
        'jay_login_register_user_panel_fields_builder_callback', // تابع کالبک
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'label_for' => 'profile_custom_fields_json',
            'class' => 'jay-profile-sub' // <--- مهم: برای آکاردیونی بودن
        ]
    );
    add_settings_field(
        'enable_mobile_change',
        'تغییر / ثبت شماره موبایل',
        'jay_login_register_user_panel_checkbox_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        ['name' => 'enable_mobile_change', 'label' => 'فعال‌سازی تب تغییر شماره موبایل']
    );

    add_settings_field(
        'enable_email_change',
        'تغییر / ثبت ایمیل',
        'jay_login_register_user_panel_checkbox_render',
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        ['name' => 'enable_email_change', 'label' => 'فعال‌سازی تب تغییر ایمیل']
    );

add_settings_field(
        'enable_password_change',
        'تغییر رمز عبور',
        'jay_login_register_user_panel_switch_group_render', // تابع جدید
        'jay_login_register_user_panel_general_tab',
        'jay_login_register_up_general_section',
        [
            'name' => 'enable_password_change', 
            'label' => 'فعال‌سازی تب تغییر رمز عبور',
            'sub_toggles' => [
                [ 'name' => 'enable_strong_password', 'label' => 'الزام به رمز عبور قوی' ]
            ]
        ]
    );

    // --- بخش ۲: تنظیمات استایل ---
    add_settings_section(
        'jay_login_register_up_style_section',
        'تنظیمات ظاهری پنل',
        'jay_login_register_user_panel_style_section_callback',
        'jay_login_register_user_panel_style_tab'
    );
}

/**
 * تابع پاک‌سازی و ذخیره تنظیمات
 */
function jay_login_register_user_panel_sanitize_settings( $input ) {
    $clean_input = [];
    
// --- 1. ذخیره منوهای سفارشی ---
    $clean_input['enable_custom_menus'] = isset($input['enable_custom_menus']) ? 'yes' : 'no';
    
    if ( ! empty( $input['custom_menus_json'] ) ) {
        $menus_raw = wp_unslash( $input['custom_menus_json'] );
        // تست دیکد کردن برای اطمینان از سالم بودن JSON
        $menus = json_decode( $menus_raw, true );
        
        if ( is_array( $menus ) ) {
             // ذخیره به صورت JSON یونیکد (برای پشتیبانی از فارسی)
             $clean_input['custom_menus_json'] = json_encode( $menus, JSON_UNESCAPED_UNICODE );
        } else {
             $clean_input['custom_menus_json'] = '[]';
        }
    } else {
        $clean_input['custom_menus_json'] = '[]';
    }
    
   if ( isset( $input['profile_custom_fields_json'] ) ) {
        // دیکد کردن جیسون برای تمیزکاری تک تک فیلدها
        $json_raw = wp_unslash($input['profile_custom_fields_json']);
        $fields = json_decode($json_raw, true);
        
        if (is_array($fields)) {
            foreach ($fields as &$field) {
                // تمیزکاری توضیحات (فقط متن ساده)
                if (isset($field['description'])) {
                    $field['description'] = sanitize_text_field($field['description']);
                } else {
                    $field['description'] = '';
                }
            }
            // انکد مجدد به جیسون (با حفظ کاراکترهای یونیکد فارسی)
            $clean_input['profile_custom_fields_json'] = json_encode($fields, JSON_UNESCAPED_UNICODE);
        } else {
            $clean_input['profile_custom_fields_json'] = '[]';
        }
    }
    $clean_input['enable_profile_info']     = isset($input['enable_profile_info']) ? 'yes' : 'no';
    $clean_input['enable_username_edit']    = isset($input['enable_username_edit']) ? 'yes' : 'no';
    $clean_input['enable_name_edit']        = isset($input['enable_name_edit']) ? 'yes' : 'no';
    $clean_input['force_persian_name']      = isset($input['force_persian_name']) ? 'yes' : 'no';
    $clean_input['required_name_edit']        = isset($input['required_name_edit']) ? 'yes' : 'no'; // جدید
    $clean_input['custom_avatar_meta_key'] = isset($input['custom_avatar_meta_key']) ? sanitize_key($input['custom_avatar_meta_key']) : 'jay_login_register_custom_avatar';
    $clean_input['enable_national_code_edit'] = isset($input['enable_national_code_edit']) ? 'yes' : 'no';
    $clean_input['required_national_code_edit'] = isset($input['required_national_code_edit']) ? 'yes' : 'no'; // جدید
    $clean_input['enable_passport_edit']    = isset($input['enable_passport_edit']) ? 'yes' : 'no';
    $clean_input['required_passport_edit']    = isset($input['required_passport_edit']) ? 'yes' : 'no'; // جدید
    $clean_input['enable_mobile_change']   = isset($input['enable_mobile_change']) ? 'yes' : 'no';
    $clean_input['enable_email_change']    = isset($input['enable_email_change']) ? 'yes' : 'no';
    $clean_input['enable_password_change'] = isset($input['enable_password_change']) ? 'yes' : 'no';
    $clean_input['enable_strong_password'] = isset($input['enable_strong_password']) ? 'yes' : 'no';

    return $clean_input;
}

/**
 * نمایش محتوای صفحه تنظیمات پنل کاربری
 */
function jay_login_register_user_panel_settings_page_html() {
    if ( ! current_user_can( 'jay_login_register_manage_settings' ) ) {
        return;
    }

   $active_tab = 'general';
    // بررسی نانس برای امنیت تب‌ها
    if ( isset( $_GET['tab'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'jay_user_panel_tabs_nonce' ) ) {
        $active_tab = sanitize_key( $_GET['tab'] );
    }
    
    // ساخت لینک‌های تب با نانس امنیتی
    $general_url = wp_nonce_url( admin_url( 'admin.php?page=jay_login_register_user_panel&tab=general' ), 'jay_user_panel_tabs_nonce' );
    $style_url   = wp_nonce_url( admin_url( 'admin.php?page=jay_login_register_user_panel&tab=style' ), 'jay_user_panel_tabs_nonce' );
    ?>
    <div class="wrap jay-login-register-wrap">
        <h1>تنظیمات پنل کاربری پیشرفته</h1>
        
        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url( $general_url ); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">عمومی</a>
            <a href="<?php echo esc_url( $style_url ); ?>" class="nav-tab <?php echo $active_tab === 'style' ? 'nav-tab-active' : ''; ?>">استایل</a>
        </nav>

        <form action="options.php" method="post">
            <?php
            settings_fields( 'jay_login_register_user_panel_group' );

            if ( $active_tab === 'general' ) {
                do_settings_sections( 'jay_login_register_user_panel_general_tab' );
            } elseif ( $active_tab === 'style' ) {
                do_settings_sections( 'jay_login_register_user_panel_style_tab' );
            }

            submit_button( 'ذخیره تنظیمات پنل' );
            ?>
        </form>
    </div>
    <?php
}

/**
 * کال‌بک توضیحات بخش عمومی
 */
function jay_login_register_user_panel_general_section_callback() {
    echo '<p>در این بخش مشخص کنید کاربران به چه امکاناتی در پنل کاربری خود دسترسی داشته باشند.</p>';
}

/**
 * کال‌بک توضیحات بخش استایل
 */
function jay_login_register_user_panel_style_section_callback() {
    echo '<p>شخصی‌سازی ظاهر پنل کاربری (تنظیمات در مراحل بعدی اضافه می‌شود).</p>';
}


/**
 * تابع رندر چک‌باکس‌ها با شرط بررسی تنظیمات اصلی
 */

function jay_login_register_user_panel_checkbox_render( $args ) {
    // تنظیمات ذخیره شده پنل کاربری
    $options = get_option( 'jay_login_register_user_panel_settings' );
    
    // تنظیمات اصلی افزونه (برای بررسی وابستگی‌ها)
    $main_settings = get_option( 'jay_login_register_settings' );
    $login_methods = $main_settings['login_methods'] ?? ['mobile']; // آرایه متدهای فعال

    $name    = $args['name'];
    $label   = $args['label'];
    $value   = isset( $options[ $name ] ) && $options[ $name ] === 'yes';

    // متغیرهای وضعیت غیرفعال بودن
    $is_disabled = false;
    $disable_msg = '';

    // ۱. بررسی شرط موبایل
    if ( $name === 'enable_mobile_change' ) {
        if ( ! in_array( 'mobile', $login_methods, true ) ) {
            $is_disabled = true;
            $disable_msg = ' (غیرفعال: ورود با موبایل در تنظیمات اصلی خاموش است)';
            $value = false;
        }
    }

    // ۲. بررسی شرط ایمیل
    if ( $name === 'enable_email_change' ) {
        if ( ! in_array( 'email', $login_methods, true ) ) {
            $is_disabled = true;
            $disable_msg = ' (غیرفعال: ورود با ایمیل در تنظیمات اصلی خاموش است)';
            $value = false;
        }
    }

    // --- ساخت لینک صحیح با نانس امنیتی ---
    // این نانس باید دقیقاً همانی باشد که در فایل settings.php چک می‌شود (jay_relog_main_settings_tabs_nonce)
    $general_settings_url = wp_nonce_url( 
        admin_url( 'admin.php?page=jay_login_register_settings_page&tab=general_settings' ), 
        'jay_relog_main_settings_tabs_nonce' 
    );

    ?>
    <label>
        <input type="checkbox" 
               name="jay_login_register_user_panel_settings[<?php echo esc_attr( $name ); ?>]" 
               value="yes" 
               <?php checked( $value ); ?>
               <?php disabled( $is_disabled ); ?>>
        
        <span class="<?php echo $is_disabled ? 'description' : ''; ?>" style="<?php echo $is_disabled ? 'color: #a00;' : ''; ?>">
            <?php echo esc_html( $label . $disable_msg ); ?>
        </span>
    </label>
    
    <?php if ( $is_disabled ) : ?>
        <p class="description" style="font-size: 11px; margin-top: 5px; color: #a00;">
            توجه: برای فعال‌سازی این گزینه، ابتدا باید قابلیت مربوطه را در <a href="<?php echo esc_url( $general_settings_url ); ?>">تنظیمات عمومی</a> فعال کنید.
        </p>
    <?php endif; ?>
    <?php
}
/**
 * بارگذاری اسکریپت‌های جاوااسکریپت مخصوص صفحه تنظیمات پنل کاربری در ادمین
 */
add_action( 'admin_enqueue_scripts', 'jay_login_register_user_panel_admin_assets' );
function jay_login_register_user_panel_admin_assets( $hook ) {
    // فقط اگر در صفحه تنظیمات پنل کاربری هستیم فایل را لود کن
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset($_GET['page']) && $_GET['page'] === 'jay_login_register_user_panel' ) {
        wp_enqueue_editor(); 
        wp_enqueue_media();
        
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script(
            'jay-login-register-user-panel-admin-js', 
            JAY_LOGIN_REGISTER_URL . 'includes/user-panel/assets/js/jay-login-register-admin-user-panel.js', // مسیر فایل
            ['jquery'], 
            filemtime( JAY_LOGIN_REGISTER_PATH . 'includes/user-panel/assets/js/jay-login-register-admin-user-panel.js' ), 
            true 
        );
    }
}

/**
 * کالبک فیلد ساز اختصاصی پنل کاربری (HTML در JS تولید می‌شود)
 */
function jay_login_register_user_panel_fields_builder_callback() {
    $options = get_option('jay_login_register_user_panel_settings');
    $json_value = isset($options['profile_custom_fields_json']) ? $options['profile_custom_fields_json'] : '[]';
    
    // اطمینان از اینکه JSON معتبر است
    if ( empty($json_value) || !is_string($json_value) ) {
        $json_value = '[]';
    }
    ?>
    <div id="jay_login_register_up_builder_wrapper">
        
        <div id="jay_login_register_up_fields_list" class="jay-login-register-fields-builder-user-panel"></div>
        
        <button type="button" id="jay_login_register_up_add_btn" class="button button-secondary" style="margin-top:10px;">
            <span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span> افزودن فیلد جدید
        </button>
        
        <textarea name="jay_login_register_user_panel_settings[profile_custom_fields_json]" id="jay_login_register_up_json_input" style="display:none;"><?php echo esc_textarea($json_value); ?></textarea>
        
        <p class="description">فیلدها را اضافه کنید. برای فیلدهای انتخابی (Select, Radio, Checkbox) می‌توانید گزینه تعریف کنید.</p>
    </div>
    <?php
}
function jay_login_register_user_panel_textfield_render( $args ) {
    $options = get_option( 'jay_login_register_user_panel_settings' );
    $name    = $args['name'];
    $default = isset($args['default']) ? $args['default'] : '';
    $value   = isset( $options[ $name ] ) ? $options[ $name ] : $default;
    $desc    = isset($args['desc']) ? $args['desc'] : '';

    echo '<input type="text" name="jay_login_register_user_panel_settings[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" class="regular-text ltr">';
    if ( ! empty( $desc ) ) {
        echo '<p class="description">' . $desc . '</p>'; // phpcs:ignore
    }
}
/**
 * تابع جدید: رندر چک‌باکس اصلی به همراه سوییچ‌های زیرمجموعه (Inline)
 */
function jay_login_register_user_panel_switch_group_render( $args ) {
    $options = get_option( 'jay_login_register_user_panel_settings' );
    $main_name = $args['name'];
    $main_label = $args['label'];
    $main_val = isset( $options[ $main_name ] ) && $options[ $main_name ] === 'yes';
    
    // آرایه زیرمجموعه‌ها
    $sub_toggles = isset($args['sub_toggles']) ? $args['sub_toggles'] : [];

    ?>
    <div class="jay-setting-wrapper">
        <label class="jay-main-checkbox-label">
            <input type="checkbox" class="jay-main-trigger" 
                   name="jay_login_register_user_panel_settings[<?php echo esc_attr( $main_name ); ?>]" 
                   value="yes" 
                   <?php checked( $main_val ); ?>>
            <?php echo esc_html( $main_label ); ?>
        </label>

        <?php if ( ! empty($sub_toggles) ) : ?>
            <div class="jay-inline-subs <?php echo $main_val ? 'active' : ''; ?>">
                <?php foreach ( $sub_toggles as $sub ) : 
                    $sub_name = $sub['name'];
                    $sub_label = $sub['label'];
                    $sub_val = isset( $options[ $sub_name ] ) && $options[ $sub_name ] === 'yes';
                ?>
                    <label class="jay-toggle-switch">
                        <input type="checkbox" 
                               name="jay_login_register_user_panel_settings[<?php echo esc_attr( $sub_name ); ?>]" 
                               value="yes" 
                               <?php checked( $sub_val ); ?>>
                        <span class="jay-toggle-slider"></span>
                        <?php echo esc_html( $sub_label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
/**
 * کالبک بیلدر منوهای سفارشی
 */
function jay_login_register_user_panel_menus_builder_callback() {
    $options = get_option('jay_login_register_user_panel_settings');
    $is_enabled = isset($options['enable_custom_menus']) && $options['enable_custom_menus'] === 'yes';
    $json_value = isset($options['custom_menus_json']) ? $options['custom_menus_json'] : '[]';
    
    if ( empty($json_value) || !is_string($json_value) ) $json_value = '[]';
    
    ?>
    <div id="jay_up_menus_wrapper" style="<?php echo $is_enabled ? '' : 'display:none;'; ?>">
        <div id="jay_up_menus_list" class="jay-login-register-fields-builder-user-panel"></div>
        
        <button type="button" id="jay_up_add_menu_btn" class="button button-secondary" style="margin-top:10px;">
            <span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span> افزودن آیتم منو
        </button>
        
        <textarea name="jay_login_register_user_panel_settings[custom_menus_json]" id="jay_up_menus_json_input" style="display:none;"><?php echo esc_textarea($json_value); ?></textarea>
        
        <p class="description">آیتم‌های منو را اضافه کنید. می‌توانید لینک مستقیم یا محتوای داخلی (شورت‌کد/متن) تعریف کنید.</p>
    </div>
    <?php
}
