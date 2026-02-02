<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Add the admin menu page
add_action( 'admin_menu', 'jay_login_register_add_admin_menu' );
function jay_login_register_add_admin_menu() {

$svg_icon_raw = '<svg width="20" height="20" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
 <text x="5" y="82" fill="white" font-family="Arial, sans-serif" font-size="85px" font-weight="bold">J</text>
 <text x="45" y="82" fill="white" font-family="Arial, sans-serif" font-size="80px" font-weight="bold">R</text>
</svg>';
 $svg_icon_encoded = 'data:image/svg+xml;base64,' . base64_encode($svg_icon_raw);

 // ۲. ساخت منوی اصلی (بدون تغییر)
 add_menu_page(
 'تنظیمات JAY Relog',
'JAY Relog',
 'jay_login_register_manage_settings',
 'jay_login_register_settings_page',
'jay_login_register_settings_page_html',
 $svg_icon_encoded,
 30
 );

// چون شناسه (slug) این زیرمنو با منوی اصلی یکی است، وردپرس زیرمنوی تکراری نمی‌سازد.
 add_submenu_page(
 'jay_login_register_settings_page',        // شناسه منوی والد
 'تنظیمات افزونه JAY Relog',   // عنوان کامل صفحه
 'تنظیمات',                    
 'jay_login_register_manage_settings',
 'jay_login_register_settings_page',        
 'jay_login_register_settings_page_html'
);
   add_submenu_page(
        'jay_login_register_settings_page',               
        'کنترل دسترسی پیشخوان',                 
        'کنترل دسترسی',                    
        'jay_login_register_manage_access_control',                         
        'jay_login_register_access_control',               
        'jay_login_register_access_control_page_html' 
    );
    add_submenu_page(
        'jay_login_register_settings_page',
        'تنظیمات پنل کاربری',
        'پنل کاربری',
        'jay_login_register_manage_settings',
        'jay_login_register_user_panel',
        'jay_login_register_user_panel_settings_page_html' // تابع نمایش در فایل user-panel-settings تعریف شده است
    );
 // ۴. ساخت زیرمنوی آموزش (بدون تغییر)
add_submenu_page(
 'jay_login_register_settings_page',
 'راهنمای افزونه JAY Relog',
 'آموزش',
 'jay_login_register_manage_settings',
 'jay_login_register_instructions',
 'jay_login_register_instructions_page_html'
 );
 add_submenu_page(
  null, 
 'شخصی‌سازی قالب',
 'شخصی‌سازی قالب',
 'jay_login_register_manage_settings',
 'jay_login_register_style_customizer',
 'jay_login_register_style_customizer_page_html'
);
 
}
// Register settings using the Settings API
add_action( 'admin_init', 'jay_login_register_settings_init' );
function jay_login_register_settings_init() {
    register_setting( 'jay_login_register_settings_group', 'jay_login_register_settings', 'jay_login_register_sanitize_settings' );
    register_setting( 'jay_login_register_access_settings_group', 'jay_login_register_access_settings', 'jay_login_register_sanitize_access_settings' );
    register_setting( 'jay_login_register_user_columns_group', 'jay_login_register_user_columns_settings', 'jay_login_register_sanitize_user_columns_settings' );
    register_setting( 'jay_login_register_custom_columns_group', 'jay_login_register_custom_columns_settings', 'jay_login_register_sanitize_custom_columns' );
    // === بخش ۱: تنظیمات پنل پیامک ===
    add_settings_section( 'jay_login_register_main_section', null, null, 'jay_login_register_main_section' );
    add_settings_field( 'sms_provider', 'سرویس‌دهنده پیامک', 'jay_login_register_sms_provider_render', 'jay_login_register_main_section', 'jay_login_register_main_section' );
    
    add_settings_field( 'ipanel_api_key', 'کلید API (iPanel)', 'jay_login_register_passwordfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'ipanel_api_key', 'wrapper_class' => 'jay-login-register-ipanel-field'] );
    add_settings_field( 'ipanel_pattern_code', 'کد پترن (iPanel)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'ipanel_pattern_code', 'wrapper_class' => 'jay-login-register-ipanel-field'] );
    add_settings_field( 'ipanel_sender_line', 'خط ارسال کننده (iPanel)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'ipanel_sender_line', 'wrapper_class' => 'jay-login-register-ipanel-field'] );
    add_settings_field('ipanel_pattern_variable','نام متغیر پترن (iPanel)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', [ 'name' => 'ipanel_pattern_variable','wrapper_class' => 'jay-login-register-ipanel-field', 'default' => 'code' ]);
    add_settings_field('farazsms_api_key','کلید API (فراز اس‌ام‌اس)','jay_login_register_passwordfield_render','jay_login_register_main_section','jay_login_register_main_section',[ 'name' => 'farazsms_api_key','wrapper_class' => 'jay-login-register-farazsms-field']);
    add_settings_field('farazsms_pattern_code','کد پترن (فراز اس‌ام‌اس)','jay_login_register_textfield_render','jay_login_register_main_section','jay_login_register_main_section',['name' => 'farazsms_pattern_code','wrapper_class' => 'jay-login-register-farazsms-field']);
    add_settings_field('farazsms_sender_line', 'خط ارسال کننده (فراز اس‌ام‌اس)','jay_login_register_textfield_render', 'jay_login_register_main_section','jay_login_register_main_section',['name' => 'farazsms_sender_line', 'wrapper_class' => 'jay-login-register-farazsms-field']);
    add_settings_field('farazsms_pattern_variable', 'نام متغیر پترن (فراز)', 'jay_login_register_textfield_render',  'jay_login_register_main_section','jay_login_register_main_section', ['name' => 'farazsms_pattern_variable', 'wrapper_class' => 'jay-login-register-farazsms-field',  'default' => 'code' ]);
    add_settings_field( 'kavenegar_api_key', 'کلید API (Kavenegar)', 'jay_login_register_passwordfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'kavenegar_api_key', 'wrapper_class' => 'jay-login-register-kavenegar-field'] );
    add_settings_field( 'kavenegar_template', 'الگو / پترن (پیامک)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'kavenegar_template', 'wrapper_class' => 'jay-login-register-kavenegar-field'] );
    add_settings_field( 'kavenegar_use_voice', 'کد تایید صوتی', 'jay_login_register_kavenegar_voice_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['wrapper_class' => 'jay-login-register-kavenegar-field'] );
    add_settings_field( 'kavenegar_voice_template', 'الگو / پترن (صوتی)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'kavenegar_voice_template', 'wrapper_class' => 'jay-login-register-kavenegar-field jay-login-register-kavenegar-voice-template-field'] );
    add_settings_field( 'smsir_api_key', 'کلید API (SMS.ir)', 'jay_login_register_passwordfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'smsir_api_key', 'wrapper_class' => 'jay-login-register-smsir-field'] );
    add_settings_field( 'smsir_template_id', 'شناسه الگو (SMS.ir)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'smsir_template_id', 'wrapper_class' => 'jay-login-register-smsir-field'] );
    add_settings_field( 'smsir_parameter_name', 'نام پارامتر کد (SMS.ir)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'smsir_parameter_name', 'wrapper_class' => 'jay-login-register-smsir-field'] );
    
    add_settings_field( 'raygansms_access_hash', 'کد دسترسی (RayganSMS)', 'jay_login_register_passwordfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'raygansms_access_hash', 'wrapper_class' => 'jay-login-register-raygansms-field'] );
    add_settings_field( 'raygansms_pattern_id', 'شناسه الگو (RayganSMS)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'raygansms_pattern_id', 'wrapper_class' => 'jay-login-register-raygansms-field'] );
    add_settings_field( 'raygansms_token_name', 'نام پارامتر کد (RayganSMS)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'raygansms_token_name', 'wrapper_class' => 'jay-login-register-raygansms-field'] );
    add_settings_field( 'melipayamak_username', 'نام کاربری (ملی پیامک)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'melipayamak_username', 'wrapper_class' => 'jay-login-register-melipayamak-field'] );
    add_settings_field( 'melipayamak_password', 'رمز عبور (ملی پیامک)', 'jay_login_register_passwordfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'melipayamak_password', 'wrapper_class' => 'jay-login-register-melipayamak-field'] );
    add_settings_field( 'melipayamak_body_id', 'کد متن (Body ID)', 'jay_login_register_textfield_render', 'jay_login_register_main_section', 'jay_login_register_main_section', ['name' => 'melipayamak_body_id', 'wrapper_class' => 'jay-login-register-melipayamak-field'] );

    // === بخش ۲: تنظیمات عمومی ===
    add_settings_section( 'jay_login_register_general_section', null, null, 'jay_login_register_general_section' );
    add_settings_field( 'otp_length', 'تعداد ارقام کد تایید', 'jay_login_register_otp_length_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'otp_validity_period', 'اعتبار کد تایید (دقیقه)', 'jay_login_register_otp_validity_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'otp_max_retries', 'حداکثر تلاش برای کد تایید', 'jay_login_register_otp_retries_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'otp_lockout_duration', 'مدت زمان مسدودیت (دقیقه)', 'jay_login_register_otp_lockout_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'id_methods', 'روش‌های احراز هویت', 'jay_login_register_id_methods_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'login_methods', 'روش‌های ورود/عضویت مجاز', 'jay_login_register_login_methods_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'otp_block_method', 'روش مسدودسازی', 'jay_login_register_block_method_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'login_page_id', 'برگه ورود/عضویت', 'jay_login_register_login_page_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'redirect_page_id', 'هدایت پس از ورود/عضویت', 'jay_login_register_redirect_page_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'change_phone_page_id', 'برگه پروفایل کاربری', 'jay_login_register_change_phone_page_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    add_settings_field( 'logout_page_id', 'برگه خروج', 'jay_login_register_logout_page_render', 'jay_login_register_general_section', 'jay_login_register_general_section' );
    // === بخش ۳: استایل ===
    add_settings_section( 'jay_login_register_style_section', null, null, 'jay_login_register_style_section' );
    add_settings_field( 'logo_id', 'آپلود لوگو', 'jay_login_register_logo_upload_render', 'jay_login_register_style_section', 'jay_login_register_style_section' );
    add_settings_field( 'otp_input_style', 'استایل فیلد کد تایید (OTP)', 'jay_login_register_otp_input_style_render', 'jay_login_register_style_section', 'jay_login_register_style_section' );
    add_settings_field( 'form_style', 'قالب‌های فرم', 'jay_login_register_style_templates_render', 'jay_login_register_style_section', 'jay_login_register_style_section' );
     // بخش جدید برای صفحه شخصی‌سازی
    add_settings_section( 'jay_login_register_customizer_section', 'تنظیمات پس‌زمینه', null, 'jay_login_register_style_customizer_page' );
    add_settings_field( 'form_bg_color', 'رنگ یا گرادینت پس‌زمینه', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_section', ['name' => 'form_bg_color', 'default' => 'linear-gradient(135deg, #667eea, #764ba2, #f093fb)'] );
    add_settings_field( 'form_container_bg', 'پس‌زمینه کادر فرم', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_section', ['name' => 'form_container_bg', 'default' => 'rgba(255, 255, 255, 0.1)'] );
    add_settings_field( 'form_border_radius', 'گردی گوشه‌ها (px)', 'jay_login_register_numberfield_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_section', ['name' => 'form_border_radius', 'default' => 24] );
    add_settings_field( 'form_backdrop_blur', 'میزان محو شدگی (px)', 'jay_login_register_numberfield_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_section', ['name' => 'form_backdrop_blur', 'default' => 20] );
    add_settings_field( 'form_border', 'حاشیه (Border)', 'jay_login_register_textfield_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_section', ['name' => 'form_border', 'default' => '1px solid rgba(255, 255, 255, 0.2)'] );
    add_settings_field( 'form_box_shadow', 'سایه (Box Shadow)', 'jay_login_register_textfield_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_section', ['name' => 'form_box_shadow', 'default' => '0 8px 32px 0 rgba(0, 0, 0, 0.1)'] );
    // بخش جدید برای رنگ‌های عناصر فرم
    add_settings_section( 'jay_login_register_customizer_elements_section', 'تنظیمات عناصر فرم', null, 'jay_login_register_style_customizer_page' );
    add_settings_field( 'form_button_bg', 'پس‌زمینه دکمه اصلی', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_button_bg', 'default' => 'linear-gradient(90deg, #0073aa, #00c6ff)'] );
    add_settings_field( 'form_button_text_color', 'رنگ متن دکمه اصلی', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_button_text_color', 'default' => '#fff'] );
    add_settings_field( 'form_switcher_color', 'رنگ متن(تعویض کدملی/گذرنامه)', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_switcher_color', 'default' => '#fff'] );
 
    add_settings_field( 'form_label_color', 'رنگ متن لیبل‌ها', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_label_color', 'default' => '#fff'] );
    add_settings_field( 'form_h_color', 'رنگ عناوین (H2, H3)', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_h_color', 'default' => '#fff'] );
    add_settings_field( 'form_p_color', 'رنگ پاراگراف‌ها (متن توضیحات)', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_p_color', 'default' => '#fff'] );
    add_settings_field( 'form_button_secondary_bg', 'پس‌زمینه دکمه دوم', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_button_secondary_bg', 'default' => 'rgba(255, 255, 255, 0.15)'] );
    add_settings_field( 'form_button_secondary_text', 'رنگ متن دکمه دوم', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_button_secondary_text', 'default' => '#fff'] );
    add_settings_field( 'form_input_bg', 'پس‌زمینه فیلدها', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_input_bg', 'default' => 'rgba(0, 0, 0, 0.2)'] );
    add_settings_field( 'form_input_border', 'رنگ حاشیه فیلدها', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_input_border', 'default' => '#888'] );
    add_settings_field( 'form_input_text', 'رنگ متن فیلدها', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section', ['name' => 'form_input_text', 'default' => '#fff'] );
    // بخش جدید برای رنگ‌های پیام خطا
    add_settings_section( 'jay_login_register_customizer_error_section', 'تنظیمات پیام خطا', null, 'jay_login_register_style_customizer_page' );
    add_settings_field( 'form_error_bg', 'پس‌زمینه کادر خطا', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_error_section', ['name' => 'form_error_bg', 'default' => 'rgba(220, 53, 69, 0.5)'] );
    add_settings_field( 'form_error_border', 'رنگ حاشیه کادر خطا', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_error_section', ['name' => 'form_error_border', 'default' => 'rgba(220, 53, 69, 0.8)'] );
    add_settings_field( 'form_error_text', 'رنگ متن کادر خطا', 'jay_login_register_generic_color_render', 'jay_login_register_style_customizer_page', 'jay_login_register_customizer_error_section', ['name' => 'form_error_text', 'default' => '#fff'] );

    // === بخش ۴: تنظیمات کپچا ===
    add_settings_section( 'jay_login_register_captcha_section', null, null, 'jay_login_register_captcha_section' );
    add_settings_field( 'captcha_settings', 'نوع کپچا', 'jay_login_register_captcha_render', 'jay_login_register_captcha_section', 'jay_login_register_captcha_section' );
    // === بخش ۵: ورود اجتماعی (جدید) ===
   add_settings_section( 
        'jay_login_register_social_section', 
        'تنظیمات شبکه‌های اجتماعی', 
        'jay_login_register_social_section_callback', 
        'jay_login_register_social_section' 
    );
    // === بخش ۶: تنظیمات ایمیل OTP ===
     add_settings_section(
     'jay_login_register_email_section',
     'تنظیمات ارسال کد تایید با ایمیل',
     'jay_login_register_email_settings_render_html', // از یک تابع برای رندر کل بخش استفاده می‌کنیم
     'jay_login_register_email_section'
     );
     
     // === بخش ۷: فیلدهای دلخواه ===
    add_settings_section(
        'jay_login_register_fields_section',
        'اطلاعات شخصی',
        'jay_login_register_fields_section_callback',
        'jay_login_register_fields_section'
    );
     // دریافت نام کاربری (با سوییچ ضروری)
    add_settings_field(
        'enable_username',
        'دریافت نام کاربری (ورود/عضویت)',
        'jay_login_register_switch_group_render', // تابع جدید
        'jay_login_register_fields_section',
        'jay_login_register_fields_section',
        [
            'name' => 'enable_username', 
            'label' => 'نمایش فیلد نام کاربری',
            'sub_toggles' => [
                [ 'name' => 'required_username', 'label' => 'الزامی' ]
            ]
        ]
    );
    
   add_settings_field(
        'enable_name_fields',
        'دریافت نام و نام خانوادگی',
        'jay_login_register_switch_group_render', // تابع جدید
        'jay_login_register_fields_section',
        'jay_login_register_fields_section',
        [
            'name' => 'enable_name_fields', 
            'label' => 'نمایش فیلد نام و نام خانوادگی (ثبت نام)',
            'sub_toggles' => [
                [ 'name' => 'required_name_fields', 'label' => 'الزامی' ],
                [ 'name' => 'force_persian_name_fields', 'label' => 'فقط فارسی' ]
            ]
        ]
    );
    // چک باکس فعال سازی فیلدهای سفارشی
    add_settings_field(
        'enable_custom_fields_global',
        'فیلدهای سفارشی',
        'jay_login_register_checkbox_render',
        'jay_login_register_fields_section',
        'jay_login_register_fields_section',
        ['name' => 'enable_custom_fields_global', 'label' => 'افزودن فیلدهای سفارشی به فرم ثبت‌نام']
    );

    // محیط ساخت فیلدها (Builder)
    add_settings_field(
        'custom_fields_builder',
        'مدیریت فیلدها',
        'jay_login_register_custom_fields_builder_render', // تابع جدید رندر
        'jay_login_register_fields_section',
        'jay_login_register_fields_section'
    );
    
}
/**
 * محتوای کامل تب "تنظیمات ایمیل" را نمایش می‌دهد.
 */

function jay_login_register_email_settings_render_html() {
    $options = get_option('jay_login_register_settings');

    // خواندن تمام مقادیر از دیتابیس با مقادیر پیش‌فرض
    $email_otp_enable = isset($options['email_otp_enable']) && $options['email_otp_enable'] === 'yes';
    $email_send_method = $options['email_send_method'] ?? 'default';

    $email_from_name = $options['email_from_name'] ?? get_bloginfo('name');
    $email_from_address = $options['email_from_address'] ?? get_bloginfo('admin_email');

    $smtp_host = $options['smtp_host'] ?? '';
    $smtp_port = $options['smtp_port'] ?? 587;
    $smtp_encryption = $options['smtp_encryption'] ?? 'tls';
    $smtp_auth = $options['smtp_auth'] ?? 'yes';
    $smtp_user = $options['smtp_user'] ?? '';
    $smtp_pass = $options['smtp_pass'] ?? '';

    $email_otp_subject = $options['email_otp_subject'] ?? 'کد تایید ورود شما: [otp_code]';
    $email_otp_body = $options['email_otp_body'] ?? "سلام،\n\nکد تایید شما برای ورود به سایت [site_name] عبارت است از:\n\n[otp_code]\n\nاین کد به مدت [validity_period] دقیقه معتبر است.";
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">فعال‌سازی</th>
            <td>
                <label>
                    <input type="checkbox" id="jay_login_register_email_otp_enable" name="jay_login_register_settings[email_otp_enable]" value="yes" <?php checked($email_otp_enable); ?>>
                    <strong>فعال‌سازی ورود با کد یکبار مصرف از طریق ایمیل</strong>
                </label>
            </td>
        </tr>
    </table>

    <div id="jay-login-register-email-options-wrapper" style="<?php echo $email_otp_enable ? '' : 'display: none;'; ?>">
        <hr>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">روش ارسال (Mailer)</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="jay_login_register_settings[email_send_method]" value="default" <?php checked($email_send_method, 'default'); ?>>
                            ارسال از طریق وردپرس (پیش‌فرض)
                            <p class="description">اگر افزونه ای دیگر برای کنترل ایمیل استفاده میکنید این گزینه را انتخاب کنید</p>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="jay_login_register_settings[email_send_method]" value="smtp" <?php checked($email_send_method, 'smtp'); ?>>
                            <strong>ارسال مستقیم با SMTP (پیشنهادی)</strong>
                            <p class="description">اتصال مستقیم به سرور ایمیل برای ارسال تضمینی.</p>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <div id="jay-login-register-smtp-wrapper" style="<?php echo ($email_send_method === 'smtp') ? '' : 'display: none;'; ?>">
            <h3>تنظیمات SMTP</h3>
            <p>این اطلاعات را می‌توانید از کنترل پنل هاست خود (بخش Email Accounts > Connect Devices) دریافت کنید.</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="jay_login_register_smtp_host">میزبان SMTP</label></th>
                    <td><input type="text" id="jay_login_register_smtp_host" name="jay_login_register_settings[smtp_host]" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text ltr" placeholder="mail.yourdomain.com"></td>
                </tr>
                <tr valign="top">
                    <th scope="row">رمزنگاری</th>
                    <td>
                        <select name="jay_login_register_settings[smtp_encryption]">
                            <option value="none" <?php selected($smtp_encryption, 'none'); ?>>None</option>
                            <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>>TLS</option>
                        </select>
                        <p class="description">اگر مطمئن نیستید، TLS را انتخاب کنید. اگر پورت 465 است، از SSL استفاده کنید.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="jay_login_register_smtp_port">پورت SMTP</label></th>
                    <td><input type="number" id="jay_login_register_smtp_port" name="jay_login_register_settings[smtp_port]" value="<?php echo esc_attr($smtp_port); ?>" class="small-text" placeholder="587"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="jay_login_register_smtp_user">نام کاربری SMTP</label></th>
                    <td><input type="text" id="jay_login_register_smtp_user" name="jay_login_register_settings[smtp_user]" value="<?php echo esc_attr($smtp_user); ?>" class="regular-text ltr" placeholder="you@yourdomain.com"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="jay_login_register_smtp_pass">رمز عبور SMTP</label></th>
                    <td><input type="password" id="jay_login_register_smtp_pass" name="jay_login_register_settings[smtp_pass]" value="<?php echo esc_attr($smtp_pass); ?>" class="regular-text ltr"></td>
                </tr>
            </table>
            <hr>
        <h3>اطلاعات فرستنده</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="jay_login_register_email_from_name">نام فرستنده</label></th>
                <td><input type="text" id="jay_login_register_email_from_name" name="jay_login_register_settings[email_from_name]" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text"></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="jay_login_register_email_from_address">ایمیل فرستنده</label></th>
                <td><input type="email" id="jay_login_register_email_from_address" name="jay_login_register_settings[email_from_address]" value="<?php echo esc_attr($email_from_address); ?>" class="regular-text ltr">
                    <p class="description">در حالت SMTP، این ایمیل باید با نام کاربری SMTP شما یکسان باشد.</p>
                </td>
            </tr>
        </table>
        
        </div>



        <hr>
        <h3>شخصی‌سازی قالب ایمیل</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="jay_login_register_email_otp_subject">موضوع ایمیل</label></th>
                <td><input type="text" id="jay_login_register_email_otp_subject" name="jay_login_register_settings[email_otp_subject]" value="<?php echo esc_attr($email_otp_subject); ?>" class="regular-text"></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="jay_login_register_email_otp_body">محتوای ایمیل</label></th>
                <td>
                    <?php wp_editor($email_otp_body, 'jay_login_register_email_otp_body', ['textarea_name' => 'jay_login_register_settings[email_otp_body]', 'media_buttons' => false, 'textarea_rows' => 7]); ?>
                    <p class="description">کدهای کوتاه مجاز: <code>[otp_code]</code>, <code>[site_name]</code>, <code>[validity_period]</code></p>
                </td>
            </tr>
        </table>

        <hr>
        <h3>تست ارسال ایمیل</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="jay_login_register_test_email">ارسال به</label></th>
                <td>
                    <input type="email" id="jay_login_register_test_email" class="regular-text ltr" placeholder="آدرس ایمیل تستی خود را وارد کنید">
                    <button type="button" class="button button-secondary" id="jay-relog-send-test-email">ارسال ایمیل تستی</button>
                    <span class="spinner" style="float: none; vertical-align: middle;"></span>
                    <p id="jay-relog-test-email-status" style="margin-top: 10px;"></p>
                </td>
            </tr>
        </table>
    </div>
<?php
}

/**
 * یک فیلد عمومی برای انتخاب رنگ رندر می‌کند.
 */
function jay_login_register_generic_color_render($args) {
    $options = get_option('jay_login_register_settings');
    $name = $args['name'];
    $default = $args['default'] ?? '';
    $value = $options[$name] ?? $default;
    ?>
    <div class="jay-color-picker-wrapper">
        <input type="text" name="jay_login_register_settings[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($value); ?>" class="jay-color-picker-input regular-text">
    </div>
    <?php
}
/**
 * جدید: نمایش چک‌باکس برای فعال‌سازی کد صوتی کاوه نگار
 */
function jay_login_register_kavenegar_voice_render() {
    $options = get_option('jay_login_register_settings');
    $is_checked = isset($options['kavenegar_use_voice']) && $options['kavenegar_use_voice'] === 'yes';
    ?>
    <label>
        <input class="jay-login-register-kavenegar-field" type="checkbox" name="jay_login_register_settings[kavenegar_use_voice]" value="yes" <?php checked($is_checked); ?>>
        فعال‌سازی ارسال کد تایید به صورت تماس صوتی (فقط برای کاوه نگار)
    </label>
    <p class="description">در صورت فعال بودن، به جای پیامک، با کاربر تماس گرفته شده و کد برای او خوانده می‌شود.</p>
    <?php
}
/**
 * جدید: نمایش چک‌باکس‌های روش‌های احراز هویت
 */
function jay_login_register_id_methods_render() {
    $options = get_option('jay_login_register_settings');
    // پیش‌فرض: هر دو روش فعال هستند
    $methods = $options['id_methods'] ?? ['codemeli', 'passport'];
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="jay_login_register_settings[id_methods][]" value="codemeli" <?php checked( in_array('codemeli', $methods) ); ?>>
            فعال‌سازی کد ملی
        </label>
        <br>
        <label>
            <input type="checkbox" name="jay_login_register_settings[id_methods][]" value="passport" <?php checked( in_array('passport', $methods) ); ?>>
            فعال‌سازی گذرنامه
        </label>
    <p class="description">روش‌های احراز هویت مورد نظر را انتخاب کنید. اگر هر دو گزینه غیرفعال باشند، این مرحله به طور کامل حذف خواهد شد.</p>
        <?php  ?>
        <p class="jay-login-register-description-note">
            <strong>توجه:</strong> اگر این گزینه‌ها فعال باشند، کد ملی یا گذرنامه فقط در اولین ورود یا عضویت از کاربر پرسیده و ذخیره می‌شود. در مراجعات بعدی، سیستم کاربر را شناسایی کرده و مستقیماً به مرحله ورود با رمز عبور / کد تایید هدایت می‌کند.
        </p>
        <?php ?>
    </fieldset>
    <?php
}
/**
 * نمایش چک‌باکس‌های انتخاب روش ورود (موبایل / ایمیل).
 */
function jay_login_register_login_methods_render() {
    $options = get_option('jay_login_register_settings');
    // پیش‌فرض: فقط موبایل فعال است
    $methods = $options['login_methods'] ?? ['mobile'];
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="jay_login_register_settings[login_methods][]" value="mobile" <?php checked( in_array('mobile', $methods, true) ); ?>>
            فعال‌سازی ورود/عضویت با شماره موبایل
        </label>
        <br>
        <label>
            <input type="checkbox" name="jay_login_register_settings[login_methods][]" value="email" <?php checked( in_array('email', $methods, true) ); ?>>
            فعال‌سازی ورود/عضویت با ایمیل
        </label>
    <p class="description">روش‌های ورودی که می‌خواهید در فرم اصلی پشتیبانی شوند را انتخاب کنید. حداقل یک گزینه باید فعال باشد.</p>
    </fieldset>
    <?php
}

/**
 * جدید: نمایش چک‌باکس‌های روش مسدودسازی
 */
function jay_login_register_block_method_render() {
    $options = get_option('jay_login_register_settings');
    $methods = isset($options['otp_block_method']) && is_array($options['otp_block_method']) ? $options['otp_block_method'] : ['phone'];
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="jay_login_register_settings[otp_block_method][]" value="phone" <?php checked( in_array('phone', $methods) ); ?>>
            مسدودسازی بر اساس شماره موبایل (پیشنهادی)
        </label>
        <br>
        <label>
            <input type="checkbox" name="jay_login_register_settings[otp_block_method][]" value="ip" <?php checked( in_array('ip', $methods) ); ?>>
            مسدودسازی بر اساس آدرس IP
        </label>
        <p class="description">می‌توانید یک یا هر دو روش را برای امنیت بیشتر انتخاب کنید.</p>
    </fieldset>
    <?php
}
/**
 * جدید: نمایش فیلد زمان اعتبار کد تایید
 */
function jay_login_register_otp_validity_render() {
    $options = get_option('jay_login_register_settings');
    $value = isset($options['otp_validity_period']) ? absint($options['otp_validity_period']) : 2; // پیش‌فرض ۲ دقیقه
    echo '<input type="number" name="jay_login_register_settings[otp_validity_period]" value="' . esc_attr($value) . '" min="1" max="10">';
    echo '<p class="description">کاربر تا چند دقیقه برای وارد کردن کد فرصت دارد؟ (زمان تایمر ارسال مجدد)</p>';
}
/**
 * جدید: نمایش فیلد مدت زمان مسدودیت
 */
function jay_login_register_otp_lockout_render() {
    $options = get_option('jay_login_register_settings');
    $value = isset($options['otp_lockout_duration']) ? absint($options['otp_lockout_duration']) : 15; // پیش‌فرض ۱۵ دقیقه
    echo '<input type="number" name="jay_login_register_settings[otp_lockout_duration]" value="' . esc_attr($value) . '" min="1">';
    echo '<p class="description">پس از تلاش‌های ناموفق، کاربر برای چند دقیقه مسدود شود؟</p>';
}
/**
 * جدید: نمایش فیلد حداکثر تلاش برای کد تایید
 */
function jay_login_register_otp_retries_render() {
    $options = get_option('jay_login_register_settings');
    $value = isset($options['otp_max_retries']) ? absint($options['otp_max_retries']) : 3; // پیش‌فرض ۳ بار تلاش
    echo '<input type="number" name="jay_login_register_settings[otp_max_retries]" value="' . esc_attr($value) . '" min="1" max="10">';
    echo '<p class="description">کاربر چند بار می‌تواند کد تایید را اشتباه وارد کند قبل از اینکه موقتاً مسدود شود؟</p>';
}

/**
 * جدید: پاک‌سازی داده‌های ستون‌های سفارشی
 */
function jay_login_register_sanitize_custom_columns( $input ) {
    $new_input = [];
    if ( isset( $input['columns'] ) && is_array( $input['columns'] ) ) {
        foreach ( $input['columns'] as $column ) {
            // فقط ستون‌هایی که نام و متاکی دارند را ذخیره کن
            if ( ! empty( $column['name'] ) && ! empty( $column['key'] ) ) {
                $new_column = [
                    'name'    => sanitize_text_field( $column['name'] ),
                    'key'     => sanitize_key( $column['key'] ), // sanitize_key برای متاکی مناسب است
                    'display' => in_array( $column['display'], [ 'value', 'icon' ] ) ? $column['display'] : 'value',
                ];
                $new_input[] = $new_column;
            }
        }
    }
    return [ 'columns' => $new_input ];
}

/**
 * گزینه‌های ارسالی از فرم کنترل دسترسی را پاک‌سازی می‌کند.
 */
function jay_login_register_sanitize_access_settings( $input ) {
  $sanitized_input = ['allow_admin_access' => []];

  if ( ! empty( $input['allow_admin_access'] ) && is_array( $input['allow_admin_access'] ) ) {
  foreach ( $input['allow_admin_access'] as $role ) {
  $sanitized_input['allow_admin_access'][] = sanitize_key( $role );
    }
  }

  // --- جدید: پاک‌سازی فیلد مخفی‌سازی wp-login.php ---
  $sanitized_input['hide_wp_login'] = ( isset( $input['hide_wp_login'] ) && $input['hide_wp_login'] === 'yes' ) ? 'yes' : 'no';

  return $sanitized_input;
}
/**
 * جدید: پاک‌سازی گزینه‌های تب "مدیریت ستون‌های کاربران"
 */
function jay_login_register_sanitize_user_columns_settings( $input ) {
    $sanitized_input = ['hidden_columns' => []];
    if ( ! empty( $input['hidden_columns'] ) && is_array( $input['hidden_columns'] ) ) {
        // لیست تمام ستون‌های معتبر برای اعتبارسنجی
        $valid_columns = array_keys( jay_login_register_get_all_user_columns() );
        foreach ( $input['hidden_columns'] as $column ) {
            $sanitized_column = sanitize_key( $column );
            if ( in_array( $sanitized_column, $valid_columns, true ) ) {
                $sanitized_input['hidden_columns'][] = $sanitized_column;
            }
        }
    }
    return $sanitized_input;
}

/**
 * جدید: تابع کمکی برای دریافت لیست تمام ستون‌های کاربران
 */
function jay_login_register_get_all_user_columns() {
    return [
        'username'    => 'نام کاربری',
        'name'        => 'نام',
        'email'       => 'ایمیل',
        'role'        => 'نقش',
        'posts'       => 'نوشته‌ها',
        'registration_date' => 'تاریخ عضویت',
        'jay_mobile'    => 'شماره موبایل ', 
        'jay_edit_access'   => 'دسترسی ویرایش پروفایل', 

    ];
}

// تابع ریدایرکت
function jay_login_register_redirect_page_render() {
    $options = get_option('jay_login_register_settings');
    $selected_page = isset($options['redirect_page_id']) ? absint($options['redirect_page_id']) : 0;
    
    wp_dropdown_pages([
        'name'             => 'jay_login_register_settings[redirect_page_id]',
        'selected'         => esc_attr($selected_page),
        'show_option_none' => '— صفحه اصلی سایت —',
        'option_none_value'=> '0',
    ]);
    echo '<p class="description">اگر کاربر مستقیماً از برگه ورود/عضویت وارد شود، به کدام برگه هدایت شود؟</p>';
}

// تابع جدید برای رندر فیلد انتخاب برگه
function jay_login_register_login_page_render() {
    $options = get_option('jay_login_register_settings');
    $selected_page = isset($options['login_page_id']) ? absint($options['login_page_id']) : 0;
    

    wp_dropdown_pages([
        'name'             => 'jay_login_register_settings[login_page_id]',
        'selected'         => esc_attr($selected_page),
        'show_option_none' => '— انتخاب کنید —',
        'option_none_value'=> '0',
    ]);
    echo '<p class="description">برگه‌ای که شورت‌کد فرم در آن قرار دارد را انتخاب کنید.</p>';
}

// Render functions for fields
function jay_login_register_textfield_render($args) {
    $options = get_option('jay_login_register_settings');
    $name = $args['name'];
    $value = isset($options[$name]) ? $options[$name] : ($args['default'] ?? '');
    $class = isset($args['wrapper_class']) ? esc_attr($args['wrapper_class']) : '';
    
    if ( ! empty($class) ) {
        printf('<div class="%s">', esc_attr($class));
    }
    printf(
        '<input type="text" name="jay_login_register_settings[%s]" value="%s" class="regular-text">',
        esc_attr($name),
        esc_attr($value)
    );
 
    if ( ! empty($class) ) {
        echo "</div>";
    }
}

function jay_login_register_passwordfield_render($args) {
    $options = get_option('jay_login_register_settings');
    $name = $args['name'];
    $value = isset($options[$name]) ? $options[$name] : '';
    $class = isset($args['wrapper_class']) ? esc_attr($args['wrapper_class']) : '';

    if ( ! empty($class) ) {
     printf('<div class="%s">', esc_attr($class));
    }

  printf(
        '<input type="password" name="jay_login_register_settings[%s]" value="%s" class="regular-text" autocomplete="new-password">',
        esc_attr($name),
        esc_attr($value)
    );
    
    if ( ! empty($class) ) {
        echo "</div>";
    }
}

function jay_login_register_readonly_field_render($args) {
    $value = $args['value'];
    printf('<input type="text" value="%s" class="regular-text" readonly>', esc_attr($value));
}

function jay_login_register_select_render() {
    $options = get_option('jay_login_register_settings');
    $value = isset($options['send_method']) ? $options['send_method'] : 'pattern';
    ?>
    <select name="jay_login_register_settings[send_method]" id="jay_login_register_send_method_select">
        <option value="pattern" <?php selected($value, 'pattern'); ?>><?php esc_html_e('ارسال با پترن (الگو)', 'jay-login-register'); ?></option>
        <option value="api" <?php selected($value, 'api'); ?>><?php esc_html_e('ارسال با API (ارسال سریع)', 'jay-login-register'); ?></option>

    </select>
    <?php
}

function jay_login_register_logo_upload_render() {
    $options = get_option('jay_login_register_settings');
    $logo_id = isset($options['logo_id']) ? absint($options['logo_id']) : 0;
    printf('<input type="hidden" name="jay_login_register_settings[logo_id]" id="jay_login_register_logo_id" value="%s">', esc_attr($logo_id));
    
    
    // **مهم:** فیلد آپلود فقط زمانی نمایش داده می‌شود که لوگویی آپلود نشده باشد
    if ( $logo_id ) {
        // **اصلاح کلیدی:** استفاده از تابع استاندارد وردپرس برای نمایش عکس
        echo '<div id="jay_login_register_logo_preview_wrapper">';
        echo wp_get_attachment_image($logo_id, 'medium', false, ['id' => 'jay_login_register_logo_preview', 'style' => 'max-width:150px; height:auto;']);
        echo '<div><a href="#" id="jay_login_register_remove_logo_button">' . esc_html__( 'حذف لوگو', 'jay-login-register' ) . '</a></div>';
        echo '</div>';
    } else {
        // دکمه آپلود فقط زمانی نمایش داده می‌شود که لوگویی انتخاب نشده باشد
        echo '<input type="button" class="button button-secondary" id="jay_login_register_upload_logo_button" value="' . esc_attr__( 'انتخاب لوگو', 'jay-login-register' ) . '">';
        echo '<p class="description">' . esc_html__( 'یک تصویر برای لوگوی فرم انتخاب کنید.', 'jay-login-register' ) . '</p>';
    }
}

/**
 * جدید: نمایش منوی کشویی برای انتخاب سرویس‌دهنده پیامک
 */
function jay_login_register_sms_provider_render() {
    $options = get_option('jay_login_register_settings');
    $provider = $options['sms_provider'] ?? 'ipanel';
    ?>
    <select name="jay_login_register_settings[sms_provider]" id="jay_login_register_sms_provider_select">
        <option value="ipanel" <?php selected($provider, 'ipanel'); ?>>iPPanel</option>
        <option value="kavenegar" <?php selected($provider, 'kavenegar'); ?>>کاوه نگار</option>
        <option value="farazsms" <?php selected($provider, 'farazsms'); ?>>فراز اس ام اس</option>
        <option value="modirpayamak" <?php selected($provider, 'modirpayamak'); ?>>مدیر پیامک</option>
        <option value="tabansms" <?php selected($provider, 'tabansms'); ?>>تابان اس ام اس</option>
        <option value="smsir" <?php selected($provider, 'smsir'); ?>>SMS.ir</option>
        <option value="raygansms" <?php selected($provider, 'raygansms'); ?>>RayganSMS</option>
        <option value="melipayamak" <?php selected($provider, 'melipayamak'); ?>>ملی پیامک</option>
    </select>
    <p class="description">سرویس‌دهنده پیامک خود را انتخاب کنید.</p>
    <?php
}


/**
 * جدید: نمایش محتوای بخش ورود اجتماعی (شامل آکاردیون‌ها)
 */
function jay_login_register_social_section_callback() {
    echo '<p>تنظیمات مربوط به ورود از طریق شبکه‌های اجتماعی را در این بخش مدیریت کنید.</p>';

    $options = get_option('jay_login_register_settings');
    $is_checked = isset($options['eitaa_login_enable']) && $options['eitaa_login_enable'] === 'yes';
    $eitaa_tokens = $options['eitaa_tokens'] ?? []; // لیست توکن‌ها را می‌خوانیم
    ?>
    <div class="jay-login-register-accordion" style="margin-top: 15px;">
        <h4 class="accordion-title">تنظیمات ورود/عضویت با ایتا</h4>
        <div class="accordion-content">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">فعال‌سازی</th>
                        <td>
                            <label>
                                <input type="checkbox" name="jay_login_register_settings[eitaa_login_enable]" value="yes" <?php checked($is_checked); ?>>
                                فعال‌سازی ورود و عضویت خودکار با ایتا
                            </label>
                            <p class="description">با فعال‌سازی این گزینه، کاربرانی که از لینک برنامک ایتا وارد سایت شوند، به صورت خودکار احراز هویت خواهند شد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">توکن‌های برنامه ایتا</th>
                        <td>
                            <div id="eitaa-tokens-repeater">
                                <div class="repeater-rows">
                                    <?php if ( ! empty( $eitaa_tokens ) ) : ?>
                                        <?php foreach ( $eitaa_tokens as $index => $token_data ) : ?>
                                            <div class="repeater-row">
                                                <input type="text" name="jay_login_register_settings[eitaa_tokens][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $token_data['name'] ); ?>" placeholder="نام برنامه (مثال: فروشگاه اصلی)">
                                                <input type="password" name="jay_login_register_settings[eitaa_tokens][<?php echo esc_attr( $index ); ?>][token]" value="<?php echo esc_attr( $token_data['token'] ); ?>" placeholder="توکن برنامه (Bot Token)" class="regular-text" autocomplete="new-password">
                                                <button type="button" class="button button-secondary remove-row">حذف</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button button-primary add-row">افزودن توکن جدید</button>
                            </div>
                            <p class="description">توکن‌های برنامه‌هایی که از پنل <a href="https://eitaayar.ir/admin/app" target="_blank">ایتایار</a> دریافت کرده‌اید را در این قسمت وارد کنید.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>






<div class="jay-login-register-accordion" style="margin-top: 15px;">
  <h4 class="accordion-title">تنظیمات ورود/عضویت با گوگل (Gmail)</h4>
 <div class="accordion-content">
  <?php
  $is_google_checked = isset($options['google_login_enable']) && $options['google_login_enable'] === 'yes';
  $google_client_id = $options['google_client_id'] ?? '';
  $google_client_secret = $options['google_client_secret'] ?? '';
  ?>
  <table class="form-table">
  <tbody>
  <tr>
  <th scope="row">فعال‌سازی</th>
  <td>
  <label>
  <input type="checkbox" id="jay_login_register_google_login_enable" name="jay_login_register_settings[google_login_enable]" value="yes" <?php checked($is_google_checked); ?>>
  فعال‌سازی ورود و عضویت با حساب گوگل
  </label>
  </td>
  </tr>
  </tbody>
  </table>
  <div id="jay-login-register-google-fields" style="<?php echo $is_google_checked ? '' : 'display: none;'; ?>">
  <p>برای دریافت کلیدها، باید یک پروژه در Google Cloud Console بسازید.</p>
  <p>
  <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="button button-secondary">دریافت کلید از گوگل</a>
  <button type="button" class="button button-secondary" id="toggle-google-instructions">راهنمای دریافت کلید</button>
  </p>
<div id="google-instructions-panel" style="display: none;">
    <p><strong>مراحل دریافت کلیدهای گوگل:</strong></p>
    <ol>
        <li>ابتدا وارد <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> شوید.</li>
        <li>اگر برای اولین بار است که کلید می‌سازید، گوگل شما را به صفحه <strong>"OAuth consent screen"</strong> هدایت می‌کند. در این صفحه:
            <ul style="list-style-type: disc; padding-right: 20px;">
                <li>گزینه <strong>External</strong> را انتخاب کرده و روی CREATE کلیک کنید.</li>
                <li>در فرم بعدی، فقط فیلدهای اجباری مانند نام برنامه (App name) و ایمیل پشتیبانی (User support email) را پر کرده و در انتهای صفحه روی SAVE AND CONTINUE کلیک کنید (نیازی به پر کردن سایر بخش‌ها نیست).</li>
                <li>سپس از منوی سمت چپ دوباره به بخش <strong>Credentials</strong> بازگردید.</li>
            </ul>
        </li>
        <li>در صفحه <strong>Credentials</strong>، از منوی بالا روی <strong>+ CREATE CREDENTIALS</strong> کلیک کرده و گزینه <strong>OAuth client ID</strong> را انتخاب کنید.</li>
        <li>در فرم جدید:
            <ul style="list-style-type: disc; padding-right: 20px;">
                <li><strong>Application type:</strong> این گزینه را حتماً روی <strong>Web application</strong> قرار دهید.</li>
                <li><strong>Name:</strong> یک نام دلخواه برای شناسایی کلید وارد کنید (مثلاً: Poian Login). این نام به کاربران نمایش داده نمی‌شود.</li>
            </ul>
        </li>
        <li>کمی پایین‌تر، در بخش <strong>Authorized redirect URIs</strong>، روی دکمه <strong>ADD URI</strong> کلیک کنید.</li>
        <li>آدرس زیر را به طور کامل کپی کرده و در فیلد جدید پیست کنید. این آدرس بسیار مهم است و باید دقیقاً همین باشد:
            <br><code style="direction:ltr; display:block; margin:5px 0; padding: 5px; background: #eee; border: 1px solid #ddd;"><?php echo esc_url(home_url('/?jay-google-auth=1')); ?></code>
        </li>
        <li>در نهایت روی دکمه آبی رنگ <strong>CREATE</strong> در پایین صفحه کلیک کنید.</li>
        <li>گوگل به شما <strong>Your Client ID</strong> و <strong>Your Client Secret</strong> را نمایش می‌دهد. این دو مقدار را کپی کرده و در فیلدهای زیر وارد کنید.</li>
    </ol>
</div>
<table class="form-table">
  <tbody>
  <tr valign="top">
  <th scope="row"><label for="google_client_id">Client ID</label></th>
  <td><input type="text" id="google_client_id" name="jay_login_register_settings[google_client_id]" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text" /></td>
  </tr>
 <tr valign="top">
  <th scope="row"><label for="google_client_secret">Client Secret</label></th>
  <td><input type="password" id="google_client_secret" name="jay_login_register_settings[google_client_secret]" value="<?php echo esc_attr($google_client_secret); ?>" class="regular-text" /></td>
  </tr>
 </tbody>
 </table>
 </div>
 </div>
  </div>

<!--بله-->
<div class="jay-login-register-accordion" style="margin-top: 15px;">
    <h4 class="accordion-title">تنظیمات ارسال کد با بله (سفیر OTP)</h4>
    <div class="accordion-content">
        <?php
        $is_bale_otp_enabled = isset($options['bale_otp_enable']) && $options['bale_otp_enable'] === 'yes';
        $bale_client_id = $options['bale_otp_client_id'] ?? '';
        $bale_client_secret = $options['bale_otp_client_secret'] ?? '';
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">فعال‌سازی</th>
                    <td>
                        <label>
                            <input type="checkbox" id="jay_login_register_bale_otp_enable" name="jay_login_register_settings[bale_otp_enable]" value="yes" <?php checked($is_bale_otp_enabled); ?>>
                            <strong>فعال‌سازی ارسال کد تایید از طریق پیام‌رسان بله</strong>
                        </label>
                        <p class="description">با فعال‌سازی این گزینه، کاربران می‌توانند انتخاب کنند که کد تایید را به جای پیامک، در اپلیکیشن بله خود دریافت کنند (این یک سرویس تجاری و پولی است).</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <div id="jay-login-register-bale-otp-fields" style="<?php echo $is_bale_otp_enabled ? '' : 'display: none;'; ?>">
            <p>برای دریافت کلیدها، باید در سامانه درگاه بله ثبت‌نام کرده و اشتراک سرویس OTP را تهیه کنید.</p>
            <p>
                <a href="https://safir.bale.ai/gateway/login" target="_blank" class="button button-secondary">ورود به سامانه درگاه بله</a>
                <button type="button" class="button button-secondary" id="toggle-bale-otp-instructions">راهنمای دریافت کلید</button>
            </p>
            <div id="bale-otp-instructions-panel" style="display: none;">
                <p><strong>مراحل دریافت کلیدهای سامانه OTP بله:</strong></p>
                <ol>
                    <li>ابتدا وارد <a href="https://safir.bale.ai/gateway/login" target="_blank">سامانه درگاه بله</a> شوید و ثبت‌نام کنید.</li>
                    <li>از منوی پنل کاربری، سرویس "ارسال رمز یک‌بار مصرف (OTP)" را پیدا کرده و اشتراک آن را تهیه کنید.</li>
                    <li>پس از تهیه اشتراک، سامانه به شما یک <strong>نام کاربری (Client ID)</strong> و یک <strong>رمز عبور (Client Secret)</strong> می‌دهد.</li>
                    <li>این دو مقدار را کپی کرده و در فیلدهای زیر وارد کنید.</li>
                </ol>
            </div>
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row"><label for="bale_otp_client_id">نام کاربری (Client ID)</label></th>
                        <td><input type="text" id="bale_otp_client_id" name="jay_login_register_settings[bale_otp_client_id]" value="<?php echo esc_attr($bale_client_id); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="bale_otp_client_secret">رمز عبور (Client Secret)</label></th>
                        <td><input type="password" id="bale_otp_client_secret" name="jay_login_register_settings[bale_otp_client_secret]" value="<?php echo esc_attr($bale_client_secret); ?>" class="regular-text" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


    <template id="eitaa-repeater-template">
        <div class="repeater-row">
            <input type="text" name="jay_login_register_settings[eitaa_tokens][__INDEX__][name]" placeholder="نام برنامه (مثال: فروشگاه اصلی)">
            <input type="password" name="jay_login_register_settings[eitaa_tokens][__INDEX__][token]" placeholder="توکن برنامه (Bot Token)" class="regular-text" autocomplete="new-password">
            <button type="button" class="button button-secondary remove-row">حذف</button>
        </div>
    </template>
    <?php
}
// Sanitize settings before saving
function jay_login_register_sanitize_settings($input) {

    $old_settings = get_option('jay_login_register_settings', []);
    $new_settings = $old_settings;

    // --- تب تنظیمات پنل پیامک ---
    if ( isset($input['sms_provider']) ) {
        $allowed_providers = ['ipanel', 'farazsms', 'modirpayamak', 'tabansms', 'kavenegar', 'smsir', 'raygansms', 'melipayamak'];
        $new_settings['sms_provider'] = in_array($input['sms_provider'], $allowed_providers) ? $input['sms_provider'] : 'ipanel';
        
        $new_settings['farazsms_api_key'] = isset($input['farazsms_api_key']) ? sanitize_text_field($input['farazsms_api_key']) : '';
        $new_settings['farazsms_sender_line'] = isset($input['farazsms_sender_line']) ? sanitize_text_field($input['farazsms_sender_line']) : '';
        $new_settings['farazsms_pattern_code'] = isset($input['farazsms_pattern_code']) ? sanitize_text_field($input['farazsms_pattern_code']) : '';
        $new_settings['farazsms_pattern_variable'] = isset($input['farazsms_pattern_variable']) ? sanitize_key($input['farazsms_pattern_variable']) : 'code'; // Sanitize as key, default 'code'


        $new_settings['ipanel_api_key'] = isset($input['ipanel_api_key']) ? sanitize_text_field($input['ipanel_api_key']) : '';
        $new_settings['ipanel_pattern_code'] = isset($input['ipanel_pattern_code']) ? sanitize_text_field($input['ipanel_pattern_code']) : '';
        $new_settings['ipanel_sender_line'] = isset($input['ipanel_sender_line']) ? sanitize_text_field($input['ipanel_sender_line']) : '';
        $new_settings['ipanel_pattern_variable'] = isset($input['ipanel_pattern_variable']) ? sanitize_key($input['ipanel_pattern_variable']) : 'code';
        $new_settings['kavenegar_api_key'] = isset($input['kavenegar_api_key']) ? sanitize_text_field($input['kavenegar_api_key']) : '';
        $new_settings['kavenegar_template'] = isset($input['kavenegar_template']) ? sanitize_text_field($input['kavenegar_template']) : '';
        $new_settings['kavenegar_use_voice'] = isset($input['kavenegar_use_voice']) && $input['kavenegar_use_voice'] === 'yes' ? 'yes' : 'no';
        $new_settings['kavenegar_voice_template'] = isset($input['kavenegar_voice_template']) ? sanitize_text_field($input['kavenegar_voice_template']) : '';
        
        $new_settings['smsir_api_key'] = isset($input['smsir_api_key']) ? sanitize_text_field($input['smsir_api_key']) : '';
        $new_settings['smsir_template_id'] = isset($input['smsir_template_id']) ? sanitize_text_field($input['smsir_template_id']) : '';
        $new_settings['smsir_parameter_name'] = isset($input['smsir_parameter_name']) ? sanitize_text_field($input['smsir_parameter_name']) : '';
        $new_settings['raygansms_access_hash'] = isset($input['raygansms_access_hash']) ? sanitize_text_field($input['raygansms_access_hash']) : '';
        $new_settings['raygansms_pattern_id'] = isset($input['raygansms_pattern_id']) ? sanitize_text_field($input['raygansms_pattern_id']) : '';
        $new_settings['raygansms_token_name'] = isset($input['raygansms_token_name']) ? sanitize_text_field($input['raygansms_token_name']) : 'token1'; 
        $new_settings['melipayamak_username'] = isset($input['melipayamak_username']) ? sanitize_text_field($input['melipayamak_username']) : '';
        $new_settings['melipayamak_password'] = isset($input['melipayamak_password']) ? sanitize_text_field($input['melipayamak_password']) : '';
        $new_settings['melipayamak_body_id'] = isset($input['melipayamak_body_id']) ? sanitize_text_field($input['melipayamak_body_id']) : '';
    }
    if ( isset($input['otp_length']) ) {
        $allowed_lengths = ['4', '5', '6', '7', '8'];
        $new_settings['otp_length'] = in_array($input['otp_length'], $allowed_lengths) ? $input['otp_length'] : '4';
    }

    if ( isset($input['otp_validity_period']) ) $new_settings['otp_validity_period'] = absint($input['otp_validity_period']);
    if ( isset($input['otp_max_retries']) ) $new_settings['otp_max_retries'] = absint($input['otp_max_retries']);
    if ( isset($input['otp_lockout_duration']) ) $new_settings['otp_lockout_duration'] = absint($input['otp_lockout_duration']);
    if ( isset($input['login_page_id']) ) $new_settings['login_page_id'] = absint($input['login_page_id']);
    if ( isset($input['redirect_page_id']) ) $new_settings['redirect_page_id'] = absint($input['redirect_page_id']);
    if ( isset($input['change_phone_page_id']) ) $new_settings['change_phone_page_id'] = absint($input['change_phone_page_id']);
    if ( isset($input['logout_page_id']) ) $new_settings['logout_page_id'] = absint($input['logout_page_id']);

    if ( isset($input['otp_length']) ) { 
        $new_settings['id_methods'] = [];
        if ( ! empty( $input['id_methods'] ) && is_array( $input['id_methods'] ) ) {
            $allowed_methods = ['codemeli', 'passport'];
            foreach ( $input['id_methods'] as $method ) {
                if ( in_array( $method, $allowed_methods, true ) ) {
                    $new_settings['id_methods'][] = $method;
                }
            }
        }
        $new_settings['otp_block_method'] = [];
        if ( ! empty( $input['otp_block_method'] ) && is_array( $input['otp_block_method'] ) ) {
            $allowed_methods = ['phone', 'ip'];
            foreach ( $input['otp_block_method'] as $method ) {
                if ( in_array( $method, $allowed_methods, true ) ) {
                    $new_settings['otp_block_method'][] = $method;
                }
            }
        }
        if ( empty( $new_settings['otp_block_method'] ) ) {
            $new_settings['otp_block_method'][] = 'phone';
        }
    }
    
    // --- بخش جدید: ذخیره روش‌های ورود (موبایل/ایمیل) ---
    if ( isset($input['login_methods']) && is_array($input['login_methods']) ) {
     $sanitized_methods = [];
     $allowed_methods = ['mobile', 'email'];
     foreach ($input['login_methods'] as $method) {
         if (in_array($method, $allowed_methods, true)) {
         $sanitized_methods[] = $method;
        }
     }
     $new_settings['login_methods'] = $sanitized_methods;
     } else {
        // اگر کاربر هیچ گزینه‌ای را ارسال نکرد (مثلاً از تب دیگری ذخیره کرده)
        // مقدار قبلی را حفظ کن، مگر اینکه اصلاً وجود نداشته باشد.
        if (!isset($new_settings['login_methods'])) {
            $new_settings['login_methods'] = ['mobile'];
        }
    }

     // اطمینان از اینکه حداقل یک روش انتخاب شده است
     if ( empty($new_settings['login_methods']) ) {
     $new_settings['login_methods'][] = 'mobile'; // بازگشت به پیش‌فرض امن
     }
    
    if ( isset($input['logo_id']) ) $new_settings['logo_id'] = absint($input['logo_id']);
    // --- جدید: ذخیره استایل فیلد OTP ---
    if ( isset($input['otp_input_style']) && in_array($input['otp_input_style'], ['single', 'multiple'], true) ) {
        $new_settings['otp_input_style'] = $input['otp_input_style'];
    } else {
        if (!isset($new_settings['otp_input_style'])) {
            $new_settings['otp_input_style'] = 'single';
        }
    }
    // --- تب استایل (انتخاب قالب و شخصی‌سازی) ---
    if ( isset($input['form_style']) || isset($input['form_bg_color']) ) {
     // ذخیره قالب انتخابی
     if ( isset($input['form_style']) ) {
     $allowed_styles = ['glass']; 
         if ( in_array($input['form_style'], $allowed_styles, true) ) {
         $new_settings['form_style'] = $input['form_style'];
         }
     }

     // ذخیره تنظیمات شخصی‌سازی کانتینر
     $new_settings['form_bg_color'] = isset($input['form_bg_color']) ? wp_strip_all_tags($input['form_bg_color']) : ($old_settings['form_bg_color'] ?? 'linear-gradient(135deg, #667eea, #764ba2, #f093fb)');
     $new_settings['form_container_bg'] = isset($input['form_container_bg']) ? wp_strip_all_tags($input['form_container_bg']) : ($old_settings['form_container_bg'] ?? 'rgba(255, 255, 255, 0.1)');
     $new_settings['form_border_radius'] = isset($input['form_border_radius']) ? absint($input['form_border_radius']) : ($old_settings['form_border_radius'] ?? 24);
     $new_settings['form_backdrop_blur'] = isset($input['form_backdrop_blur']) ? absint($input['form_backdrop_blur']) : ($old_settings['form_backdrop_blur'] ?? 20);
     $new_settings['form_border'] = isset($input['form_border']) ? wp_strip_all_tags($input['form_border']) : ($old_settings['form_border'] ?? '1px solid rgba(255, 255, 255, 0.2)');
     $new_settings['form_box_shadow'] = isset($input['form_box_shadow']) ? wp_strip_all_tags($input['form_box_shadow']) : ($old_settings['form_box_shadow'] ?? '0 8px 32px 0 rgba(0, 0, 0, 0.1)');

     // ذخیره تنظیمات رنگ عناصر
    $new_settings['form_button_bg'] = isset($input['form_button_bg']) ? wp_strip_all_tags($input['form_button_bg']) : ($old_settings['form_button_bg'] ?? 'linear-gradient(90deg, #0073aa, #00c6ff)');
    $new_settings['form_label_color'] = isset($input['form_label_color']) ? wp_strip_all_tags($input['form_label_color']) : ($old_settings['form_label_color'] ?? '#fff');
    $new_settings['form_h_color'] = isset($input['form_h_color']) ? wp_strip_all_tags($input['form_h_color']) : ($old_settings['form_h_color'] ?? '#fff');
    $new_settings['form_p_color'] = isset($input['form_p_color']) ? wp_strip_all_tags($input['form_p_color']) : ($old_settings['form_p_color'] ?? '#fff');
    $new_settings['form_button_secondary_bg'] = isset($input['form_button_secondary_bg']) ? wp_strip_all_tags($input['form_button_secondary_bg']) : ($old_settings['form_button_secondary_bg'] ?? 'rgba(255, 255, 255, 0.15)');
    $new_settings['form_button_secondary_text'] = isset($input['form_button_secondary_text']) ? wp_strip_all_tags($input['form_button_secondary_text']) : ($old_settings['form_button_secondary_text'] ?? '#fff');
    $new_settings['form_input_bg'] = isset($input['form_input_bg']) ? wp_strip_all_tags($input['form_input_bg']) : ($old_settings['form_input_bg'] ?? 'rgba(0, 0, 0, 0.2)');
    $new_settings['form_input_border'] = isset($input['form_input_border']) ? wp_strip_all_tags($input['form_input_border']) : ($old_settings['form_input_border'] ?? '#888');
    $new_settings['form_input_text'] = isset($input['form_input_text']) ? wp_strip_all_tags($input['form_input_text']) : ($old_settings['form_input_text'] ?? '#fff');
    $new_settings['form_error_bg'] = isset($input['form_error_bg']) ? wp_strip_all_tags($input['form_error_bg']) : ($old_settings['form_error_bg'] ?? 'rgba(220, 53, 69, 0.5)');
    $new_settings['form_error_border'] = isset($input['form_error_border']) ? wp_strip_all_tags($input['form_error_border']) : ($old_settings['form_error_border'] ?? 'rgba(220, 53, 69, 0.8)');
    $new_settings['form_error_text'] = isset($input['form_error_text']) ? wp_strip_all_tags($input['form_error_text']) : ($old_settings['form_error_text'] ?? '#fff');
    $new_settings['form_button_text_color'] = isset($input['form_button_text_color']) ? wp_strip_all_tags($input['form_button_text_color']) : ($old_settings['form_button_text_color'] ?? '#fff');
    $new_settings['form_switcher_color'] = isset($input['form_switcher_color']) ? wp_strip_all_tags($input['form_switcher_color']) : ($old_settings['form_switcher_color'] ?? '#fff');
}

    if ( isset($input['captcha_type']) ) {
    $allowed_captcha_types = ['none', 'math', 'honeypot', 'recaptcha_v3']; 
    $new_settings['captcha_type'] = in_array($input['captcha_type'], $allowed_captcha_types) ? $input['captcha_type'] : 'none';
    $new_settings['recaptcha_site_key'] = isset($input['recaptcha_site_key']) ? sanitize_text_field(trim($input['recaptcha_site_key'])) : '';
    $new_settings['recaptcha_secret_key'] = isset($input['recaptcha_secret_key']) ? sanitize_text_field(trim($input['recaptcha_secret_key'])) : '';
    }
    
    // --- تب ورود اجتماعی (ایتا) ---
    if ( isset( $input['eitaa_login_enable'] ) || isset( $input['eitaa_tokens'] ) ) {
  $new_settings['eitaa_login_enable'] = ( isset( $input['eitaa_login_enable'] ) && $input['eitaa_login_enable'] === 'yes' ) ? 'yes' : 'no';

    $new_settings['eitaa_tokens'] = [];
    if ( isset( $input['eitaa_tokens'] ) && is_array( $input['eitaa_tokens'] ) ) {
      foreach ($input['eitaa_tokens'] as $token_data) {
         if ( ! empty($token_data['token']) ) {
                $new_settings['eitaa_tokens'][] = [
                'name'  => sanitize_text_field($token_data['name']),
                'token' => sanitize_text_field($token_data['token']),
                ];
          }
       }
    }
}
    // --- تب ورود اجتماعی (گوگل) ---
     if ( isset( $input['google_client_id'] ) || isset( $input['google_client_secret'] ) ) {
          $new_settings['google_login_enable'] = ( isset( $input['google_login_enable'] ) && $input['google_login_enable'] === 'yes' ) ? 'yes' : 'no';
          $new_settings['google_client_id'] = isset($input['google_client_id']) ? sanitize_text_field(trim($input['google_client_id'])) : '';
          $new_settings['google_client_secret'] = isset($input['google_client_secret']) ? sanitize_text_field(trim($input['google_client_secret'])) : '';
      }
      // --- جدید: ذخیره تنظیمات بله OTP ---
    if ( isset( $input['bale_otp_client_id'] ) || isset( $input['bale_otp_client_secret'] ) ) {
        $new_settings['bale_otp_enable'] = ( isset( $input['bale_otp_enable'] ) && $input['bale_otp_enable'] === 'yes' ) ? 'yes' : 'no';
        $new_settings['bale_otp_client_id'] = isset($input['bale_otp_client_id']) ? sanitize_text_field(trim($input['bale_otp_client_id'])) : '';
        $new_settings['bale_otp_client_secret'] = isset($input['bale_otp_client_secret']) ? sanitize_text_field(trim($input['bale_otp_client_secret'])) : '';
    }
      // --- تب تنظیمات ایمیل ---
        if ( isset($input['email_otp_enable']) || isset($input['email_send_method']) ) {
        $new_settings['email_otp_enable'] = (isset($input['email_otp_enable']) && $input['email_otp_enable'] === 'yes') ? 'yes' : 'no';
        
        if (isset($input['email_send_method'])) {
            $new_settings['email_send_method'] = in_array($input['email_send_method'], ['default', 'smtp'], true) ? $input['email_send_method'] : 'default';
        }

        // ذخیره تنظیمات فرستنده
        $new_settings['email_from_name'] = isset($input['email_from_name']) ? sanitize_text_field($input['email_from_name']) : get_bloginfo('name');
        if (isset($input['email_from_address']) && is_email($input['email_from_address'])) {
            $new_settings['email_from_address'] = sanitize_email($input['email_from_address']);
        }
        
        // ذخیره تنظیمات SMTP
        $new_settings['smtp_host'] = isset($input['smtp_host']) ? sanitize_text_field($input['smtp_host']) : '';
        $new_settings['smtp_port'] = isset($input['smtp_port']) ? absint($input['smtp_port']) : 587;
        $new_settings['smtp_encryption'] = isset($input['smtp_encryption']) && in_array($input['smtp_encryption'], ['none', 'ssl', 'tls']) ? $input['smtp_encryption'] : 'tls';
        $new_settings['smtp_user'] = isset($input['smtp_user']) ? sanitize_text_field($input['smtp_user']) : '';
        // فقط اگر رمز جدیدی وارد شده بود، آن را ذخیره کن
        if ( !empty($input['smtp_pass']) ) {
            $new_settings['smtp_pass'] = sanitize_text_field($input['smtp_pass']);
        }

        // ذخیره قالب ایمیل
        $new_settings['email_otp_subject'] = isset($input['email_otp_subject']) ? sanitize_text_field($input['email_otp_subject']) : '';
        if (isset($input['email_otp_body'])) {
            $new_settings['email_otp_body'] = wp_kses_post($input['email_otp_body']);
        }
    }
      // --- تب فیلدهای دلخواه ---
        if ( isset( $input['is_fields_tab_submitted'] ) ) {
            $new_settings['enable_name_fields'] = (isset($input['enable_name_fields']) && $input['enable_name_fields'] === 'yes') ? 'yes' : 'no';

            unset($new_settings['enable_first_name']);
            unset($new_settings['enable_last_name']);
            $new_settings['enable_username'] = (isset($input['enable_username']) && $input['enable_username'] === 'yes') ? 'yes' : 'no';
            $new_settings['required_username'] = (isset($input['required_username']) && $input['required_username'] === 'yes') ? 'yes' : 'no';
            // ذخیره تنظیمات نام
            $new_settings['required_name_fields'] = (isset($input['required_name_fields']) && $input['required_name_fields'] === 'yes') ? 'yes' : 'no';
            $new_settings['force_persian_name_fields'] = (isset($input['force_persian_name_fields']) && $input['force_persian_name_fields'] === 'yes') ? 'yes' : 'no';
            
            $new_settings['enable_custom_fields_global'] = (isset($input['enable_custom_fields_global']) && $input['enable_custom_fields_global'] === 'yes') ? 'yes' : 'no';
            // ذخیره JSON فیلدها (به صورت خام چون JSON است، اما می‌توانیم کمی تمیزکاری کنیم)
            if ( isset($input['custom_fields_global_json']) ) {
                // ما اینجا stripslashes می‌کنیم چون وردپرس گاهی اسلش اضافه می‌کند و JSON خراب می‌شود
                $new_settings['custom_fields_global_json'] = wp_unslash($input['custom_fields_global_json']);
            }

        }
        
    return $new_settings;
}

// HTML for the settings page
function jay_login_register_settings_page_html() {
    if ( ! current_user_can( 'jay_login_register_manage_settings' ) ) {
        return;
    }

    $active_tab = 'sms_settings'; 
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'jay_relog_main_settings_tabs_nonce' ) ) {
        if ( isset( $_GET['tab'] ) ) {
            $active_tab = sanitize_key( $_GET['tab'] );
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <nav class="nav-tab-wrapper">
            <?php
            $base_url = 'admin.php?page=jay_login_register_settings_page';
            $sms_url = wp_nonce_url( admin_url($base_url . '&tab=sms_settings'), 'jay_relog_main_settings_tabs_nonce' );
            $general_url = wp_nonce_url( admin_url($base_url . '&tab=general_settings'), 'jay_relog_main_settings_tabs_nonce' );
            $style_url = wp_nonce_url( admin_url($base_url . '&tab=style_settings'), 'jay_relog_main_settings_tabs_nonce' );
            $captcha_url = wp_nonce_url( admin_url($base_url . '&tab=captcha_settings'), 'jay_relog_main_settings_tabs_nonce' );
            $social_url = wp_nonce_url( admin_url($base_url . '&tab=social_login'), 'jay_relog_main_settings_tabs_nonce' );
            $email_url = wp_nonce_url( admin_url($base_url . '&tab=email_settings'), 'jay_relog_main_settings_tabs_nonce' );
            $fields_url = wp_nonce_url( admin_url($base_url . '&tab=custom_fields'), 'jay_relog_main_settings_tabs_nonce' );
            ?>
          <a href="<?php echo esc_url($sms_url); ?>" class="nav-tab <?php echo $active_tab === 'sms_settings' ? 'nav-tab-active' : ''; ?>">تنظیمات پنل پیامک</a>
          <a href="<?php echo esc_url($general_url); ?>" class="nav-tab <?php echo $active_tab === 'general_settings' ? 'nav-tab-active' : ''; ?>">تنظیمات عمومی</a>
          <a href="<?php echo esc_url($fields_url); ?>" class="nav-tab <?php echo $active_tab === 'custom_fields' ? 'nav-tab-active' : ''; ?>">فیلدهای دلخواه</a>
          <a href="<?php echo esc_url($style_url); ?>" class="nav-tab <?php echo $active_tab === 'style_settings' ? 'nav-tab-active' : ''; ?>">استایل</a>
          <a href="<?php echo esc_url($captcha_url); ?>" class="nav-tab <?php echo $active_tab === 'captcha_settings' ? 'nav-tab-active' : ''; ?>">تنظیمات کپچا</a>
          <a href="<?php echo esc_url($social_url); ?>" class="nav-tab <?php echo $active_tab === 'social_login' ? 'nav-tab-active' : ''; ?>">ورود اجتماعی</a>
          <a href="<?php echo esc_url($email_url); ?>" class="nav-tab <?php echo $active_tab === 'email_settings' ? 'nav-tab-active' : ''; ?>">تنظیمات ایمیل</a>
          

    </nav>

        <form action="options.php" method="post">
            <?php
            settings_fields( 'jay_login_register_settings_group' );

            // --- تغییر ۳: فقط بخش مربوط به تب فعال را نمایش می‌دهیم ---
            if ($active_tab === 'sms_settings') {
                do_settings_sections( 'jay_login_register_main_section' );
            } elseif ($active_tab === 'general_settings') {
                do_settings_sections( 'jay_login_register_general_section' );
            } elseif ($active_tab === 'style_settings') {
                do_settings_sections( 'jay_login_register_style_section' );
            } elseif ($active_tab === 'captcha_settings') {
                do_settings_sections( 'jay_login_register_captcha_section' );
            } elseif ($active_tab === 'social_login') {
                do_settings_sections( 'jay_login_register_social_section' );
            } elseif ($active_tab === 'email_settings') {
                do_settings_sections( 'jay_login_register_email_section' );
            } elseif ($active_tab === 'custom_fields') {
                do_settings_sections( 'jay_login_register_fields_section' );
            }
            
            submit_button( 'ذخیره تنظیمات' );
            ?>
        </form>
    </div>
    <?php
}

// token tedad
function jay_login_register_otp_length_render() {
    $options = get_option('jay_login_register_settings');
    $length = $options['otp_length'] ?? '4'; 
    ?>
    <select name="jay_login_register_settings[otp_length]">
        <option value="4" <?php selected($length, '4'); ?>>۴ رقمی</option>
        <option value="5" <?php selected($length, '5'); ?>>۵ رقمی</option>
        <option value="6" <?php selected($length, '6'); ?>>۶ رقمی</option>
        <option value="7" <?php selected($length, '7'); ?>>۷ رقمی</option>
        <option value="8" <?php selected($length, '8'); ?>>۸ رقمی</option>
    </select>
    <?php
}

/**
 * محتوای HTML صفحه کنترل دسترسی را نمایش می‌دهد.
 */
function jay_login_register_access_control_page_html() {
    if ( ! current_user_can( 'jay_login_register_manage_access_control' ) ) {
        return;
    }

   $active_tab = 'admin_access';
     if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'jay_login_register_tabs_nonce' ) ) {
        // ۳. فقط در صورت معتبر بودن نانس، مقدار تب را از URL می‌خوانیم
        if ( isset( $_GET['tab'] ) ) {
            $active_tab = sanitize_key( $_GET['tab'] );
        }
    }

    ?>
    <div class="jay-login-register-wrap">
        <h1><?php esc_html_e( 'کنترل دسترسی به پیشخوان وردپرس', 'jay-login-register' ); ?></h1>
           <nav class="nav-tab-wrapper">
            <?php
            $admin_access_url = wp_nonce_url( admin_url( 'admin.php?page=jay_login_register_access_control&tab=admin_access' ), 'jay_login_register_tabs_nonce' );
            $user_columns_url = wp_nonce_url( admin_url( 'admin.php?page=jay_login_register_access_control&tab=user_columns' ), 'jay_login_register_tabs_nonce' );
            $custom_columns_url = wp_nonce_url( admin_url( 'admin.php?page=jay_login_register_access_control&tab=custom_columns' ), 'jay_login_register_tabs_nonce' );
            ?>
         <a href="<?php echo esc_url( $admin_access_url ); ?>" class="nav-tab <?php echo $active_tab === 'admin_access' ? 'nav-tab-active' : ''; ?>">
             دسترسی به پیشخوان
         </a>
         <a href="<?php echo esc_url( $user_columns_url ); ?>" class="nav-tab <?php echo $active_tab === 'user_columns' ? 'nav-tab-active' : ''; ?>">
             مدیریت ستون های کاربران
        </a>
        <a href="<?php echo esc_url( $custom_columns_url ); ?>" class="nav-tab <?php echo $active_tab === 'custom_columns' ? 'nav-tab-active' : ''; ?>">ستون‌های سفارشی</a>
        </nav>
        
           <div class="tab-content">
            <?php if ( $active_tab === 'admin_access' ) : ?>
                
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'jay_login_register_access_settings_group' );
                    $options = get_option('jay_login_register_access_settings', ['allow_admin_access' => []]);
                    $allowed_roles = $options['allow_admin_access'];
                    $hide_wp_login = $options['hide_wp_login'] ?? 'no';
                    ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'امنیت صفحه ورود', 'jay-login-register' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="hide_wp_login">
                                            <input type="checkbox" name="jay_login_register_access_settings[hide_wp_login]" id="hide_wp_login" value="yes" <?php checked( $hide_wp_login, 'yes' ); ?>>
                                            <?php esc_html_e( 'مخفی کردن wp-login.php و هدایت به برگه ورود سفارشی', 'jay-login-register' ); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'با فعال‌سازی این گزینه، دسترسی مستقیم به wp-login.php مسدود شده و کاربران به برگه‌ای که در تنظیمات عمومی انتخاب کرده‌اید، هدایت می‌شوند.', 'jay-login-register' ); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <hr>
                     <p><?php esc_html_e( 'در این بخش می‌توانید مشخص کنید کدام نقش‌های کاربری اجازه دسترسی به محیط پیشخوان را دارند.', 'jay-login-register' ); ?></p>
                    <p><strong><?php esc_html_e( 'توجه:', 'jay-login-register' ); ?></strong> <?php esc_html_e( 'نقش "مدیرکل" همیشه و تحت هر شرایطی به پیشخوان دسترسی خواهد داشت.', 'jay-login-register' ); ?></p>

                    <h2><?php esc_html_e( 'دسترسی نقش‌ها به پیشخوان', 'jay-login-register' ); ?></h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'نقش‌های مجاز', 'jay-login-register' ); ?></th>
                                <td>
                                    <fieldset>
                                        <?php
                                        $editable_roles = get_editable_roles();
                                        foreach ( $editable_roles as $role_slug => $role_details ) {
                                            if ( 'administrator' === $role_slug ) continue;
                                            $is_checked = in_array( $role_slug, $allowed_roles, true );
                                            ?>
                                            <label for="role-<?php echo esc_attr( $role_slug ); ?>">
                                                <input type="checkbox" name="jay_login_register_access_settings[allow_admin_access][]" id="role-<?php echo esc_attr( $role_slug ); ?>" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( $is_checked ); ?>>
                                                <?php echo esc_html( translate_user_role( $role_details['name'] ) ); ?>
                                            </label><br>
                                            <?php
                                        }
                                        ?>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button( 'ذخیره تغییرات' ); ?>
                </form>

            <?php elseif ( $active_tab === 'user_columns' ) : ?>
                
                <form action="options.php" method="post">
                    <h2>مدیریت ستون‌های جدول کاربران</h2>
                    <p>ستون‌هایی را که می‌خواهید از جدول کاربران در صفحه «همه کاربران» پنهان شوند، انتخاب کنید.</p>
                    <?php
                    settings_fields( 'jay_login_register_user_columns_group' );
                    $options = get_option('jay_login_register_user_columns_settings', ['hidden_columns' => []]);
                    $hidden_columns = $options['hidden_columns'];
                    $all_columns = jay_login_register_get_all_user_columns();
                    ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">ستون‌های قابل پنهان‌سازی</th>
                                <td>
                                    <fieldset>
                                        <?php foreach ( $all_columns as $slug => $label ) : ?>
                                            <?php $is_checked = in_array( $slug, $hidden_columns, true ); ?>
                                            <label for="col-<?php echo esc_attr( $slug ); ?>">
                                                <input type="checkbox" name="jay_login_register_user_columns_settings[hidden_columns][]" id="col-<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_checked ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </label><br>
                                        <?php endforeach; ?>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button( 'ذخیره تغییرات' ); ?>
                </form>

            <?php elseif ( $active_tab === 'custom_columns' ) : ?>
            <form action="options.php" method="post">
                    <h2>افزودن ستون‌های سفارشی به جدول کاربران</h2>
                    <p>در این بخش می‌توانید ستون‌های دلخواه خود را بر اساس متادیتای کاربران به جدول اضافه کنید.</p>
                    <?php
                    settings_fields( 'jay_login_register_custom_columns_group' );
                    $options = get_option( 'jay_login_register_custom_columns_settings', [ 'columns' => [] ] );
                    $columns = $options['columns'];
                    ?>
                    <div id="custom-columns-repeater">
                        <div class="repeater-rows">
                            <?php if ( ! empty( $columns ) ) : ?>
                                <?php foreach ( $columns as $index => $column ) : ?>
                            <div class="repeater-row">
                                <input type="text" name="jay_login_register_custom_columns_settings[columns][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $column['name'] ); ?>" placeholder="نام ستون (مثال: وضعیت اشتراک)">
                                <input type="text" name="jay_login_register_custom_columns_settings[columns][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $column['key'] ); ?>" placeholder="متا کی (مثال: subscription_status)">
                                <select name="jay_login_register_custom_columns_settings[columns][<?php echo esc_attr( $index ); ?>][display]">
                                    <option value="value" <?php selected( $column['display'], 'value' ); ?>>نمایش مقدار</option>
                                    <option value="icon" <?php selected( $column['display'], 'icon' ); ?>>نمایش آیکون وضعیت</option>
                                </select>
                                <button type="button" class="button button-secondary remove-row">حذف</button>
                            </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button button-primary add-row">افزودن ستون جدید</button>
                    </div>

                    <template id="repeater-template">
                        <div class="repeater-row">
                            <input type="text" name="jay_login_register_custom_columns_settings[columns][__INDEX__][name]" placeholder="نام ستون (مثال: وضعیت اشتراک)">
                            <input type="text" name="jay_login_register_custom_columns_settings[columns][__INDEX__][key]" placeholder="متا کی (مثال: subscription_status)">
                            <select name="jay_login_register_custom_columns_settings[columns][__INDEX__][display]">
                                <option value="value">نمایش مقدار</option>
                                <option value="icon">نمایش آیکون وضعیت</option>
                            </select>
                            <button type="button" class="button button-secondary remove-row">حذف</button>
                        </div>
                    </template>
                    <?php submit_button( 'ذخیره تغییرات' ); ?>
                </form>
            <?php endif; ?>

        </div>
    </div>
    <?php
}



/**
 * جدید: نمایش گزینه‌های انتخاب نوع کپچا (نسخه کامل)
 */
function jay_login_register_captcha_render() {
    $options = get_option('jay_login_register_settings');
    $captcha_type = $options['captcha_type'] ?? 'none';
    $site_key = $options['recaptcha_site_key'] ?? '';
    $secret_key = $options['recaptcha_secret_key'] ?? '';

    // اصلاح باگ: استایل مخفی‌سازی را به صورت متغیر تعریف می‌کنیم تا خواناتر باشد
    $recaptcha_display_style = ($captcha_type === 'recaptcha_v3') ? '' : 'display: none;';
    ?>
    <fieldset>
        <p>
            <label>
                <input type="radio" name="jay_login_register_settings[captcha_type]" value="none" <?php checked($captcha_type, 'none'); ?>>
                <strong>غیرفعال</strong>
            </label>
            <br>
            <span class="description">هیچ کپچایی در فرم استفاده نمی‌شود.</span>
        </p>
        <p>
            <label>
                <input type="radio" name="jay_login_register_settings[captcha_type]" value="math" <?php checked($captcha_type, 'math'); ?>>
                <strong>کپچای ریاضی </strong>
            </label>
            <br>
            <span class="description jay-login-register-description-note">یک سوال ریاضی که یکی از (ضرب / تقسیم/منها/جمع) به فرم اضافه می‌کند که جلوی ربات‌ها را می‌گیرد.</span>
        </p>
        <p>
            <label>
                <input type="radio" name="jay_login_register_settings[captcha_type]" value="honeypot" <?php checked($captcha_type, 'honeypot'); ?>>
                <strong>کپچای داخلی نامرئی (Honeypot)</strong>
            </label>
            <br>
            <span class="description">یک روش امنیتی نامرئی برای به تله انداختن ربات‌ها بدون ایجاد مزاحمت برای کاربر.(این کپچا دومرحله ای است.اگر تله را ربات پر نکند مرحله بعدی زمان پر کردن فیلد را محاسبه میکند.که به طبع ربات ها اینکار را سریع انجام میدهند و باز هم در تله ما گیر می افتند )</span>
        </p>
        <p>
            <label>
                <input type="radio" name="jay_login_register_settings[captcha_type]" value="recaptcha_v3" <?php checked($captcha_type, 'recaptcha_v3'); ?>>
                <strong style="color: #2271b1;">Google reCAPTCHA v3 (بسیار امن - پیشنهادی)</strong>
            </label>
            <br>
            <span class="description">کپچای نامرئی گوگل که بدون ایجاد مزاحمت برای کاربر، ربات‌ها را شناسایی می‌کند.امن اما ماهانه 10 هزارتا رایگان دارید</span>
        </p>
    </fieldset>

    <div id="jay-login-register-recaptcha-fields" style="<?php echo esc_attr($recaptcha_display_style); ?>">
        <hr>
        <p>برای استفاده از reCAPTCHA v3، باید کلیدهای سایت خود را از گوگل دریافت کنید.</p>
        <p>
            <a href="https://www.google.com/recaptcha/admin/create" target="_blank" class="button button-secondary">دریافت کلید از گوگل</a>
            
           
            <button type="button" class="button button-secondary" id="toggle-recaptcha-instructions">راهنمای دریافت کلید</button>
            <a href="https://www.google.com/recaptcha/admin" target="_blank" class="button button-secondary">
                اعتبار باقی مانده   
            </a>

            
        </p>
        <div id="recaptcha-instructions-panel" style="display: none;">
            <p>پس از ورود به لینک بالا با حساب گوگل خود، فرم را به شکل زیر پر کنید:</p>
            <ul>
                <li><strong>Label:</strong> یک نام دلخواه برای سایت خود وارد کنید (مثلاً: My Website).</li>
                <li><strong>reCAPTCHA type:</strong> گزینه <strong>"reCAPTCHA v3"</strong> را انتخاب کنید.</li>
                <li><strong>Domains:</strong> آدرس دامنه سایت خود را بدون http وارد کنید (مثلاً: `yourwebsite.com`).</li>
            </ul>
            <p>پس از ثبت، گوگل دو کلید به شما می‌دهد که باید در فیلدهای زیر وارد کنید.</p>
        </div>
      

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="recaptcha_site_key">Site Key</label></th>
                <td><input type="text" id="recaptcha_site_key" name="jay_login_register_settings[recaptcha_site_key]" value="<?php echo esc_attr($site_key); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="recaptcha_secret_key">Secret Key</label></th>
                <td><input type="password" id="recaptcha_secret_key" name="jay_login_register_settings[recaptcha_secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" /></td>
            </tr>
        </table>
    </div>
    <?php
}

/**
 * نمایش کارت‌های انتخاب قالب فرم.
 */
function jay_login_register_style_templates_render() {
  $options = get_option('jay_login_register_settings');
  $current_style = $options['form_style'] ?? 'glass'; 

  // در آینده می‌توانیم این آرایه را با قالب‌های بیشتر گسترش دهیم
  $templates = [
  'glass' => [
  'name' => 'استایل 1',
  'thumb' => JAY_LOGIN_REGISTER_URL . 'assets/images/style-glass-thumb.jpg',
  ],
 ];
  ?>
  <div class="jay-login-register-style-cards-wrapper">
  <?php foreach ($templates as $slug => $template) : ?>
  <div class="style-card <?php echo ($current_style === $slug) ? 'selected' : ''; ?>">
  <div class="style-card-thumb">
  <img src="<?php echo esc_url($template['thumb']); ?>" alt="<?php echo esc_attr($template['name']); ?>">
  </div>
  <div class="style-card-footer">
  <label>
  <input type="radio" name="jay_login_register_settings[form_style]" value="<?php echo esc_attr($slug); ?>" <?php checked($current_style, $slug); ?>>
  <?php echo esc_html($template['name']); ?>
  </label>
  <a href="<?php echo esc_url(admin_url('admin.php?page=jay_login_register_style_customizer')); ?>" class="button button-secondary edit-style-button jay-login-register-edit-style-button" <?php disabled($current_style, $slug, false); ?>>ویرایش</a>
  </div>
  </div>
  <?php endforeach; ?>
 </div>
  <?php
}

/**
 * جدید: نمایش گزینه‌های انتخاب استایل فیلد OTP
 */
function jay_login_register_otp_input_style_render() {
    $options = get_option('jay_login_register_settings');
    $style = $options['otp_input_style'] ?? 'single';
    ?>
    <fieldset>
        <label>
            <input type="radio" name="jay_login_register_settings[otp_input_style]" value="single" <?php checked($style, 'single'); ?>>
            تک فیلدی (پیش‌فرض)
            <p class="description">یک فیلد ورودی برای کل کد تایید نمایش داده می‌شود.</p>
        </label>
        <br>
        <label>
            <input type="radio" name="jay_login_register_settings[otp_input_style]" value="multiple" <?php checked($style, 'multiple'); ?>>
            چند فیلدی (پیشرفته)
            <p class="description">به تعداد ارقام کد تایید، فیلدهای تک کاراکتری نمایش داده می‌شود.</p>
        </label>
    </fieldset>
    <?php
}
/**
 * محتوای HTML صفحه شخصی‌سازی قالب را با ساختار آکاردئونی نمایش می‌دهد.
 */
function jay_login_register_style_customizer_page_html() {
    if ( ! current_user_can('jay_login_register_manage_settings') ) {
        return;
    }

    $options = get_option('jay_login_register_settings');
    $current_style_slug = $options['form_style'] ?? 'glass';
    $templates = [
        'glass' => ['name' => 'استایل 1'],
    ];
    $current_template_name = $templates[$current_style_slug]['name'] ?? 'قالب انتخاب شده';
    $back_url = wp_nonce_url(admin_url('admin.php?page=jay_login_register_settings_page&tab=style_settings'), 'jay_relog_main_settings_tabs_nonce');
    ?>
    <div class="wrap">
        <div class="jay-relog-customizer-header">
            <div class="jay-relog-header-main">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <a href="<?php echo esc_url($back_url); ?>" class="jay-relog-back-button page-title-action">
                    <span class="dashicons dashicons-arrow-right-alt"></span> بازگشت به انتخاب قالب
                </a>
            </div>
            <div class="jay-relog-editing-notice">
                <p>شما در حال ویرایش تنظیمات برای قالب "<strong><?php echo esc_html($current_template_name); ?></strong>" هستید.</p>
            </div>
        </div>

        <form action="options.php" method="post">
            <?php settings_fields('jay_login_register_settings_group'); ?>

            <div class="jay-login-register-accordion" style="margin-top: 15px;">
                <h4 class="accordion-title">تنظیمات کلی و پس‌زمینه</h4>
                <div class="accordion-content">
                    <table class="form-table">
                        <?php do_settings_fields('jay_login_register_style_customizer_page', 'jay_login_register_customizer_section'); ?>
                    </table>
                </div>
            </div>
            
            <div class="jay-login-register-accordion" style="margin-top: 15px;">
                <h4 class="accordion-title">متن‌ها و دکمه‌ها</h4>
                <div class="accordion-content">
                    <table class="form-table">
                        <?php do_settings_fields('jay_login_register_style_customizer_page', 'jay_login_register_customizer_elements_section'); ?>
                    </table>
                </div>
            </div>

            <div class="jay-login-register-accordion" style="margin-top: 15px;">
                <h4 class="accordion-title">پیام‌های خطا</h4>
                <div class="accordion-content">
                    <table class="form-table">
                        <?php do_settings_fields('jay_login_register_style_customizer_page', 'jay_login_register_customizer_error_section'); ?>
                    </table>
                </div>
            </div>
            
            <?php submit_button('ذخیره تغییرات'); ?>
                         <?php
     $reset_url = wp_nonce_url(admin_url('admin.php?page=jay_login_register_style_customizer&action=jay_relog_reset_styles'), 'jay_relog_reset_styles_nonce');
     ?>
     <a href="<?php echo esc_url($reset_url); ?>" id="jay-relog-reset-styles-button" class="button button-secondary" style="margin-right: 10px; color: #d63638; border-color: #d63638;">بازگردانی به پیش‌فرض</a>

        </form>
    </div>
    <?php
}
/**
 * درخواست بازگردانی تنظیمات استایل به حالت پیش‌فرض را مدیریت می‌کند.
 */
add_action('admin_init', 'jay_login_register_handle_style_reset');
function jay_login_register_handle_style_reset() {
    // 1. فقط زمانی اجرا شو که درخواست ریست ارسال شده باشد
    if ( ! isset($_GET['action']) || $_GET['action'] !== 'jay_relog_reset_styles' ) {
        return;
    }
    if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'jay_relog_reset_styles_nonce') ) {
        wp_die('خطای امنیتی!');
    }

    if ( ! current_user_can('jay_login_register_manage_settings') ) {
        wp_die('شما اجازه انجام این کار را ندارید.');
    }

    $settings = get_option('jay_login_register_settings', []);
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

    $new_settings = array_merge($settings, $defaults);
    
    // 7. ذخیره تنظیمات جدید
    update_option('jay_login_register_settings', $new_settings);
    wp_safe_redirect(admin_url('admin.php?page=jay_login_register_style_customizer&settings-updated=reset'));
    exit;
}
/**
 * یک فیلد عددی را رندر می‌کند.
 */
function jay_login_register_numberfield_render($args) {
    $options = get_option('jay_login_register_settings');
    $name = $args['name'];
    $value = isset($options[$name]) ? absint($options[$name]) : ($args['default'] ?? 0);
    printf(
        '<input type="number" name="jay_login_register_settings[%s]" value="%s" class="small-text">',
        esc_attr($name),
        esc_attr($value)
    );
}

/**
 * جدید: رندر فیلد انتخاب برگه "تغییر شماره"
 */
function jay_login_register_change_phone_page_render() {
  $options = get_option('jay_login_register_settings');
  $selected_page = isset($options['change_phone_page_id']) ? absint($options['change_phone_page_id']) : 0;

  wp_dropdown_pages([
  'name' => 'jay_login_register_settings[change_phone_page_id]',
  'selected' => esc_attr($selected_page),
  'show_option_none' => '— انتخاب نشده —',
  'option_none_value'=> '0',
  ]);
  echo '<p class="description">برگه‌ای که شورت‌کد [jay_login_register_user_panel] در آن قرار دارد.</p>';
}

/**
 * جدید: رندر فیلد انتخاب برگه "خروج"
 */
function jay_login_register_logout_page_render() {
  $options = get_option('jay_login_register_settings');
  $selected_page = isset($options['logout_page_id']) ? absint($options['logout_page_id']) : 0;

  wp_dropdown_pages([
  'name' => 'jay_login_register_settings[logout_page_id]',
  'selected' => esc_attr($selected_page),
  'show_option_none' => '— انتخاب نشده —',
  'option_none_value'=> '0',
  ]);
  echo '<p class="description">یک برگه خالی که نامک (slug) آن `logout` باشد. افزونه به طور خودکار آن را مدیریت می‌کند.</p>';
}


/**
 * نمایش توضیحات بخش فیلدها + فیلد مخفی برای تشخیص تب
 */
function jay_login_register_fields_section_callback() {
    echo '<p>در این بخش می‌توانید مشخص کنید چه اطلاعات اضافی از کاربر  دریافت شود.</p>';
    // این فیلد مخفی به ما می‌گوید که کاربر در حال ذخیره این تب خاص است
    echo '<input type="hidden" name="jay_login_register_settings[is_fields_tab_submitted]" value="1">';
}

/**
 * تابع عمومی برای رندر چک‌باکس ساده
 */
function jay_login_register_checkbox_render($args) {
    $options = get_option('jay_login_register_settings');
    $name = $args['name'];
    $label = $args['label'] ?? '';
    $value = isset($options[$name]) && $options[$name] === 'yes';
    ?>
    <label>
        <input type="checkbox" name="jay_login_register_settings[<?php echo esc_attr($name); ?>]" value="yes" <?php checked($value); ?>>
        <?php echo esc_html($label); ?>
    </label>
    <?php
}

/**
 * جدید: رندر محیط ساخت فیلدهای سفارشی در تنظیمات
 */
function jay_login_register_custom_fields_builder_render() {
    $options = get_option('jay_login_register_settings');
    $is_enabled = isset($options['enable_custom_fields_global']) && $options['enable_custom_fields_global'] === 'yes';
    $json_value = $options['custom_fields_global_json'] ?? '[]';
    
    // اطمینان از اینکه JSON معتبر است
    if ( empty($json_value) || !is_string($json_value) ) {
        $json_value = '[]';
    }
    ?>
    <div id="jay_global_fields_wrapper" style="<?php echo $is_enabled ? '' : 'display:none;'; ?>">
        <div id="jay_global_fields_list" class="jay-fields-builder-container">
            </div>
        
        <button type="button" id="jay_add_global_field_btn" class="button button-secondary">
            <span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span> افزودن فیلد جدید
        </button>
        
        <textarea name="jay_login_register_settings[custom_fields_global_json]" id="jay_custom_fields_global_json" style="display:none;"><?php echo esc_textarea($json_value); ?></textarea>
        
        <p class="description">فیلدها را اضافه کنید. برای فیلدهای انتخابی (Select, Radio, Checkbox) می‌توانید گزینه تعریف کنید.</p>
    </div>

    <?php
}
/**
 * تابع جدید: رندر چک‌باکس اصلی به همراه سوییچ‌های زیرمجموعه (برای تنظیمات اصلی)
 */
function jay_login_register_switch_group_render( $args ) {
    $options = get_option( 'jay_login_register_settings' );
    $main_name = $args['name'];
    $main_label = $args['label'];
    $main_val = isset( $options[ $main_name ] ) && $options[ $main_name ] === 'yes';
    
    // آرایه زیرمجموعه‌ها
    $sub_toggles = isset($args['sub_toggles']) ? $args['sub_toggles'] : [];

    ?>
    <div class="jay-setting-wrapper">
        <label class="jay-main-checkbox-label">
            <input type="checkbox" class="jay-main-trigger" 
                   name="jay_login_register_settings[<?php echo esc_attr( $main_name ); ?>]" 
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
                               name="jay_login_register_settings[<?php echo esc_attr( $sub_name ); ?>]" 
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
