<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7" <?php language_attributes(); ?>> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8" <?php language_attributes(); ?>> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9" <?php language_attributes(); ?>> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" <?php language_attributes(); ?>> <!--<![endif]-->
<head>
  <meta charset="utf-8">
  <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!--_____  ____  _    ___          ________  _____ _______       _____  
  / ____|/ __ \| |  | \ \        / /  ____|/ ____|__   __|/\   |  __ \ 
 | |  __| |  | | |  | |\ \  /\  / /| |__  | (___    | |  /  \  | |  | |
 | | |_ | |  | | |  | | \ \/  \/ / |  __|  \___ \   | | / /\ \ | |  | |
 | |__| | |__| | |__| |  \  /\  /  | |____ ____) |  | |/ ____ \| |__| |
  \_____|\____/ \____/    \/  \/   |______|_____/   |_/_/    \_\_____/ 
                                                              
      |\/|  _. ._ |   _ _|_ o ._   _    ()    |\/|  _   _| o  _. 
      |  | (_| |  |< (/_ |_ | | | (_|   (_X   |  | (/_ (_| | (_| 
                                   _|                            
                          © www.gouwestadmm.nl
-->


  <script src="<?php echo get_template_directory_uri(); ?>/assets/js/vendor/modernizr-2.6.2.min.js"></script>

  <script src="http://code.jquery.com/jquery-1.8.3.min.js"></script>
  <script>window.jQuery || document.write('<script src="<?php echo get_template_directory_uri(); ?>/assets/js/vendor/jquery-1.9.0.min.js"><\/script>')</script>
  <script src="<?php echo get_template_directory_uri(); ?>/assets/js/vendor/jquery.cookie.js"></script>

  <?php wp_head(); ?>

  <?php if (wp_count_posts()->publish > 0) : ?>
  <link rel="alternate" type="application/rss+xml" title="<?php echo get_bloginfo('name'); ?> Feed" href="<?php echo home_url(); ?>/feed/">
  <?php endif; ?>

  <!--<link rel="stylesheet/less" href="<?php echo get_template_directory_uri(); ?>/assets/css/less/style.less">
  <script src="<?php echo get_template_directory_uri(); ?>/assets/js/libs/less-1.3.0.min.js"></script>-->

    <!-- Fonts -->
  <link href='http://fonts.googleapis.com/css?family=Source+Sans+Pro:200,300,400,600,700,900,200italic,300italic,400italic,600italic,700italic,900italic' rel='stylesheet' type='text/css'>

</head>
