<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * نمایش اعلان راه‌اندازی در پیشخوان
 */
function jay_login_register_setup_notice() {
  // ۱. اگر کاربر در حال حاضر در حال ساخت برگه‌ها است، چیزی نشان نده
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
  if ( isset( $_GET['action'] ) && $_GET['action'] === 'jay_relog_create_pages' ) {
  return;
  }
 
  // ۲. اگر اعلان "موفقیت" داریم، آن را نشان بده و تمام
  if ( get_transient( '_jay_relog_pages_created_notice' ) ) {
  echo '<div class="notice notice-success is-dismissible"><p><strong>افزونه JAY Relog:</strong> برگه‌های مورد نیاز با موفقیت ایجاد و تنظیم شدند.</p></div>';
  delete_transient( '_jay_relog_pages_created_notice' );
  return;
  }

  // ۳. بررسی دسترسی‌ها و اینکه آیا کاربر قبلا اعلان را بسته است
  if ( ! current_user_can( 'manage_options' ) || get_user_meta( get_current_user_id(), '_jay_relog_dismissed_setup_notice', true ) ) {
  return;
 }

  // ۴. بررسی اینکه آیا برگه‌ها قبلا تنظیم شده‌اند یا خیر
  $options = get_option( 'jay_login_register_settings', [] );
  $login_page_id = $options['login_page_id'] ?? 0;
  $change_phone_page_id = $options['change_phone_page_id'] ?? 0;
  $logout_page_id = $options['logout_page_id'] ?? 0;

  // اگر همه برگه‌ها تنظیم شده‌اند، چیزی نشان نده
 if ( $login_page_id > 0 && $change_phone_page_id > 0 && $logout_page_id > 0 ) {
  return;
  }

  // ۵. نمایش اعلان اصلی
  $create_url = wp_nonce_url( admin_url( 'admin.php?page=jay_login_register_settings_page&action=jay_relog_create_pages' ), 'jay_relog_create_pages_nonce' );
  $dismiss_url = wp_nonce_url( add_query_arg( 'jay-relog-dismiss-notice', 'setup' ), 'jay_relog_dismiss_notice_nonce' );
  ?>
  <div class="notice notice-info is-dismissible">
  <p><strong>به افزونه JAY Login & Register خوش آمدید!</strong></p>
  <p>برای عملکرد صحیح افزونه، نیاز به ساخت چند برگه ضروری (مانند ورود، تغییر شماره و خروج) است.</p>
 <p>
  <a href="<?php echo esc_url( $create_url ); ?>" class="button button-primary" style="margin-left: 10px;">
  ساخت خودکار برگه‌ها
  </a>
  <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary">
  نادیده گرفتن
  </a>
  </p>
  </div>
  <?php
}

/**
 * مدیریت دکمه‌های "نادیده گرفتن" و "ساخت برگه‌ها"
 */
function jay_login_register_notice_actions() {
  // ۱. مدیریت "نادیده گرفتن"
if ( isset( $_GET['jay-relog-dismiss-notice'] ) && $_GET['jay-relog-dismiss-notice'] === 'setup' ) {
  $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
  if ( ! wp_verify_nonce( $nonce, 'jay_relog_dismiss_notice_nonce' ) ) {
  wp_die( 'خطای امنیتی' );
   }
  update_user_meta( get_current_user_id(), '_jay_relog_dismissed_setup_notice', '1' );
  wp_safe_redirect( remove_query_arg( [ 'jay-relog-dismiss-notice', '_wpnonce' ] ) );
  exit;
 }

  // ۲. مدیریت "ساخت برگه‌ها"
  if ( isset( $_GET['action'] ) && $_GET['action'] === 'jay_relog_create_pages' ) {
if ( ! current_user_can( 'manage_options' ) ) {
  wp_die( 'شما اجازه انجام این کار را ندارید.' );
  }
  // (اصلاح شده) ابتدا نانس را با isset بررسی و سپس sanitize می‌کنیم
  $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
  if ( ! wp_verify_nonce( $nonce, 'jay_relog_create_pages_nonce' ) ) {
  wp_die( 'خطای امنیتی' );
  }

  jay_login_register_create_all_pages();
  set_transient( '_jay_relog_pages_created_notice', '1', 30 );
  wp_safe_redirect( admin_url( 'admin.php?page=jay_login_register_settings_page&tab=general_settings' ) );
  exit;
 }
}

/**
 * تابع کمکی برای ایجاد یک برگه و بازگرداندن ID آن
 * @param string $title عنوان برگه
 * @param string $slug نامک (Slug) برگه
 * @param string $content محتوای برگه (شورت‌کد)
 * @return int ID برگه ایجاد شده
 */
function jay_login_register_create_page( $title, $slug, $content ) {
  // بررسی می‌کند که آیا قبلاً برگه‌ای با این نامک وجود دارد یا خیر
  $existing_page = get_page_by_path( $slug, OBJECT, 'page' );
  if ( $existing_page ) {
  return $existing_page->ID; // اگر وجود داشت، ID همان را برمی‌گرداند
  }

  $page_data = [
  'post_title'  => $title,
 'post_name' => $slug,
 'post_content' => $content,
  'post_status'  => 'publish',
  'post_type'  => 'page',
  'comment_status' => 'closed', // بستن نظرات
  'ping_status'  => 'closed', // بستن پینگ‌ها
  ];

  $page_id = wp_insert_post( $page_data );
  return $page_id;
}

/**
 * تابع اصلی: ایجاد تمام برگه‌های مورد نیاز
 */
function jay_login_register_create_all_pages() {
  $options = get_option( 'jay_login_register_settings', [] );

  $pages_to_create = [
  'login_page_id' => [
  'title' => 'ورود | عضویت',
  'slug' => 'jay-login-register',
  'content' => '[jay_login_register_form]',
  ],
 'change_phone_page_id' => [
  'title' => 'پروفایل کاربری',
  'slug' => 'jay-user-profile',
  'content' => '[jay_login_register_user_panel]',
  ],
  'logout_page_id' => [
  'title' => 'خروج',
  'slug' => 'logout', // این نامک برای عملکرد صحیح تابع خروج ضروری است
  'content' => '', // برگه خروج باید خالی باشد
  ],
  ];

  $updated = false;
  foreach ( $pages_to_create as $option_key => $page_details ) {
  // فقط اگر برگه قبلا در تنظیمات ذخیره نشده بود، آن را بساز
  if ( empty( $options[ $option_key ] ) ) {
  $page_id = jay_login_register_create_page( $page_details['title'], $page_details['slug'], $page_details['content'] );
  if ( $page_id > 0 ) {
  $options[ $option_key ] = $page_id;
  $updated = true;
  }
  }
  }

  // اگر حداقل یک برگه جدید ایجاد و ذخیره شده بود، کل تنظیمات را آپدیت کن
  if ( $updated ) {
  update_option( 'jay_login_register_settings', $options );
  }
}

/**
 * فیلتر کردن قالب برگه برای برگه‌های افزونه
 */
function jay_login_register_template_include( $template ) {
  $options = get_option( 'jay_login_register_settings', [] );
  $login_page_id = $options['login_page_id'] ?? 0;
  $change_phone_page_id = $options['change_phone_page_id'] ?? 0;

  // لیست برگه‌هایی که باید از قالب اختصاصی ما استفاده کنند
  $jay_pages = [ $login_page_id, $change_phone_page_id ];

  // اگر برگه فعلی یکی از برگه‌های ماست
  if ( is_page( $jay_pages ) ) {
  $new_template = JAY_LOGIN_REGISTER_PATH . 'templates/jay-login-register-template.php';
  if ( file_exists( $new_template ) ) {
  return $new_template;
  }
  }

  return $template; // در غیر این صورت، قالب پیش‌فرض تم را برگردان
}

/**
 * افزودن برچسب "برگه افزونه" به لیست برگه‌ها در پیشخوان
 */
function jay_login_register_add_post_states( $post_states, $post ) {
  $options = get_option( 'jay_login_register_settings', [] );

  $page_id = $post->ID;
  $label = '<strong>— برگه JAY Relog</strong>';

  // بررسی هر سه برگه
  if ( isset($options['login_page_id']) && $page_id == $options['login_page_id'] ) {
  $post_states['jay_login_page'] = $label;
  }
  if ( isset($options['change_phone_page_id']) && $page_id == $options['change_phone_page_id'] ) {
  $post_states['jay_change_phone_page'] = $label;
  }
  if ( isset($options['logout_page_id']) && $page_id == $options['logout_page_id'] ) {
  $post_states['jay_logout_page'] = $label;
  }

  return $post_states;
}
