<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// افزودن متا باکس به تمام پست‌تایپ‌های عمومی
add_action( 'add_meta_boxes', 'jay_login_register_add_access_meta_box' );
function jay_login_register_add_access_meta_box() {
    $post_types = get_post_types( ['public' => true] );
    foreach ( $post_types as $post_type ) {
    add_meta_box(
        'jay_login_register_access_control',
        'تنظیمات دسترسی JAY Relog',
        'jay_login_register_access_meta_box_html',
        $post_type,
        'normal', // <-- به جای side
        'low'     // <-- به جای default
    );
     }
}

// محتوای HTML متا باکس
function jay_login_register_access_meta_box_html( $post ) {
    wp_nonce_field( 'jay_login_register_save_meta_box_data', 'jay_login_register_meta_box_nonce' );

     // دریافت مقادیر ذخیره شده
    $requires_login = get_post_meta( $post->ID, '_jay_login_register_requires_login', true );
    $protection_method = get_post_meta( $post->ID, '_jay_login_register_protection_method', true );
    $allowed_roles = get_post_meta( $post->ID, '_jay_login_register_allowed_roles', true );
    $required_meta_key = get_post_meta( $post->ID, '_jay_login_register_required_meta_key', true );
    $meta_key_redirect_id = get_post_meta( $post->ID, '_jay_login_register_meta_key_redirect_page_id', true );
    $meta_error_title = get_post_meta( $post->ID, '_jay_login_register_meta_error_title', true );
    $meta_error_message = get_post_meta( $post->ID, '_jay_login_register_meta_error_message', true );
    $meta_error_button = get_post_meta( $post->ID, '_jay_login_register_meta_error_button', true );
    
    if ( ! is_array($allowed_roles) ) $allowed_roles = [];
     if ( empty($protection_method) ) {
        $protection_method = ( empty($required_meta_key) && empty($allowed_roles) ) ? 'none' : 'block_page';
    }
    ?>
    
<p>
        <label><input type="checkbox" name="jay_login_register_requires_login_field" value="yes" <?php checked( $requires_login, 'yes' ); ?>> <strong>برای مشاهده این محتوا نیاز به ورود است؟</strong></label>
    </p>
    <hr>

    <h4>روش محافظت:</h4>
    <p class="description" style="font-size: 13px; color: #c00;">
    <strong>توجه:</strong> این گزینه‌ها تنها زمانی اعمال می‌شوند که حداقل یک «محدودیت بر اساس کلید متا» یا «محدودیت بر اساس نقش» در پایین تنظیم کرده باشید.
</p>
<p>
        <label><input type="radio" name="jay_login_register_protection_method" value="none" <?php checked( $protection_method, 'none', true ); ?>> <strong>هیچکدام (محافظت غیرفعال)</strong></label>
    </p>
    <p>
        <label><input type="radio" name="jay_login_register_protection_method" value="block_page" <?php checked( $protection_method, 'block_page', true ); ?>> <strong>مسدود کردن کل صفحه:</strong> (پیشنهادی) کاربر وارد نشده به صفحه ورود هدایت می‌شود و کاربر بدون دسترسی، یک صفحه خطای کامل می‌بیند.</label>
    </p>
    <p>
        <label><input type="radio" name="jay_login_register_protection_method" value="replace_content" <?php checked( $protection_method, 'replace_content' ); ?>> <strong>جایگزینی محتوای اصلی:</strong> هدر و فوتر سایت نمایش داده می‌شود، اما محتوای اصلی با پیغام خطا جایگزین می‌گردد.</label>
    </p>
    <hr>

    <div class="jay-login-register-accordion">
        <h4 class="accordion-title">محدودیت بر اساس کلید متا (Meta Key)</h4>
        <div class="accordion-content">
            <p>
                <label><strong>کلید متا مورد نیاز:</strong><br>
                <input type="text" name="jay_login_register_required_meta_key" value="<?php echo esc_attr($required_meta_key); ?>" class="widefat" placeholder="مثال: bargozarimada"></label>
            </p>
            <p>
                <label><strong>برگه هدایت :</strong><br>
                <?php wp_dropdown_pages(['name' => 'jay_login_register_meta_key_redirect_page_id', 'selected' => esc_attr($meta_key_redirect_id), 'show_option_none' => '— انتخاب کنید —', 'option_none_value' => '0']); ?>
                </p>
            <p>
                <label><strong>عنوان پیغام خطا:</strong><br>
                <input type="text" name="jay_login_register_meta_error_title" value="<?php echo esc_attr($meta_error_title); ?>" class="widefat" placeholder="مثال: دسترسی ویژه"></label>
            </p>
            <p>
                <label><strong>متن پیغام خطا:</strong><br>
                <textarea name="jay_login_register_meta_error_message" class="widefat" rows="4" placeholder="مثال: کاربر گرامی با شماره [user_phone]، برای مشاهده ..."><?php echo esc_textarea($meta_error_message); ?></textarea>
                <small>شماره کاربر به صورت دائمی نمایش داده میشود</small>
            </p>
            <p>
                <label><strong>متن دکمه:</strong><br>
                <input type="text" name="jay_login_register_meta_error_button" value="<?php echo esc_attr($meta_error_button); ?>" class="widefat" placeholder="مثال: تکمیل اطلاعات و دریافت دسترسی"></label>
            </p>
        </div>
    </div>

    <div class="jay-login-register-accordion" style="margin-top: 10px;">
        <h4 class="accordion-title">محدودیت بر اساس نقش کاربری</h4>
        <div class="accordion-content">
              <p><strong>فقط به نقش(های) کاربری خاصی نمایش داده شود:</strong></p>
            <?php
            $editable_roles = get_editable_roles();
            foreach ( $editable_roles as $role_slug => $role_details ) {
                echo '<label><input type="checkbox" name="jay_login_register_allowed_roles[]" value="' . esc_attr( $role_slug ) . '" ' . checked( in_array( $role_slug, $allowed_roles ), true, false ) . '> ' . esc_html( translate_user_role( $role_details['name'] ) ) . '</label>';
            }
            ?>
            <p class="description">اگر هیچ نقشی را انتخاب نکنید، تمام کاربران واجد شرایط به محتوا دسترسی خواهند داشت.</p>

            </div>
    </div>
    <?php
}

// ذخیره داده‌های متا باکس
add_action( 'save_post', 'jay_login_register_save_meta_box_data' );
function jay_login_register_save_meta_box_data( $post_id ) {
    $nonce = isset( $_POST['jay_login_register_meta_box_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jay_login_register_meta_box_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'jay_login_register_save_meta_box_data' ) ) {
        return;
    }
    // بررسی‌های امنیتی اولیه
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // ذخیره وضعیت نیاز به ورود
    update_post_meta( $post_id, '_jay_login_register_requires_login', isset($_POST['jay_login_register_requires_login_field']) ? 'yes' : 'no' );
    
    // **منطق جدید:** ابتدا مقادیر محدودیت‌ها را می‌خوانیم
    $meta_key = isset($_POST['jay_login_register_required_meta_key']) ? sanitize_key(wp_unslash($_POST['jay_login_register_required_meta_key'])) : '';
    $allowed_roles = [];
    if ( isset( $_POST['jay_login_register_allowed_roles'] ) && is_array( $_POST['jay_login_register_allowed_roles'] ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $unslashed_roles = wp_unslash( $_POST['jay_login_register_allowed_roles'] );
        $allowed_roles = array_map( 'sanitize_key', $unslashed_roles );
    }

    // **حالا بر اساس وجود محدودیت، روش محافظت را تعیین می‌کنیم**
    $protection_method_raw = isset( $_POST['jay_login_register_protection_method'] ) ? sanitize_text_field( wp_unslash( $_POST['jay_login_register_protection_method'] ) ) : 'none';

    if ( empty($meta_key) && empty($allowed_roles) ) {
        // اگر هیچ محدودیتی تنظیم نشده، روش را اجباراً روی 'none' قرار بده
        $protection_method = 'none';
    } else {
        // اگر محدودیت وجود دارد، از مقدار ارسالی استفاده کن
        // و اگر کاربر 'none' را انتخاب کرده بود، به حالت پیشنهادی برگردان
        $protection_method = in_array($protection_method_raw, ['block_page', 'replace_content']) ? $protection_method_raw : 'block_page';
    }
    update_post_meta($post_id, '_jay_login_register_protection_method', $protection_method);

    // ذخیره اطلاعات مربوط به محدودیت متادیتا
    update_post_meta($post_id, '_jay_login_register_required_meta_key', $meta_key);

    $redirect_id = isset($_POST['jay_login_register_meta_key_redirect_page_id']) ? absint(wp_unslash($_POST['jay_login_register_meta_key_redirect_page_id'])) : 0;
    update_post_meta($post_id, '_jay_login_register_meta_key_redirect_page_id', $redirect_id);
    
    $error_title = isset($_POST['jay_login_register_meta_error_title']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_meta_error_title'])) : '';
    update_post_meta($post_id, '_jay_login_register_meta_error_title', $error_title);
    
    $error_button = isset($_POST['jay_login_register_meta_error_button']) ? sanitize_text_field(wp_unslash($_POST['jay_login_register_meta_error_button'])) : '';
    update_post_meta($post_id, '_jay_login_register_meta_error_button', $error_button);

    $error_message = isset($_POST['jay_login_register_meta_error_message']) ? wp_kses_post(wp_unslash($_POST['jay_login_register_meta_error_message'])) : '';
    update_post_meta($post_id, '_jay_login_register_meta_error_message', $error_message);

    update_post_meta( $post_id, '_jay_login_register_allowed_roles', $allowed_roles );
}
