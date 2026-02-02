<?php
/**
 * Jay Login & Register Page Template
 *
 * This template is used to display the login/register and change phone pages
 * without the theme's header, footer, or sidebar.
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$body_classes = ['jay-relog-template-active'];
?> 
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class( $body_classes ); ?>>
  <?php
  // حلقه استاندارد وردپرس برای نمایش محتوای برگه (که همان شورت‌کد ماست)
  if ( have_posts() ) :
  while ( have_posts() ) :
  the_post();
  the_content();
 endwhile;
 endif;
  ?>
  <?php wp_footer(); ?>
</body>
</html>
