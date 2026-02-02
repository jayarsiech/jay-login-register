<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ثبت شورت‌کد پنل کاربری
 */ 
add_shortcode( 'jay_login_register_user_panel', 'jay_login_register_render_user_panel' );
function jay_login_register_render_user_panel() {

    if ( ! is_user_logged_in() ) {
        return '<div class="jay-login-register-message-box jay-login-register-warning">برای دسترسی به پنل کاربری، ابتدا باید وارد حساب خود شوید.</div>';
    }
    wp_enqueue_style( 'jay-login-register-global-fonts' );
    // ۱. فعال‌سازی فلگ اختصاصی پنل کاربری
    global $jay_login_register_is_user_panel;
    $jay_login_register_is_user_panel = true;

    $panel_settings = get_option( 'jay_login_register_user_panel_settings', [] );
    $show_mobile    = isset($panel_settings['enable_mobile_change']) && $panel_settings['enable_mobile_change'] === 'yes';
    $show_email     = isset($panel_settings['enable_email_change']) && $panel_settings['enable_email_change'] === 'yes';
    $show_password  = isset($panel_settings['enable_password_change']) && $panel_settings['enable_password_change'] === 'yes';
    $show_profile   = isset($panel_settings['enable_profile_info']) && $panel_settings['enable_profile_info'] === 'yes';

    $user_id      = get_current_user_id();
    $current_user = wp_get_current_user();
// تنظیمات منوهای سفارشی
    $custom_menus_json = isset($panel_settings['custom_menus_json']) ? $panel_settings['custom_menus_json'] : '[]';
    $custom_menus = json_decode($custom_menus_json, true);
    $has_custom_menus = is_array($custom_menus) && !empty($custom_menus);
    $show_custom_menus = isset($panel_settings['enable_custom_menus']) && $panel_settings['enable_custom_menus'] === 'yes';
    
    $active_tab = '';
    if ($show_profile) $active_tab = 'profile';
    elseif ($show_mobile) $active_tab = 'mobile';
    elseif ($show_email) $active_tab = 'email';
    elseif ($show_password) $active_tab = 'password';

    ob_start();
    ?>
    <div class="jay-login-register-instagram-panel">
        <div class="jay-instagram-container">
            
            <div class="jay-instagram-sidebar">
                <div class="jay-user-meta-header">
<div class="jay-user-avatar-wrapper"> 
                        <div class="jay-avatar-container" title="برای تغییر عکس کلیک کنید">
                            <?php echo get_avatar( $user_id, 80 ); ?>
                            <div class="jay-avatar-overlay">
                                <span class="dashicons dashicons-camera"></span>
                            </div>
                            <span class="jay-avatar-spinner"></span>
                        </div>
                        <input type="file" id="jay_avatar_upload_input" accept="image/jpeg, image/png, image/gif" style="display:none;">
                        
                        <div class="jay-avatar-actions" style="display:none; margin-top:5px;">
                             <small id="jay-avatar-delete-btn" style="color:red; cursor:pointer;">حذف عکس</small>
                        </div>
                    </div>
                    <div class="jay-user-info">
                        <strong><?php echo esc_html( $current_user->display_name ); ?></strong>
                        <span>@<?php echo esc_html( $current_user->user_login ); ?></span>
                        <span class="jay-membership-badge">
                            <?php echo esc_html( jay_login_register_get_user_membership_time( $user_id ) ); ?>
                        </span>
                    </div>
                </div>
                

                     <ul class="jay-instagram-menu">
                         <?php
                    // --- ۱. دکمه بازگشت (انتقال به داخل لیست) ---
                    $settings = get_option('jay_login_register_settings');
                    $redirect_page_id = !empty($settings['redirect_page_id']) ? absint($settings['redirect_page_id']) : 0;
                    $return_url = $redirect_page_id > 0 ? get_permalink( $redirect_page_id ) : home_url('/');
                    $return_label = $redirect_page_id > 0 ? get_the_title( $redirect_page_id ) : 'صفحه نخست';
                    ?>
                    <li class="jay-menu-item jay-return-item-wrapper">
                        <a href="<?php echo esc_url( $return_url ); ?>" style="display:flex; align-items:center; width:100%; height:100%; color:inherit; text-decoration:none;">
                           <?php echo esc_html( $return_label ); ?>
                        </a>
                    </li>

                    <?php
                    // --- ۲. رندر منوهای سفارشی نوع "لینک" (انتقال به داخل لیست) ---
                    if ( $show_custom_menus && $has_custom_menus ) {
                        foreach ( $custom_menus as $menu ) {
                            if ( $menu['type'] === 'link' && jay_login_register_check_menu_logic( $user_id, $menu ) ) {
                                $url = !empty($menu['url']) ? $menu['url'] : '#';
                                echo '<li class="jay-menu-item">';
                                echo '<a href="' . esc_url( $url ) . '" style="display:flex; align-items:center; width:100%; height:100%; color:inherit; text-decoration:none;">';
                                echo esc_html( $menu['label'] );
                                echo '</a>';
                                echo '</li>';
                            }
                        }
                    }
                    ?>
                         <?php
                    // --- رندر منوهای سفارشی نوع "محتوا" (تب‌ها) ---
                    if ( $show_custom_menus && $has_custom_menus ) {
                        foreach ( $custom_menus as $menu ) {
                            // فقط اگر نوع "محتوا" باشد و شرط نمایش برقرار باشد
                            if ( $menu['type'] === 'content' && jay_login_register_check_menu_logic( $user_id, $menu ) ) {
                                $target_id = 'custom-' . $menu['id'];
                                echo '<li class="jay-menu-item" data-target="' . esc_attr($target_id) . '">';
                                // آیکون حذف شد، فقط متن
                                echo esc_html( $menu['label'] );
                                echo '</li>';
                            }
                        }
                    }
                    ?>
                    <?php if ( $show_profile ) : ?>
                    <li class="jay-menu-item <?php echo ($active_tab === 'profile') ? 'active' : ''; ?>" data-target="profile">ویرایش پروفایل</li>
                    <?php endif; ?>
                    <?php if ( $show_mobile ) : ?>
                    <li class="jay-menu-item <?php echo ($active_tab === 'mobile') ? 'active' : ''; ?>" data-target="mobile">تغییر شماره موبایل</li>
                    <?php endif; ?>
                    <?php if ( $show_email ) : ?>
                    <li class="jay-menu-item <?php echo ($active_tab === 'email') ? 'active' : ''; ?>" data-target="email">تغییر ایمیل</li>
                    <?php endif; ?>
                    <?php if ( $show_password ) : ?>
                    <li class="jay-menu-item <?php echo ($active_tab === 'password') ? 'active' : ''; ?>" data-target="password">تغییر رمز عبور</li>
                    <?php endif; ?>
                   <li class="jay-menu-item jay-logout-item">
                        <?php
                        $settings = get_option('jay_login_register_settings');
                        $logout_page_id = !empty($settings['logout_page_id']) ? absint($settings['logout_page_id']) : 0;
                        // اگر برگه خروج تنظیم شده بود، لینکش را بگیر، وگرنه لینک پیش‌فرض وردپرس
                        $logout_url = $logout_page_id ? get_permalink($logout_page_id) : wp_logout_url( home_url() );
                        ?>
                        <a href="<?php echo esc_url( $logout_url ); ?>">خروج از حساب</a>
                    </li>
                </ul>
            </div>

            <div class="jay-instagram-content">
                <?php if ( $show_profile ) : ?>
                <div id="jay-tab-profile" class="jay-tab-content <?php echo ($active_tab === 'profile') ? 'active' : ''; ?>">
                    <h2>ویرایش پروفایل</h2>
<form class="jay-panel-form" id="jay-profile-update-form">
                        
<?php 
// --- بررسی دسترسی ویرایش (قابلیت جدید) ---
    $edit_perm = get_user_meta( $user_id, 'jay_can_edit_profile', true );
    // اگر خالی بود یا 1 بود یعنی اجازه دارد. اگر 0 بود یعنی ندارد.
    $user_can_edit = ( $edit_perm === '' || $edit_perm === '1' );
    
    $disabled_attr = $user_can_edit ? '' : 'disabled readonly style="background:#f5f5f5; color:#777; cursor:not-allowed;"';
    // دریافت تنظیمات
    $can_edit_username = isset($panel_settings['enable_username_edit']) && $panel_settings['enable_username_edit'] === 'yes';
    
    $can_edit_name     = isset($panel_settings['enable_name_edit']) && $panel_settings['enable_name_edit'] === 'yes';
    $force_persian     = isset($panel_settings['force_persian_name']) && $panel_settings['force_persian_name'] === 'yes';
    $req_name          = isset($panel_settings['required_name_edit']) && $panel_settings['required_name_edit'] === 'yes';
    
    $can_edit_nc       = isset($panel_settings['enable_national_code_edit']) && $panel_settings['enable_national_code_edit'] === 'yes';
    $req_nc            = isset($panel_settings['required_national_code_edit']) && $panel_settings['required_national_code_edit'] === 'yes';
    
    $can_edit_passport = isset($panel_settings['enable_passport_edit']) && $panel_settings['enable_passport_edit'] === 'yes';
    $req_passport      = isset($panel_settings['required_passport_edit']) && $panel_settings['required_passport_edit'] === 'yes';
    
    $nc_val = get_user_meta($user_id, 'codemeli', true);
    $pass_val = get_user_meta($user_id, 'gozarname', true);
    
    // مارکر ستاره
    $mark_name = $req_name ? '<span style="color:red; margin-right:3px;">*</span>' : '';
    $mark_nc   = $req_nc ? '<span style="color:red; margin-right:3px;">*</span>' : '';
    $mark_pass = $req_passport ? '<span style="color:red; margin-right:3px;">*</span>' : '';
    ?>

    <?php if ($can_edit_username): ?>
    <div class="jay-login-register-field">
        <label>نام کاربری</label>
        <input type="text" name="jay_panel_username" id="jay_panel_username" class="jay-login-register-input" value="<?php echo esc_attr($current_user->user_login); ?>" dir="ltr" style="text-align:left;" <?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <div id="jay-username-status" class="jay-field-status"></div>
    </div>
    <?php endif; ?>

    <?php if ($can_edit_name): ?>
    <div class="jay-login-register-field">
        <label>نام <?php echo wp_kses_post($mark_name); ?></label>
        <input type="text" name="jay_panel_first_name" id="jay_panel_first_name" class="jay-login-register-input" value="<?php echo esc_attr($current_user->first_name); ?>" <?php echo $force_persian ? 'data-force-persian="true"' : ''; ?> <?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <div id="jay-firstname-status" class="jay-field-status"></div>
    </div>
    <div class="jay-login-register-field">
        <label>نام خانوادگی <?php echo wp_kses_post($mark_name); ?></label>
        <input type="text" name="jay_panel_last_name" id="jay_panel_last_name" class="jay-login-register-input" value="<?php echo esc_attr($current_user->last_name); ?>" <?php echo $force_persian ? 'data-force-persian="true"' : ''; ?> <?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <div id="jay-lastname-status" class="jay-field-status"></div>
    </div>
    <?php endif; ?>

    <?php 
    // تشخیص حالت تلفیقی (هر دو فعال باشند)
    $is_identity_merged = ($can_edit_nc && $can_edit_passport);
    // اگر حالت تلفیقی است، گذرنامه باید مخفی شروع شود (مگر اینکه کدملی خالی باشد و گذرنامه پر باشد)
    $show_passport_initially = false;
    if ($is_identity_merged && empty($nc_val) && !empty($pass_val)) {
        $show_passport_initially = true;
    }
    ?>

    <?php if ($can_edit_nc): ?>
    <div id="jay-panel-nc-group" class="jay-login-register-field" style="position:relative; <?php echo ($is_identity_merged && $show_passport_initially) ? 'display:none;' : ''; ?>">
        <label>کد ملی <?php echo wp_kses_post($mark_nc); ?></label>
        <input type="text" name="jay_panel_national_code" id="jay_panel_national_code" class="jay-login-register-input" value="<?php echo esc_attr($nc_val); ?>" inputmode="numeric" maxlength="10" <?php echo ($is_identity_merged && $show_passport_initially) ? 'disabled' : $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

        <?php if ($is_identity_merged): ?>
            <div style="text-align:left; margin-top:5px;">
                <a href="#" class="jay-panel-switch-identity" data-target="passport" style="font-size:12px; color:#0073aa; text-decoration:none; border-bottom:1px dashed;">ورود با شماره گذرنامه</a>
            </div>
        <?php endif; ?>
        
        <div id="jay-nationalcode-status" class="jay-field-status"></div>
    </div>
    <?php endif; ?>

    <?php if ($can_edit_passport): ?>
    <div id="jay-panel-pass-group" class="jay-login-register-field" style="position:relative; <?php echo ($is_identity_merged && !$show_passport_initially) ? 'display:none;' : ''; ?>">
        <label>شماره گذرنامه <?php echo wp_kses_post($mark_pass); ?></label>
        <input type="text" name="jay_panel_passport" id="jay_panel_passport" class="jay-login-register-input" value="<?php echo esc_attr($pass_val); ?>" dir="ltr" style="text-align:left;" <?php echo ($is_identity_merged && !$show_passport_initially) ? 'disabled' : $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

        <?php if ($is_identity_merged): ?>
            <div style="text-align:left; margin-top:5px;">
                <a href="#" class="jay-panel-switch-identity" data-target="nc" style="font-size:12px; color:#0073aa; text-decoration:none; border-bottom:1px dashed;">ورود با کد ملی</a>
            </div>
        <?php endif; ?>

        <div id="jay-passport-status" class="jay-field-status"></div>
    </div>
    <?php endif; ?>
                        <?php
                        // --- رندر فیلدهای سفارشی ---
                        $custom_fields_json = isset($panel_settings['profile_custom_fields_json']) ? $panel_settings['profile_custom_fields_json'] : '';
                        
                        if ( ! empty($custom_fields_json) ) {
                            $fields = json_decode($custom_fields_json, true);
                            if ( is_array($fields) && !empty($fields) ) {

                            foreach ( $fields as $field ) {
                                
                                 $key   = isset($field['key']) ? sanitize_key($field['key']) : '';
                                    $label = isset($field['label']) ? esc_html($field['label']) : '';
                                    $type  = isset($field['type']) ? $field['type'] : 'text';
                                    $is_required = isset($field['is_required']) && $field['is_required'] == 1;
                                    $req_mark = $is_required ? '<span style="color:red; margin-right:3px;">*</span>' : '';
                                    
                                    if ( empty($key) ) continue;

                                    // --- 1. بررسی شرط متاکی (Server Side Logic) ---
                                    // اگر شرط برقرار نباشد، continue می‌زنیم تا اصلا HTML چاپ نشود
                                    if ( ! empty($field['logic_meta_rules']) && is_array($field['logic_meta_rules']) ) {
                                        $meta_relation = isset($field['logic_meta_relation']) ? $field['logic_meta_relation'] : 'AND';
                                        $meta_passed = ($meta_relation === 'AND') ? true : false;
                                        
                                        foreach ( $field['logic_meta_rules'] as $rule_key ) {
                                            $has_meta = metadata_exists( 'user', $user_id, $rule_key );
                                            // اگر مقدار متا هم مهم بود (خالی نباشد) از get_user_meta استفاده کنید
                                            // $has_meta = !empty(get_user_meta($user_id, $rule_key, true));

                                            if ( $meta_relation === 'AND' ) {
                                                if ( ! $has_meta ) { $meta_passed = false; break; }
                                            } else { // OR
                                                if ( $has_meta ) { $meta_passed = true; break; }
                                            }
                                        }
                                        if ( ! $meta_passed ) continue;
                                    }

                                    // --- 2. آماده‌سازی شرط فیلد (Client Side Logic) ---
                                    $wrapper_attr = '';
                                    $wrapper_style = '';
                                    
                                    if ( ! empty($field['logic_field_rules']) && is_array($field['logic_field_rules']) ) {
                                        // داده‌های منطق را به JSON تبدیل می‌کنیم تا JS بخواند
                                        $logic_data = [
                                            'relation' => isset($field['logic_field_relation']) ? $field['logic_field_relation'] : 'AND',
                                            'rules'    => $field['logic_field_rules']
                                        ];
                                        $wrapper_attr = "data-conditional-logic='" . esc_attr(json_encode($logic_data)) . "'";
                                        
                                        // کلاس jay-conditional-field را اضافه می‌کنیم
                                        $wrapper_style = 'display:none;'; 
                                    }

                                    $saved_val = get_user_meta($user_id, $key, true);
                                    $input_name = 'jay_panel_meta_' . $key;
                                    $field_disabled = isset($user_can_edit) && !$user_can_edit ? 'disabled readonly style="background:#f5f5f5; color:#777; cursor:not-allowed;"' : '';
                                    $description_html = '';
                                    if ( ! empty($field['description']) ) {
                                        $description_html = '<p class="jay-field-description" style="font-size: 11px; color: #666; margin: 4px 0 0 0;">' . esc_html($field['description']) . '</p>';
                                    }

                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    echo '<div class="jay-login-register-field jay-login-register-custom-field-wrapper jay-conditional-wrapper" ' . $wrapper_attr . ' style="' . $wrapper_style . '">';
                                    echo '<label style="display:block; margin-bottom:8px; font-weight:600;">' . esc_html($label) . wp_kses_post($req_mark) . '</label>';

                                    // 1. تاریخ
                                    if ( $type === 'date' ) {
                                        $is_jalali = isset($field['is_jalali']) && $field['is_jalali'] == 1;
                                        
                                   $class_names = 'jay-login-register-input jay-datepicker';
                                    
                                    // نکته: نوع را date می‌گذاریم، اما JS آن را به text تبدیل می‌کند تا تقویم اختصاصی باز شود
                                    echo '<input type="date" name="' . esc_attr($input_name) . '" class="' . esc_attr($class_names) . '" value="' . esc_attr($saved_val) . '" ' . $field_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    if ( $is_jalali ) {
                                        echo ' data-jalali="' . esc_attr( '1' ) . '"';
                                    }
                                    echo '>';
                                    
                                        if ($is_jalali) {
                                            echo '<small style="color:#888; display:block; margin-top:3px;">(تقویم شمسی)</small>';
                                        }
                                    }
                                     
                                  // 2. سلکت (لیست بازشو) - اصلاح شده
                                    elseif ( $type === 'select' ) {
                                        echo '<select name="' . esc_attr($input_name) . '" class="jay-login-register-input" ' . $field_disabled . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo '<option value="">انتخاب کنید...</option>';
                                        
                                        if (!empty($field['options'])) {
                                            foreach ($field['options'] as $opt) {
                                                // بررسی ساختار جدید (آرایه) یا قدیم (رشته)
                                                if (is_array($opt)) {
                                                    $opt_val = isset($opt['value']) ? $opt['value'] : '';
                                                    $opt_label = isset($opt['label']) ? $opt['label'] : '';
                                                } else {
                                                    $opt_val = $opt;
                                                    $opt_label = $opt;
                                                }
                                                
                                                // تمیزکاری مقادیر برای مقایسه دقیق (حذف فاصله‌های احتمالی)
                                                $clean_saved_val = trim( (string) $saved_val );
                                                $clean_opt_val   = trim( (string) $opt_val );

                                                echo '<option value="' . esc_attr($opt_val) . '" ' . selected($clean_saved_val, $clean_opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                                            }
                                        }
                                        echo '</select>';
                                    }

                                    // 3. رادیو باتن (اصلاح شده)
                                    elseif ( $type === 'radio' ) {
                                        if (!empty($field['options'])) {
                                            echo '<div class="jay-radio-group" style="display:flex; flex-direction:column; gap:8px;">';
                                            foreach ($field['options'] as $opt) {
                                                if (is_array($opt)) {
                                                    $opt_val = $opt['value'];
                                                    $opt_label = $opt['label'];
                                                } else {
                                                    $opt_val = $opt;
                                                    $opt_label = $opt;
                                                }
                                                
                                                $checked = ($saved_val == $opt_val) ? 'checked' : '';
                                                
                                                echo '<label style="display:inline-flex; align-items:center; cursor:pointer; font-weight:normal;">';
                                                echo '<input type="radio" name="' . esc_attr($input_name) . '" value="' . esc_attr($opt_val) . '" ' . esc_attr($checked) . ' ' . $field_disabled . ' style="margin-left:8px;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                                                echo esc_html($opt_label);
                                                echo '</label>';
                                            }
                                            echo '</div>';
                                        }
                                    }

                                    // 4. چک‌باکس (اصلاح شده)
                                    elseif ( $type === 'checkbox' ) {
                                        $saved_arr = is_array($saved_val) ? $saved_val : (array) $saved_val;
                                        
                                        if (!empty($field['options'])) {
                                            echo '<div class="jay-checkbox-group" style="display:flex; flex-direction:column; gap:8px;">';
                                            foreach ($field['options'] as $opt) {
                                                if (is_array($opt)) {
                                                    $opt_val = $opt['value'];
                                                    $opt_label = $opt['label'];
                                                } else {
                                                    $opt_val = $opt;
                                                    $opt_label = $opt;
                                                }
                                                
                                                // بررسی وجود مقدار در آرایه ذخیره شده
                                                $checked = in_array($opt_val, $saved_arr) ? 'checked' : '';
                                                
                                                echo '<label style="display:inline-flex; align-items:center; cursor:pointer; font-weight:normal;">';
                                                echo '<input type="checkbox" name="' . esc_attr($input_name) . '[]" value="' . esc_attr($opt_val) . '" ' . esc_attr($checked) . ' ' . $field_disabled . ' style="margin-left:8px;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo esc_html($opt_label);
                                                echo '</label>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    // 6. پاراگراف (Textarea) - جدید
                                    elseif ( $type === 'textarea' ) {
                                        echo '<textarea name="' . esc_attr($input_name) . '" class="jay-login-register-input jay-login-register-textarea" rows="4" ' . $field_disabled . '>' . esc_textarea($saved_val) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
                                    // 7. شماره (Number) - جدید
                                    elseif ( $type === 'number' ) {
                                        // محدودیت طول در فرانت‌اند (برای UX)
                                        $max_len_attr = '';
                                        if ( !empty($field['number_len']) ) {
                                            $max_len_attr = 'maxlength="' . esc_attr($field['number_len']) . '"';
                                        }
                                        echo '<input type="tel" name="' . esc_attr($input_name) . '" class="jay-login-register-input" value="' . esc_attr($saved_val) . '" ' . $field_disabled . ' ' . $max_len_attr . ' inputmode="numeric" placeholder="فقط عدد وارد کنید">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
                                    // 5. متن (پیش‌فرض)
                                    else {
                                        echo '<input type="text" name="' . esc_attr($input_name) . '" class="jay-login-register-input" value="' . esc_attr($saved_val) . '" ' . $field_disabled . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
                                    echo wp_kses_post( $description_html );
                                    echo '</div>';
                                } 
                                
                                
                            }
                        }
                       ?>
                        
                        <?php if ( $user_can_edit ) : ?>
                            <button type="button" class="jay-login-register-button" data-action="panel_update_profile">ذخیره تغییرات</button>
                        <?php else : ?>
                            <div class="jay-message-warning" style="background: #ffe6e6; color: #d63031; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #ffcccc; margin-top: 20px;">
                                <span class="dashicons dashicons-warning" style="vertical-align: middle; margin-left: 5px;"></span>
                                <?php esc_html_e( 'شما اجازه ویرایش مشخصات خود را ندارید. برای تغییر اطلاعات با پشتیبانی تماس بگیرید.', 'jay-login-register' ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="jay-login-register-messages"></div>
                    </form>
                    </div>
                <?php endif; ?>
                <?php if ( $show_mobile ) : ?>
                <div id="jay-tab-mobile" class="jay-tab-content <?php echo ($active_tab === 'mobile') ? 'active' : ''; ?>">
                    <?php 
                    $current_mobile = get_user_meta( $user_id, 'jay_mobile', true ); 
                    if ( empty( $current_mobile ) ) : 
                    ?>
                        <h2>ثبت شماره موبایل</h2>
                        <p>شما هنوز شماره موبایلی ثبت نکرده‌اید. برای فعال‌سازی ورود با موبایل، شماره خود را وارد کنید.</p>
                        
                        <div id="jay-panel-mobile-add-container">
                            <form class="jay-panel-form">
                                <div class="jay-login-register-field">
                                    <label>شماره موبایل</label>
                                    <input type="text" id="jay_new_mobile_direct" class="jay-login-register-input" placeholder="مثال: 09123456789" dir="ltr" style="text-align:left;">
                                </div>
                                <button type="button" class="jay-login-register-button" data-action="panel_send_new_mobile_otp_direct">
                                    ارسال کد تایید
                                </button>
                                <div class="jay-login-register-messages"></div>
                            </form>
                        </div>
                        
                        <div id="jay-panel-mobile-change-dynamic-container"></div>

                    <?php else : 
                        // --- حالت دوم: شماره موبایل دارد (تغییر شماره) --- 
                    ?>
                        <h2>تغییر شماره موبایل</h2>
                        <p>برای تغییر شماره، ابتدا باید یک کد تایید به شماره فعلی شما ارسال شود.</p>
                        
                        <div id="jay-panel-mobile-change-step-1">
                            <form class="jay-panel-form">
                                <div class="jay-login-register-field">
                                    <label>شماره موبایل فعلی (غیرقابل ویرایش)</label>
                                    <input type="text" class="jay-login-register-input" value="<?php echo esc_attr($current_mobile); ?>" readonly style="background-color: #f0f0f1; cursor: default; opacity: 0.8;">
                                </div>
                                <button type="button" class="jay-login-register-button" data-action="panel_send_old_mobile_otp">
                                    ارسال کد تایید به شماره فعلی
                                </button>
                                <div class="jay-login-register-messages"></div>
                            </form>
                        </div>
                        <div id="jay-panel-mobile-change-dynamic-container"></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
               <?php if ( $show_email ) : ?>
                <div id="jay-tab-email" class="jay-tab-content <?php echo ($active_tab === 'email') ? 'active' : ''; ?>">
                    <h2>تغییر ایمیل</h2>
                    <p>برای تغییر ایمیل، ابتدا باید یک کد تایید به ایمیل فعلی شما ارسال شود.</p>

                    <div id="jay-panel-email-change-step-1">
                        <form class="jay-panel-form">
                            <div class="jay-login-register-field">
                                <label>ایمیل فعلی (غیرقابل ویرایش)</label>
                                <input type="text" class="jay-login-register-input" value="<?php echo esc_attr($current_user->user_email); ?>" readonly style="background-color: #f0f0f1; cursor: default; opacity: 0.8; direction: ltr; text-align: left;">
                            </div>
                            
                            <button type="button" class="jay-login-register-button" data-action="panel_send_old_email_otp">
                                ارسال کد تایید به ایمیل فعلی
                            </button>
                            <div class="jay-login-register-messages"></div>
                        </form>
                    </div>

                    <div id="jay-panel-email-change-dynamic-container"></div>
                </div>
                <?php endif; ?>
               <?php if ( $show_password ) : ?>
                <div id="jay-tab-password" class="jay-tab-content <?php echo ($active_tab === 'password') ? 'active' : ''; ?>">
                    <h2>تغییر رمز عبور</h2>
                    
                    <div id="jay-panel-password-step-1">
                        <p>برای تغییر رمز عبور، ابتدا رمز عبور فعلی خود را وارد کنید.</p>
                        <form class="jay-panel-form" onsubmit="return false;">
                            <div class="jay-login-register-field">
                                <label>رمز عبور فعلی</label>
                                <input type="password" id="jay_current_password_input" class="jay-login-register-input" placeholder="رمز فعلی...">
                                <div id="jay-password-check-status" class="jay-field-status"></div>
                            </div>
                        </form>
                    </div>

                    <div id="jay-panel-password-step-2" style="display:none;">
                        <p>هویت شما تایید شد. رمز عبور جدید را وارد کنید.</p>
                        <form class="jay-panel-form">
                            <div class="jay-login-register-field">
                                <label>رمز عبور جدید</label>
                                <input type="password" name="jay_new_password" id="jay_new_password" class="jay-login-register-input">
                                <div class="jay-password-strength-meter">
                                    <div class="jay-strength-bar"></div>
                                </div>
                                <small class="jay-strength-text"></small>
                            </div>

                            <div class="jay-login-register-field">
                                <label>تکرار رمز عبور جدید</label>
                                <input type="password" name="jay_confirm_password" id="jay_confirm_password" class="jay-login-register-input">
                                <small id="jay-password-match-status"></small>
                            </div>

                            <button type="button" class="jay-login-register-button" data-action="panel_change_password_final">ذخیره رمز عبور جدید</button>
                            <div class="jay-login-register-messages"></div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <?php 
                // --- رندر محتوای منوهای سفارشی ---
                if ( isset($panel_settings['enable_custom_menus']) && $panel_settings['enable_custom_menus'] === 'yes' && $has_custom_menus ) {
                    foreach ( $custom_menus as $menu ) {
                        // فقط اگر نوع "محتوا" باشد و شرط نمایش برقرار باشد
                        if ( $menu['type'] === 'content' && jay_login_register_check_menu_logic( $user_id, $menu ) ) {
                            $target_id = 'custom-' . $menu['id'];
                            ?>
                            <div id="jay-tab-<?php echo esc_attr($target_id); ?>" class="jay-tab-content">
                                <h2><?php echo esc_html( $menu['label'] ); ?></h2>
                                <div class="jay-custom-tab-body">
                                    <?php 
                                    // اگر محتوا قدیمی بود (Base64 نبود)، همان را نشان بده
                                    $content = $menu['content'];
                                    if ( base64_encode(base64_decode($content, true)) === $content){
                                        $content = base64_decode($content);
                                        $content = urldecode($content);
                                    }
                                    
                                    echo do_shortcode( wp_kses_post( $content ) ); 
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
                ?>
                <div class="jay-content-loader" style="display:none;"><span class="jay-spinner"></span></div>
            </div>
        </div>
    </div>
    <?php
return Jay_Login_Register_Minifier::html( ob_get_clean() );
    
}
/**
 * بررسی شرط نمایش منوهای سفارشی
 */
function jay_login_register_check_menu_logic( $user_id, $menu ) {
    // اگر متاکی تنظیم نشده، همیشه نمایش بده
    if ( empty($menu['logic_metas']) || !is_array($menu['logic_metas']) ) {
        return true;
    }

    $relation = isset($menu['logic_relation']) ? $menu['logic_relation'] : 'AND';
    $logic_type = isset($menu['logic_type']) ? $menu['logic_type'] : 'show'; // show | hide

    // بررسی وجود متاکی‌ها
    $has_conditions = ($relation === 'AND') ? true : false;
    
    foreach ( $menu['logic_metas'] as $meta_key ) {
        $exists = metadata_exists( 'user', $user_id, $meta_key );
        
        if ( $relation === 'AND' ) {
            if ( ! $exists ) { $has_conditions = false; break; }
        } else { 
            if ( $exists ) { $has_conditions = true; break; }
        }
    }

    // تصمیم‌گیری نهایی بر اساس نوع لاجیک (نمایش یا مخفی)
    if ( $logic_type === 'show' ) {
        // اگر قرار است در صورت وجود نمایش دهیم:
        return $has_conditions;
    } else {
        return ! $has_conditions;
    }
}
