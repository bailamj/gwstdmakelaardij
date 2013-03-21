<?php
/**
 * Name: XML Property Importer
 * Class: class_wpp_property_import
 * Global Variable: wpp_property_import
 * Internal Slug: property_import
 * JS Slug: wpp_property_import
 * Minimum Core Version: 1.34.0
 * Feature ID: 5
 * Version: 3.2.7.3
 * Description: WP-Property premium feature for automated importing of XML, CSV, JSON, Google Spreadsheet and MLS/RETS data.
 *
 * @updated 3.2.7
 * - November 21, 2012 - Fixed issues related to safety
 * - November 20, 2012 - Fixed 'memory exceeded' issue
 * - November 18, 2012 - Added suppressing errors for simplexml_load_string().
 * - October 1, 2012 - Added a PHRETS debug log which is active when in WPP Developer Mode.
 *
 * @updated 3.2.5
 * - September 26, 2012, had to fix an issue with RETS queries failing when required search fields weren't used.
 *
 */

define( 'WPP_XMLI_Version', '3.2.7.3' );

add_action( 'wpp_post_init', array( 'class_wpp_property_import', 'init' ) );
add_action( 'wpp_pre_init', array( 'class_wpp_property_import', 'pre_init' ) );

if( !is_admin() ) do_action( 'wpp_init' );

/**
 * WP-Property Premium Importer Function
 *
 * Handles XML, CSV, JSON and RETS importing;
 *
 * @version 2.33
 * @package WP-Property
 * @subpackage WP-Property XML Property Importer
 */
 class class_wpp_property_import {

  /*
   * ( custom ) Capability to manage the current feature
   */
  static protected $capability = "manage_wpp_import";

  /**
   * Special functions that must be called prior to init
   *
   */
  function pre_init() {
    /* Add capability */
    add_filter( 'wpp_capabilities', array( 'class_wpp_property_import', "add_capability" ) );
  }

  /**
  * Called at end of WPP init hook, in WP init hook
  *
  * Run-time settings are stored in $wpp_property_import['runtime']
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function init() {
    global $wpp_property_import, $wp_properties;

    /* Load settings */
    if( isset( $wp_properties['configuration']['feature_settings']['property_import'] ) ) {
      $wpp_property_import = $wp_properties['configuration']['feature_settings']['property_import'];
    }

    /* Load default settings */
    if( empty( $wpp_property_import ) ) {
      class_wpp_property_import::load_default_settings();
    }

    // Load run-time settings
    $wpp_property_import['post_table_columns'] = array(
      'post_title' => __( 'Property Title' ),
      'post_content' => __( 'Property Content' ),
      'post_excerpt' => __( 'Property Excerpt' ),
      'post_status' => __( 'Property Status' ),
      'menu_order' => __( 'Property Order' ),
      'post_date' => __( 'Property Date' ),
      'post_author' => __( 'Property Author' )
    );

    //** If cron, do not load rest. */
    if( defined( 'DOING_WPP_CRON' ) ) {
       return;
    }


    /* Setup pages */
    add_action( 'admin_menu', array( 'class_wpp_property_import', 'admin_menu' ) );

    /* Admin before header actions */
    add_action( 'admin_init', array( 'class_wpp_property_import', 'admin_init' ) );

    /* Handle all AJAX calls*/
    add_action( 'wp_ajax_wpp_property_import_handler', array( 'class_wpp_property_import', 'admin_ajax_handler' ) );

    /* Load Scripts */
    add_action( 'admin_enqueue_scripts', array( 'class_wpp_property_import', 'admin_enqueue_scripts' ) );

    /* Manual update from hash */
    add_action( 'wpp_post_init', array( 'class_wpp_property_import', 'run_from_cron_hash' ) );

    /* Init jqueryui widget */
    add_action( 'admin_print_styles', array( 'class_wpp_property_import', 'jqueryui_widget_stylesheet_init' ) );

    add_action( 'wp_ajax_wpp_ajax_show_xml_imort_history', array( 'class_wpp_property_import', 'wpp_ajax_show_xml_imort_history' ) );

    add_action( 'wpp_settings_help_tab', array( 'class_wpp_property_import', 'wpp_settings_help_tab' ) );

    add_filter( 'upload_mimes', array( 'class_wpp_property_import', 'add_upload_mimes' ) );
    add_filter( 'wpp_admin_overview_columns', array( 'class_wpp_property_import', 'wpp_admin_overview_columns' ) );
    add_filter( 'wpp_stat_filter_wpp_xml_import', array( 'class_wpp_property_import', 'wpp_stat_filter_wpp_xml_import' ) );

    // Modify admin body class of imported properties. Order of 10 is important because class_core::admin_body_class is ran on 5 */
    add_filter( 'admin_body_class', array( 'class_wpp_property_import', 'admin_body_class' ), 10 );

    add_action( 'post_submitbox_misc_actions', array( 'class_wpp_property_import', 'post_submitbox_misc_actions' ) );

    //** Listen for browser-based importing */
    if( !empty( $_REQUEST['wpp_schedule_import'] ) ) {

      //** Get schedule from hash */
      foreach( $wpp_property_import['schedules'] as $sch_id => $sch ){

        if( $sch['hash'] == $_REQUEST['wpp_schedule_import'] ) {

          if ( ob_get_level() == 0 ) {
            ob_start();
          }

          class_wpp_property_import::handle_browser_import( $sch_id, $sch );

        }

      }

    }

  }

  /*
   * Performs the import while in browser.
   */
  function handle_browser_import( $sch_id, $import_data ) {
    global $wpp_import_result_stats;

    set_time_limit( 0 );
    ignore_user_abort( true );

    //** Match found.  **/
    $_REQUEST['wpp_schedule_import'] = true;
    $_REQUEST['schedule_id'] =  $sch_id;
    $_REQUEST['wpp_action'] = 'execute_schedule_import';
    $_REQUEST['echo_log'] = 'true';

    if( $_REQUEST['output'] == 'xml' ) {
      header( 'Content-type: text/xml' );
      print "<?xml version=\"1.0\"?>\n<xml_import>\n";
    } else { ?>

      <html xmlns="http://www.w3.org/1999/xhtml" class="graceful_death">
      <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <title>XMLI: <?php echo $import_data['name']; ?></title>
      <style type="text/css">
        html { height: 100%; background-color: #F2F2F2 }
        body{ background: rgba(255, 255, 255, 0.39); border: 10px solid rgba(227, 227, 227, 0.54); border-radius: 0.5em; color: #333333; font-family: Calibri,Times New Roman; line-height: 1.5em; margin: 3em 5em; padding: 1.5em; }
        ul.summary { background:#E7E7E7;border: 1px solid #A4A4A4;line-height: 1.7em;list-style: none outside none;margin-top:1em;padding: 10px; }
        span.time { color: #929292; margin-right: 8px; }
      </style><?php
    }

    class_wpp_property_import::maybe_echo_log( sprintf( __( 'Starting Browser-Initiated Import: %1s. Using XML Importer %2s and WP-Property %3s.', 'wpp' ), $import_data['name'], WPP_XMLI_Version, WPP_Version ));
    class_wpp_property_import::admin_ajax_handler();

    $last_time_entry = class_wpp_property_import::maybe_echo_log( "Total run time %s seconds.", true, true, true );

    if( $_REQUEST['output'] != 'xml' ) {
      echo $last_time_entry['message'];
    }

    $total_processing_time = $last_time_entry['timetotal'];

    if( is_array( $wpp_import_result_stats ) ) {

      $added_properties = $wpp_import_result_stats['quantifiable']['added_properties'];
      $updated_properties = $wpp_import_result_stats['quantifiable']['updated_properties'];
      $downloaded_images = $wpp_import_result_stats['quantifiable']['downloaded_images'];
      $total_properties = $added_properties + $updated_properties;

      if( $total_properties ) {
        $time_per_property = round( ( $total_processing_time / $total_properties ), 3 );
      }

      if( $wpp_import_result_stats['quality_control']['skipped_images'] ) {
        $wpp_import_result_stats[] = $wpp_import_result_stats['quality_control']['skipped_images'] . ' images skipped due to low resolution.';
        unset( $wpp_import_result_stats['quality_control']['skipped_images'] );
      }

      if( $time_per_property ) {
        $wpp_import_result_stats[] = $last_time_entry['timetotal'] . ' seconds total processing time, averaging ' . $time_per_property . ' seconds per property.';
      }

      unset( $wpp_import_result_stats['quantifiable'] );

      $result_stats = '<ul class="summary"><li>' . implode( '</li><li>', $wpp_import_result_stats ) . '</li></ul>';

      if( $_REQUEST['output'] != 'xml' ) {
        echo $result_stats;
      }
    }

    if( $import_data['send_email_updates'] == 'on' ) {
      //** Send email about import end with all data. */
      class_wpp_property_import::email_notify( $result_stats, ' ' . $import_data['name'] . ' ( #'. $sch_id . ' ) is complete.' );
    }

    if( $_REQUEST['output'] == 'xml' ) {

      print "<result_stats>\n";
      foreach( $wpp_import_result_stats as $row ) {
        print "\t<stat>" . $row . "</stat>\n";
      }
      print "</result_stats>\n";


      print "</xml_import>";
    } else {
      ?>
      </body></html>
      <?php
    }

    die();

  }


  /*
   * Adds Custom capability to the current premium feature
   */
  function add_capability( $capabilities ) {

    $capabilities[self::$capability] = __( 'Manage XML Importer','wpp' );;

    return $capabilities;
  }

  /**
   * Modify body class of imported properties on back-end
   *
   */
  function admin_body_class( $id ) {
    global $current_screen, $post;

    if( $current_screen->id == 'property' ) {

      $wpp_import_schedule_id = get_post_meta( $post->ID, 'wpp_import_schedule_id', true );

      if( $wpp_import_schedule_id ) {
        return 'wpp_property_edit wpp_imported_property';
      } else {
        return 'wpp_property_edit';
      }

    }

  }


  /**
   * Displays information on property editing pages for properties that came from an XML Import
   *
   */
  function post_submitbox_misc_actions( $id ) {
    global $post, $wp_properties;

    if( !$wpp_import_schedule_id = get_post_meta( $post->ID, 'wpp_import_schedule_id', true ) ) {
      return;
    }


    //** Get time stamp from new format ( Version 2.6.0+ ) */
    $import_time = get_post_meta( $post->ID, 'wpp_import_time', true );

    //** Get time stamp from old meta_key ( pre-version 2.6.0 ) if new meta_key does not exist */
    if( empty( $import_time ) ) {
      $import_time = get_post_meta( $post->ID, 'wpp_xml_import', true );
    }

    $import_url = admin_url( "edit.php?post_type=property&page=wpp_property_import#{$wpp_import_schedule_id}" );

    $import_name = $wp_properties['configuration']['feature_settings']['property_import']['schedules'][$wpp_import_schedule_id]['name'];

    if( !empty( $import_time ) ) {

      $import_time =  date_i18n(  __( 'M j, Y @ G:i' ), strtotime( $import_time ) );

    ?>
    <div class="misc-pub-section xml_import_time misc-pub-section-last">
      <span class="wpp_i_time_stamp"><?php printf( __( 'Imported on: <b>%1$s</b> <a href="%2$s" title="%3$s">Importer</a>', 'wpp' ),$import_time, $import_url, $import_name ); ?> <b></b></span>
    </div>
    <?php
    }

  }


  /**
   * Deletes a non empty directory, directory must end with '/'
   *
   */
  function delete_directory( $dirname, $delete_files = false ) {

    if ( is_dir( $dirname ) ) {
      $dir_handle = opendir( $dirname );
    } else {
      return false;
    }

    while( $file = readdir( $dir_handle ) ) {

      if ( $file == '.' || $file == '..' ) {
        continue;
      }

      if ( $delete_files && !is_dir( trailingslashit( $dirname ) . $file ) ) {
        unlink( $dirname."/".$file );
      }

      if ( is_dir( trailingslashit( $dirname ) . $file ) ) {
        class_wpp_property_import::delete_directory( trailingslashit( $dirname ) . $file , $delete_files );
      }

    }

    closedir( $dir_handle );

    if( @rmdir( $dirname ) ) {
      //class_wpp_property_import::maybe_echo_log( "Deleted directory: {$dirname}" );
      return true;
    } else {
      //class_wpp_property_import::maybe_echo_log( "Could not remove directory: {$dirname}" );
      return false;
    }
  }


  /**
   * {}
   *
   */
  function get_orphan_attachments( $dirname = false ) {
    global $wpdb;
    $orphan = $wpdb->get_col( "
    SELECT ID FROM {$wpdb->posts}
    WHERE (
      post_title = 'Property Image'
      OR post_content LIKE  'PDF flyer for%'
      OR LENGTH( post_content_filtered ) = 32
    )  AND ( post_parent = 0 AND post_type ='attachment')" );

    return $orphan;

  }


  /**
   * Deletes a non empty directory, directory must end with '/'
   * In support of 'delete_post'
   */
  function delete_orphan_directories( $dirname = false ) {

    if( !$dirname ) {
      $uploads = wp_upload_dir();
      $dirname = trailingslashit( $uploads['basedir'] ) . 'wpp_import_files';
    }

    if( is_dir( $dirname ) ) {
      $dir_handle = opendir( $dirname );
    } else {
      return false;
    }

    while( $file = readdir( $dir_handle ) ) {
      if ( $file == "." || $file == ".." || $file == 'temp' ) {
        continue;
      }

      if ( is_dir( trailingslashit( $dirname ) . $file ) ) {
        class_wpp_property_import::delete_directory( trailingslashit( $dirname ) . $file );
      }

    }

    closedir( $dir_handle );

    if( @rmdir( $dirname ) ) {
      //class_wpp_property_import::maybe_echo_log( "Removed directory: {$dirname}" );
      return true;
    } else {
      return false;
    }

  }

  /**
  * Adds a colum to the overview table
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function wpp_stat_filter_wpp_xml_import( $timestamp ) {
    return human_time_diff( $timestamp ) . ' '. __( 'ago' );
  }

  /**
  * Adds a colum to the overview table
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function wpp_admin_overview_columns( $columns ) {
    $columns['wpp_xml_import'] =  __( 'Last Import','wpp' );
    return $columns;
  }

  /**
  * Checks if the current view should display a log during import, or perform the import silently.
  *
  * If should be echoed, does so, unless explicitly told not to.
  * If no text is passed, returns bool
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function maybe_echo_log( $text = false, $echo = true, $last_entry = false, $return_times = false ) {
    global $wpp_runtime_log, $wpp_import_result_stats;

    $newline = ( php_sapi_name() == 'cli' ? "\r\n" : '<br />' );

    if( empty( $wpp_runtime_log['first_entry'] ) ) {
    	$mtime = explode( ' ', microtime() );
      $timestart = $mtime[1] + $mtime[0];
      $wpp_runtime_log['first_entry'] = $timestart;
    }

    if( $last_entry && isset( $wpp_runtime_log['first_entry'] ) ) {

      $mtime = microtime();
      $mtime = explode( ' ', $mtime );
      $timeend = $mtime[1] + $mtime[0];
      $timetotal = $timeend - $wpp_runtime_log['first_entry'];

      $timetotal = ( function_exists( 'number_format_i18n' ) ) ? number_format_i18n( $timetotal, $precision ) : number_format( $timetotal, $precision );
      $text = str_replace( '%s', $timetotal, $text );

    }

    if( !$text ) {
      return;
    }

    /** Return time, meant for running at end of script */
    if( $return_times ) {

      if( $_REQUEST['output'] == 'xml' ) {
        return array(
          'timetotal' => $timetotal,
          'message' => __( 'Time: ' ) . date( 'H:i:s' ) . ': ' . $text . $newline
        );
      } else {
        return array(
          'timetotal' => $timetotal,
          'message' => '<span class="time">' . __( 'Time: ' ) . date( 'H:i:s' ) . ':</span>' . $text . $newline
        );
      }

    }

    //** Only excho when we are doing a browser-side import, and ehco is enabled */
    if( isset( $_REQUEST['wpp_schedule_import'] ) && $_REQUEST['echo_log'] == 'true' ) {
      if( $text && $echo ) {

        if( !isset( $_REQUEST['do_not_pad'] ) ) {
          $end = $newline .  str_pad( '',4096 );
        } else {
          $end = "\n";
        }

        if( $_REQUEST['output'] == 'xml' ) {
          echo "<entry>\n";
          echo "\t<timestamp>" . time() . "</timestamp>\n";
          echo "\t<time>" . date( 'H:i:s' ) . "</time>\n";
          echo "\t<event>". $text . "</event>\n";
          echo "</entry>\n";
        } else {
          echo  '<span class="time">' . __( 'Time: ' ) . date( 'H:i:s' ) . ':</span>' . $text . '' . $end;
        }

        if( !isset( $_REQUEST['do_not_pad'] ) ) {
          ob_flush();flush();
        }

      } else {
        return $echo;
      }
    }

    return false;
  }

  /**
  * Called via AJAX function from settings page to display the import history
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function wpp_ajax_show_xml_imort_history() {
    global $wpdb;

    $imported = $wpdb->get_results( "SELECT post_title, post_id, meta_value FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE meta_key = 'wpp_import_time' AND meta_value != '' AND post_title IS NOT NULL ORDER BY meta_value DESC LIMIT 0, 500" );

    echo "<ol style='padding-left: 10px;'>";
    foreach( $imported as $object )
      echo '<li><a href="' . get_permalink( $object->post_id ) . '">' . $object->post_title . '</a> Edit: <a href="' . get_edit_post_link( $object->post_id ) . ' ">( ' .$object->post_id.' )</a> - ' . human_time_diff( $object->meta_value ). ' ago</li>';
    echo "</ol>";

    die();
  }

  /**
  * Add things to Help tab
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function wpp_settings_help_tab() {
    global $wp_properties, $wpdb;

    //** Check for orphan images */
    $orphan_attachments = count( class_wpp_property_import::get_orphan_attachments() );

    ?>

    <script type="text/javascript">
      jQuery( document ).ready( function() {

        jQuery( ".wppi_delete_all_orphan_attachments" ).click( function( e ) {

          var notice_container = jQuery( '.wppi_delete_all_orphan_attachments_result' ).show();

          jQuery( notice_container ).html( "Deleting all unattached images. You can close your browser, the operation will continue until completion." );

          jQuery.post( ajaxurl, {
            action: 'wpp_property_import_handler',
            wpp_action: 'delete_all_orphan_attachments'
          },
          function( result ) {
            if( result && result.success ) {
              jQuery( notice_container ).html( result.ui );
            } else {
              jQuery( notice_container ).html( '<?php _e( 'An error occured.', 'wpp' ); ?>' );
            }

          }, 'json' );

        });


        jQuery( "#wpp_ajax_show_xml_imort_history" ).click( function() {

          jQuery( ".wpp_ajax_show_xml_imort_history_result" ).html( "" );

          jQuery.post( ajaxurl, {
              action: 'wpp_ajax_show_xml_imort_history'
            }, function( data ) {
              jQuery( ".wpp_ajax_show_xml_imort_history_result" ).show();
              jQuery( ".wpp_ajax_show_xml_imort_history_result" ).html( data );

            });
        });

      });
    </script>

    <?php if( $orphan_attachments ) { ?>
    <div class="wpp_settings_block"><?php printf( __( 'There are (%1s) unattached files related to listings that were imported using the XML Importer.', 'wpp' ), $orphan_attachments ); ?>
      <input type="button" value="<?php _e( 'Delete Unattached Files','wpp' ) ?>" class="wppi_delete_all_orphan_attachments">
      <div class="hidden wppi_delete_all_orphan_attachments_result wpp_class_pre" style="height: auto;"></div>
      <div class="description"></div>
    </div>
    <?php } ?>

    <div class='wpp_settings_block'><?php _e( 'Look up XML import history.','wpp' ) ?>
      <input type="button" value="<?php _e( 'Show XML Import History','wpp' ) ?>" id="wpp_ajax_show_xml_imort_history">
      <div class="hidden wpp_ajax_show_xml_imort_history_result wpp_class_pre"></div>
      <div class="description"><?php _e( 'Show last 500 imported items in descending order.','wpp' ) ?></div>
    </div>

  <?php

  }



  /*
   * Jquery ui stylesheet inint
   *
   * Copyright 2010 - 2012 Usability Dynamics, Inc.
   */

  function jqueryui_widget_stylesheet_init() {
    if ( isset( $_REQUEST['page'] ) &&  $_REQUEST['page'] == 'wpp_property_import' && file_exists( WPP_Path . 'css/jquery-ui.css' ) ) {
      wp_register_style( 'jquery-ui-styles', WPP_URL . 'css/jquery-ui.css' );
      wp_enqueue_style( 'jquery-ui-styles' );
    }
  }

  /**
  * Hooks into 'admin_init'
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function admin_init() {
    global $wpp_property_import,  $wp_properties, $wp_messages;

      if( $wpp_property_import['settings']['allow_xml_uploads_via_media_uploader'] == 'true' ) {
        add_filter( 'upload_mimes', array( 'class_wpp_property_import', 'add_upload_mimes' ) );
      }

      // Download backup of configuration
      if( $_REQUEST['page'] == 'wpp_property_import'
        && $_REQUEST['wpp_action'] == 'download-wpp-import-schedule'
        && wp_verify_nonce( $_REQUEST['_wpnonce'], 'download-wpp-import-schedule' ) ) {


          $schedule_id = $_REQUEST['schedule_id'];

          $schedule_data = $wp_properties['configuration']['feature_settings']['property_import']['schedules'][$schedule_id];

          $filename[] = 'wpp-schedule';
          $filename[] = sanitize_key( get_bloginfo( 'name' ) );
          $filename[] = sanitize_key( $schedule_data['name'] );
          $filename[] = date( 'Y-m-d' ) . '.txt';

          header( "Cache-Control: public" );
          header( "Content-Description: File Transfer" );
          header( "Content-Disposition: attachment; filename=" . implode( '-', $filename ) );
          header( "Content-Transfer-Encoding: binary" );
          header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ), true );

          echo json_encode( $wp_properties['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] );

        die();

    }

    //* Handle Import of schedule from an uploaded file */
    if( $_REQUEST['page'] == 'wpp_property_import'
      && $_REQUEST['wpp_action'] == 'import_wpp_schedule'
        && wp_verify_nonce( $_REQUEST['_wpnonce'], 'wpp_import_import_schedule' ) ) {

          if( $backup_file = $_FILES['wpp_import']['tmp_name']['import_schedule'] ) {

            $imported_schedule = file_get_contents( $backup_file );

            if( !empty( $imported_schedule ) ) {
              $imported_schedule = @json_decode( $imported_schedule, true );
            }

             if( is_array( $imported_schedule ) ) {

              $schedule_id = time();

              // generate new hash
              $imported_schedule['hash'] = md5( sha1( $schedule_id ) );
              $imported_schedule['name'] = $imported_schedule['name'] . ' ' .  __( '( Imported )', 'wpp' );

              $wp_properties['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] = $imported_schedule;

              update_option( 'wpp_settings', $wp_properties );

              wp_redirect( admin_url( "edit.php?post_type=property&page=wpp_property_import&message=imported" ) );

             } else {
              $wp_messages['error'][] = __( 'Schedule coult not be imported.','wpp' );
             }

          }
    }
  }

  /**
  * Add XML/CSV/JSON mimes to allow WP Media Uploader to handle import files
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function add_upload_mimes( $current ) {
    global $wpp_property_import;

    $current['xml'] = 'text/xml';
    $current['csv'] = 'text/csv';
    $current['json'] = 'application/json';
    $current['json'] = 'text/json';

    return $current;

  }

  /**
  * Hooks into 'admin_enqueue_scripts"
  *
  * Loads all admin scripts.
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function admin_enqueue_scripts() {
    global $wpp_property_import, $current_screen;

    if( !isset( $current_screen->id ) ) {
      return;
    }
  }

  /**
  * Hooks into 'admin_menu"
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function admin_menu() {
    global $wpp_property_import;
    $page = add_submenu_page( 'edit.php?post_type=property', __( 'Importer','wpp' ), __( 'Importer','wpp' ), self::$capability, 'wpp_property_import', array( 'class_wpp_property_import','page_main' ) );

    add_action( "load-{$page}", array( 'class_wpp_property_import', 'wpp_importer_page_load' ) );
  }

  /**
  * Content for Import settings on WPP settings apge
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function settings_page( $tabs ) {
  }

  /**
  * Load default settings, should only be done on first run
  *
  * Also used to lay out data structure.
  * Don't run this if settings exist, they will be overwritten.
  *
  * Settings boolean values must be stored in string format to compy with WPP
  *
  * @todo Add settings
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function load_default_settings() {

    $d['settings'] = array(
      'allow_xml_uploads_via_media_uploader' => 'true'
    );

    $d['schedules'] = false;


    return $d;

  }


/**
  * Run cron job from hash
  *
  *
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function run_from_cron_hash(){
    global $wpp_property_import, $wpp_import_result_stats;

    if( !isset( $wpp_property_import ) ) {
      $wpp_property_import = $wp_properties['configuration']['feature_settings']['property_import'];
    }

     //** Cycle through schedules and try to mach. **/
    if ( !empty( $wpp_property_import['schedules'] ) ) /** Warning fix korotkov@ud */
      foreach( $wpp_property_import['schedules'] as $sch_id => $sch ){

        if( $sch['hash'] == WPP_IMPORTER_HASH ) {

        //** Match found.  **/
        $_REQUEST['wpp_schedule_import'] = true;
        $_REQUEST['schedule_id'] =  $sch_id;
        $_REQUEST['wpp_action'] = 'execute_schedule_import';
        $_REQUEST['echo_log'] = 'true';
        $_REQUEST['do_not_pad'] = true;
        $_REQUEST['do_not_flush'] = true;


        if( $sch['send_email_updates'] == 'on' ) {
          //** Send email about import start */
          /* class_wpp_property_import::email_notify( 'Import has begun.', 'Schedule #'. $sch_id . ' Initiated' ); */
        }


        //** Wrap all echoed data into ob */
        ob_start();

          class_wpp_property_import::maybe_echo_log( sprintf( __( 'Starting Cron-Initiated Import: %1s. Using XML Importer %2s and WP-Property %3s.', 'wpp' ), $sch['name'], WPP_XMLI_Version, WPP_Version ));
          class_wpp_property_import::admin_ajax_handler();
          $last_time_entry = class_wpp_property_import::maybe_echo_log( "Total run time %s seconds.", true, true, true );
          $import_result .= ob_get_contents();
        ob_end_clean();

        $total_processing_time = $last_time_entry['timetotal'];

        if( is_array( $wpp_import_result_stats ) ) {


          $added_properties = $wpp_import_result_stats['quantifiable']['added_properties'];
          $updated_properties = $wpp_import_result_stats['quantifiable']['updated_properties'];
          $downloaded_images = $wpp_import_result_stats['quantifiable']['downloaded_images'];
          $total_properties = $added_properties + $updated_properties;
          $time_per_property = round( ( $total_processing_time / $total_properties ), 2 );

          if( $wpp_import_result_stats['quality_control']['skipped_images'] ) {
            $wpp_import_result_stats[] = $wpp_import_result_stats['quality_control']['skipped_images'] . ' images skipped due to low resolution.';
            unset( $wpp_import_result_stats['quality_control']['skipped_images'] );
          }

          $wpp_import_result_stats[] = $last_time_entry['timetotal'] . ' seconds total processing time, averaging ' . $time_per_property . ' seconds per property.';

          unset( $wpp_import_result_stats['quantifiable'] );

          $result_stats = '<ul class="summary"><li>' . implode( '</li><li>', $wpp_import_result_stats ) . '</li></ul>';
          $cron_result = implode( "\n", $wpp_import_result_stats );

        } else {
          $cron_result = 'No stats were returned by import process.';
        }

         $import_header = $sch['name'] . ' ( #'. $sch_id . ' ) is complete.';

        if( $sch['send_email_updates'] == 'on' ) {
          //** Send email about import end with all data. */
          class_wpp_property_import::email_notify( $result_stats . nl2br( $import_result ) ,$import_header );
        } else {

        }

        //** Display on stats in the cron email. */
        die( strtoupper( $import_header ) .  "\n\n" . $cron_result ."\n\n". $import_result );
      }

    }

  }

  /**
   * Settings page load handler
   */
  function wpp_importer_page_load() {

    $contextual_help['XML Importer Help'][] = '<h3>' . __( "XML Importer Help", "wpp" ) . '</h3>';
    $contextual_help['XML Importer Help'][] = '<p>' . __( 'By default, xPath are executed in the xPath input boxes. <a target="_blank" href="http://www.w3schools.com/xpath/xpath_syntax.asp">W3 Schools XPath Syntax</a>. ', "wpp" ) . '</p>';
    $contextual_help['XML Importer Help'][] = '<p>' . __( 'Example: get all the option values that have a label for "height": <b>options/option[label = "Height"]/value </b>', "wpp" ) . '</p>';

    $contextual_help['RETS'][] = '<p>' . __( '<b>Property Resource Class:</b> Typically this is used to specify the type of property listing, such as Commercial or Residential, the naming convention varies depending on RETS provider. Use <a href="http://rets.usabilitydynamics.com/" target="_blank">rets.usabilitydynamics.com</a> to determine. ', 'wpp' ) . '</p>';

    $contextual_help['XPath Query to Property Elements'][] = '<h3>' . __( "XPath Query to Property Elements", "wpp" ) . '</h3>';
    $contextual_help['XPath Query to Property Elements'][] = '<p>' . __( 'In order to begin importing data, you must first identify what the "repeating property element" is in the XML file.  Typically this would be something like <b>property</b> or <b>object</b>, where the corresponding XPath rules would be <b>//property</b> or <b>//object</b>, respectively. The easiest way to identify it is to look through the feed for a repeating pattern. The query must select the elements in order to cycle through them and apply the XPath Rules in the Attribute Map section. ', "wpp" ) . '</p>';

    $contextual_help['Import Limits'][] = '<h3>' . __( 'Import Limits', "wpp" ) . '</h3>';
    $contextual_help['Import Limits'][] = '<p>' . __( 'There are two type of limits - the first limit will stop the import after a certain number of objects have been processed before they are checked for quality, while the second limit will stop only after the specified number of objects has actually passsed quality inspection, and have been saved to the database.', "wpp" ) . '</p>';
    $contextual_help['Import Limits'][] = '<p>' . __( 'Limiting imports works well when you are running incremental imports.  A limit of <b>10</b> will stop after 10 properties have been created.  The importer does not count properties that were skipped during import or that already exist in the system - properties that already exist will be marked as updated.', "wpp" ) . '</p>';

    $contextual_help['Running the Import'][] = '<h3>' . __( "Running the Import", "wpp" ) . '</h3>';
    $contextual_help['Running the Import'][] = '<p>' . __( 'There are two ways to process an import, using the browser, or by setting up a cron job.  Using the browser is easy, and viable when you have a small feed, or a very good server. ', "wpp" ) . '</p>';
    $contextual_help['Running the Import'][] = '<p>' . __( 'When working with larger feeds, or for the purposes of automation, it is advisable to execute your import script using a cron job.  For every "Import Schedule" you create, a <b>Cron Command</b> field will be displayed, followed by the command you would need to enter into the cron job builder, for the import schedule to be executed.', "wpp" ) . '</p>';

    $contextual_help['Function: free_text'][] = '<h3>' . __( "Function: free_text", "wpp" ) . '</h3>';
    $contextual_help['Function: free_text'][] = '<p>' . __( 'To insert some common text, use the <b>free_text</b> command, like so: <b>free_text: Imported from Some List</b> and the text will be kept as is.', "wpp" ) . '</p>';

    $contextual_help['Function: concat'][] = '<h3>' . __( "Function: concat", "wpp" ) . '</h3>';
    $contextual_help['Function: concat'][] = '<p>' . __( "You can also combine free text wtih xPath rule results using <b>concat</b>, example: <b>concat:http://sourcesite.com/images/'Photo7'</b> will result in the text between the quotes being executed as xPath rules, while text outside of quotes being inserted as it is.", "wpp" ) . '</p>';
    $contextual_help['Function: concat'][] = '<p>' . __( "You can also use concat to combine multiple xPath rules together, for example you can create the Property Title from a few XML attributes: <b>concat:'bedrooms' bedroom house in 'location/city'</b>", "wpp" ) . '</p>';

    $contextual_help['Function: concat_list'][] = '<h3>' . __( "Function: concat_list", "wpp" ) . '</h3>';
    $contextual_help['Function: concat_list'][] = '<p>' . __( 'Example: [concat_list root_path="options/option" label_path="label" value_path="value" concat_character=":" ] will look for options/option path, then grab child "value" and "label" paths and import them as a single line. ', "wpp" ) . '</p>';

    //** Hook this action is you want to add info */
    $contextual_help = apply_filters( 'wpp_importer_page_help', $contextual_help );

    if( is_callable( array( 'WPP_Core', 'wpp_contextual_help' ) ) ) {
      do_action( 'wpp_contextual_help', array( 'contextual_help' => $contextual_help ) );

    } else if( is_callable(array( 'WP_Screen','add_help_tab' ))) {

      //** Loop through help items and build tabs */
      foreach ( (array) $contextual_help as $help_tab_title => $help) {

        //** Add tab with current info */
        get_current_screen()->add_help_tab( array(
          'id'      => sanitize_title( $help_tab_title ),
          'title'   => $help_tab_title,
          'content' => implode( '' , (array) $contextual_help[$help_tab_title] ),
        ));

      }

    }

  }


  /**
   * Handle deleting properties that originated from a feed
   *
   * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
   */
  function delete_feed_properties( $schedule_id, $schedule_settings, $exclude = false ) {
    global $wpdb, $wp_properties;

    if( !is_array( $exclude ) ) {
      $exclude = false;
    }

    if( $all_feed_properties = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE post_type = 'property' AND meta_key = 'wpp_import_schedule_id' and meta_value = %s  GROUP BY p.ID" , $schedule_id ) ) ) {

      $r['total_found'] = count( $all_feed_properties );

      class_wpp_property_import::maybe_echo_log( sprintf( __( 'Found %1s properties from database that were imported from this feed.', 'wpp' ), $r['total_found'] ) );

      foreach( $all_feed_properties as $property_id ) {

        //** If an array of property IDs to exclude is passed, check if property is in array, if so - bail */
        if( $exclude && in_array( $property_id, $exclude ) ) {
          continue;
        }

        //** Delete the actual object */
        if( wp_delete_post( $property_id, true ) ) {
          $r['deleted_objects'][] = $property_id;

          if( $schedule_settings['log_detail'] == 'on' ) {
            class_wpp_property_import::maybe_echo_log( sprintf( __( 'Property ID %1s has been deleted. Total deleted so far: %2s', 'wpp' ), $property_id, count( $r['deleted_objects'] ) ) );
          }

        } else {
          //** Unable to delete property for some reason.
        }

      }

      if( is_array( $r['deleted_objects'] ) ) {
        $r['deleted_count'] = count( $r['deleted_objects'] );
      }

      if( $r['total_found'] != $property_delete_counter ) {
        $r['remaining'] = ( $r['total_found'] - $property_delete_counter );
      }

    } else {
      $r['total_found'] = 0;
    }

    return $results;

  }

  /**
  * Handle all admin ajax actions
  *
  * Success or failure is returned as string 'true' or 'false in $return['success']
  * Whatever is to be displayed return in $return['ui']
  *
  *
  * @todo $data and $schedule_settings seem to be used intermittently, should consolidate into one
  * @todo see if raw_preview and source_evaluation do not do some of the same functions
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function admin_ajax_handler( $from_cron = false ) {
    global $wpp_property_import, $wp_properties, $wpdb, $wpp_import_result_stats;

    if( isset( $_REQUEST['wpp_action'] ) ) {
      $wpp_action = $_REQUEST['wpp_action'];
    }

    if( isset( $_REQUEST['wpp_action_type'] ) ) {
      $action_type = $_REQUEST['wpp_action_type'];
    }

    if( isset( $_REQUEST['do_not_use_cache'] ) ) {
      $do_not_use_cache = true;
    } else {
      $do_not_use_cache = false;
    }

    //* wpp_schedule_import is passed when an actual import is being executed, it is supposed to contain the hash.  If it does not, schedule_id should be set. */
    $wpp_schedule_import = ( !empty( $_REQUEST['wpp_schedule_import'] ) ? $_REQUEST['wpp_schedule_import'] : false );

    //** $schedule_id should always be passed, otherwise we will not be able to use cache. If no ID, set it to false. */
    $schedule_id = ( !empty( $_REQUEST['schedule_id'] ) ? $_REQUEST['schedule_id'] : false );

    //** Check if this schedule exists in DB */
    if( empty( $wpp_property_import['schedules'][$schedule_id] ) ) {
      $result['schedule_exists'] = false;
    } else {
      $result['schedule_exists'] = true;
    }

    //** Default to true */
    $result['success'] = 'true';

    // If something is wrong, set success to 'false'
    if( empty( $wpp_action ) ) {
      $result['success'] = 'false';
    }

    //** Set custom post type for URL queries that need to pass credentials via post */
    if( isset( $data['wpp_property_import']['postauth'] ) ){
      $request_method = 'post';
    } else{
      $request_method = 'get';
    }

    //** Load the import data, this i used by CRON and Browser Access */
    if( defined( 'DOING_WPP_CRON' ) || ( $wpp_schedule_import && $result['schedule_exists'] ) ) {

      //** Load the import schedule DATA from DB. Load into two variables for now, due to some inconstiancies later wher eboth are refered to. */
      $data['wpp_property_import'] = $wpp_property_import['schedules'][$schedule_id];

      $doing_full_import = true;

    } elseif( $wpp_action == 'execute_schedule_import' && !empty( $_REQUEST['data'] ) ) {
      //** Entire data array is passed, this happens when a schedule is Saved or "Preview Import" has been initiated */

      parse_str( $_REQUEST['data'], $data );

      $data = stripslashes_deep( $data );

       //** When we are running a preview on an unsaved schedule ( schedule may exist, but changes made and not commited ) - which happens a lot when testing */
       if( $_REQUEST['raw_preview'] == 'true' || $action_type == 'source_evaluation' || $_REQUEST['preview'] == 'true' ) {

        $preview_import = true;

        /* Generate temporary $schedule_id for this preview ONLY if it was not passed.  We return this later so same ID is used for this session.  */
        if( !$schedule_id ) {
          $schedule_id = time();
        }

      }

    } elseif( $wpp_action == 'save_new_schedule' || $wpp_action == 'update_schedule' ) {

      parse_str( $_REQUEST['data'], $data );

      $data = stripslashes_deep( $data );

      //** $schedule_settings should be referenced from now on in this function */
      $schedule_settings = $data['wpp_property_import'];

    }

    //** Regardless of schedule data loaded from DB or from $_POST, if its there its stored in $data['wpp_property_import'] */
    if( $schedule_id && $data['wpp_property_import'] ) {


      //** Load the schedule_id into $data variable for convenience */
      $data['schedule_id'] = $schedule_id;

      //** $schedule_settings should be referenced from now on in this function */
      $schedule_settings = $data['wpp_property_import'];

      /** @todo this may need to be fixed to get source type from memory */
      $data['source_type'] = $schedule_settings['source_type'];

    }

    //** If enabled, enable query tracking */
    if( $schedule_settings[ 'show_sql_queries' ] ) {
      define( 'SAVEQUERIES', true );
    }

    //** wpp_schedule_import is set when doing import via CRON or HTTP */
    if( !$wpp_schedule_import ) {
      ob_start();
    }


    /*
      The following switch goes through various actions that can be handled, and the results are loaded into an array
      which is returned in JSON format after the switch is complete

      The following are the currently used.

      $result[ui] = whatever will be displayed, can contain HTML
      $result[success]   = true or false
      $result[data_from_cache]  = if the current data is loaded from cache
      $result[message]  = a non-HTML message
      $result[schedule_id]  = if working with a schedule, it's ID
      $result[hash]   = hash of schedule
      $result[common_tag_name]  = used when auto-matching and WPP tries to guess the tag name
      $result[auto_matched_tags]  = used when auto-matching , returns array of found tags

    */


    //** Handle actions */
    switch( $wpp_action ) {

      case 'save_new_schedule':

        //** Not sure if this is necessary, why not use global variable? andy@UD */
        $wpp_settings = get_option( 'wpp_settings' );

        //** Generate plain ( internal ) hash based on current timestamp. schedule_id may already be pased though. */
        if( !$schedule_id ) {
          $schedule_id = time();
        }

        /** Load data from _REQUEST data */
        $new_schedule = $schedule_settings;

        //** Assign new hash to it based on time. */
        $schedule_hash = md5( sha1( $schedule_id ) );
        $new_schedule['hash'] = $schedule_hash;

        //** Commit to DB */
        $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] = $new_schedule;


        update_option( 'wpp_settings', $wpp_settings );

        //** Add hash to return json */
        $result['hash'] = $new_schedule['hash'];

      break;

      case 'update_schedule':
        $upd_schedule = $schedule_settings;
        $wpp_settings = get_option( 'wpp_settings' );

        $schedule_hash = md5( sha1( $schedule_id ) );

        $upd_schedule['hash'] = $schedule_hash;

        //** Preserve lastrun settings ( not passed via $_POST ) */
        $upd_schedule['lastrun'] = $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id]['lastrun'];

        $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] = $upd_schedule;

        //** Remove any messed up schedules */
        foreach( $wpp_settings['configuration']['feature_settings']['property_import']['schedules'] as $this_id => $data ) {
          if( strlen( $this_id ) != 10 || empty( $data ) ) {
            unset( $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$this_id] );
          }

        }

        update_option( 'wpp_settings', $wpp_settings );

      break;


      case 'delete_schedule':

        if( $schedule_id ) {
          $wpp_settings = get_option( 'wpp_settings' );
          unset( $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] );
          update_option( 'wpp_settings', $wpp_settings );
          $result['success'] = 'true';

          $import_directory = class_wpp_property_import::create_import_directory( array( 'ad_hoc_temp_dir' => $schedule_id ) );

          if( $import_directory['ad_hoc_temp_dir'] ) {
            class_wpp_property_import::delete_directory( $import_directory['ad_hoc_temp_dir'] , true );
          }

        }

      break;

      case 'delete_all_orphan_attachments':

        set_time_limit( 0 );
        ignore_user_abort( true );

        $deleted_orphan_image_count = array();

        foreach( class_wpp_property_import::get_orphan_attachments() as $orphan_image_id ) {
          if( wp_delete_attachment( $orphan_image_id, true ) ) {
            $deleted_orphan_image_count[] = $orphan_image_id;
          }
        }

        if( is_array( $deleted_orphan_image_count ) && count( $deleted_orphan_image_count ) > 0 ) {

          class_wpp_property_import::delete_orphan_directories();
          $result['success'] = true;
          $result['ui'] = sprintf( __( 'Deleted %1s unattached property files that were created from an XML import.', 'wpp' ), count( $deleted_orphan_image_count ) );

          if( class_exists( 'WPP_UD_F' ) ) {
            WPP_UD_F::log($result['ui']);
          }

        } else {
          $result['success'] = false;
          $result['ui'] = __( 'Something went wrong, did not delete any unattached images.', 'wpp' );
        }


      break;

      case 'delete_all_schedule_properties':

        if( $schedule_id ) {

          set_time_limit( 0 );
          ignore_user_abort( true );

          $deleted_count = array();

          if( $all_properties = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE post_type = 'property' AND meta_key = 'wpp_import_schedule_id' and meta_value = %s  GROUP BY p.ID", $schedule_id ) ) ) {

            $operation_start = time();

            foreach( $all_properties as $property_id ) {
              if( wp_delete_post( $property_id, true ) ) {
                $deleted_count[] = true;
              }
            }

            $operation_length = WPP_F::format_numeric( time() - $operation_start );

            $deleted_count  = array_sum( $deleted_count );

            //** Remove last run stats from schedule */
            $wpp_settings = get_option( 'wpp_settings' );
            $this_schedule = $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id];
            unset( $this_schedule['lastrun'] );
            $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] = $this_schedule;
            update_option( 'wpp_settings', $wpp_settings );


            if( $deleted_count == count( $all_properties ) ) {
              $result['ui'] = sprintf( __( 'All %1$s properties have been deleted in %2$s seconds.', 'wpp' ), $deleted_count, $operation_length );
            } else {
              $result['ui'] = sprintf( __( 'Although %1$s properties were found, only %2$s have been deleted in %3$s seconds.', 'wpp' ), count( $all_properties ), $deleted_count, $operation_length );
            }

          } else {
            $result['ui'] = __( 'Something went wrong, no properties were found to delete.', 'wpp' );
          }

          $result['success'] = 'true';
        }

      break;

      case 'execute_schedule_import':

        //** Not the most elegant solution, but some of these imports can take a while. Using cron is advised. */
        set_time_limit( -1 );

        class_wpp_property_import::maybe_echo_log( "Started loading XML from source." );

        //** For now do not cache live source full imports */
        if( $doing_full_import || $data['source_type'] == 'rets' || $do_not_use_cache ) {
          //** Do not use cache data when doing full RETS import */
          $result['data_from_cache'] = false;

        } else {

          //** Try to get source from cache if it exists.  If found, returned as SimpleXMLElement */
          $cached_data = class_wpp_property_import::get_cached_source( $schedule_id, $data['source_type'] );

          if( !empty( $cached_data ) ) {

            $cache_age = time() - $cached_data['time'];

            //** If cached file is over 30 minutes old, we do not use it */
            if( $cache_age > 1800 ) {
              $cached_data = false;
              class_wpp_property_import::maybe_echo_log( "Cached file is too old, data not used." );
            } else {
              class_wpp_property_import::maybe_echo_log( "Using data in cached file." );
            }

          }

        }

        if( isset( $cached_data ) && !empty( $cached_data ) ) {

          $xml_data = $cached_data['xml_data'];

          //** Loaded from cache */
          $result['data_from_cache'] = true;

          class_wpp_property_import::maybe_echo_log( "Raw feed data loaded from cache." );

        } else {

          //** Try to open the provided file, if RETS feed, data converted into XML and images are downloaded. If source eval, RETS only gets first result. */
          $response = class_wpp_property_import::wpp_make_request( $data['wpp_property_import']['url'], $request_method, $data );

          class_wpp_property_import::maybe_echo_log( "Raw feed data loaded from live source." );

          $result['data_from_cache'] = false;

          //** If error object returned, keep processing, it will be echoed later */
          if( !is_wp_error( $response ) && !empty( $response['body'] ) ) {

            //** If response exists load the raw contents into a variable. */
            $xml_data = $response['body'];

          }

        }

        //* Remove namespaces since there is little support for them */
        $xml_data = str_replace( 'xmlns=','nothing=',$xml_data );

        //** Main function where we load the XML data and convert into object */
        if( $xml = @simplexml_load_string( $xml_data, 'SimpleXMLElement', LIBXML_NOCDATA ) ) {

          class_wpp_property_import::maybe_echo_log( "XML Object loaded successfully from raw data." );

          //** Create temp folder and images. */
          if( $schedule_temp_path = class_wpp_property_import::create_import_directory( array( 'ad_hoc_temp_dir' => $schedule_id ) ) ) {

            class_wpp_property_import::maybe_echo_log( sprintf( __( 'Created temporary directory for import: %1$s.', 'wpp' ), $schedule_temp_path['ad_hoc_temp_dir'] ) );

            $data['temporary_directory'] = $schedule_temp_path['ad_hoc_temp_dir'];

            //** Determine cache file name */
            if( $data['source_type'] ) {
              $cache_file_name = $data['source_type']  . '_cache.xml';
            } else {
              $cache_file_name = 'cache.xml';  /* This should not realy happen */
            }

            $cache_file = $data['temporary_directory'] . '/' . $cache_file_name;

            //** Cache the source */
            if( file_put_contents( $cache_file, $xml_data ) ) {
              $xml_file_size = class_wpp_property_import::format_size( filesize( $cache_file ) );
              $result['file_size'] = $xml_file_size;
              class_wpp_property_import::maybe_echo_log( "XML data ( {$xml_file_size} ), loaded from source, cached in: ". $cache_file );

              $cache_file_url = $schedule_temp_path['ad_hoc_temp_url'] . '/' . $cache_file_name;

            } else {
              class_wpp_property_import::maybe_echo_log( 'Unable to to create cache of source into temorary directory: ' . $data['temporary_directory'] );
            }

          }

          //** All good to go, we can proceed with cycles */
          $process_import = true;

        } else {

          if( is_wp_error( $response ) ) {
            class_wpp_property_import::maybe_echo_log( sprintf( __( 'Could not load XML Object from raw data: %1s.', 'wpp' ), $response->get_error_message() ) );
          } elseif( empty( $xml ) ) {
            class_wpp_property_import::maybe_echo_log( __( 'Could not load XML Object from raw data - empty result returned.', 'wpp' ) );
          } else {
            class_wpp_property_import::maybe_echo_log( __( 'Could not load XML Object from raw data.', 'wpp' ) );
          }

          $result['success'] = 'false';
          $result['message'] = __( 'XML data cannot be loaded.', 'wpp' );
          break; /* break throws the logic to the end of the $wpp_action function, and returns all $result data */

        }

        $root_element_xpath = $data['wpp_property_import']['root_element'];

        //** If no root element xpath passed, we return the raw data */
        if( !empty( $root_element_xpath ) ) {
          $objects = @$xml->xpath( $root_element_xpath );
        } else {
          $objects = $xml;
          $root_element_xpath = false;
        }

        if( $wpp_schedule_import ) {
          if( $objects ) {
            class_wpp_property_import::maybe_echo_log( "Extracted " . count( $objects ) . " total objects from the repeating property elements query." );
          } else {
            class_wpp_property_import::maybe_echo_log( "Failed to extract any objects from the repeating property elements query. Quitting." );
            return;
          }
        }

        /** Analayze data, always - shouldn't take too long once its loaded. */
        if( $auto_matched_tags = class_wpp_property_import::analyze_feed( $data['wpp_property_import']['source_type'] == 'wpp' ? 'property' : false, $xml ) ) {
          $result['auto_matched_tags'] = $auto_matched_tags;
        }

        //** Handle raw preview or no objects. */
        if( !$objects || ( isset( $_REQUEST['raw_preview'] ) && $_REQUEST['raw_preview'] == 'true' ) ) {

          if( $result['data_from_cache'] ) {
            $result['ui'] .= __( 'Data loaded from cache.' ,'wpp' ) .  "\n\n";
          }

          if( $root_element_xpath ) {

            if( $objects ) {
              $result['ui'] .= count( $objects ) . __( ' object(s) found with XPath Rule: ' ,'wpp' ) . $root_element_xpath . "\n\n";
              $result['preview_bar_message'] = sprintf( __( '%1s objects identified: <a href="%2s" target="_blank">download processed XML file</a> ( %3s ).', 'wpp' ), count( $objects ), $cache_file_url, $result['file_size'] );
            } else {
              $result['ui'] .= __( 'Root Element XPath Rule: ' ,'wpp' ) . $root_element_xpath . "\n\n";
            }

          } else {
            $result['ui'] .= __( 'No Root Element XPath Rule, displaying most root elements.','wpp' ). "\n\n";
          }

          if( !$objects ) {
            $result['ui'] .= __( 'No objects found.','wpp' ). "\n\n";

          } else {
            $truncate_limit = 50000;
            $total_length = strlen( print_r( $objects, true ) );

            if( $total_length > $truncate_limit ) {
              $result['ui'] .= sprintf( __( 'Preview truncated: showing: %1s of full feed:', 'wpp' ),  ( round( ( $truncate_limit / $total_length ), 4 ) * 100 ) . '%' ) . "\n\n";
            }

            $result['ui'] .= htmlentities( substr( print_r( $objects,true ),0,$truncate_limit ) );

            $result['ui'] .= "\n\n\n" . sprintf( __( 'Available tags in source: %1s', 'wpp' ), "\n\n" .  print_r( $result['auto_matched_tags'], true ) ) . "\n\n";

            $result['success'] = 'true';
          }

          //** Blank out auto matched tags */
          /* $result['auto_matched_tags'] = 'none'; */

          break; /* break throws the logic to the end of the $wpp_action function, and returns all $result data */
        }

        //** Load schedule data from DB, if it isn't already loaded, such as by a preview. */
        if( !$schedule_settings ) {
          $schedule_settings = $wp_properties['configuration']['feature_settings']['property_import']['schedules'][$schedule_id];
        }

        //** Build array of slugs that may have multiple values */
        $allow_multiple = array( 'images' );
        foreach( $wp_properties['taxonomies'] as $slug => $tax ) {
          $allow_multiple[] = $slug;
        }

        $allow_multiple = apply_filters( 'wpp_import_attributes_allow_multiple', $allow_multiple );

        //** Stop here if we are only evaluating, and return tags */
        if( isset( $action_type ) && $action_type == 'source_evaluation' ) {

          $result['common_tag_name'] = $common_tag_name;
          $result['success'] = 'true';

          break; /* break throws the logic to the end of the $wpp_action function, and returns all $result data */

        } /** End source_evaluation */


        //** Add certain rules automatically */
        if( $data['wpp_property_import']['source_type'] == 'wpp' ) {

          //** Add parent GPID */
          array_push( $data['wpp_property_import']['map'], array(
            'wpp_attribute' => 'parent_gpid',
            'xpath_rule' => 'parent_gpid'
          ) );

        }

        $wpp_import_result_stats[] = "Extracted " . count( $objects ) . " total objects from the repeating property elements query.";

        //** Cycle through individual objects and load queried information into $import_data array; */
        if( $objects && $schedule_settings['log_detail'] == 'on' ) {
          class_wpp_property_import::maybe_echo_log( "Beginning object cycle." );
        }

        $counter = 0;
        foreach( $objects as $import_object ) {

          //** Process every rule and run query against the object */
          foreach( $data['wpp_property_import']['map'] as $rule ) {

            unset( $return );

            $rule_attribute = $rule['wpp_attribute'];
            $xpath_rule = stripslashes( $rule['xpath_rule'] );

            if( empty( $xpath_rule ) ) {
              continue;
            }

            if( strpos( $xpath_rule, 'free_text:' ) !== false ) {

              //* Handle plain text */
              $import_data[$counter][$rule_attribute][] = str_replace( 'free_text:', '', $xpath_rule );

            } elseif( strpos( $xpath_rule, 'concat_list' ) !== false ) {
              //* Import label/value pairs for non-existant meta_keys   */

              //** Breaks xpath rule into array */
              $xpath_atts = shortcode_parse_atts( $xpath_rule, $defaults );

              //* Get Root Path */
              $concat_results = @$import_object->xpath( $xpath_atts['root_path'] );

              if( is_array( $concat_results ) ) {
                foreach( $concat_results as $single_result ) {
                  $label = $single_result->xpath( $xpath_atts['label_path'] );
                  $value = $single_result->xpath( $xpath_atts['value_path'] );

                  $label = trim( ( string )$label[0] );
                  $value = trim( ( string )$value[0] );

                  $value = class_wpp_property_import::format_single_value( array( 'value' => $value, 'rule_attribute' => $rule_attribute, 'schedule_settings' => $schedule_settings ) );

                  $import_data[$counter][$rule_attribute][] =$label . ( $label & $value ? $xpath_atts['concat_character'] : '' ) . $value ;
                }

              }

              continue;


            } elseif( strpos( $xpath_rule, 'concat:' ) !== false ) {

              //* concat: 'expression one' some text 'expression two'  */
              $xpath_rule = str_replace( 'concat:', '', $xpath_rule );

              $num_matched = preg_match_all( "/'([^']*)'/", $xpath_rule, $matches, PREG_SET_ORDER );

              //* concat matches found  */
              if( $matches ) {
                foreach( $matches as $match_rule ) {

                  //* get the requeted attributes from concat list and load into temporary array*/
                  $concat_results[] = @$import_object->xpath( $match_rule[1] );

                  foreach( $concat_results as $concat_result ) {

                    $this_value = ( string )$concat_result[0];
                    $this_value = class_wpp_property_import::format_single_value( array( 'value' => $this_value, 'rule_attribute' => $rule_attribute, 'schedule_settings' => $schedule_settings ) );

                    //* load single-item results into another temp array*/
                    $to_concat[$match_rule[1]] = $this_value;
                  }

                }

                foreach( $to_concat as $match_key => $match_value ) {
                  //* replace the original rule with the real XML values if they exist */
                  $xpath_rule = str_replace( "'" . $match_key . "'", $match_value, $xpath_rule );
                }

                  //* remove extra apostraphes and trim line */
                $import_data[$counter][$rule_attribute][] = trim( str_replace( "'",'', $xpath_rule ) );

                continue;
              }



            } else {
              //* Handle regular xpath */
              $return = @$import_object->xpath( $xpath_rule );
            }


            //* If nothing returned at all, go to next rule */
            if( !$return ) {
              continue;
            }


            //* Cycle through returns and save them into $import_data */
            foreach( $return as $attribute ) {

              //* Add first matched value to rule_attribute */
              $this_value = ( string ) $attribute[0];

              $this_value = class_wpp_property_import::format_single_value( array( 'value' => $this_value, 'rule_attribute' => $rule_attribute, 'schedule_settings' => $schedule_settings ) );

              $import_data[$counter][$rule_attribute][] = $this_value;

            }


          } //** end single rule cycle */

          //**  All Rules have been processed. Cycle back through rules and concatenate any values that are not allowed to have multiple values */
          if( $import_data[$counter] ) {
            foreach( $import_data[$counter] as $rule_attribute => $values ) {

              if( !in_array( $rule_attribute, $allow_multiple ) ) {

                $values = ( array ) $values;

                if( count( $values ) > 1 ) {

                  //** Make sure featured-image is not being concatenated */
                  if( $rule_attribute == 'featured-image' ) {

                    //** Make sure there is a regular image array */
                    if( !is_array( $import_data[$counter]['images'] ) ) {
                      $import_data[$counter]['images'] = array();
                    }

                    //** Move all but the first featured image into regular image array, into the beginning, because its probably important */
                    $import_data[$counter]['images'] = array_merge( array_slice( $values, 1 ), $import_data[$counter]['images'] );

                    //** Remove all but the first image for the featured image array */
                    $import_data[$counter][$rule_attribute] =  array_slice( $values, 0, 1 );


                  } else {
                    unset( $import_data[$counter][$rule_attribute] );
                    $import_data[$counter][$rule_attribute][0] = implode( "\n", $values );
                  }

                }
              }

            }
          }

          $import_data[$counter]['unique_id'] = $data['wpp_property_import']['unique_id'];

          $import_data[$counter] = apply_filters( 'wpp_xml_import_do_rule',$import_data[$counter], $import_object,$data['wpp_property_import'] );

          //** If skipping properties without images, cycle back through and remove any properties without images */
          if( isset( $schedule_settings['minimum_images'] ) && $schedule_settings['minimum_images'] > 0 ) {

            $total_images = count( $import_data[$counter]['images'] ) + count( $import_data[$counter]['featured-image'] );

            if( $total_images < $schedule_settings['minimum_images'] ) {
              unset( $import_data[$counter] );
              $no_image_skip[] = true;
            }

          }

          //** If preview, stop after first processed property */
          if( $preview_import ) {
            $result['success'] = 'true';
            $result['ui'] = "XPath Rule: {$root_element_xpath}\n\n" . htmlentities( print_r( $import_data[$counter], true ) );
            break; /* break throws the logic to the end of the $wpp_action function, and returns all $result data */
          }


          if( $schedule_settings['log_detail'] == 'on' ) {

            if( is_array( $import_data[$counter] ) ) {
              $extracted_attributes = count( array_keys( $import_data[$counter] ) );
            } else {
              $extracted_attributes = 0;
            }

            class_wpp_property_import::maybe_echo_log( sprintf( __( 'XPath rules for object #%1d processed with %2d extracted attributes.', 'wpp' ), ( $counter + 1 ), $extracted_attributes ) );
          }

          $counter++;

          if( !empty( $schedule_settings['limit_scanned_properties'] ) && $schedule_settings['limit_scanned_properties'] == $counter ) {
            class_wpp_property_import::maybe_echo_log( sprintf( __( 'Stopping import due to specified pre-QC limit of %1d.', 'wpp' ), $counter ) );
            $wpp_import_result_stats[] = $import_created . " new properties imported, stopping due to pre-QC limit.";
            break;
          }
        } //** end $objects loop */

        //** In case didn't get stopped in the loop above */
        if( $preview_import ) {
          break;
        }

        //** Check how many properties had no images */
        if( is_array( $no_image_skip ) ) {
          $no_image_skip = array_sum( $no_image_skip );
          class_wpp_property_import::maybe_echo_log( "Skipped {$no_image_skip} properties because they had no images." );
          $wpp_import_result_stats[] = "{$no_image_skip} properties skipped because they have no images.";
        }

        class_wpp_property_import::maybe_echo_log( "All XPath rules processed, ".count( $import_data )." properties remain." );
        $wpp_import_result_stats[] = count( $import_data ) . " properties remaining after processing XPath rules on objects.";

        class_wpp_property_import::maybe_echo_log( sprintf( __( 'Importing %1s', 'wpp' ), htmlspecialchars( $schedule_settings['url'] ) ) );

        $import_updated = $import_created = $imported_images = $imported_images_size = $existing_images = 0;

        //** Do the actual import **/
        if( $process_import && !empty( $import_data ) ) {

          UD_F::log( 'Running XML Import job ' .$schedule_id . ' at ' . date( "F j, Y, g:i a", time() ) );

          //** Dump all properties and their attachments before importing anything new */
          if( $schedule_settings['remove_all_before_import'] == 'on' ) {

            $all_properties = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'property'" );

            class_wpp_property_import::maybe_echo_log( "Deleting all old properties. " . count( $all_properties ). " found." );

            $property_delete_counter = 0;
            $attachment_delete_counter = 0;

            foreach( (array) $all_properties as $property_id ) {
              if( wp_delete_post( $property_id, true ) ) {
                $property_delete_counter++;
              }
            }

            if( $property_delete_counter ) {

              class_wpp_property_import::maybe_echo_log( "{$property_delete_counter} properties deleted." );
              class_wpp_property_import::maybe_echo_log( "{$attachment_delete_counter} attachments deleted." );

              $wpp_import_result_stats[] = "All existing properties ( " . $property_delete_counter . " ) deleted.";
           }


          } elseif( $schedule_settings['remove_all_from_this_source'] == 'on' ) {
            //** Remove all objects that originated from this feed */

            $result = class_wpp_property_import::delete_feed_properties( $schedule_id, $schedule_settings );

            if( $result['deleted_count'] > 0 ) {
              $wpp_import_result_stats[] = sprintf( __( 'Deleted all ( %1s ) properties that originated from this feed.', 'wpp' ), $result['deleted_count'] );
              class_wpp_property_import::maybe_echo_log( sprintf( __( 'Deleted all ( %1s ) properties that originated from this feed.', 'wpp' ), $result['deleted_count'] ) );
            } elseif( $result['total_found'] == 0 ) {
              class_wpp_property_import::maybe_echo_log( __( 'Did not find any properties that have been imported from this feed to remove.', 'wpp' ) );
            }

            //** End: $schedule_settings['remove_all_from_this_source'] == 'on' */
          }  else {
            class_wpp_property_import::maybe_echo_log( __( 'Did not remove any old properties.', 'wpp' ) );
          }

          // Handle the actual import
          class_wpp_property_import::maybe_echo_log( 'Beginning object cycle. We have ' . count( $import_data ) . ' objects.' );

          //** Cycle through each XML object **/
          foreach( $import_data as $zero_counter => $single_object_data ) {

            //** Updated counter to not be zero based */
            $counter = ( $zero_counter + 1 );

            if( !empty( $schedule_settings['limit_properties'] ) && $schedule_settings['limit_properties'] == $import_created ) {
              class_wpp_property_import::maybe_echo_log( sprintf( __( 'Stopping import due to specified post-QC limit of %1d.', 'wpp' ), $counter ) );
              $wpp_import_result_stats[] = $import_created . " new properties imported, stopping due to limit.";
              break;
            }

            $unique_id = $single_object_data[$schedule_settings['unique_id']][0];

            //** Skip object import if no unique ID value exists ( @todo may need to add an option to not do this, some feeds may not have unique attributes - potanin@UD ) */
            if( empty( $unique_id ) ) {
              class_wpp_property_import::maybe_echo_log( "Skipping property, unique ID not found. " );
              continue;
            }

            /** Perform single object importing */
            $import_object = class_wpp_property_import::import_object( $single_object_data, $schedule_id,$counter );

            if( is_wp_error( $import_object ) ) {

              //** Error occured */
              class_wpp_property_import::maybe_echo_log( 'Error on single object import: ' . $import_object->get_error_message() );

              //** Stop this object import */
              continue;

            } elseif ( is_numeric( $import_object[0] ) ) {

              // Actual post_id stored in $import_object[0]
              $imported_objects[] = $import_object[0];

              if( $import_object[1] == 'u' ) {
                 $import_updated += 1;
              } else if( $import_object[1] == 'c' ){
                $import_created += 1;
              }

              $imported_images += $import_object[2];
              $imported_images_size += $import_object[3];
              $existing_images += $import_object[4];

              unset($import_object);

            } else {
              // This happens if the property was not inserted, or deleted, such as if there were no images.
            }
          }

          class_wpp_property_import::maybe_echo_log( sprintf( __( 'Object cycle done. Completed %1d cycles.', 'wpp' ), $counter -1 ) );

          //** Remove any objects that are no longer in source ( do not remove non existant if we only did a limited import ) */
          if( empty( $schedule_settings['limit_properties'] )  && empty( $schedule_settings['limit_scanned_properties'] )  && $schedule_settings['remove_non_existant'] == 'on' && $schedule_settings['remove_all_from_this_source'] != 'on' ) {

            $result = class_wpp_property_import::delete_feed_properties( $schedule_id, $schedule_settings, $imported_objects );

            if( $result['deleted_count'] > 0 ) {
              $wpp_import_result_stats[] = sprintf( __( 'Deleted ( %1s ) properties that are no longer in the feed.', 'wpp' ), $result['deleted_count'] );
              class_wpp_property_import::maybe_echo_log( sprintf( __( 'Deleted ( %1s ) properties that are no longer in the feed.', 'wpp' ), $result['deleted_count'] ) );
            } elseif( $result['total_found'] == 0 ) {
              class_wpp_property_import::maybe_echo_log( __( 'Did not find any properties that have been imported from this feed to remove.', 'wpp' ) );
            }

          } //** End: $schedule_settings['remove_non_existant'] == 'on' */

          //** Reassociate WP parent IDs **//
          //class_wpp_property_import::reassociate_parent_ids();

          //** Update addresses of imported properties */
          if( $schedule_settings['revalidate_addreses_on_completion'] == 'on' ) {
            class_wpp_property_import::maybe_echo_log( "Revalidating all imported addresses." );
            $revalidation_result = WPP_F::revalidate_all_addresses( array( 'property_ids' => $imported_objects, 'echo_result' => 'false', 'skip_existing' => 'true' ) );
            class_wpp_property_import::maybe_echo_log( $revalidation_result['message'] );
          }

          if( count( $revalidation_result['geo_data'] ) ) {
            $wpp_import_result_stats[] = count( $revalidation_result['geo_data'] ) . " addresses re-validated.";
          } else {
            /* $wpp_import_result_stats[] = "No addresses revalidated."; */
          }

          //** Delete temporary files and folder */
          if( $data['temporary_directory'] ) {
            if( class_wpp_property_import::delete_directory( $data['temporary_directory'] , true ) ) {
              class_wpp_property_import::maybe_echo_log( __( 'Deleted the import temporary directory.', 'wpp' ) );
            } else {
              class_wpp_property_import::maybe_echo_log( __( 'Unable to delete the import temporary directory', 'wpp' ) );
            }
          }

          UD_F::log( 'Completed XML Import job ' . $_REQUEST['schedule_id'] . ' at ' . date( "F j, Y, g:i a", time() ) . ', ( '  . $import_created . ' )  created and ( ' .  $import_updated  . ' ) objects updated. ' );

          if( $import_created ) {
            $wpp_import_result_stats[] = "Total of " . $import_created . " new properties added.";
            $wpp_import_result_stats['quantifiable']['added_properties'] = $import_created ;
          }

          if( $import_updated ) {
            $wpp_import_result_stats[] = "Total of " . $import_updated . " properties updated.";
            $wpp_import_result_stats['quantifiable']['updated_properties'] = $import_updated ;
          }

          if( $imported_images  ) {

            if( $imported_images_size ) {
              $imported_images_size = class_wpp_property_import::format_size( $imported_images_size );
              $wpp_import_result_stats['quantifiable']['imported_images_size'] = $imported_images_size;
            }

            $wpp_import_result_stats[] = "Total of " . $imported_images . " images at {$imported_images_size} downloaded and associated.";
            $wpp_import_result_stats['quantifiable']['downloaded_images'] = $imported_images ;
          }

          if( $existing_images ) {
            $wpp_import_result_stats[] = "Total of " . $existing_images  . " images were already on disk.";
            $wpp_import_result_stats['quantifiable']['existing_images'] = $existing_images ;
          }


          //** Handle updating settings after import is complete */
          if( $schedule_id ) {
            //** Cannot get get_option() because it uses cached values, and since the importer could have taken a while, changes may have been made to options */
            $wpp_settings = unserialize( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name ='wpp_settings'" ) );

            $schedule_settings['lastrun']['time'] = time();
            $schedule_settings['lastrun']['u'] = ( !empty( $import_updated ) ) ? $import_updated : 0;
            $schedule_settings['lastrun']['c'] = ( !empty( $import_created ) ) ? $import_created : 0;

            $wpp_settings['configuration']['feature_settings']['property_import']['schedules'][$schedule_id] = $schedule_settings;

            update_option( 'wpp_settings', $wpp_settings );
          }

        }

        class_wpp_property_import::delete_orphan_directories();

        do_action( 'wpp_xml_import_complete' );

        //** Print out queries up to this point, and blank out the query log */
        if( $schedule_settings[ 'show_sql_queries' ] && !empty( $wpdb->queries ) ) {
          foreach( (array) $wpdb->queries as $query_data ) {
            class_wpp_property_import::maybe_echo_log( $query_data[ 0 ] );
          }
        }

      break; /* end case: execute_schedule_import */


      case 'add_edit_schedule':

        $edit_current = array();

        if( !empty( $_REQUEST['schedule_id'] ) ) {
          //** Existing Schedule */
          $edit_current = $wpp_property_import['schedules'][$_REQUEST['schedule_id']];
          $new_schedule = false;
        } else {
          //** New Schedule - load defaults */
          $edit_current['map'][1]['wpp_attribute'] = 'post_title';
          $new_schedule = true;
        }

      ?>

    <style type="text/css">
      <?php if( $edit_current['source_type'] == "gs" ): ?>
        div#wpp_property_import_ajax div.wpp_property_import_setup .wpp_property_import_gs_options wpp_i_advanced_source_settings { display: block; }
      <?php elseif( $edit_current['source_type'] == "rets" ): ?>
        div#wpp_property_import_ajax div.wpp_property_import_setup .wpp_property_import_rets_options wpp_i_advanced_source_settings { display: block; }
        div#wpp_property_import_ajax div.wpp_property_import_setup .wpp_property_import_rets_options wpp_i_advanced_source_settings { display: none; }
      <?php endif; ?>
    </style>
    <div class="wpp_property_import_setup" import_type="<?php echo ( $edit_current['source_type'] ? $edit_current['source_type'] : 'xml' ); ?>" >

      <form id="wpp_property_import_setup" action="#">

      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="wpp_property_import_name"><?php _e( 'Import name', 'wpp' ); ?></label>
            </th>
            <td>
              <input class="regular-text wpp_property_import_name"  id="wpp_property_import_name" name="wpp_property_import[name]" type="text" value="<?php echo $edit_current['name']?>"/>
            </td>
          </tr>

        <tr class="step_one">
          <th>
            <label for="wpp_property_import_remote_url"><?php _e( 'Source', 'wpp' ); ?></label>
          </th>
          <td>
            <ul class="wppi_source_option_preview_wrapper">
              <li>
                <label for="wpp_property_import_remote_url"><?php _e( 'URL', 'wpp' ); ?></label>
                <input class="regular-text wpp_property_import_remote_url" name="wpp_property_import[url]"  type="text" id="wpp_property_import_remote_url" value="<?php echo esc_attr( $edit_current['url'] ); ?>" />

                <label for="wpp_property_import_source_type"><?php _e( 'Type:', 'wpp' ); ?></label>
                <select id="wpp_property_import_source_type" name="wpp_property_import[source_type]"  >
                  <option value="">  </option>
                  <option <?php selected( $edit_current['source_type'], 'xml' ); ?> value="xml"><?php _e( "XML / JSON", 'wpp' ); ?></option>
                  <option <?php selected( $edit_current['source_type'], 'csv' ); ?> value="csv"><?php _e( "CSV", 'wpp' ); ?></option>
                  <option <?php selected( $edit_current['source_type'], 'gs' ); ?>   value="gs"><?php _e( "Google Spreadsheet", 'wpp' ); ?></option>
                  <option <?php selected( $edit_current['source_type'], 'wpp' ); ?>   value="wpp"><?php _e( "WP-Property Feed", 'wpp' ); ?></option>
                  <option <?php selected( $edit_current['source_type'], 'rets' ); ?>   value="rets"><?php _e( "RETS", 'wpp' ); ?></option>
                </select>
                <span id="wpp_property_import_source_status" class="button"></span>
              </li>
              <li class="wpp_i_source_feedback"></div>
            </ul>

            <ul class="wpp_something_advanced_wrapper wppi_source_option_preview_wrapper">

              <li class="wpp_i_source_specific wpp_i_advanced_source_settings" wpp_i_source_type="xml">
                <input type="checkbox" id="wpp_property_import_use_postauth_checkbox" name="wpp_property_import[postauth]" <?php echo checked( 'on', $edit_current['postauth'] ); ?>/>
                <label class="description" for="wpp_property_import_use_postauth_checkbox"><?php echo __( 'Send GET variables as POST data.','wpp' ); ?></label>
              </li>
              <?php /*
              <li class="wpp_i_source_specific wpp_i_advanced_source_settings" wpp_i_source_type="csv">
                <input type="checkbox" id="wpp_i_csv_no_headers" name="wpp_property_import[csv][no_headers]" <?php echo checked( 'on', $edit_current['csv']['no_headers'] ); ?>/>
                <label class="description" for="wpp_i_csv_no_headers"><?php echo __( 'First line <b>does not</b> contain headers.','wpp' ); ?></label>
              </li>
              */ ?>
              <li class="wpp_i_source_specific wpp_property_import_gs_options wpp_i_advanced_source_settings" wpp_i_source_type="gs">
                <input type="text" class="regular-text"  name="wpp_property_import[google_username]" id='wpp_property_import_username'  value="<?php echo $edit_current['google_username']?>" />
                <label for="wpp_property_import_username"><?php _e( 'Google Username', 'wpp' ); ?></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_gs_options wpp_i_advanced_source_settings" wpp_i_source_type="gs">
                <input type="password" class="regular-text"  name="wpp_property_import[google_password]" id='wpp_property_import_password' value="<?php echo $edit_current['google_password']?>" />
                <label for="wpp_property_import_password"><?php _e( 'Google Password', 'wpp' ); ?></label>
               </li>
              <li class="wpp_i_source_specific wpp_property_import_gs_options wpp_i_advanced_source_settings"  wpp_i_source_type="gs">
                <input type="text" class="regular-text"  name="wpp_property_import[google_extra_query]" id='wpp_property_import_extra_query'  value="<?php echo $edit_current['google_extra_query']?>" />
                <label for="wpp_property_import_extra_query"><?php _e( 'Google Extra Query Vars', 'wpp' ); ?></label><br />
                <span class="description"><?php _e( 'See the <a href="http://code.google.com/apis/spreadsheets/data/3.0/reference.html#ListParameters" target="_blank">Google Spreadsheet API docs</a> for the format of this field ( should be name value pairs, without the beginning "?" )', 'wpp' ); ?></span>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options"  wpp_i_source_type="rets">
                <input type="text" class="regular-text wpp_required" name="wpp_property_import[rets_username]" id='wpp_property_import_rets_username'  value="<?php echo $edit_current['rets_username']?>" />
                <label for="wpp_property_import_rets_username"><?php _e( 'RETS Username.', 'wpp' ); ?></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options"  wpp_i_source_type="rets">
                <input type="password" class="regular-text wpp_required"  name="wpp_property_import[rets_password]" id='wpp_property_import_rets_password'  value="<?php echo $edit_current['rets_password']?>" />
                <label for="wpp_property_import_rets_password"><?php _e( 'RETS Password.', 'wpp' ); ?></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options"  wpp_i_source_type="rets">
                <input type="text" class="regular-text wpp_required"  name="wpp_property_import[rets_class]" id='wpp_property_import_rets_class'  value="<?php echo $edit_current['rets_class']?>" />
                <label for="wpp_property_import_rets_class"><?php _e( 'Property Resource Class.', 'wpp' ); ?> </label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options" wpp_i_source_type="rets">
                <input type="text" class="regular-text wpp_required"  name="wpp_property_import[rets_pk]" id='wpp_property_import_rets_pk'  value="<?php echo $edit_current['rets_pk']?>" />
                <label for="wpp_property_import_rets_pk"><?php _e( 'Primary Key for Resource.', 'wpp' ); ?> <span class="description"><?php _e( 'Also referred to as "Key Field". Default is "ListingKey"', 'wpp' ); ?></span></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options wpp_i_advanced_source_settings" wpp_i_source_type="rets">
                <input type="text" class="regular-text"  name="wpp_property_import[rets_photo]" id='wpp_property_import_rets_photo'  value="<?php echo $edit_current['rets_photo']?>" />
                <label for="wpp_property_import_rets_photo"><?php _e( 'Photo Object.', 'wpp' ); ?> <span class="description"><?php _e( 'Default is "Photo"', 'wpp' ); ?></span></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options wpp_i_advanced_source_settings" wpp_i_source_type="rets">
                <input type="text" class="regular-text"  name="wpp_property_import[rets_agent]" id='wpp_property_import_rets_agent'  value="<?php echo $edit_current['rets_agent']?>" />
                <label for="wpp_property_import_rets_agent"><?php _e( 'User-Agent String.', 'wpp' ); ?> <span class="description"><?php _e( 'May be required by your RETS', 'wpp' ); ?></span></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options wpp_i_advanced_source_settings" wpp_i_source_type="rets">
                <input type="text" class="regular-text"  name="wpp_property_import[rets_agent_password]" id='wpp_property_import_rets_agent_password'  value="<?php echo $edit_current['rets_agent_password']?>" />
                <label for="wpp_property_import_rets_agent_password"><?php _e( 'User-Agent Password.', 'wpp' ); ?> <span class="description"><?php _e( 'May be required by your RETS', 'wpp' ); ?></span></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options wpp_i_advanced_source_settings" wpp_i_source_type="rets">
                <?php /** @todo Remove inline styling - adding for now as quick fix -- williams@UD */ ?>
                <select style="width:25em;" name="wpp_property_import[rets_version]" id='wpp_property_import_rets_version'>
                  <?php foreach( array( 'RETS/1.0' => '1.0', 'RETS/1.5' => '1.5', 'RETS/1.7' => '1.7', 'RETS/1.7.2' => '1.7.2', ) as $key => $option ) { ?>
                    <option value = '<?php echo $key; ?>' <?php echo ( $edit_current['rets_version'] == $key ? 'selected="selected"' : '' ); ?>><?php echo $option; ?></option>
                  <?php } ?>
                </select>
                <label for="wpp_property_import_rets_version"><?php _e( 'RETS Version.', 'wpp' ); ?> <span class="description"><?php _e( 'Version is set by your RETS provider.', 'wpp' ); ?></span></label>
              </li>
              <li class="wpp_i_source_specific wpp_property_import_rets_options wpp_i_advanced_source_settings"  wpp_i_source_type="rets">
                <input type="text" class="regular-text"  name="wpp_property_import[rets_query]" id='wpp_property_import_rets_query'  value="<?php echo $edit_current['rets_query']?>" />
                <label for="wpp_property_import_rets_query"><?php _e( 'Property Query.', 'wpp' ); ?> <span class="description"><?php _e( 'Accepts <a href="https://www.flexmls.com/support/rets/tutorials/dmql/" target="_blank">DMQL</a> - Default is "(ListingStatus=|Active)"', 'wpp' ); ?></span></label>
              </li>

              <li class="wpp_show_advanced_wrapper">
                <span class="wpp_show_advanced" advanced_option_class="wpp_i_advanced_source_settings" show_type_source="wpp_property_import_source_type" show_type_element_attribute="wpp_i_source_type"><?php _e( 'Toggle Advanced Source Options', 'wpp' ); ?></span>
              </li>
            </ul>

            <ul class="wppi_source_option_preview_wrapper">
            <li>
              <label for="wpp_property_import_choose_root_element" class="description"><?php echo __( 'Root XPath Query:','wpp' ); ?></label>
              <input type='text' id="wpp_property_import_choose_root_element" name="wpp_property_import[root_element]" value="<?php echo esc_attr( $edit_current['root_element'] ); ?>" class="wpp_property_import_choose_root_element"/>
              <span class="wpp_link wpp_toggle_contextual_help" wpp_scroll_to="#tab-link-xpath-query-to-property-elements"><?php _e( 'What is this?', 'wpp' ); ?></span>
            </li>
            </ul>

            <ul class="wppi_source_option_preview_wrapper">
              <li>
                <ul>
                  <li>
                    <input type="button" id="wpp_i_preview_raw_data" value="<?php _e( 'Preview Raw Data', 'wpp' ); ?>" class="button-secondary" <?php echo $p_d?>>
                    <span class="wpp_i_preview_raw_data_result"></span>
                    <span class="wpp_i_close_preview hidden wpp_link"><?php _e( 'Close Preview','wpp' );?></span>
                  </li>
                  <li>
                    <div class="wppi_raw_preview_result"></div>
                  </li>
                </ul>
              </li>
            </ul>


          </td>
        </tr>


        <tr>
          <th>
            <label for="wpp_property_import_property_type"><?php _e( 'Default Property Type', 'wpp' ); ?></label>
          </th>
          <td>
           <select  name="wpp_property_import[property_type]" id="wpp_property_import_property_type">
            <?php foreach( $wp_properties['property_types'] as $property_slug => $property_title ): ?>
            <option value="<?php echo $property_slug; ?>" <?php selected( $property_slug, $edit_current['property_type'] ); ?>><?php echo $property_title; ?></option>
            <?php endforeach; ?>
           </select>
           <span class="description"><?php _e( 'Will be defaulted to if no xPath rule exists for the "Property Type".', 'wpp' ); ?></span>
          </td>
        </tr>

        <tr>

          <th>
            <label for="wpp_property_import_settings"><?php _e( 'Advanced Options' ); ?></label>
          </th>
          <td>

            <input type="hidden" name="wpp_property_import[is_scheduled]" value="on" />

            <ul class="wpp_property_import_settings hidden">

            <li class="wpp_xi_advanced_setting">
              <label class="description" for="wpp_property_limit_scanned_properties"><?php echo __( '<b>Pre-QC Limit:</b> Limit import to the first','wpp' );?>
              <input type="text"  class="wpp_xmli_enforce_integer"  id="wpp_property_limit_scanned_properties" name="wpp_property_import[limit_scanned_properties]" value="<?php echo ( empty( $edit_current['limit_scanned_properties'] ) ? '' : $edit_current['limit_scanned_properties'] ); ?>"/>
              <?php echo __( 'properties in the feed.','wpp' );?>
              <span wpp_scroll_to="h3.limit_import" class="wpp_link wpp_toggle_contextual_help"><?php _e( 'More about limits.', 'wpp' ); ?></span>
            </label>
            </li>
            <li class="wpp_xi_advanced_setting">
              <label class="description"><?php echo __( '<b>Post-QC Limit:</b> Limit import to the first','wpp' );?>
              <input type="text"   class="wpp_xmli_enforce_integer"  id="wpp_property_limit_properties" name="wpp_property_import[limit_properties]" value="<?php echo ( empty( $edit_current['limit_properties'] ) ? '' : $edit_current['limit_properties'] ); ?>"/>
              <?php echo __( 'created properties that have passed quality standards.','wpp' );?>
              </label>
            </li>

             <li class="wpp_xi_advanced_setting">
              <?php printf(
                __( 'Only import images that are over %1spx in width, and %2spx in height.','wpp' ),
                '<input type="text" value="'. $edit_current["min_image_width"] .'" name="wpp_property_import[min_image_width]" />',
                '<input type="text" value="'. $edit_current["min_image_height"] .'"  name="wpp_property_import[min_image_height]" />'
                );
              ?>
              <span class="description"><?php _e( 'Minimum sizes are ignored if blank.','wpp' );?></span>
            </li>

            <li class="wpp_xi_advanced_setting">
              <label class="description"><?php echo __( 'Imported properties must have at least ','wpp' );?>
              <input type="text" id="wpp_i_minimum_images" class="wpp_xmli_enforce_integer" name="wpp_property_import[minimum_images]" value="<?php echo ( empty( $edit_current['minimum_images'] ) ? '' : $edit_current['minimum_images'] ); ?>"/><?php echo __( ', but no more than ','wpp' );?>
              <input type="text" id="wpp_i_limit_images" name="wpp_property_import[limit_images]" value="<?php echo ( empty( $edit_current['limit_images'] ) ? '' : $edit_current['limit_images'] ); ?>"/>
              <?php echo __( ' valid images.','wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <label class="description" for="wpp_property_reimport_delay"><?php echo __( 'Do not import properties that have been imported less than ','wpp' );?>
              <input type="text" id="wpp_property_reimport_delay" name="wpp_property_import[reimport_delay]" value="<?php echo ( empty( $edit_current['reimport_delay'] ) ? 0 : $edit_current['reimport_delay'] ); ?>"/>
              <?php echo __( 'hour(s) ago.','wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <label class="description">
                <input type="checkbox" name="wpp_property_import[automatically_feature_first_image]" value="on"<?php echo checked( 'on', $edit_current['automatically_feature_first_image'] ); ?> />
                <?php echo __( 'Automatically set the first image as the thumbnail.' ,'wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_import_remove_non_existant_properties" name="wpp_property_import[remove_non_existant]" value="on"<?php echo checked( 'on', $edit_current['remove_non_existant'] ); ?> />
              <label class="description" for="wpp_property_import_remove_non_existant_properties">
                <?php echo __( 'Remove properties that are no longer in source XML from this site\'s database. This can now be done if the the import configuration has a Pre-QC or Post-QD Limit.' , 'wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_remove_images" name="wpp_property_import[remove_images]" value="on" <?php echo checked( 'on', $edit_current['remove_images'] ); ?>/>
              <label class="description" for="wpp_property_remove_images"><?php echo __( 'When updating an existing property, remove all old images before downloading new ones.','wpp' );?></label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_send_email_updates" name="wpp_property_import[send_email_updates]" value="on" <?php echo checked( 'on', $edit_current['send_email_updates'] ); ?>/>
              <label class="description" for="wpp_send_email_updates">
                <?php printf( __( 'Send email updates to the site admin e-mail address ( %1s ) when import schedules are executed and completed.','wpp' ), get_option( 'admin_email' ) );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_import_remove_all_from_this_source" name="wpp_property_import[remove_all_from_this_source]" value="on"<?php echo checked( 'on', $edit_current['remove_all_from_this_source'] ); ?> />
              <label class="description" for="wpp_property_import_remove_all_from_this_source">
                <?php echo __( 'Remove all properties that were originally imported from this feed on import.','wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_import_remove_all_before_import" name="wpp_property_import[remove_all_before_import]" value="on"<?php echo checked( 'on', $edit_current['remove_all_before_import'] ); ?> />
              <label class="description" for="wpp_property_import_remove_all_before_import">
                <?php echo __( 'Completely remove <b>all</b> existing properties prior to import.','wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_fix_caps" name="wpp_property_import[fix_caps]" value="on"<?php echo checked( 'on', $edit_current['fix_caps'] ); ?> />
              <label class="description" for="wpp_property_fix_caps">
                <?php echo __( 'Fix strings that are in all caps.','wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_force_remove_formatting" name="wpp_property_import[force_remove_formatting]" value="on"<?php echo checked( 'on', $edit_current['force_remove_formatting'] ); ?> />
              <label class="description" for="wpp_property_force_remove_formatting">
                <?php echo __( 'Scan for any formatting tags and strip them out.','wpp' );?>
              </label>
            </li>

            <?php if( class_exists( 'class_wpp_slideshow' ) ) { ?>
            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_property_automatically_load_slideshow_images" name="wpp_property_import[automatically_load_slideshow_images]" value="on"<?php echo checked( 'on', $edit_current['automatically_load_slideshow_images'] ); ?> />
              <label class="description" for="wpp_property_automatically_load_slideshow_images">
                <?php echo __( 'Automatically load imported images into property slideshow.','wpp' );?>
              </label>
            </li>
            <?php } ?>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_import_revalidate_addreses_on_completion" name="wpp_property_import[revalidate_addreses_on_completion]" value="on"<?php echo checked( 'on', $edit_current['revalidate_addreses_on_completion'] ); ?> />
              <label class="description" for="wpp_import_revalidate_addreses_on_completion">
                <?php echo __( 'Geolocate listings that do not already have latitute and longitude values.','wpp' );?>
              </label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <input type="checkbox" id="wpp_import_log_detail" name="wpp_property_import[log_detail]" value="on" <?php echo checked( 'on', $edit_current['log_detail'] ); ?> />
              <label class="description" for="wpp_import_log_detail"><?php echo __( 'Enable detailed logging to assist with troubleshooting.','wpp' );?></label>
            </li>

            <li class="wpp_xi_advanced_setting">
              <label class="description">
              <input type="checkbox" name="wpp_property_import[show_sql_queries]" value="on" <?php echo checked( 'on', $edit_current['show_sql_queries'] ); ?> />
              <?php echo __( 'Show SQL Queries and errors.','wpp' );?></label>
            </li>

            <?php do_action( 'wpp_import_advanced_options', $edit_current ); ?>

            </ul>

            <span class="wpp_property_toggle_import_settings wpp_link"><?php _e( 'Toggle Advanced Options', 'wpp' ); ?></span> <span class="wpp_property_toggle_import_settings wpp_xi_advanced_option_counter"></span>

          </td>
        </tr>


        <tr>
          <th><?php _e( 'Automatic Matching', 'wpp' ); ?></th>
          <td>
            <input type="button" value="<?php _e( 'Automatically Match', 'wpp' ); ?>" class='button' id="wpp_import_auto_match" />
            <span><?php _e( 'This will work for WP-Property exports and imports, but will have mixed results with uniquely formatted feeds.', 'wpp' ); ?></span>
          </td>
        </tr>

        <tr>
          <th><?php _e( 'Attribute Map', 'wpp' ); ?></th>
          <td>
            <p>
              <?php _e( 'Use XPath rules to setup the paths to the individual XML attributes to match them up with WP-Property attributes.','wpp' ); ?>
              <span class="wpp_xi_sort_rules wpp_link"><?php _e( 'Sort Attribute Rules', 'wpp' ); ?></span>.
            </p>
            <table id="wpp_property_import_attribute_mapper" auto_increment="true" class="ud_ui_dynamic_table widefat">
              <thead>
                <tr>
                  <th style="width: 5%;"><input style="margin:0;" type="checkbox" id="check_all"></th>
                  <th style="width: 150px;"><?php echo __( 'WP-Property Attribute','wpp' );?></th>
                  <th style="width: auto;"><?php echo __( 'XPath Rule','wpp' );?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach( $edit_current['map'] as $index => $attr ){ ?>
                <tr class="wpp_dynamic_table_row">
                    <td>
                      <input type="checkbox" name="wpp_property_import[map][<?php echo ( $index ); ?>][check]">
                    </td>
                    <td>
                    <select name="wpp_property_import[map][<?php echo ( $index ); ?>][wpp_attribute]"  class='wpp_import_attribute_dropdown'>
                      <option></option>
                      <optgroup label="<?php _e( 'WordPress Attributes', 'wpp' ); ?>">
                        <?php foreach( $wpp_property_import['post_table_columns'] as $column_name => $column_label ) { ?>
                          <option value="<?php echo $column_name; ?>" <?php selected( $attr['wpp_attribute'], $column_name ); ?> ><?php echo $column_label; ?></option>
                        <?php } ?>
                        <option value="images" <?php echo ( $attr['wpp_attribute'] == 'images' ) ? 'selected="selected"':''; ?> >Images ( allows multiple )</option>
                        <option value="featured-image" <?php echo ( $attr['wpp_attribute'] == 'featured-image' ) ? 'selected="selected"':''; ?> >Featured Image</option>
                      </optgroup>
                      <optgroup label="<?php _e( 'Taxonomies', 'wpp' ); ?>">
                    <?php foreach( $wp_properties['taxonomies'] as $tax_slug => $tax ){ ?>
                          <option value="<?php echo $tax_slug; ?>" <?php echo ( $attr['wpp_attribute'] == $tax_slug ) ? 'selected="selected"':''; ?> ><?php echo $tax['label']; ?> ( allows multiple )</option>
                    <?php } ?>
                      </optgroup>
                      <optgroup label="<?php _e( 'Attributes', 'wpp' ); ?>">
                    <?php foreach( WPP_F::get_total_attribute_array() as $property_stat_slug => $property_stat_label ): ?>
                      <option value="<?php echo $property_stat_slug; ?>" <?php echo ( $attr['wpp_attribute'] == $property_stat_slug ) ? 'selected="selected"':''; ?> ><?php echo $property_stat_label; ?></option>
                    <?php endforeach ;?>
                      </optgroup>
                      <optgroup label="<?php _e( 'Address', 'wpp' ); ?>">
                        <option value='street_number' <?php selected( $attr['wpp_attribute'], 'street_number' ); ?>><?php _e( 'Street Number', 'wpp' ); ?></option>
                        <option value='route' <?php selected( $attr['wpp_attribute'], 'route' ); ?>><?php _e( 'Street', 'wpp' ); ?></option>
                        <option value='city' <?php selected( $attr['wpp_attribute'], 'city' ); ?>><?php _e( 'City', 'wpp' ); ?></option>
                        <option value='county' <?php selected( $attr['wpp_attribute'], 'county' ); ?>><?php _e( 'County', 'wpp' ); ?></option>
                        <option value='state' <?php selected( $attr['wpp_attribute'], 'state' ); ?>><?php _e( 'State', 'wpp' ); ?></option>
                        <option value='country <?php selected( $attr['wpp_attribute'], 'country' ); ?>'><?php _e( 'Country', 'wpp' ); ?></option>
                        <option value='postal_code' <?php selected( $attr['wpp_attribute'], 'postal_code' ); ?>><?php _e( 'Postal Code', 'wpp' ); ?></option>
                        <option value='latitude' <?php selected( $attr['wpp_attribute'], 'latitude' ); ?>><?php _e( 'Latitude', 'wpp' ); ?></option>
                        <option value='longitude' <?php selected( $attr['wpp_attribute'], 'longitude' ); ?>><?php _e( 'Longitude', 'wpp' ); ?></option>
                      </optgroup>

                      <optgroup label="<?php _e( 'WP-Property Attributes', 'wpp' ); ?>">
                        <option value='property_type' <?php selected( $attr['wpp_attribute'], 'property_type' ); ?>><?php _e( 'Property Type', 'wpp' ); ?></option>
                        <?php if( class_exists( 'class_agents' ) ) { ?>
                        <option value='wpp_agents' <?php selected( $attr['wpp_attribute'], 'wpp_agents' ); ?>><?php _e( 'Property Agent', 'wpp' ); ?></option>
                        <?php } ?>
                        <option value='wpp_gpid' <?php selected( $attr['wpp_attribute'], 'wpp_gpid' ); ?>><?php _e( 'Global Property ID', 'wpp' ); ?></option>
                        <option value='display_address' <?php selected( $attr['wpp_attribute'], 'display_address' ); ?>><?php _e( 'Display Address', 'wpp' ); ?></option>
                      </optgroup>
                    </select>
                  </td>
                  <td><input style="width: 100%;" name="wpp_property_import[map][<?php echo ( $index )?>][xpath_rule]" type="text" class='xpath_rule' value="<?php echo esc_attr( $attr['xpath_rule'] ); ?>" /></td>
                </tr>
                <?php } ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3">
                    <div class="alignleft">
                    <input type="button" class="wpp_import_delete_row button-secondary" value="<?php _e( 'Delete Selected','wpp' ) ?>" />
                    <input type="button" class="wpp_add_row button-secondary" value="<?php _e( 'Add Row','wpp' ) ?>" />

                    <?php if( !$edit_current ){ $p_d = 'disabled="disabled"'; } ?>

                    <span class="wpp_i_unique_id_wrapper">
                      <select id="wpp_property_import_unique_id" name="wpp_property_import[unique_id]">
                        <?php $total_attribute_array = WPP_F::get_total_attribute_array();?>
                        <?php foreach( $edit_current['map']  as $attr ) { ?>
                          <option value="<?php echo $attr['wpp_attribute']; ?>" <?php selected( $attr['wpp_attribute'], $edit_current['unique_id'] ); ?>><?php echo $total_attribute_array[$attr['wpp_attribute']]; ?> ( <?php echo $attr['wpp_attribute']; ?> )</option>
                        <?php } ?>
                      </select>
                      <span class="description"></span>
                    </span>

                    </div>

                    <div class="alignright">
                      <?php $save_button_id = ( $new_schedule ? 'id="wpp_property_import_save"' : 'id="wpp_property_import_update" schedule_id="'.$_REQUEST['schedule_id'].'"' );  ?>
                      <input type="button" <?php echo $save_button_id ?> class="button-primary" value="<?php _e( 'Save Configuration','wpp' ) ?>" <?php echo $p_d?> />

                    </div>
                  </td>
                </tr>

              </tfoot>

            </table>
        </td>
        </tr>
        <tr class="wpp_i_import_actions <?php echo ( $edit_current ? '' : 'hidden' ); ?>">
          <th></th>
          <td>
            <div class="wpp_i_import_actions_bar">
                <input type="hidden" id="import_hash" value="<?php echo $edit_current['hash']; ?>" />
                <input type="button" id="wpp_i_preview_action" value="<?php _e( 'Preview Import', 'wpp' ); ?>" class="button-secondary" <?php echo $p_d?>>
                <input type="button" id="wpp_i_do_full_import" value="<?php _e( 'Process Full Import', 'wpp' ); ?>" class="button-secondary" <?php echo $p_d?>>
                <div class="wpp_i_ajax_message"></div>
            </div>

            <div class="wpp_i_import_preview">
              <div id="wpp_import_object_preview" class="hidden"><div class="wp-tab-panel"></div></div>
            </div>
          </td>
        </tr>

        </tbody>
      </table>
      </form>
      </div>


      <?php

      break;


    } //** end: $wpp_action switch */


    //** Load some attributes into return if they exist */
    if( $schedule_hash ) {
      $result['hash'] = $schedule_hash;
    }

    if( $schedule_id ) {
      $result['schedule_id'] = $schedule_id;
    }

    //** if not doing an import, this this function is used to generate a JSON responde for UI */
    if( !$wpp_schedule_import && !isset( $result['ui'] ) ) {
      $result['ui'] = ob_get_contents();
      ob_end_clean();
    }

    //** Check encoding. Not necessary because we still force UTF8, for debugging purposes */
    $encoding = WPP_F::detect_encoding( $result['ui'] );

    if( is_wp_error( $encoding ) ) {
      $result['encoding'] = $encoding->get_error_message();
    } elseif( !empty( $encoding ) ) {
      $result['encoding'] = $encoding;
    } else {
      $result['encoding'] = 'UTF-8';
    }

    $result['ui'] = utf8_encode( $result['ui'] );

    //** Check if this is being called from an AJAX request  */
    if( !empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) {

      $json_encode = json_encode( $result );
      $json_decode = ( array ) json_decode( $json_encode );

      if( empty( $json_decode['ui'] ) ) {
        $json_decode['ui'] = sprintf( __( 'No UI data was returned. Mostly likely an encoding error. Encoding: %1s ', 'wpp' ), $result['encoding'] );
      }

      die( json_encode( $json_decode ) );

    } else {
      return $result;
    }

    /** Important - do not die() this function unless we have an AJAX request */

}

  /**
  * Traverse through XML DOM
  *
  * Everything handles via this page. Other functions are done via AJAX.
  *
  * @todo Could be improved
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function traverse( DomNode $node, $level=0 ){
    global $wpp_property_import;

    $this_tag = class_wpp_property_import::handle_node( $node, $level );

    if ( $node->hasChildNodes() ) {
      $children = $node->childNodes;
      foreach( $children as $kid ) {
        if ( $kid->nodeType == XML_ELEMENT_NODE ) {
          class_wpp_property_import::traverse( $kid, $level+1 );
        } else {
        $wpp_property_import['runtime']['tags'][] = $this_tag;
        }
      }
    } else {
      // means that there's no value
      $wpp_property_import['runtime']['tags'][] = $this_tag;
    }



  }

  /**
  * Functions used by traverse()
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function handle_node( DomNode $node, $level ) {
    global $wpp_property_import;


    if ( $node->nodeType == XML_ELEMENT_NODE ) {
      return $node->tagName;
    }
  }

  /**
  * Import overview page.
  *
  * Everything handles via this page. Other functions are done via AJAX.
  *
  * @Page ID: property_page_wpp_property_import
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function page_main() {
    global $wp_properties, $wpdb, $wpp_property_import, $current_screen, $wp_messages;

    if( isset( $_REQUEST['message'] ) ) {

      switch( $_REQUEST['message'] ) {

        case 'imported':
        $wp_messages['notice'][] = __( 'Schedule imported from file.', 'wpp' );
        break;

      }
    }


    ?>
    <style type="text/css">
      body.property_page_wpp_property_import #wpp_property_import_step {
        background: none repeat scroll 0 0 #FFFADE;
        border: 1px solid #D7D3BC;
        margin: 10px 0;
        padding: 10px;
      }

      body.property_page_wpp_property_import  #wpp_property_import_setup .wpp_i_preview_raw_data_result {
        margin-left: 8px;
      }

      body.property_page_wpp_property_import #wpp_property_import_setup .wpp_i_error_text {
        background: none repeat scroll 0 0 #924848;
        border-radius: 3px 3px 3px 3px;
        color: #FFFCFC;
        max-width: none;
        padding: 2px 7px;
      }

      body.property_page_wpp_property_import #wpp_property_import_setup .wpp_i_ajax_message.wpp_i_error_text {
        margin-top: 2px;
      }

      body.property_page_wpp_property_import #wpp_property_import_setup .wpp_i_close_preview.wpp_link {
        float: right;
        line-height: 30px;
        margin-right: 7px;
      }

    </style>

    <script type="text/javascript">

   <?php

    // Load list of all usable attributes into global JS array

    $get_total_attribute_array = WPP_F::get_total_attribute_array();
    if( is_array( $get_total_attribute_array ) ) {
      $get_total_attribute_array = array_keys( $get_total_attribute_array );
      echo "var wpp_attributes = ['" . implode( "','", $get_total_attribute_array ) . "'];";
    }

    ?>

    var wpp_auto_matched_tags;
    var import_count = 0;
    var import_hash = false;
    var schedule_id;

    jQuery( document ).ready( function( $ ) {

      wpp_i_refresh_dom();

      jQuery( ".wpp_xi_sort_rules" ).live( "click", function() {

        var list_wrapper = jQuery( "#wpp_property_import_attribute_mapper" );
        var listitems = jQuery( ".wpp_dynamic_table_row", list_wrapper ).get();

        listitems.sort( function( a, b ) {

          var compA = jQuery( "select.wpp_import_attribute_dropdown option:selected", a ).text();
          var compB = jQuery( "select.wpp_import_attribute_dropdown option:selected", b ).text();

          if( compA === undefined ) {
            compA = 0;
          } else {
            compA = compA;
          }

          if( compB === undefined ) {
            compB = 0;
          } else {
            compB = compB;
          }

          var index = ( compA < compB ) ? -1 : ( compA > compB ) ? 1 : 0;
          console.log( compA + ' - ' + compB + ': ' + index );

          return index;

        } )

        jQuery.each( listitems, function( idx, itm ) {
          list_wrapper.append( itm );
        });

      });


      jQuery( "#wpp_property_import_choose_root_element" ).live( "change", function() {
        var value = jQuery( this ).val();
        var fixed_value = value.replace( /'/g, '"' );
        jQuery( this ).val( fixed_value );
      });


      jQuery( ".wpp_xi_advanced_setting input[type=checkbox]" ).live( "change", function() {
        var wrapper = jQuery( this ).closest( ".wpp_xi_advanced_setting" );
        if( jQuery( this ).is( ":checked" ) ) {
          jQuery( wrapper ).addClass( "wpp_xi_enabld_row" );
        } else {
          jQuery( wrapper ).removeClass( "wpp_xi_enabld_row" );
        }

        wpp_xi_advanced_option_counter();

      });

      jQuery( ".wpp_xi_advanced_setting input[type=text]" ).live( "change", function() {
        var wrapper = jQuery( this ).closest( ".wpp_xi_advanced_setting" );
        var value = jQuery( this ).val();

        if( value === "" || value === '0' ) {

          /* If 0 blank out this value */
          jQuery( this ).val( '' );

          /* Check if all inputs are empty */
          if( jQuery( "input:text[value != '' ]", wrapper ).length == 0 ) {
             jQuery( wrapper ).removeClass( 'wpp_xi_enabld_row' );
          }

        } else {
          jQuery( wrapper ).addClass( 'wpp_xi_enabld_row' );
        }

        wpp_xi_advanced_option_counter();

      });


      jQuery( '.wpp_property_toggle_import_settings' ).live( "click", function() {
        jQuery( ".wpp_property_import_settings" ).toggle();
        wpp_xi_advanced_option_counter();

      });


      jQuery( ".wpp_import_delete_row" ).live( "click", function(){
          if( !jQuery( 'input[name^="wpp_property_import[map]"]:checkbox:checked' ).length ) return false;
          if( !confirm( 'Are you sure you want remove these items?' ) ) return false;
          jQuery( 'input[name^="wpp_property_import[map]"]:checkbox' ).each( function(){
            if( this.checked ){
              if( jQuery( '#wpp_property_import_attribute_mapper .wpp_dynamic_table_row' ).length == 1 )
                  jQuery( ".wpp_add_row" ).click();
              jQuery( this ).parents( '.wpp_dynamic_table_row' ).remove();

            }
          } )
          jQuery( jQuery( '[name^="wpp_property_import[map]"]:checkbox' ).parents( '.wpp_dynamic_table_row' ).get().reverse() ).each( function( index ){
            jQuery( this ).find( 'input,select' ).each( function() {
            var old_name = jQuery( this ).attr( 'name' );
            var matches = old_name.match( /\[( \d{1,2} )\]/ );
            if ( matches ) {
                old_count = parseInt( matches[1] );
                new_count = ( index + 1 );
            }
            var new_name = old_name.replace( '[' + old_count + ']','[' + new_count + ']' );
            jQuery( this ).attr( 'name', new_name );
            });
          } )

          //** Create unique ID selected  **/
          wpp_import_build_unique_id_selector();
      } )

      jQuery( '#check_all' ).live( 'click',function(){
        if( this.checked ){
          jQuery( '[name^="wpp_property_import[map]"]:checkbox' ).attr( 'checked','checked' );
        }
        else{
          jQuery( '[name^="wpp_property_import[map]"]:checkbox' ).attr( 'checked','' );
        }
      } )

      jQuery( "#wpp_i_do_full_import" ).live( "click", function() {

        /* Blank out preview result since it will be deleted after completion of import */
        jQuery( "wpp_i_preview_raw_data_result" ).html( "" );

        var import_hash = jQuery( "#import_hash" ).val();

        if( import_hash != "" ) {
          window.open( "<?php echo get_bloginfo( 'home' ); ?>/?wpp_schedule_import=" + import_hash + "&echo_log=true" );
        } else {
          wpp_i_actions_bar_message( "<?php _e( 'Please save schedule first.','wpp' ); ?>", "bad", 7000 );
        }

      });

      jQuery( "#wpp_i_preview_action" ).live( "click", function() {

        var source_type = jQuery( "#wpp_property_import_source_type option:selected" ).val();

        jQuery( "#wpp_i_preview_action" ).val( 'Loading...' );

        /*jQuery( "#wpp_i_preview_action" ).attr( 'disabled', true ); */

        jQuery( "#wpp_import_object_preview" ).html( "<pre class=\"wpp_class_pre\"></pre>" );

        var params = {
          action: 'wpp_property_import_handler',
          wpp_action: 'execute_schedule_import',
          preview: 'true',
          data: jQuery( "#wpp_property_import_setup" ).serialize()
        }

        if( schedule_id !== undefined ) {
          params.schedule_id = schedule_id;
        }

        if( source_type !== undefined ) {
          params.source_type = source_type;
        }

        jQuery.post( ajaxurl, params,
          function( result ) {
            jQuery( "#wpp_i_preview_action" ).attr( 'disabled', false );
            jQuery( "#wpp_i_preview_action" ).val( 'Preview Again' );

            if( result.success == 'true' ) {
              wpp_i_actions_bar_message();
              jQuery( "#wpp_import_object_preview" ).show();
              jQuery( "#wpp_import_object_preview" ).html( "<pre class=\"wpp_class_pre\">" + result.ui + "</pre>" );
            } else {
              alert( result.message );
            }

          }, "json" )
          .success( function() { } )
          .error( function( result ) {
            if( result.status == 500 ) {
              wpp_i_actions_bar_message( "<?php _e( '500 Internal Server Error! Your hosting account is most likely running out of memory.','wpp' ); ?>", 'bad' );
              jQuery( "#wpp_i_preview_action" ).val( 'Preview Again' );
            }
          });
      });


      jQuery( ".wpp_i_close_preview" ).live( "click", function() {
        jQuery( "#wpp_i_preview_raw_data" ).val( '<?php _e( 'Preview Raw Data','wpp' ); ?>' );
        jQuery( ".wpp_i_close_preview" ).hide();
        jQuery( ".wppi_raw_preview_result" ).hide();
        jQuery( ".wppi_raw_preview_result" ).html( "" );
      });

      jQuery( "#wpp_i_preview_raw_data" ).live( "click", function() {

        var source_type = jQuery( "#wpp_property_import_source_type option:selected" ).val();

        if( !wpp_i_validate_source_info( source_type ) ) {
          return false;
        }

        if( source_type == "" ) {
          jQuery( "#wpp_property_import_source_type" ).addClass( 'wpp_error' );
          return;
        } else {
          jQuery( "#wpp_property_import_source_type" ).removeClass( 'wpp_error' );
        }

        wpp_i_preview_raw_preview_result( "<?php _e( 'Loading...','wpp' );?>" );

        jQuery( "#wpp_i_preview_raw_data" ).attr( 'disabled', true );

        var params = {
          action: 'wpp_property_import_handler',
          wpp_action: 'execute_schedule_import',
          raw_preview: 'true',
          data: jQuery( "#wpp_property_import_setup" ).serialize()
        }

        if( schedule_id !== undefined ) {
          params.schedule_id = schedule_id;
        }

        if( source_type !== undefined ) {
          params.source_type = source_type;
        }

        wpp_xi_loading_show();

        jQuery.post( ajaxurl, params, function( result ) {

          wpp_xi_loading_hide();

          jQuery( "#wpp_i_preview_raw_data" ).attr( 'disabled', false );
          jQuery( "#wpp_i_preview_raw_data" ).val( '<?php _e( 'Preview Again','wpp' ); ?>' );

          if( result.success == 'true' ) {

            wpp_i_preview_raw_preview_result( result.preview_bar_message );

            /* Should always return a schedule ID */
            set_schedule_id( result.schedule_id );

            jQuery( ".wpp_i_close_preview" ).show();
            jQuery( ".wppi_raw_preview_result" ).show();
            jQuery( ".wppi_raw_preview_result" ).html( "<pre class=\"wpp_class_pre\">" + result.ui + "</pre>" );
          } else {
            wpp_i_preview_raw_preview_result( result.message, "bad" );
          }

          wpp_xi_loading_hide();

        }, "json" )
        .success( function() { } )
        .error( function( result ) {

          jQuery( "#wpp_i_preview_raw_data" ).attr( 'disabled', false );

          if( result.status == 500 ) {
            wpp_i_preview_raw_preview_result( "<?php _e( '500 Internal Server Error! Your hosting account is most likely running out of memory.','wpp' ); ?>", 'bad' );
            jQuery( "#wpp_i_preview_raw_data" ).val( '<?php _e( 'Preview Raw Data','wpp' ); ?>' );
          }

          wpp_i_preview_raw_preview_result( result.responseText, "bad" );

          wpp_xi_loading_hide();

        });


      });



      //** Update to get matched tags from array. */
      jQuery( "#wpp_import_auto_match" ).live( "click", function() {
        wpp_property_import_perform_auto_matching();
      });

      jQuery( "#wpp_property_import_save" ).live( "click", function( e ) {

        e.preventDefault();

        var this_button = this;
        var original_text = "<?php _e( 'Save Configuration','wpp' ); ?>";

        wpp_i_actions_bar_message( "<?php _e( 'Saving the XML Importer schedule, please wait...','wpp' ); ?>" );

        jQuery( this_button ).val( "<?php _e( 'Processing...','wpp' ); ?>" );


        var params = {
          action: 'wpp_property_import_handler',
          wpp_action: 'save_new_schedule',
          data: jQuery( "#wpp_property_import_setup" ).serialize()
        }

        /* schedule_id may have been created during a preview or source eval */
        if( schedule_id !== undefined ) {
          params.schedule_id = schedule_id;
        }

        jQuery.post( ajaxurl, params, function( result ) {
            if( result.success == 'true' ) {
              wpp_i_actions_bar_message( "<?php _e( 'Schedule has been saved.','wpp' ); ?>", "good", 7000 );
              set_schedule_id( result.schedule_id );
              set_schedule_hash( result.hash );
              jQuery( this_button ).val( original_text );

            } else {
              wpp_i_actions_bar_message( result.message, 'error' );
            }
        },"json" );
      });

      jQuery( "#wpp_property_import_update" ).live( "click", function( e ) {

        e.preventDefault();

        var this_button = this;
        var original_text = "<?php _e( 'Save Configuration','wpp' ); ?>";

        wpp_i_actions_bar_message( "<?php _e( 'Updating the XML Importer schedule, please wait...','wpp' ); ?>" );

        jQuery( this_button ).val( "<?php _e( 'Processing...','wpp' ); ?>" );

        schedule_id = jQuery( this ).attr( 'schedule_id' );

        jQuery.post( ajaxurl, {
          action: 'wpp_property_import_handler',
          wpp_action: 'update_schedule',
          schedule_id: schedule_id,
          data: jQuery( "#wpp_property_import_setup" ).serialize()
          },
          function( result ) {

            if( typeof result === 'object' && result.success === 'true' ) {
              set_schedule_id( result.schedule_id );
              set_schedule_hash( result.hash );
              wpp_i_actions_bar_message( "<?php _e( 'Schedule has been updated.','wpp' ); ?>", "good", 7000 );

              jQuery( this_button ).val( original_text );

            } else {
              wpp_i_actions_bar_message( result.message, 'error' );
            }

          },
          "json" );
      });

      /* Activated when "Add New" buttin is clicked */
      jQuery( "#wpp_property_import_add_import" ).click( function() {
        jQuery( ".updated" ).remove();
        wpp_i_show_schedule_editor_ui();
      });


      jQuery( ".wpp_property_import_edit_report" ).click( function( e ) {
        var schedule_id = jQuery( this ).attr( 'schedule_id' );
        wpp_i_show_schedule_editor_ui( schedule_id );
      });


      /* Sort schedules on overview page */
     jQuery( ".wpp_i_sort_schedules" ).click( function( e ) {

      e.preventDefault();

      jQuery( ".wpp_i_sort_schedules a" ).removeClass( "current" );

      jQuery( "a", this ).addClass( "current" );

      var sort_by = jQuery( this ).attr( "sort_by" );
      var sort_direction = jQuery( this ).attr( "sort_direction" );
      var mylist = jQuery( '#wpp_property_import_overview tbody' );
      var listitems = mylist.children( 'tr' ).get();

      listitems.sort( function( a, b ) {

        var compA = jQuery( a ).attr( sort_by );
        var compB = jQuery( b ).attr( sort_by );

        if( compA === undefined ) {
          compA = 0;
        } else {
          compA = parseInt( compA );
        }

        if( compB === undefined ) {
          compB = 0;
        } else {
          compB = parseInt( compB );
        }

        if( sort_direction == "DESC" ) {
          var index = ( compA < compB ) ? -1 : ( compA > compB ) ? 1 : 0;
        } else {
          var index = ( compA > compB ) ? -1 : ( compA < compB ) ? 1 : 0;
        }


        return index;

      } )

      //** Switch sorting direction */
      if( sort_direction == "DESC" ) {
        jQuery( this ).attr( "sort_direction", "ASC" )
      } else {
        jQuery( this ).attr( "sort_direction", "DESC" )
      }

      jQuery.each( listitems, function( idx, itm ) { mylist.append( itm ); });



    });


    jQuery( ".wppi_delete_all_feed_properties" ).click( function( e ) {

      e.preventDefault();

      var verify_response;
      var row = jQuery( this ).parents( ".wpp_i_schedule_row" );
      var total_properties = jQuery( row ).attr( "total_properties" );
      var schedule_id = jQuery( row ).attr( "schedule_id" );
      var import_title = jQuery( row ).attr( "import_title" );

      verify_response = prompt( "<?php _e( 'Confirm that you want to delete all ', 'wpp' ); ?>" + total_properties + "<?php _e( ' properties from this feed by typing in in \'delete\' below, or press \'Cancel\' to cancel.', 'wpp' ); ?>" );

      if( verify_response == "delete" ) {

        jQuery( "#wpp_property_import_ajax" ).show();
        jQuery( "#wpp_property_import_ajax" ).html( "<div class='updated below-h2'><p>Deleting all properties from "+import_title+". You can close your browser, the operation will continue until completion.</p></div>" );

        jQuery.post( ajaxurl, {
          action: 'wpp_property_import_handler',
          schedule_id: schedule_id,
          wpp_action: 'delete_all_schedule_properties'
          },
          function( result ) {
            if( result.success == 'true' ) {
              jQuery( "#wpp_property_import_ajax" ).html( "<div class='updated below-h2'><p>" + result.ui + "</p></div>" );
            } else {
              jQuery( "#wpp_property_import_ajax" ).html( "<div class='updated below-h2'><p><?php _e( 'An error occured.', 'wpp' ); ?></p></div>" );
            }
          },
          "json" );

      } else {
        return;
      }

    });


    jQuery( ".wpp_property_import_delete_report" ).click( function( e ) {
    e.preventDefault();

    if( !confirm( "<?php _e( 'Are you sure?', 'wpp' ); ?>" ) )
      return;

      var schedule_id = jQuery( this ).attr( 'schedule_id' );
      var rmel = jQuery( this ).parents( 'tr' );

      jQuery.post( ajaxurl, {
        action: 'wpp_property_import_handler',
        schedule_id: schedule_id,
        wpp_action: 'delete_schedule'
        },
        function( result ) {
          if( result.success == 'true' ) {
            jQuery( rmel ).remove();
            if( jQuery( '#wpp_property_import_overview tr' ).length == 1 )
              jQuery( '#wpp_property_import_overview' ).remove();
          }
        },
        "json" );
    });


    /* Vlidate source and get info when one of the source-related fields is updated. */
    jQuery( "#wpp_property_import_remote_url, #wpp_property_import_username, #wpp_property_import_password" ).live( "change", function() {
      wpp_import_determine_settings();
    });

    /* If source is selected, remove error and determine settings */
    jQuery( "#wpp_property_import_source_type" ).live( "change", function() {
      wpp_import_determine_settings();
      jQuery( "#wpp_property_import_source_type" ).removeClass( 'wpp_error' );
    });

    /* Vlidate source when "Source is Good" label is pressed. Third argument forces cache refresh. */
    jQuery( '#wpp_property_import_source_status' ).live( 'click', function() {
      wpp_property_import_evaluate_source( this, false, true );
    });


    jQuery( "#wpp_property_import_unique_id" ).live( 'change',function(){
      wpp_import_build_unique_id_selector();
    } )


    jQuery( 'select[name^="wpp_property_import[map]"]' ).live( 'change',function(){
      wpp_import_build_unique_id_selector();
    } )


    /* Ensure all necessary data for given source is filled in */
    function wpp_i_validate_source_info( type ) {
      var required_fields = jQuery( "input.wpp_required", "[wpp_i_source_type=" + type + "]" );
      var success = true;

      if( required_fields.length < 1 ) {
        return true;
      }

      jQuery( required_fields ).each( function() {
        var value = jQuery.trim( jQuery( this ).val() );

        if( value == "" ) {
          jQuery( this ).addClass( "wpp_error" );
          success = false;
        } else {
          jQuery( this ).val( value );
          jQuery( this ).removeClass( "wpp_error" );
        }

      });

      return success;
    }


    /*  Displays editor UI */
    function wpp_i_show_schedule_editor_ui( passed_schedule_id ) {

      wpp_xi_loading_show();


      jQuery( ".updated" ).remove();

      if( passed_schedule_id == "" ) {
        var new_import = true;
      }

      schedule_id = passed_schedule_id;

      params = {
        action: 'wpp_property_import_handler',
        wpp_action: 'add_edit_schedule'
      }

      if( !new_import ) {
        params.schedule_id = schedule_id;
      }

      jQuery.post( ajaxurl, params,
      function( result ) {
        if( result.success == 'true' ) {
          jQuery( ".wpp_import_overview_page_element" ).hide();
          jQuery( "#wpp_property_import_ajax" ).html( result.ui ).show();
          jQuery( "#wpp_property_import_name" ).focus();

          wpp_xi_loading_hide();

          wpp_i_run_on_import_ui_display();

        }
      },
      "json" );

    }


    /* Handles functions and UI configurations when a major DOM change is made */
    function wpp_xi_advanced_option_counter() {

      /* Special Rules: Limits cannot be used with property deletion */
      if( jQuery( '#wpp_property_limit_properties' ).val() || jQuery( '#wpp_property_limit_scanned_properties').val() ) {
        wpp_xi_disable_advanced_option( '#wpp_property_import_remove_non_existant_properties' );
      } else {
        wpp_xi_enable_advanced_option( '#wpp_property_import_remove_non_existant_properties' );
      }

      /* Special Rules: Enable SQL Query option when Advanced Logging is on */
      if( jQuery( '#wpp_import_log_detail' ).is( ':checked' ) ) {
        wpp_xi_enable_advanced_option( 'input[name="wpp_property_import[show_sql_queries]"]' );
      } else {
        wpp_xi_disable_advanced_option( 'input[name="wpp_property_import[show_sql_queries]"]' );
      }

      /* Special Rules: If set to remove all properties from this feed only, option of removing all properties not available */
      if( jQuery( 'input[name="wpp_property_import[remove_all_from_this_source]"]' ).is( ':checked' ) ) {
        wpp_xi_disable_advanced_option( 'input[name="wpp_property_import[remove_all_before_import]"]' );
      } else {
        wpp_xi_enable_advanced_option( 'input[name="wpp_property_import[remove_all_before_import]"]' );
      }

      var count = jQuery( ".wpp_xi_advanced_setting.wpp_xi_enabld_row" ).length;

      if( jQuery( ".wpp_property_import_settings" ).is( ":visible" ) || count == 0 ) {
        jQuery( "span.wpp_xi_advanced_option_counter" ).html( '' );
        return;
      }

      jQuery( "span.wpp_xi_advanced_option_counter" ).html( "( " + count + " <?php _e( 'Enabled Options', 'wpp' ); ?> )" );

    }


    /* Disables, resets, and grays out an option */
    function wpp_xi_disable_advanced_option( element ) {
      jQuery( element ).prop( 'disabled', true );
      jQuery( element ).prop( 'checked', false );
      jQuery( element ).closest( 'li.wpp_xi_advanced_setting' ).css( 'opacity', 0.3 ).removeClass( '.wpp_xi_enabld_row' );
    }


    /* Enables an option */
    function wpp_xi_enable_advanced_option( element ) {
      jQuery( element ).prop( 'disabled', false );
      jQuery( element ).closest( 'li.wpp_xi_advanced_setting' ).css( 'opacity',  1 );
    }


    /* Handles functions and UI configurations when a major DOM change is made */
    function wpp_i_refresh_dom() {

      var current_page = false;

      if( window.location.hash ) {

        if( window.location.hash.length == 11 ) {
          var hash = window.location.hash.replace( "#", "" );
          wpp_i_show_schedule_editor_ui( hash );
          current_page = 'schedule_editor';
        } else if( window.location.hash == "#add_new_schedule" ) {
          wpp_i_show_schedule_editor_ui( hash );
          current_page = 'add_new_schedule';
        } else {
          current_page = 'overview';
        }
      }
    }


    /* Determine if "Toggle Advanced Settings" link should be displayed for this source type */
    function wpp_import_determine_settings() {

      var source_url = jQuery( "#wpp_property_import_remote_url" ).val();
      var source_type = jQuery( "#wpp_property_import_source_type option:selected" ).val();

      if( source_url != "" && source_type != "" ) {
        wpp_import_source_status( 'ready_to_check' );
      } else {
        wpp_import_source_status( '' );
      }

      var source_url = jQuery( "#wpp_property_import_remote_url" ).val();
      var source_type = jQuery( "#wpp_property_import_source_type" ).val();
      var source_label = jQuery( "#wpp_property_import_source_type option:selected" ).html();

      /* Hide all non-import-type-specific advanced options */
      jQuery( ".wpp_i_advanced_source_settings" ).hide();

      /* Hide all configuration not related to this type */
      jQuery( ".wpp_something_advanced_wrapper.wppi_source_option_preview_wrapper .wpp_i_source_specific" ).hide();
      jQuery( ".wpp_something_advanced_wrapper.wppi_source_option_preview_wrapper .wpp_i_source_specific[wpp_i_source_type = "+ source_type +" ]" ).not( ".wpp_i_advanced_source_settings" ).show();

      /* Check if this source type has any advance settings, and then dispaly the link if appropriate */
      if( jQuery( "li.wpp_i_advanced_source_settings[wpp_i_source_type='"+source_type+"']" ).length ) {
        jQuery( ".wppi_source_option_preview_wrapper.wpp_something_advanced_wrapper" ).show();
        jQuery( ".wppi_source_option_preview_wrapper.wpp_something_advanced_wrapper .wpp_show_advanced" ).text( "<?php _e( 'Toggle Advanced', 'wpp' ); ?> " + source_label + " <?php _e( 'Settings', 'wpp' ); ?>" );
      } else {
        jQuery( ".wppi_source_option_preview_wrapper.wpp_something_advanced_wrapper" ).hide();
      }

      /* Check to see if any of our fields need to be update automatically */
      if( source_type == "rets" || source_type == "gs" ){
        jQuery( "#wpp_property_import_choose_root_element" ).val( "//ROW" );
      }

      if( source_type == "csv" ){
        jQuery( "#wpp_property_import_choose_root_element" ).val( "//object" );
      }

    }

    /* Ran after the import editor screen is displayed */
    function wpp_i_run_on_import_ui_display() {
      wpp_import_determine_settings();
      wpp_import_build_unique_id_selector();

      jQuery( ".wpp_xi_advanced_setting input[type=checkbox]" ).each( function() {
        var wrapper = jQuery( this ).closest( "li.wpp_xi_advanced_setting" );
        if( jQuery( this ).is( ":checked" ) ) {
          jQuery( wrapper ).addClass( 'wpp_xi_enabld_row' );
        } else {
          jQuery( wrapper ).removeClass( 'wpp_xi_enabld_row' );
        }
      });

      jQuery( ".wpp_xi_advanced_setting input[type=text]" ).each( function() {
        var wrapper = jQuery( this ).closest( "li.wpp_xi_advanced_setting" );

        if( jQuery( this ).val() != "" && jQuery( this ).val() != "0" ) {
          jQuery( wrapper ).addClass( 'wpp_xi_enabld_row' );
          return;
        } else {

          /* Clear out zeroes */
          jQuery( this ).val( '' );

          jQuery( wrapper ).removeClass( 'wpp_xi_enabld_row' );
        }
      });

      wpp_xi_advanced_option_counter();

    }

  });


    /**
     * Toggle loading icon at the top of the page.
     *
     */
    function wpp_xi_loading_show() {
      jQuery( ".wpp_xi_loader" ).css( "display", "inline-block" );
    }

    /**
     * Toggle loading icon at the top of the page.
     *
     */
    function wpp_xi_loading_hide() {
      jQuery( ".wpp_xi_loader" ).hide();
    }


    /**
     * Rebuild unique ID dropdown
     *
     */
    function wpp_import_build_unique_id_selector() {

      var uid_container = jQuery( ".wpp_i_unique_id_wrapper" );
      var uid_select_element = jQuery( "#wpp_property_import_unique_id" );

      /* Get current UID */
      var selected_id = uid_select_element.val();

      var selected_attributes = jQuery( "select[name^='wpp_property_import[map]'] option:selected[value!='']" ).length;

      /* Blank out dropdown eleemnt */
      uid_select_element.html( '' );

      uid_select_element.append( '<option value=""> - </option>' );

      jQuery( 'select[name^="wpp_property_import[map]"] option:selected' ).each( function(){

        var attribute_slug = jQuery( this ).val();

        var cur = jQuery( 'select#wpp_property_import_unique_id option[value="'+attribute_slug+'"]' );

        /* Make sure that the attribute isn't already added to the UID dropdown and a value exists  */
        if( cur.length == 0 && cur.val() != "" ) {

          var title = jQuery( this ).html();
          uid_select_element.append( '<option value="'+attribute_slug+'">'+title+'</option>' );
        }

        if( selected_id != "" && selected_id != null ) {
          uid_select_element.val( selected_id );
        }

      });

      //* No attribute found, nothing to display in UID dropdown */
      if( selected_attributes == 0 ) {
        jQuery( '.wpp_i_unique_id_wrapper' ).hide();
      } else {
        jQuery( '.wpp_i_unique_id_wrapper' ).show();

        if( selected_id == "" ) {
          jQuery( "span.description", uid_container ).html( "<?php echo __( 'Select a unique ID attribute.','wpp' );?>" );
        } else {
          jQuery( "span.description", uid_container ).html( "<?php echo __( 'Unique ID attribute.','wpp' );?>" );
        }
      }


    }


    /**
     * Add attribute and xPath rows based on matched tags from XML feed and avaialble attributes.
     *
     */
    function wpp_property_import_perform_auto_matching() {

      var wpp_all_importable_attributes = new Array();
      var wpp_all_importable_attributes_labels = new Array();

      var wpp_successful_matches = 0;

      var source_type = jQuery( "#wpp_property_import_source_type" ).val();

      jQuery( "#wpp_import_auto_match" ).attr( 'disabled', false );

      jQuery( "#wpp_import_auto_match" ).val( 'Automatically Match' );

      //** If tags are not defined, attempt to reload */
      if( wpp_auto_matched_tags === undefined ) {

        //** Disable button and start auto import */
        jQuery( "#wpp_import_auto_match" ).val( 'Reloading XML...' );
        jQuery( "#wpp_import_auto_match" ).attr( 'disabled', true );

        wpp_property_import_evaluate_source( false, wpp_property_import_perform_auto_matching );

        return;
      }

      /* Get all WPP tags from first dropdown */
      jQuery( "#wpp_property_import_attribute_mapper .wpp_dynamic_table_row option" ).each( function() {

        var meta_key = jQuery( this ).val();
        var label = jQuery( this ).text();

        /* Add to importable attributes array if key is not blank, and not already in there. Note: inArray() returns -1 one when no match is found. */
        if( meta_key != "" && !jQuery.inArray( meta_key, wpp_all_importable_attributes ) != -1 )  {
          wpp_all_importable_attributes.push( meta_key );
        }

        /* Add to importable attribute labels as well. */
        if( label != "" && !jQuery.inArray( label, wpp_all_importable_attributes_labels ) != -1 )  {
          wpp_all_importable_attributes_labels.push( label );
        }

      });


      /* Cycle through auto-matched tags, and attempt to match them with WPP tags */
      jQuery.each( wpp_auto_matched_tags,function() {

        /* Get key from XML source */
        var xml_tag = String( this );

        /* We convert to lower case for comparison because WPP attribute slugs are always lower case . */
        var wpp_like_xml_tag = wpp_create_slug( xml_tag );

        /* console.log( xml_tag + " slugged to: " + wpp_like_xml_tag ); */

        /* Do special functions if this is WPP Import */
        if( source_type == 'wpp' ) {

          // Do not load images this way, the real images are in the 'gallery' key in WPP exports
          if( wpp_like_xml_tag == 'images' ) {
            return true;
          }
        }

        /* Check if current xml_tag ( from auto_matched array from XML source exists in importable attributes array. */
        if( jQuery.inArray( wpp_like_xml_tag, wpp_all_importable_attributes ) != -1 )  {

          /* We have a match for this attribute */

        } else {

          /* Try matching based on label if nothing is found in keys */
          if( jQuery.inArray( wpp_like_xml_tag, wpp_all_importable_attributes_labels ) != -1 )  {

          } else {
            return;
          }

          return;
        }

        /* If this attribute already appears to be mapped over, we skip. */
        if( jQuery( '#wpp_property_import_attribute_mapper .wpp_dynamic_table_row option[value=' + wpp_like_xml_tag +']:selected' ).length > 0 ) {
          return true;
        }

        /* Add row to table, and enter xpath rule */
        var added_row = wpp_add_row( jQuery( '.wpp_add_row' ) );

        jQuery( '.wpp_import_attribute_dropdown', added_row ).val( wpp_like_xml_tag );

        jQuery( '.xpath_rule', added_row ).val( xml_tag );

        wpp_successful_matches++;


      });

      //** Handle special WPP attributes **/
      if( source_type == 'wpp' ) {

        if( jQuery( '#wpp_property_import_attribute_mapper .wpp_dynamic_table_row option[value=images]:selected' ).length < 1 ) {
          var added_row = wpp_add_row( jQuery( '.wpp_add_row' ) );
          jQuery( '.wpp_import_attribute_dropdown', added_row ).val( 'images' );
          jQuery( '.xpath_rule', added_row ).val( 'gallery/*/large' );
          wpp_successful_matches++;
       }
      }

      if( source_type == 'rets' ) {

        /*
        console.log( 'trying to add rets image' );
        console.log( jQuery( '#wpp_property_import_attribute_mapper .wpp_dynamic_table_row option[value=images]:selected' ).length );
        */

        if( jQuery( '#wpp_property_import_attribute_mapper .wpp_dynamic_table_row option[value=images]:selected' ).length < 1 ) {
          var added_row = wpp_add_row( jQuery( '.wpp_add_row' ) );
          jQuery( '.wpp_import_attribute_dropdown', added_row ).val( 'images' );
          jQuery( '.xpath_rule', added_row ).val( 'wpp_gallery/*/path' );
          wpp_successful_matches++;
       }
      }

      /* alert( wpp_successful_matches ); Should do something with the result, although it is clearly visual. */

      //** Clean up table **/
      wpp_property_rule_table_remove_blank_rows();

      //** Create unique ID selected  **/
      wpp_import_build_unique_id_selector();

      //** Select WPP Unique ID  **/
      if( source_type == 'wpp' ) {
        jQuery( "#wpp_property_import_unique_id" ).val( 'wpp_gpid' );
      }

    }

    /**
     * Remove any blank rows from table.
     *
     */
    function wpp_property_rule_table_remove_blank_rows() {

      if( jQuery( "#wpp_property_import_attribute_mapper .wpp_dynamic_table_row" ).length < 2 ) {
        return;
      }

      jQuery( "#wpp_property_import_attribute_mapper .wpp_dynamic_table_row" ).each( function() {

        var wpp_import_attribute_dropdown = jQuery( '.wpp_import_attribute_dropdown option:selected', this ).val();

        var xpath_rule = jQuery( '.xpath_rule', this ).val();

        /* console.log( wpp_import_attribute_dropdown + ' xpath_rule ' + xpath_rule ); */

        if( xpath_rule == '' && wpp_import_attribute_dropdown == '' ) {
          jQuery( this ).remove();
        }

      });


    }


    /**
     * Ran when any information regarding the source URL, type, or login information is chaged.
     *
     * Verifies source can be loaded and is valid.
     * Returns matched_tags and stored in wpp_auto_matched_tags
     */
    function wpp_property_import_evaluate_source( object, callback_func, do_not_use_cache ) {

      /* Be default we do not re-cache */
      if( !do_not_use_cache ) {
        do_not_use_cache = false;
      }

      var remote_url = jQuery( "#wpp_property_import_remote_url" ).val();
      var root_element = jQuery( "#wpp_property_import_choose_root_element" ).val();
      var import_type = jQuery( "#wpp_property_import_source_type option:selected" ).val();

      if( !remote_url.length || import_type == "" ) {
        return;
      }

      jQuery( ".wpp_i_source_feedback" ).hide();

      //** Set root element query based on source type, and show/hide other items */
      jQuery( '.wpp_property_import_gs_options wpp_i_advanced_source_settings, .wpp_property_import_rets_options wpp_i_advanced_source_settings' ).hide();

      if( import_type == "wpp" ){
        jQuery( "#wpp_property_import_choose_root_element" ).val( '/properties/property' );
      } else if( import_type == "gs" ){
        jQuery( "#wpp_property_import_choose_root_element" ).val( 'ROW' );
        jQuery( '.wpp_property_import_gs_options wpp_i_advanced_source_settings' ).show();
      } else if( import_type == "rets" ){
        jQuery( "#wpp_property_import_choose_root_element" ).val( '/ROWS/ROW' );
        jQuery( '.wpp_property_import_rets_options wpp_i_advanced_source_settings' ).show();
      }

      //** If currently edited element is the remote URL, try to guess the source type */
      if( jQuery( object ).attr( 'id' ) == 'wpp_property_import_remote_url' ) {

        //** Set Import Type to WPP if source appears to be a WPP export */
        if( remote_url.search( "action=wpp_export_properties" ) > 0 ) {
          //** Set source type to WPP */
          jQuery( "#wpp_property_import_source_type" ).val( 'wpp' );

        } else if( remote_url.search( "spreadsheets.google.com" ) > 0 ) {
          //** Set source type to Google Spreadsheet */
          jQuery( "#wpp_property_import_source_type" ).val( 'gs' );

        }

      }

      /** If we're RETS or Google, and we don't have a user/pass, we return */
      if( import_type == "rets" || import_type == "gs" ){
        if( import_type == "gs" && ( jQuery( "#wpp_property_import_username" ).val() == "" || jQuery( "#wpp_property_import_password" ).val() == "" ) ){
          alert( "Please fill out all required fields, check the advanced properties if you're unsure of where to look." );
          return false;
        }
        if( import_type == "rets" && ( jQuery( "#wpp_property_import_rets_username" ).val() == "" || jQuery( "#wpp_property_import_rets_password" ).val() == "" || jQuery( "#wpp_property_import_rets_class" ).val() == "" ) ){
          alert( "Please fill out all required fields, check the advanced properties if you're unsure of where to look." );
          return false;
        }
      }

      wpp_xi_loading_show();

      wpp_import_source_status( 'processing' );

      var params = {
        action: 'wpp_property_import_handler',
        wpp_action: 'execute_schedule_import',
        wpp_action_type: 'source_evaluation',
        source_type: jQuery( "#wpp_property_import_source_type option:selected" ).val(),
        data: jQuery( "#wpp_property_import_setup" ).serialize()
      }

      if( do_not_use_cache ) {
        params.do_not_use_cache = true;
      }

      //** If we have a schedule_id, we pass it into source eval */
      if( schedule_id !== undefined ) {
        params.schedule_id = schedule_id;
      }

      jQuery.post( ajaxurl, params, function( result ) {

          wpp_xi_loading_hide();

          /* Should always return a schedule ID */
          set_schedule_id( result.schedule_id );
          set_schedule_hash( result.hash );

          //** Load auto matched tags into global variable */
          wpp_auto_matched_tags = result.auto_matched_tags;

          //** Enable the Automatically Match button */
          jQuery( "#wpp_import_auto_match" ).val( "<?php _e( 'Automatically Match', 'wpp' ); ?>" );
          jQuery( "#wpp_import_auto_match" ).attr( "disabled", false );

          if( result.success == 'true' ) {
              wpp_import_source_status( 'good' );

              /* Callback a function, most likely wpp_property_import_perform_auto_matching() to finish matching */
              if ( callback_func && typeof( callback_func ) === "function" ) {
                  callback_func();
              }

          } else {
            wpp_import_source_status( 'bad' );
            wpp_i_source_check_result( result.message, 'bad' );
          }

        }, 'json' )
        .success( function() { } )
        .error( function( result ) {

          wpp_xi_loading_hide();

          if( result.status == 500 ) {
            wpp_import_source_status( 'server_error' );
            wpp_i_source_check_result( "<?php _e( 'The source evaluation resulted in an Internal Server Error!', 'wpp' ); ?>", 'bad' );
            return;
          }

          /* Proper result not returned, and not a specific error */
          wpp_import_source_status( 'bad' );
          wpp_i_source_check_result( "<?php echo _e( 'Request error:' ); ?> " + result.responseText, 'bad' );
          return;

        });

    }


    /**
     * Sets schedule_id for current DOM
     *
     */
    function set_schedule_id( schedule_id ) {

      /* Set URL hash */
      window.location.hash = schedule_id;

      /* Return for good measure */
      return schedule_id;

    }


    /**
     * Sets schedule_id for current DOM
     *
     */
    function set_schedule_hash( schedule_hash ) {

      /* Set global variable */
      import_hash = schedule_hash;

      /* Set DOM element */
      jQuery( "#import_hash" ).val( import_hash );

      /* Return for good measure */
      return schedule_hash;

    }


    /**
     * Display a message in the "Action Bar", below the Attribute Map
     *
     */
    function wpp_i_actions_bar_message( message, type, delay ) {
      var error_class = false;
      var element = jQuery( ".wpp_i_import_actions_bar .wpp_i_ajax_message" );

      /* Remove all classes */
      element.removeClass( "wpp_i_error_text" );

      if( type !== undefined && type != "" ) {

        if( type == 'bad' ) {
          var add_class = 'wpp_i_error_text'
        } else if ( type == 'good' ) {
          var add_class = ''
        } else {
          var add_class = type;
        }

      }

      //* If no message passed, just hide the element and bail */

      if( message == "" || message == undefined ) {

        if( delay != undefined ) {
          element.delay( delay ).fadeOut( "slow" );
        } else {
          element.hide();
        }

        return;
      }

      /* If we are adding a class */
      if( add_class ) {


        //* Add default class back on if one was passd */
        element.addClass( 'wpp_i_ajax_message' );

        /* Add custom class */
        element.addClass( add_class );
      }

      element.show();

      element.html( message );

      if( delay != undefined ) {
        element.delay( delay ).fadeOut( "slow" );
      }

    }


    /**
     * Display a message in the "Raw Preview" message section
     *
     */
    function wpp_i_source_check_result( message, type ) {

      var element = jQuery( "li.wpp_i_source_feedback" );

      element.show();
      element.removeClass( 'wpp_i_error_text' );

       if( message === undefined ) {
        element.html( '' );
        element.hide( '' );
        return;
       }

      if( type == 'bad' ) {
        element.addClass( 'wpp_i_error_text' );
      }

      element.html( message );

    }


    /**
     * Display a message in the "Raw Preview" message section
     *
     */
    function wpp_i_preview_raw_preview_result( message, type ) {

      var element = jQuery( "span.wpp_i_preview_raw_data_result" );

      element.removeClass( 'wpp_i_error_text' );

       if( message === undefined ) {
        element.html( '' );
        return;
       }

      if( type == 'bad' ) {
        element.addClass( 'wpp_i_error_text' );
      }

      element.html( message );

    }


    /**
     * Sets the status of the source URL in UI
     *
     */
    function wpp_import_source_status( status ) {

       jQuery( "#wpp_property_import_source_status" ).removeClass();

      if( status == '' ) {
        jQuery( "#wpp_property_import_source_status" ).hide();
        return;
      }

      jQuery( "#wpp_property_import_source_status" ).show();

      if( status == 'ready_to_check' ) {
        jQuery( "#wpp_property_import_source_status" ).text( '<?php _e( 'Check Source', 'wpp' ); ?>' );
      }

      if( status == 'processing' ) {
        jQuery( "#wpp_property_import_source_status" ).addClass( 'wpp_import_source_processing' );
        jQuery( "#wpp_property_import_source_status" ).text( '<?php _e( 'Processing...', 'wpp' ); ?>' );
      }

      if( status == 'good' ) {
        jQuery( "#wpp_property_import_source_status" ).addClass( 'wpp_import_source_good' );
        jQuery( "#wpp_property_import_source_status" ).text( '<?php _e( 'Source is Good: Reload.', 'wpp' ); ?>' );
      }

      if( status == 'bad' ) {
        jQuery( "#wpp_property_import_source_status" ).addClass( 'wpp_import_source_bad' );
        jQuery( "#wpp_property_import_source_status" ).text( '<?php _e( 'Cannot Load Source: Reload.', 'wpp' ); ?>' );
      }

      if( status == 'server_error' ) {
        jQuery( "#wpp_property_import_source_status" ).addClass( 'wpp_import_source_bad' );
        jQuery( "#wpp_property_import_source_status" ).text( '<?php _e( 'Internal Server Error!', 'wpp' ); ?>' );
      }

    }


  </script>

     <div class="wrap">
      <h2><?php _e( 'Property Importer', 'wpp' ); ?>
        <a id="wpp_property_import_add_import" class="button add-new-h2" href="#add_new_schedule"><?php _e( 'Add New' ); ?></a>
        <span class="wpp_xi_loader"></span>
      </h2>

      <?php if( isset( $wp_messages['error'] ) && $wp_messages['error'] ): ?>
      <div class="error">
        <?php foreach( $wp_messages['error'] as $error_message ): ?>
          <p><?php echo $error_message; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if( isset( $wp_messages['notice'] ) && $wp_messages['notice'] ): ?>
      <div class="updated fade">
        <?php foreach( $wp_messages['notice'] as $notice_message ): ?>
          <p><?php echo $notice_message; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <div id="wpp_property_import_ajax"></div>

    <?php if( !empty( $wpp_property_import['schedules'] ) ):

      $cron_path = str_replace( 'core/premium/class_wpp_property_import.php', 'cron.php', __FILE__ );
      ?>

    <?php if( count( $wpp_property_import['schedules'] ) > 1 ) { ?>
    <ul class="subsubsub wpp_import_overview_page_element">
      <li class="all"><?php _e( 'Sort by:', 'wpp' ); ?></a> </li>
      <li class="wpp_i_sort_schedules" sort_direction="ASC" sort_by="lastrun"><a href="#"><?php _e( 'Last Run', 'wpp' ); ?></a> |</li>
      <li class="wpp_i_sort_schedules" sort_direction="ASC" sort_by="created"><a href="#"><?php _e( 'Created Properties', 'wpp' ); ?> </a> |</li>
      <li class="wpp_i_sort_schedules" sort_direction="ASC" sort_by="updated"><a href="#"><?php _e( 'Updated Properties', 'wpp' ); ?> </a> | </li>
      <li class="wpp_i_sort_schedules" sort_direction="ASC" sort_by="total_properties"><a href="#"><?php _e( 'Total Properties', 'wpp' ); ?> </a> | </li>
      <li class="wpp_i_sort_schedules" sort_direction="ASC" sort_by="limit"><a href="#"><?php _e( 'Limit', 'wpp' ); ?></a></li>
    </ul>
    <?php } ?>


      <table id="wpp_property_import_overview" class="widefat wpp_import_overview_page_element">
      <thead>
        <tr>
          <th><?php _e( "Saved Import Schedules", 'wpp' ); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach( $wpp_property_import['schedules'] as $sch_id => $sch ):

        if( empty( $sch_id ) ) {
          continue;
        }

        $this_row_data = array();

        if( $sch['lastrun']['time'] ) {
          $vital_stats[$sch_id][] = __( 'Last run ', 'wpp' ) . human_time_diff( $sch['lastrun']['time'] ) . __( ' ago.', 'wpp' );
          $this_row_data[] = "lastrun=\"{$sch['lastrun']['time']}\" ";
        }

        if( $sch['lastrun']['u'] ) {
          $vital_stats[$sch_id][] = __( 'Updated ', 'wpp' ) . $sch['lastrun']['u'] .  __( ' objects.', 'wpp' );
          $this_row_data[] = "updated=\"{$sch['lastrun']['u']}\" ";
        }

        if( $sch['lastrun']['c'] ) {
          $vital_stats[$sch_id][] = __( 'Created ', 'wpp' ) .  $sch['lastrun']['c'] . __( ' objects.', 'wpp' );
          $this_row_data[] = "created=\"{$sch['lastrun']['c']}\" ";
        }

        if( $sch['limit_properties'] ) {
          $vital_stats[$sch_id][] = __( 'Limited to ', 'wpp' ) .  $sch['limit_properties'] . __( ' objects.', 'wpp' );
          $this_row_data[] = "limit=\"{$sch['limit_properties']}\" ";
        }

        if( $total_properties = $wpdb->get_var( $wpdb->prepare ( "SELECT COUNT( ID ) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE post_type = 'property' AND meta_key = 'wpp_import_schedule_id' and meta_value = %s " , $sch_id ) ) ) {
          $vital_stats[$sch_id][] = __( 'Total Properties: ', 'wpp' ) . WPP_F::format_numeric( $total_properties );
          $this_row_data[] = "total_properties=\"{$total_properties}\" ";
        } else {
          $total_properties = false;
        }


      ?>
        <tr <?php echo implode( '', $this_row_data ); ?> class="wpp_i_schedule_row" schedule_id="<?php echo $sch_id; ?>" import_title="<?php echo esc_attr( $sch['name'] ); ?>">
          <td class="post-title column-title">
            <ul>
              <li><strong><a href="#<?php echo $sch_id; ?>" schedule_id="<?php echo $sch_id; ?>" class="wpp_property_import_edit_report"><?php echo $sch['name']; ?></a></strong></li>
              <li><?php _e( 'Source URL:', 'wpp' ); ?> <span class="wpp_i_overview_special_data"><?php echo $sch['url']; ?></span></li>
              <li><?php _e( 'Cron Command:', 'wpp' ); ?> <span class="wpp_i_overview_special_data">php -q <?php echo $cron_path . ' do_xml_import ' . $sch['hash']; ?></span></li>
              <?php if( $vital_stats[$sch_id] ) { ?>
              <li><span class="wpp_i_overview_special_data"><?php echo implode( ' | ' , $vital_stats[$sch_id] ); ?></span></li>
              <?php } ?>
              <li>
                <a href="#<?php echo $sch_id; ?>" schedule_id="<?php echo $sch_id; ?>" class="wpp_property_import_edit_report"><?php _e( 'Edit', 'wpp' ); ?></a> |
                <a href="#" schedule_id="<?php echo $sch_id; ?>" class="wpp_property_import_delete_report"><?php _e( 'Delete', 'wpp' ); ?></a> |
                <a href="<?php echo  get_bloginfo( 'home' )."/?wpp_schedule_import=".$sch['hash']."&echo_log=true"; ?>" target="_blank" /><?php _e( 'Run Import in Browser', 'wpp' ); ?></a> |
                <a href="<?php echo wp_nonce_url( "edit.php?post_type=property&page=wpp_property_import&wpp_action=download-wpp-import-schedule&schedule_id={$sch_id}", 'download-wpp-import-schedule' ); ?>"  class=""><?php _e( 'Save to File', 'wpp' ); ?></a> |
                <?php if( $total_properties > 0 ) { ?>
                <a href="#" schedule_id="<?php echo $sch_id; ?>" class="wppi_delete_all_feed_properties"><?php _e( 'Delete All Properties', 'wpp' ); ?></a>
                <?php } ?>
                <span class="wpp_loader"></span>
                <div class="run_progressbar" style="width:500px"></div>
              </li>
            </ul>
          </td>
        </tr>
      <?php endforeach;  ?>
      </tbody>
      </table>
      <?php else: ?>
      <p class="wpp_import_overview_page_element"><?php echo __( 'You do not have any saved schedules. Create one now.','wpp' ); ?>
      <?php endif; ?>


     <div class="wpp_import_import_schedule wpp_import_overview_page_element">
     <form method="post" action="<?php echo admin_url( 'edit.php?post_type=property&page=wpp_property_import' ); ?>"  enctype="multipart/form-data" />

      <input type="hidden" name="wpp_action" value="import_wpp_schedule" />
      <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'wpp_import_import_schedule' ); ?>" />
      <?php _e( "Import Schedule", 'wpp' ); ?>: <input name="wpp_import[import_schedule]" type="file" />

       <input type="submit" value="<?php _e( 'Upload File','wpp' ); ?>" class="btn"/>
     </form>
     </div>

    </div>

    <?php

  }


  /**
  * Imports an object into WP-Property
  *
  *
  * @todo Properly handle merging
  * @todo May want to move more of the core save_property() into filters, so they can be used here, and elsewhere
  * @todo Image handling
  * @todo Need way of settings property type
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  */
  function import_object( $data, $schedule_id, $counter = false ) {
    global $wpp_property_import, $wpdb, $wp_properties;

    //** Stas *//
    $internal_stats = array();

    $wp_upload_dir = wp_upload_dir();

    // Load schedule settings
    $schedule_settings = $wp_properties['configuration']['feature_settings']['property_import']['schedules'][$schedule_id];

    if( $schedule_settings['regenerate_thumbnails'] == 'true' ) {
      $regenerate_thumbnails = true;
    } else {
      $regenerate_thumbnails = false;
    }

    // Load wp_posts columns and their labels
    $post_table_columns = array_keys( $wpp_property_import['post_table_columns'] );

    // Load WPP taxonomies
    foreach( $wp_properties['taxonomies'] as $slug => $tax ){
      $taxonomies[] = $slug;
    }

    // Load defaults for new properties
    $defaults = apply_filters( 'wpp_import_object_defaults', $defaults = array(
      'post_title' => $data['post_title'][0],
      'post_content' => $data['post_content'][0],
      'post_status' => 'publish',
      'post_type' => 'property',
      'ping_status' => get_option( 'default_ping_status' ),
      'post_parent' => 0
      ), $data );


    //* Handle WPP Import in a special way */
    if( $schedule_settings['source_type'] == "wpp" ) {
      $wpp_gpid = $data['wpp_gpid'][0];
      $post_exists = WPP_F::get_property_from_gpid( $wpp_gpid );
    }

    $unique_id_attribute = $data['unique_id'];
    $unique_id_value = $data[$unique_id_attribute][0];

    if( !$post_exists ) {

      if( !$post_exists = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} where meta_key='{$unique_id_attribute}' and meta_value=%s LIMIT 0, 1", $unique_id_value ) ) ) {

        // If no old ID found in meta table, attempt to find it in main posts table
        if( in_array( $data['unique_id'], $post_table_columns ) && $schedule_settings['source_type'] != "wpp" ) {
          $post_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM ".$wpdb->posts." where %s = %s ", $data['unique_id'], $data[$data['unique_id']][0] ));
        }
      }
    }

    if( !empty( $post_exists ) ) {
      // Existing property

      if( $schedule_settings['log_detail'] == 'on' ) {
        class_wpp_property_import::maybe_echo_log( __( 'Updating existing listing.', 'wpp' ) );
      }

      // Set ID to match old post ( so duplicate doesn't get created )
      $defaults['ID'] = $post_exists;

      // Update post with default data ( which may be overwritten later during full import )
      $post_id = wp_update_post( $defaults );

      if( is_numeric( $post_id ) ) {
        /** Post ID exists */
        $mode = 'u';

        $property_url = get_permalink( $post_id );
        class_wpp_property_import::maybe_echo_log( $counter  . " - updated <a href='{$property_url}' target='_blank'>#{$post_id}</a>." );

      } else {
        return new WP_Error( 'fail', __( "Attempted to update property, but wp_update_post() did not return an ID. " ) );
      }

      // Property Skipping. Only applicable to existing properties when reimport_delay setting has a value.
      if( !empty( $schedule_settings['reimport_delay'] ) ) {

        do_action( 'wpp_import_property_before_skip',$post_id, $data );

        $last_import = get_post_meta( $post_id, 'wpp_xml_import', true );

        $time_since_last_import = time() - $last_import;

        $reimport_delay_in_seconds = ( $schedule_settings['reimport_delay'] * 60 * 60 );

        if( $time_since_last_import < $reimport_delay_in_seconds ) {
          $skip = true;
        }

        // Allow override of skip or not
        $skip = apply_filters( 'wpp_import_skip_import', $skip, $post_id, $schedule_settings );

        // Do the skipping
        if( $skip ) {

          if( $schedule_settings['log_detail'] == 'on' ) {
            class_wpp_property_import::maybe_echo_log( __( 'Skipping property, last import ' ) . human_time_diff( $last_import ) . __( ' ago' ) );
          }

          // Stop this import and return to next object
          return array( $post_id, $mode );

        } else {

          class_wpp_property_import::maybe_echo_log( "Importing. Unique ID: ( {$unique_id_value} )" );
        }

      }

    } else {

      if( $schedule_settings['log_detail'] == 'on' ) {
        class_wpp_property_import::maybe_echo_log( __( 'Creating new listing.', 'wpp' ) );
      }

      if( $schedule_settings[ 'show_sql_queries' ] == 'true' ) {
        $wpdb->show_errors();
      }

      //** New property. */
      $post_id = wp_insert_post( $defaults, true );

      if( $post_id && !is_wp_error( $post_id ) ) {

        $mode = 'c';

        $property_url = get_permalink( $post_id );
        class_wpp_property_import::maybe_echo_log( '#' . $counter  . " - created <a href='{$property_url}' target='_blank'>#{$post_id}</a>" );

      }
    }

    // At this point a blank property is either created or the existing $post_id is set, should be no reason it not be set, but just in case. */
    if( is_wp_error( $post_id ) ) {
      return new WP_Error( 'fail', sprintf( __( 'Object import failed. Error: %1s.', 'wpp' ), $post_id->get_error_message() ) );
    }

    if( !is_numeric( $post_id ) && empty( $defaults['post_title'] ) ) {
      return new WP_Error( 'fail', __( 'Object import failed - no Property Title detected or set, a requirement to creating a property.', 'wpp' ) );
    }

    if( !$post_id ) {
      return new WP_Error( 'fail', __( 'Object import failed.  Post cold not be created nor updated, and post_id was not found or created.', 'wpp' ) );
    }

    unset( $data['unique_id'] );

    // remove any orphaned image
    if( $schedule_settings['remove_images'] == 'on' ) {

      $all_images = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent = %d ", $post_id ), ARRAY_A );

      $internal_stats['removed_images'] = 0;
      foreach( $all_images as $image_row ) {

        if( wp_delete_attachment( $image_row->ID, true ) ) {
          $internal_stats['removed_images']++;
        }

      }

      if( $internal_stats['removed_images'] ) {
        class_wpp_property_import::maybe_echo_log( "Removed ( {$internal_stats['removed_images']} ) old images." );
      }
    }

//** Clean Up Attached Images:  *//
    if( $schedule_settings['do_not_clean_up_attachments'] != 'on' ) {

      /** Get all attached images - in ascending post_date order (oldest attachments first) */
      $all_attachments = $wpdb->get_results( $wpdb->prepare("SELECT ID as attachment_id, post_date, post_content_filtered, guid, post_name, meta_value as _wp_attached_file FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE post_type = 'attachment' AND post_parent = %d AND meta_key = '_wp_attached_file' ORDER BY post_date ASC ", $post_id ) );

      //** Cycle through all attached and verify md5 */
      foreach( $all_attachments as $key => $attached) {

        $all_attachments[$key]->full_path = trailingslashit( $wp_upload_dir['basedir'] ) . $attached->_wp_attached_file;
        $all_attachments[$key]->attachment_url = trailingslashit( $wp_upload_dir['baseurl'] ) . $attached->_wp_attached_file;
        $all_attachments[$key]->exists = file_exists( $all_attachments[$key]->full_path ) ? true : false ;

        if( !$all_attachments[$key]->exists ) {
          $all_attachments[$key]->exists = false;
          class_wpp_property_import::maybe_echo_log( sprintf( __( 'Attachment referenced in database not found on disk: (%1s), removing reference from DB.', 'wpp' ), $all_attachments[$key]->attachment_url ) );
          wp_delete_attachment( $attached->attachment_id );
          continue;
        }

        $all_attachments[$key]->md5_file = md5_file( $all_attachments[$key]->full_path );

        // If this attachment already exists in our MD5 array, we delete it
        if( in_array( $all_attachments[$key]->md5_file, (array) $found_md5s ) ) {
          $all_attachments[$key]->duplicate = true;
          wp_delete_attachment( $attached->attachment_id, true );

          if( $schedule_settings['log_detail'] == 'on' ) {
            class_wpp_property_import::maybe_echo_log( sprintf( __( 'Duplicate attachment file found (%1s) and deleted.', 'wpp' ), $all_attachments[$key]->attachment_url ) );
          }

        } else {
          //** Add attachment to array of existing unique MD5s * /
          $found_md5s[] = $all_attachments[$key]->md5_file;
        }

      }

    }

    // Keep track of all attributes that have values and have been imported
    $processed_attributes = array();

    // Cycle through attributes ( which include meta value, images and taxonomies )
    foreach( $data as $attribute => $values ) {

      $attribute_data = WPP_F::get_attribute_data( $attribute );

      // If no values, stop processing this attribute
      if( empty( $values ) ) {
        continue;
      }

      // Convert value to array format if it isn't
      if( !is_array( $values ) ) {
        $values = array( $values );
      }

       if( $attribute == 'images' ) {
        timer_start();
       }

      //** Get array of keys we will not encode on import */
      $keys_to_not_encode = apply_filters( 'wpp_import_do_not_encode_attributes', array( 'wpp_agents', 'images' ) );

      //** Values are in array format, cycle through them */
      foreach( $values as $value ) {

         /** don't encode urls or attributes in exclusion array */
        if( !WPP_F::isURL( $value ) && ( !in_array( $attribute, $keys_to_not_encode ) && $attribute_data['storage_type'] != 'post_table' ) ) {
          $original_value = $value;
          $value = WPP_F::encode_mysql_input( $value,  $attribute );
        }

        //** Handle Agent Matching */
        if( $attribute == 'wpp_agents' ) {

          $agent_match_bridge = ( $schedule_settings['wpp_agent_attribute_match'] ? $schedule_settings['wpp_agent_attribute_match'] : 'display_name' );

          //** Attempt to find agent ID based on provided data */

          $possible_match = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE  {$agent_match_bridge} = '%s' LIMIT 0, 1", $value ) );

          if( !is_numeric( $possible_match ) ) {
            //** Try meta table */
            $possible_match = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE  meta_key='{$agent_match_bridge}' AND meta_value= '%s'  LIMIT 0, 1 ", $value ) );
          }

          if( is_numeric( $possible_match ) ) {

            if( $schedule_settings['log_detail'] == 'on' ) {
              class_wpp_property_import::maybe_echo_log( "Property agent found based on {$agent_match_bridge} with ID of $possible_match - adding to {$post_id}  property." );
            }

            delete_post_meta( $post_id, 'wpp_agents' );
            add_post_meta( $post_id, 'wpp_agents', $possible_match );
          }


        } elseif ( $attribute == 'images' ) {

          //** If image limit reached, skip this cycle */
          if( !empty( $schedule_settings['limit_images'] ) && count( $internal_stats['imported_images'] ) >= $schedule_settings['limit_images'] ) {
            continue;
          }

          /** Handle images */
          if( $attach_image = class_wpp_property_import::attach_image( array(
            'post_id' => $post_id,
            'image' => $value,
            'data' => $data,
            'mode' => $mode,
            'schedule_settings' => $schedule_settings,
            'schedule_id' => $schedule_id,
            'regenerate_thumbnails' => $regenerate_thumbnails
          ) ) ) {

            if( $attach_image['action'] == 'image_downloaded' ) {
              $internal_stats['imported_images'][] = $attach_image['thumb_id'];
              $internal_stats['imported_images_size'][] = $attach_image['image_size'];
            } else {
              $internal_stats['existing_images'][] = $attach_image['thumb_id'];
            }

          }

          if( count( $internal_stats['imported_images'] ) == 1 && $schedule_settings[ 'automatically_feature_first_image' ] ) {
            update_post_meta( $post_id, '_thumbnail_id', $attach_image['thumb_id'] );
          }

        } elseif( $attribute == 'featured-image' ) {

          /** Handle Featured Image */
          if( $attach_image = class_wpp_property_import::attach_image( array(
            'post_id' => $post_id,
            'image' => $value,
            'data' => $data,
            'mode' => $mode,
            'schedule_settings' => $schedule_settings,
            'schedule_id' => $schedule_id,
            'regenerate_thumbnails' => $regenerate_thumbnails
          ) ) ) {

            update_post_meta( $post_id, '_thumbnail_id', $attach_image['thumb_id'] );

            if( $attach_image['action'] == 'image_downloaded' ) {
              $internal_stats['imported_images'][] = $attach_image['thumb_id'];
              $internal_stats['imported_images_size'][] = $attach_image['image_size'];
            } else {
              $internal_stats['existing_images'][] = $attach_image['thumb_id'];
            }

            if( $schedule_settings['log_detail'] == 'on' ) {
              class_wpp_property_import::maybe_echo_log( "Imported featured image, set thumbnail to {$attach_image['thumb_id']} for {$post_id}." );
            }

          } else {
            //* Image was not imported for some reason. */
          }

        } elseif ( in_array( $attribute, ( array ) $taxonomies ) ) {

           /** Handle taxonomies */
          $to_add_taxonomies[$attribute][] = apply_filters( 'wpp_xml_import_value_on_import', $value, $attribute, 'taxonomy', $post_id );

        } elseif ( in_array( $attribute, $post_table_columns ) ) {

          /** Handle values that are stored in main posts table */
          $wpdb->update( $wpdb->posts, array( $attribute => apply_filters( 'wpp_xml_import_value_on_import', $value, $attribute, 'post_table', $post_id ) ), array( 'ID' => $post_id ) );

        } else {

          /** Handle regular meta fields */
          $value = apply_filters( 'wpp_xml_import_value_on_import', $value, $attribute, 'meta_field', $post_id );

          if( !empty( $value ) ) {
            update_post_meta( $post_id, $attribute, $value );
          }

        }

      }


      if( $attribute == 'images' ) {
        $image_import_time = timer_stop();

        if( $internal_stats['imported_images'] ) {

          $total_imported_images = count( $internal_stats['imported_images'] );
          $imported_images_size = class_wpp_property_import::format_size( array_sum( $internal_stats['imported_images_size'] ) );

          if( $regenerate_thumbnails ) {
            class_wpp_property_import::maybe_echo_log( "$total_imported_images image(s) imported at at {$imported_images_size} in {$image_import_time} seconds. ( Thumbnails were regenerated. )" );
          } else {
            class_wpp_property_import::maybe_echo_log( "$total_imported_images image(s) at {$imported_images_size} imported in {$image_import_time} seconds." );
          }
        }

        if( $internal_stats['existing_images'] ) {
          $existing_images = count( $internal_stats['existing_images'] );

          if( $schedule_settings['log_detail'] == 'on' ) {
            class_wpp_property_import::maybe_echo_log( "$existing_images image(s) already exists." );
          }
        }

      }

      /** Add processed attribute to array */
      $processed_attributes[] = $attribute;

    } /* end: foreach( $data as $attribute => $values )  */


    /** Add all taxonomies */
    if( !empty( $to_add_taxonomies ) ) {

      foreach( $to_add_taxonomies as $tax_slug => $terms ) {
        // We are not appending, but replacing all of them

        if( !taxonomy_exists( $tax_slug ) ) {
          continue;
        }

        $tt_ids = wp_set_object_terms( $post_id, $terms, $tax_slug );

      }

    }

    /** Set property_type to default if not set from individual property */
    if( !in_array( 'property_type', $processed_attributes ) ) {
      update_post_meta( $post_id, 'property_type', $schedule_settings['property_type'] );
    }

    //** Automatically setup slideshows */
    if( is_array( $internal_stats['imported_images'] ) && $schedule_settings['automatically_load_slideshow_images'] == 'on' ) {
      update_post_meta( $post_id, 'slideshow_images',$internal_stats['imported_images'] );
      /* class_wpp_property_import::maybe_echo_log( "Automatically loaded ( ".count( $internal_stats['imported_images'] )." ) property slideshow images." ); */
    }

    /** Take note of which import schedule this property came from for future association */
    update_post_meta( $post_id, 'wpp_import_schedule_id', $schedule_id );

    /** Update last imported timestamp to current */
    update_post_meta( $post_id, 'wpp_import_time', time() );

    /** Set GPID for property if one isnt set */
    WPP_F::maybe_set_gpid( $post_id );

    //** Attempt to reassemble the 'address_attribute' if it is not set */
    if( $address_attribute = $wp_properties['configuration']['address_attribute'] ) {

      $current_address = get_post_meta( $post_id, $address_attribute, true );

      if( empty( $current_address ) ) {
        if( $fixed_address = WPP_F::reassemble_address( $post_id ) ) {
          update_post_meta( $post_id, $address_attribute, $fixed_address );
          class_wpp_property_import::maybe_echo_log( "No address found for property, reassembled it from parts: {$fixed_address}" );
        }
      }

    }


    /** Save parent GPID association to meta for later association */
    if( $data['parent_gpid'][0] ) {
      update_post_meta( $post_id,'parent_gpid', $data['parent_gpid'][0] );
      class_wpp_property_import::maybe_echo_log( "Parent GPID found for {$post_id} -> {$data['parent_gpid'][0]}  ." );
    }

    do_action( 'wpp_import_property',$post_id, $data );

    //** If the current property did not have any images, we delete it now ( not the best way, but since the object is created before this function, the only way w/o restructoring */
    if( isset( $schedule_settings['minimum_images'] ) && $schedule_settings['minimum_images'] > 0 ) {

      $images = get_children( array( 'post_parent' => $post_id, 'post_type' => 'attachment', 'post_mime_type' => 'image' ) );

      if( count( $images ) < $schedule_settings['minimum_images'] ) {
        if( wp_delete_post( $post_id, true ) ) {
          class_wpp_property_import::maybe_echo_log( __( 'After import property has less than the required amount of images - deleting property as configured.', 'wpp' ) );
          return false;
        } else {
          class_wpp_property_import::maybe_echo_log( __( 'After import property has no images, but was unable to delete the property.', 'wpp' ) );
        }
      } else {

        if( $schedule_settings['log_detail'] == 'on' ) {
          class_wpp_property_import::maybe_echo_log( sprintf( __( 'After import property has a total of %1s images.', 'wpp' ), count( $images ) ) );
        }
      }

    }

    $return_data = array(
      '0' => $post_id,
      '1' => $mode,
      '2' => count( $internal_stats['imported_images'] ),
      '3' => array_sum( ( is_null( $internal_stats['imported_images_size'] ) ? array() : $internal_stats['imported_images_size'] ) ),
      '4' => count( $internal_stats['existing_images'] )
    );

    return $return_data;
    //  do_action( 'save_property',$post_id, $_REQUEST, $geo_data );
  }

  /**
  * Makes a request to specified url
  *
  * Recognized XML or Google Spreadsheet feed
  *
  * @todo $limit=1 on RETS seems to prevent preview query from looking past the first result, and doing something like /ROWS/ROW[L_Class = "COMMERCIAL"] does not work, but need to test. - potanin@UD
  * @todo Add error handling, right now rets class jus dies on errors, such as with GetObject, no feedback to user.
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function wpp_make_request( $url, $method = 'get', $data ) {
    global $wpp_property_import, $wpp_import_result_stats, $wp_properties;

    //** Set schedule ID */
    $schedule_id = $data['schedule_id'];

    /** Go ahead and include our Zend directory */
    set_include_path( WPP_Path.'third-party/' );
    require_once( 'Zend/Gdata/Spreadsheets.php' );
    require_once( 'Zend/Gdata/ClientLogin.php' );

    if( isset( $_REQUEST['stepping'] ) ) {
      // Open up our temp file for writing
      if( !is_dir( WPP_Path."cache" ) ) {
       mkdir( WPP_Path."cache" );
       chmod( WPP_Path."cache", 0755 );
      }
    }

    $newvars = array();

    if( $data['wpp_property_import']['source_type'] == 'gs' ) {
      try{
        /** Only connect if we aren't a stepping element */
        if( !isset( $_REQUEST['stepping_element'] ) ){
          $gdata = new gc_import();
          /* Build our query */
          $query = new Zend_Gdata_Spreadsheets_ListQuery();
          $query->setSpreadsheetKey( $gdata->parse_spreadsheet_key( $data['wpp_property_import']['url'] ) );
          $query->setWorksheetId( 1 );
          $query_url = $query->getQueryUrl();
          if( !empty( $data['wpp_property_import']['google_extra_query'] ) ) $query_url .= "?".$data['wpp_property_import']['google_extra_query'];
          /** Connect to the spreadsheet */
          $gdata->gdata_connect( $data['wpp_property_import']['google_username'], $data['wpp_property_import']['google_password'] );
          $listFeed = $gdata->gdata['ss_service']->getListFeed( $query_url );

          /** Loop through the rows, building our XML string */
          $str = '<?xml version="1.0"?><GC>';
          $rows = 1;

          foreach( $listFeed->entries AS $entry ) {
            $str .= '<ROW>';
            $str .= '<IMPORT_ROWID>'.$rows.'</IMPORT_ROWID>';
            /* Build a generic random number */
            $rowData = $entry->getCustom();
            foreach( $rowData as $customEntry ){
              $str .= '<'.strtoupper( $customEntry->getColumnName() ).'>'.htmlentities( $customEntry->getText() ).'</'.strtoupper( $customEntry->getColumnName() ).'>';
            }
            $str .= '</ROW>';
            $rows++;
          }
          $str .= '</GC>';

          if( isset( $_REQUEST['stepping'] ) ){
            file_put_contents( WPP_Path."cache/".$data['wpp_property_import']['hash'].".xml", $str );
          }

        }else{
          $str = file_get_contents( WPP_Path."cache/".$data['wpp_property_import']['hash'].".xml" );
        }

        return array( 'body' => $str );
      } catch( Exception $e ) {
        die( json_encode( array( 'success' => 'false', 'message' => $e->getMessage() ) ) );
      }

    } else if( $data['wpp_property_import']['source_type'] == 'csv' ) {

        $url_array = parse_url( $url );

        if( !empty( $url_array['query'] ) ) {
          parse_str( $url_array['query'], $newvars );
        }

        if( $method == 'post' && count( $newvars ) && !empty( $newvars ) ) {
            $return = wp_remote_post( $url, array( 'timeout' => 120, 'body' => array( 'request' => serialize( $newvars ) ) ) );
        } else {
          $return = wp_remote_get( $url, array( 'timeout' => 120 ) );
        }

        //** Check if data is JSON or XML */
        if( is_wp_error( $return ) ) {
          return $return;
        } else {

          //** Create a temporary file that fgetcsv() can read through */
          if( !empty( $return['body'] ) ) {

            $xml_from_csv = WPP_F::csv_to_xml( $return['body'] );

            //** Load the converted XML Back into body - as if nothing even happened */
            $return['body'] = $xml_from_csv;

            return $return;

          }

        }


    } else if( $data['wpp_property_import']['source_type'] == 'rets' ) {

      try {

        $import = $data['wpp_property_import'];

        /** Create my new rets feed */
        $rets = new phRETS();

        /** @updated 3.2.6 - potanin@UD */
        if( $wp_properties[ 'configuration' ][ 'developer_mode' ] == 'true' ) {
          $upload_dir = wp_upload_dir();
          $rets->SetParam( 'debug_mode', true );
          $rets->SetParam( 'debug_file', $upload_dir['basedir'] . '/xmli.rets.log' );
        }

        $rets->AddHeader("Accept", "*/*");
        $rets->AddHeader( 'User-Agent', !empty( $import['rets_agent'] ) ? $import['rets_agent'] : 'WP-Property/1.0' );
        $rets->AddHeader( 'RETS-Version', !empty( $import['rets_version'] ) ? $import['rets_version'] : 'RETS/1.7' );

        if( isset($import['rets_agent_password']) && !empty($import['rets_agent_password']) ){
          $connect = $rets->Connect( $import['url'], $import['rets_username'], $import['rets_password'], $import['rets_agent_password'] );
        }else{
          $connect = $rets->Connect( $import['url'], $import['rets_username'], $import['rets_password'] );
        }

        if( !$connect ) {
          $error_details = $rets->Error();
          $error_text = strip_tags($error_details['text']);
          $error_type = strtoupper($error_details['type']);
          throw new Exception( "Could not connect to RETS server: $error_type - $error_text" );
        }

        /** Include our function here to write arrays to XML */
        if( !function_exists( 'write' ) ) {
          function write( XMLWriter $xml, $data ){

            foreach( $data as $key => $value ) {

              $key = !is_numeric( $key ) ? $key : '_' . $key;

              if( is_array( $value ) ) {
                $xml->startElement( $key );
                write( $xml, $value );
                $xml->endElement();
                continue;
              }

              $xml->writeElement( $key, $value );

            }
          }
        }

        /** Start our XML */
        $xml = new XmlWriter();
        $xml->openMemory();
        $xml->startDocument( '1.0', 'UTF-8' );
        $xml->startElement( 'ROWS' );

        /** set limit */
        if( !empty( $import['limit_scanned_properties'] ) ) {
          $limit = $import['limit_scanned_properties'];
        } else {
          $limit = 0;
        }

        /** On preview, we have to get the FULL feed, but not all the images */
        if( $_REQUEST['wpp_action_type'] == 'source_evaluation' || $_REQUEST['preview'] == true || $_REQUEST['raw_preview'] == 'true' ) {
          $partial_cache = true;
          $limit = 1;

          $_required = array();
          $_one_required = array();
          $_searchable = array();

          /* Do quick analysis of meta data @updated 1.3.6 */
          foreach( (array) $rets->GetMetadata( 'Property', $import['rets_class'] ) as $item ) {

            $_attribute_key = $item[ 'StandardName' ] ? $item[ 'StandardName' ] : $item[ 'LongName' ];

            $item = array_filter( (array) $item );

            if( $item[ 'Required' ] == 1 ) {
              $_required[ $_attribute_key ] = $item;
            }

            if( $item[ 'Required' ] == 2 ) {
              $_one_required[ $_attribute_key ] = $item;
            }

            if( $item[ 'Searchable' ] ) {
              $_searchable[ $_attribute_key ] = $item;
            }
          }

        }

        /** Determine our Query */
        $rets_query = !empty( $import['rets_query'] ) ? $import['rets_query'] : '(ListingStatus=|Active)';

        /** Determine our main ID */
        $rets_pk = !empty( $import['rets_pk'] ) ? $import['rets_pk'] : 'ListingKey';

        /** Determine our Photo object */
        $rets_photo = !empty( $import['rets_photo'] ) ? $import['rets_photo'] : 'Photo';

        /** Search for Properties */
        $search = $rets->SearchQuery( 'Property', $import['rets_class'], $rets_query, array( 'Limit' => $limit ) );

        if( !$search ) {

          preg_match_all( '/\(([^=]+)=(.*?)\)/sim', $rets_query, $matches, PREG_SET_ORDER );

          foreach( (array) $matches as $match ) {
            $_used_keys[] = $match[1];
          }

          switch( $rets->error_info[ 'code' ] ) {

            /* Invalid Query Syntax. */
            case 20206:
            break;

            /* Missing close parenthesis on subquery. | Required search fields missing. | Illegal number in range for field List Price. */
            case 20203:

              if( count( $_used_keys ) != count( $_required ) ) {
                throw new Exception( "The search query failed because this provider requires certain attributes to be included in the search query. Required attribute(s): " . implode( ', ', array_keys( $_required ) ) . ( $_one_required ? ". At least one: " . implode( ', ', array_keys( $_one_required ) ) : '' ) );
              }

            break;

          }

          /* See if we are missing required attributes */

          throw new Exception( "There was an issue doing the RETS search: ". $rets->error_info['text'] );
        }

        class_wpp_property_import::maybe_echo_log( 'RETS connection established. Got ' . $rets->NumRows( $search ) . ' out of ' . $rets->TotalRecordsFound( $search ) . ' total listings.' );

        $total_rets_images = array();
        $processed_properties = array();

        $row_count = 1;

        //** Create a temp directory using the import ID as name */
        $image_directory = class_wpp_property_import::create_import_directory( array( 'ad_hoc_temp_dir' => $schedule_id ) );

        if( $image_directory ) {
          $image_directory = $image_directory['ad_hoc_temp_dir'];
        } else {
          class_wpp_property_import::maybe_echo_log( sprintf( __( 'Image directory %1s could not be created.', 'wpp' ), $image_directory ) );
        }

        while ( $row = $rets->FetchRow( $search ) ) {

          class_wpp_property_import::keep_hope_alive();

          $photos = false;
          $valid_images = 0;

          //** When partial cache only get images for first property */
          if( $row_count > 1 && $partial_cache ) {
            $skip_photos = true;
          }

          //** Do not get images if we are skipping photos */
          if( $image_directory && $rets_photo && !$skip_photos && isset( $row[ $rets_pk ] ) ) {
            $photos = $rets->GetObject( 'Property', $rets_photo, $row[$rets_pk] );
          }

          //** Begin image cycle - go through every image and write it to schedule's temp directory */
          foreach( (array) $photos as $image_count => $photo ) {

            if( $import['limit_images'] && $image_count == $import['limit_images']  ) {
              break;
            }

            if( !preg_match( '/^image/', $photo[ 'Content-Type' ] ) ) {
              continue;
            }

            try{

              if( !$photo['Data'] ) {
                Throw new exception( sprintf( __( 'Could not save image saved image - empty file returned by server.', 'wpp' ) ) );
              }

              $filetype = preg_split( "%/%", $photo['Content-Type'] );
              $filename = $image_directory . '/' . $row[$rets_pk] . '_' . ( $image_count + 1 ) . '.' . $filetype[1];

              //** Write image data to file */
              @file_put_contents( $filename, $photo['Data'] );

              if( is_file( $filename) ) {

                $this_image_size = @getimagesize( $filename );

                //** Check if image is valid, delete if not, and log message if detail is on*/
                if( !$this_image_size ) {

                  if( @unlink( $new_file_path ) && $import['log_detail'] == 'on' ) {
                    class_wpp_property_import::maybe_echo_log( sprintf( __( 'Image %1s downloaded, but appears corrupt - deleting.', 'wpp' ), $image ) );
                  }

                  continue;
                }

                //** If minimum width or height are set, we check them here, and delete image if does  not meet quality standards */
                if( $import['min_image_width'] > 0 && ( $this_image_size[0] < $import['min_image_width'] ) ) {
                  $image_size_fail = true;
                }

                if( $import['max_image_height'] > 0 && ( $this_image_size[1] < $import['max_image_height'] ) ) {
                  $image_size_fail = true;
                }

                if( $image_size_fail ) {

                  if( @unlink( $filename ) && $import['log_detail'] == 'on' ) {
                    class_wpp_property_import::maybe_echo_log( sprintf( __( 'Image %1s downloaded, but image size failed quality standards - deleting.', 'wpp' ), $image ) );
                    $wpp_import_result_stats['quality_control']['skipped_images']++;
                  }

                  continue;
                }

                //** @todo Add image size validation */
                $valid_images++;

                $saved_image_file_sizes += filesize( $filename );
                $saved_image_dimensions[] = $this_image_size;

                $row['wpp_gallery'][ 'image' . $image_count ][ 'path' ] = $filename;

                if( $import['log_detail'] == 'on' ) {
                  //class_wpp_property_import::maybe_echo_log( sprintf( __( 'Saved image: %1s for %2s (%3s).', 'wpp' ), $filename, $import['rets_pk'], $row[ $rets_pk ] ) );
                }

              } else {
                Throw new exception( "Could not save image {$row[$rets_pk]} to {$filename}. ");
              }

            } catch( Exception $e ){
              class_wpp_property_import::maybe_echo_log( $e->getMessage() );
            }

          }

          if( $photos && $import['log_detail'] == 'on' ) {
            class_wpp_property_import::maybe_echo_log( sprintf( __( 'Downloaded %1s image(s) for %2s #%3s.', 'wpp' ), $valid_images, $import['rets_pk'], $row[ $rets_pk ] ) );
          }

          $processed_properties[ $row[$rets_pk] ] = true;

          //** Write row data, with formatted image data, back to $xml object */
          write( $xml, array(
            'ROW' => $row
          ));

          $row_count++;

        } /** End of RETS $search cycle */

        if( is_array( $total_rets_images ) ) {
          $total_rets_images = array_sum( $total_rets_images );
        }

        if( is_array( $processed_properties ) ) {
          $processed_properties = count( $processed_properties );
        }

        if( is_array( $saved_image_dimensions ) ) {

          foreach( $saved_image_dimensions as $image_size ) {
            $average_image_width[] = $image_size[0];
            $average_image_height[] = $image_size[1];
          }

          $average_image_width = round( array_sum( $average_image_width ) / count( $average_image_width ) );
          $average_image_height = round( array_sum( $average_image_height ) / count( $average_image_height ) );

          if( $average_image_width || $average_image_height ) {
            $average_size = " and average image size: {$average_image_width}px by {$average_image_height}px ";
          }

        }

        if( $saved_image_file_sizes ) {
          $total_image_size = @class_wpp_property_import::format_size( $saved_image_file_sizes );
        }

        $total_rets_images = count( $saved_image_dimensions );

        class_wpp_property_import::maybe_echo_log( "Initial RETS cycle complete, processed {$processed_properties} properties, and downloaded {$total_rets_images} images adding up to {$total_image_size} {$average_size}." );
        $wpp_import_result_stats[] = "Found {$processed_properties} properties and {$total_rets_images} images adding up to {$total_image_size} {$average_size} in RETS feed.";

        $xml->endElement();
        $str = $xml->outputMemory( true );
        $rets->FreeResult( $search );

        return array( 'body' => $str );

      } catch( Exception $e ){
        die( json_encode( array( 'success' => 'false', 'message' => $e->getMessage() ) ) );
      }

    } else {

      /** Only connect if we aren't a stepping element */
      if( !isset( $_REQUEST['stepping_element'] ) ) {

        $url_array = parse_url( $url );

        if( !empty( $url_array['query'] ) ) {
          parse_str( $url_array['query'], $newvars );
        }

        if( $method == 'post' && count( $newvars ) && !empty( $newvars ) ) {
            $return = wp_remote_post( $url, array( 'timeout' => 120, 'body' => array( 'request' => serialize( $newvars ) ) ) );
        } else {
          $return = wp_remote_get( $url, array( 'timeout' => 120 ) );
        }
        //** Check if data is JSON or XML */
        if( is_wp_error( $return ) ) {
          return $return;
        } else {
          $maybe_json = WPP_F::json_to_xml( $return['body'] );
        }

        //** If json_to_xml() returns something then data was in JSON, but is now converted into XML */
        if( $maybe_json ) {
          $return['body'] = $maybe_json;
        }

        /** Write our cached file */
        if( isset( $_REQUEST['stepping'] ) ){
          file_put_contents( WPP_Path."cache/".$data['wpp_property_import']['hash'].".xml", $return['body'] );
        }

      } else {
        $str = file_get_contents( WPP_Path."cache/".$data['wpp_property_import']['hash'].".xml" );
        $return['body'] = $str;
      }

      return $return;
    }

  }


  /**
  * Attaches an image from url to specified post
  *
  * @args passed via $settings: $schedule_settings
  *
  * @todo Add validation early on that passed $image is an image
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function attach_image( $settings = false ) {
    global $wpp_property_import, $wpdb;

    if( !$settings ) {
      return false;
    }

    $wp_upload_dir = wp_upload_dir();

    $image_request_method = false;

    //** Extra image import settings */
    extract( $settings );

    $image = trim( $image );

    if ( !( ( $uploads = wp_upload_dir( current_time( 'mysql' ) ) ) && false === $uploads['error'] ) ) {
      return false; // upload dir is not accessible
    }

    //** Figure out if this file is local, or remote based off the URL */

    //$local_file = ( stripos( 'http://', $image ) === false || stripos( 'https://', $image ) === false ) ? true : false;
    $local_file = ( preg_match( '%^https?://%i', $image ) ? false : true );

    //** Will break out URL or Path properly. File filename cannot be generated for whatever reason, we create a random one. */
    $filename  = strlen( basename( $image ) ) === 0 ? basename( $image ) : rand( 1000000, 9999999 );

    // Create md5 hash for the new image, to see if it already exists */
    $hash_image = @md5_file( $image );

    //** Fix filename if neither a JPG or a GIF extension can be found */
    if( !strpos( strtolower( $filename ), '.jpeg' ) &&  !strpos( strtolower( $filename ), '.jpg' ) && !strpos( !strtolower( $filename ), '.gif' ) ) {
      // class_wpp_property_import::maybe_echo_log( 'Creating random filename for image.' );
      $filename = rand( 100000000, 999999999 ) . '.jpg';
    }

    //** Create directory structure if it isn't there already */
    $import_directory = class_wpp_property_import::create_import_directory( array( 'post_id' => $post_id ) );

    if( $import_directory['post_dir'] ) {
      $property_directory = $import_directory['post_dir'];
    }

    if( !is_dir( $property_directory ) ) {
      class_wpp_property_import::maybe_echo_log( "Unable to create image directory: {$property_directory}." );
      return false;
    }

    //** Update uploads path for our unique file storage structure */
    $new_file_path = trailingslashit( $property_directory ) . wp_unique_filename( $property_directory, $filename );

    //** If do_not_check_existance is passed, we skip this step */
    if( !$do_not_check_existance ) {

      $file_exists = $wpdb->get_row( $wpdb->prepare( "SELECT ID, guid, post_date, post_parent FROM {$wpdb->posts} WHERE post_content_filtered = '". $hash_image ."' AND post_parent = %d LIMIT 1", $post_id ) );

      if( $file_exists && $post_id == $file_exists->post_parent ) {
        do_action( 'wpp_xml_import_attach_image', $post_id, $image, $file_exists->ID, $data );

        return array(
          'thumb_id' => $file_exists->ID,
          'action' => 'image_exists'
        );

      }

      if( !empty( $file_exists ) ) {
        $uploads_old = wp_upload_dir( $file_exists->post_date );
        $old_file = pathinfo( $file_exists->guid );
        $old_file_size = @filesize( $uploads_old['path'].'/'.$old_file['basename'] );
        $new_file_size = intval( class_wpp_property_import::get_remote_file_size( $image,false ) );

        if( ( $old_file_size == $new_file_size ) && ( intval( $file_exists->post_parent ) == intval( $post_id ) ) ) {
          return false;
        } else if( $old_file_size == $new_file_size ) {
          wp_delete_attachment( $post_id,true );
        }
      }

    } /* end do_not_check_existance */


    //** Frist method of getting images */
    if( $local_file ) {

      //** If URL appears to be a path, we load contents, but what if it's not there? */
      if( file_exists( $image ) ) {
        $content = file_get_contents( $image );
        $this_image_size = @getimagesize( $image );
      } else {
        //class_wpp_property_import::maybe_echo_log( sprintf( __( 'Listing image  ( ' .  $image . ' )  appears to be local, but could not be accessed.', 'wpp' ), $image ) );
        return;
      }

      $image_request_method = __( 'on disk using file_get_contents()','wpp' );

    } else {

      if( $schedule_settings['log_detail'] == 'on' ) {
        class_wpp_property_import::maybe_echo_log( sprintf( __( 'Attempting to get image  ( ' .  $image . ' )  using wp_remote_get()', 'wpp' ), $image ) );
      }

      $image_request = wp_remote_get( preg_replace('~\s~','%20', $image), array( 'timeout' => 10 ) );

      if( is_wp_error( $image_request ) ) {
        class_wpp_property_import::maybe_echo_log( "Unable to get image ( {$image} ) : " . (!empty($image_request))?$image_request->get_error_message():'' );
        return;
      }

      $content = $image_request['body'];

      //** By now we have tried all possible venues of download the image */
      if ( empty( $content ) ) {
        class_wpp_property_import::maybe_echo_log( sprintf( __( 'Unable to get image %1s using the %2s method.', 'wpp' ), $image, $image_request_method ) );
        return false;
      }

    }

    //** Save the new image to disk */
    file_put_contents( $new_file_path, $content );
    unset($content);

    $this_image_size = @getimagesize( $new_file_path );

    //** Check if image is valid, delete if not, and log message if detail is on*/
    if( !$this_image_size ) {

      if( $schedule_settings['log_detail'] == 'on' ) {
        class_wpp_property_import::maybe_echo_log( sprintf( __( 'Image %1s corrupted - skipped.', 'wpp' ), $image ) );
      }

      @unlink( $new_file_path );

      return false;
    }

    //** If minimum width or height are set, we check them here, and delete image if does  not meet quality standards */
    if(
      ( $schedule_settings['min_image_width'] > 0 && ( $this_image_size[0] < $schedule_settings['min_image_width'] ) ) ||
      ( $schedule_settings['max_image_height'] > 0 && ( $this_image_size[1] < $schedule_settings['max_image_height'] ) )
    ){
      $image_size_fail = true;
    }

    if( $image_size_fail ) {

      if( $schedule_settings['log_detail'] == 'on' ) {
        class_wpp_property_import::maybe_echo_log( sprintf( __( 'Image %1s downloaded, but image size failed quality standards - deleting.', 'wpp' ), $image ) );
        $wpp_import_result_stats['quality_control']['skipped_images']++;
      }

      if ($local_file){
        @unlink( $new_file_path );
      }

      return false;
    }

    /** Try to remove the old one, if it still exists */
    if( !preg_match( '/^http/', $image ) ){
      unlink( $image );
    }

    apply_filters( 'wpp_xi_image_save', $new_file_path, $settings );

    //** Bail if it didn't work for some reason */
    if ( ! file_exists( $new_file_path ) ) {
      class_wpp_property_import::maybe_echo_log( sprintf( __( 'Unable to save image %1s.', 'wpp' ), $image ) );
      return false;
    }

    // Set correct file permissions
    $stat = stat( dirname( $new_file_path ) );

    chmod( $new_file_path, 0644 );

    // get file type
    $wp_check_filetype = wp_check_filetype( $new_file_path );

    // No file type! No point to proceed further
    if ( ( !$wp_check_filetype['type'] || !$wp_check_filetype['ext'] ) && !current_user_can( 'unfiltered_upload' ) ) {
      class_wpp_property_import::maybe_echo_log( "Image saved to disk, but some problem occured with file type." );
      return false;
    }

    include_once  ABSPATH . 'wp-admin/includes/image.php';

    // use image exif/iptc data for title and caption defaults if possible
    if ( $image_meta = wp_read_image_metadata( $new_file_path ) ) {
      if ( trim( $image_meta['title'] ) )
        $title = $image_meta['title'];
      if ( trim( $image_meta['caption'] ) )
        $post_content = $image_meta['caption'];
    }

    // Compute the URL
    $url = $uploads['baseurl'] . "/wpp_import_files/$post_id/$filename";

    $attachment = array(
      'post_mime_type' => $wp_check_filetype['type'],
      'guid' => $url,
      'post_name' => 'wpp_i_' . time() . '_' . rand( 10000,100000 ),
      'post_parent' => $post_id,
      'post_title' => ( $title ? $title : 'Property Image' ),
      'post_content' => ( $post_content ? $post_content : '' ),
      'post_content_filtered' => $hash_image
    );

    $thumb_id = wp_insert_attachment( $attachment, $new_file_path, $post_id );

    if ( !is_wp_error( $thumb_id ) ) {
      update_post_meta( $thumb_id, 'wpp_imported_image', true );

      //** Resizes images.  *//
      if( $regenerate_thumbnails ) {
        wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file_path ) );
      }

      do_action( 'wpp_xml_import_attach_image', $post_id,$image, $thumb_id, $data );
    }

    return array(
      'thumb_id' => $thumb_id,
      'action' => 'image_downloaded',
      'image_size' => filesize( $new_file_path )
    );

  }



  /**
  * Cycle through all properties with parent_gpid and update post_parent variable to mach
  *
  * @todo This seems to run every time regardles of if there were any parent/child relationships in the first place.
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc.
  */
  function reassociate_parent_ids() {
    global $wpdb;

    class_wpp_property_import::maybe_echo_log( "Beginning parent IDs association." );

    //** Find properties that have a parent GPID. Get IDs and GPIDs of all properties that have parent_gpid meta_key value *//
    $orphans = $wpdb->get_results( "SELECT post_id, meta_value as gpid FROM {$wpdb->postmeta} WHERE meta_key = 'parent_gpid' AND meta_value IS NOT NULL" );

    if( empty( $orphans ) ) {
      //** No orphan properties *//
      class_wpp_property_import::maybe_echo_log( "No orphan properties - association stopped." );
      return;
    }

    //** Find properties' parents by GPID. *//
    foreach( $orphans as $orphan ) {
      $post_parent = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpp_gpid' AND meta_value = %s LIMIT 0, 1", $orphan->gpid ) );

      if( $post_parent ) {
        //** Parent found *//
        $wpdb->update( $wpdb->posts, array( 'post_parent' => $post_parent ), array( 'ID' => $orphan->post_id ) );
        class_wpp_property_import::maybe_echo_log( "Associated child {$orphan->post_id} with parent {$post_parent}." );
      }

    }

    class_wpp_property_import::maybe_echo_log( "Parent ID association complete." );


  }

  /**
  * Gets remote file size from url
  *
  * Copyright 2010 - 2012 Usability Dynamics, Inc. <info@usabilitydynamics.com>
  *
  */
  function get_remote_file_size( $url, $readable = true ){
     $parsed = parse_url( $url );
     $host = $parsed["host"];
     $fp = @fsockopen( $host, 80, $errno, $errstr, 20 );
     if( !$fp ) return false;
     else {
       @fputs( $fp, "HEAD $url HTTP/1.1\r\n" );
       @fputs( $fp, "HOST: $host\r\n" );
       @fputs( $fp, "Connection: close\r\n\r\n" );
       $headers = "";
       while( !@feof( $fp ) )$headers .= @fgets ( $fp, 128 );
     }
     @fclose ( $fp );
     $return = false;
     $arr_headers = explode( "\n", $headers );
     foreach( $arr_headers as $header ) {
        // follow redirect
        $s = 'Location: ';
        if( substr( strtolower ( $header ), 0, strlen( $s ) ) == strtolower( $s ) ) {
          $url = trim( substr( $header, strlen( $s ) ) );
          return get_remote_file_size( $url, $readable );
        }

        // parse for content length
       $s = "Content-Length: ";
       if( substr( strtolower ( $header ), 0, strlen( $s ) ) == strtolower( $s ) ) {
         $return = trim( substr( $header, strlen( $s ) ) );
         break;
       }
     }
     if( $return && $readable ) {
        $size = round( $return / 1024, 2 );
        $sz = "KB"; // Size In KB
        if ( $size > 1024 ) {
          $size = round( $size / 1024, 2 );
          $sz = "MB"; // Size in MB
        }
        $return = "$size $sz";
     }
     return $return;
  }

  /**
   * Gets data from cached source if it exists
   *
   *
   * @returns false on failure, XML in SimpleXMLElement format if data is there.
   */
  function get_cached_source( $schedule_id, $source_type = false ) {

    if ( !( ( $uploads = wp_upload_dir( current_time( 'mysql' ) ) ) && false === $uploads['error'] ) ) {
      return false; // upload dir is not accessible
    }

    if( $source_type ) {
      $source_type = $source_type . '_';
    }

    $cache_file = $uploads['basedir']."/wpp_import_files/temp/{$schedule_id}/{$source_type}cache.xml";

    //** Check if  a source_cache file exists and is not empty */
    if( file_exists( $cache_file ) && filesize( $cache_file ) ) {

      $xml_data = file_get_contents( $cache_file );

      $result['time'] = filemtime( $cache_file );
      $result['xml_data'] = $xml_data;

      return $result;

    }


    return false;


  }


  /**
   * This function creates the cached image directory
   *
   *
   * @param array $args If passed, creates a folder in the temp directory
   * @returns false on failure, directory path if known
   */
  function create_import_directory( $args = false ) {

    if ( !( ( $uploads = wp_upload_dir( current_time( 'mysql' ) ) ) && false === $uploads['error'] ) ) {
      return false; // upload dir is not accessible
    }

    //** The base directory all the other files and directories will be in */
    $base_dir = $uploads['basedir'] . '/wpp_import_files';
    $base_url = $uploads['baseurl'] . '/wpp_import_files';

    //** Check if directory is there, or create it and chmod it, for true */
    if( is_dir( $base_dir ) || ( mkdir( $base_dir ) && chmod( $base_dir, 0755 ) ) ) {
      $exists['base_dir'] = $base_dir;
      $exists['base_url'] = $base_url;
    }

    //** Create a new directory if we have too many objects in root one */
    $sub_folder = 1;
    while( count( @scandir( $exists['base_dir'] . '/' . $sub_folder ) ) > 500 ) {
       $sub_folder++;
    }

    $current_sub_dir =$exists['base_dir'] . '/' . $sub_folder;
    $current_sub_url =$exists['base_url'] . '/' . $sub_folder;

    //** Create directory structure if it isn't there already */
    if( is_dir( $current_sub_dir ) || ( mkdir( $current_sub_dir ) && chmod( $current_sub_dir, 0755 ) ) ) {
      $exists['current_sub_dir'] = $current_sub_dir;
      $exists['current_sub_url'] = $current_sub_url;
    }

    $generic_temp_dir = $exists['base_dir'] . '/temp';
    $generic_temp_url = $exists['base_url'] . '/temp';

    //** Create a generic temporary directory */
    if( is_dir( $generic_temp_dir ) || ( mkdir( $generic_temp_dir )  && chmod( $generic_temp_dir, 0755 ) ) ) {
      $exists['generic_temp_dir'] = $generic_temp_dir;
      $exists['generic_temp_url'] = $generic_temp_url;
    }


    if( $args['ad_hoc_temp_dir'] ) {

      $ad_hoc_temp_dir = $exists['generic_temp_dir'] . '/' . $args['ad_hoc_temp_dir'];
      $ad_hoc_temp_url = $exists['generic_temp_url'] . '/' . $args['ad_hoc_temp_dir'];

      if( is_dir( $ad_hoc_temp_dir ) || ( mkdir( $ad_hoc_temp_dir ) && chmod( $ad_hoc_temp_dir, 0755 ) ) ) {
        $exists['ad_hoc_temp_dir'] = $ad_hoc_temp_dir;
        $exists['ad_hoc_temp_url'] = $ad_hoc_temp_url;
      }

    }

    if( $args['post_id'] ) {

      $post_dir = $current_sub_dir . '/' . $args['post_id'];
      $post_url = $current_sub_url . '/' . $args['post_id'];

      if( is_dir( $post_dir ) || ( mkdir( $post_dir ) && chmod( $post_dir, 0755 ) ) ) {
        $exists['post_dir'] = $post_dir;
        $exists['post_url'] = $post_url;
      }

    }

    if( is_array( $exists ) ) {
      return $exists;
    }

    return false;


  }

  /*
   * Called during rule processing for all single values.
   *
   * @params array ( value, rule_attribute, schedule_settings )
   */
  function format_single_value( $data ) {

    $to_skip_attributes = array( 'images', 'featured-image' );
    $original_value = $data['value'];
    $schedule_settings = $data['schedule_settings'];

    //** Certain fields should be skipped because they will not use any text formatting */
    if( in_array( $data['rule_attribute'], $to_skip_attributes ) ) {
      return $data['value'];
    }

    //* Property type must be a slug */
    if( $data['rule_attribute'] == 'property_type' ) {
      return UD_F::create_slug( $data['value'], array( 'separator' => '_' ) );
    }

    //** If caps lock fixing is enabled, and this string is ALL caps */
    if( isset( $data['schedule_settings']['fix_caps'] ) && ( $data['schedule_settings']['fix_caps'] == 'on' && ( strtoupper( $data['value'] ) == $data['value'] ) ) ) {
      $data['value'] = ucwords( strtolower( $data['value'] ) );
    }

    //** Attempt to remove any formatting */
    if( $data['schedule_settings']['force_remove_formatting'] == 'on' ) {
      $data['value'] = strip_tags( $data['value'] );
    }

    $data['value'] = str_replace( '&nbsp;', '&', $data['value'] );

    return $data['value'];

  }


/**
  * Email notification system, for using from cron.
  *
  * @version 2.5.6
  */
  function email_notify( $message_text, $short_text = false ) {
    global $wpdb;

    //** Try to get custom WPP email. If not, the default admin_email will work. */
    if( !$notification_email = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wpp_importer_cron_email'" ) ) {
      $notification_email = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'admin_email'" );
    }

    //** Need to get the domain from, DB since $_SERVER is not available in cron */
    $siteurl = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );
    $domain = parse_url( $siteurl, PHP_URL_HOST );

    $subject = 'Update from XML Importer' . ( $short_text ? ': ' . $short_text : '' );
    $headers = 'From: "XML Importer" <xml_importer@'.$domain.'>';

    //$message[] = "Update from XML Importer:\n";
    $message[] = '<div style="font-size: 1.6em;margin-bottom: 5px;">XML Importer: '.$short_text.'</div><div style="font-size: 1em;color:#555555;">' . $message_text . '</div>';

    add_filter( 'wp_mail_content_type',create_function( '', 'return "text/html";' ) );

    if( wp_mail( $notification_email,$subject, implode( '', $message ), $headers ) ) {
      return true;
    }

    return false;

  }


/**
  * Convert bytes to a more appropriate format.
  *
  * @version 2.5.6
  */
  function format_size( $size ) {
    $sizes = array( " Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB" );
    if ( $size == 0 ) { return( 'n/a' ); } else {
    return ( round( $size/pow( 1024, ( $i = floor( log( $size, 1024 ) ) ) ), 2 ) . $sizes[$i] ); }
  }


  /**
   * Analyzes source feed after it has been converted into XML.
   *
   * @version 3.0.0
   */
  function analyze_feed( $common_tag_name = false, $xml ) {

    $root_element_parts = explode( '/', $data['wpp_property_import']['root_element'] );
    $common_tag_name = end( $root_element_parts );

    $query = "//{$common_tag_name}/*[not( * )]";

    /** Get all unique tags */
    $isolated_tags = @$xml->xpath( $query );

    $matched_tags = array();

    if( is_array( $isolated_tags ) ) {

      foreach ( $isolated_tags as $node ) {
        $matched_tags[$node->getName()] = true;
      }

      /** Isolate tag names in array */
      $matched_tags = array_keys( $matched_tags );

    }

    return is_array( $matched_tags ) ? $matched_tags : false;

  }

  /**
   * Keep the MySQL Connection alive (and hope).
   *
   * @since 3.2.1
   * @author potanin@UD
   */
  function keep_hope_alive() {
    global $wpdb;

    $wpdb->query( "SELECT 1" );

  }


}

/**
 * The import class for Google Spreadsheets
 *
 * @version 1.0
 */
class gc_import {
  /** Class variable for gdata array - it contains all the gdata variables required */
  var $gdata = array();

  /*
   * This function parses a URL for the Google Spreadsheet Key
   * @since 1.0
   * @param string $url The spreadsheet URL
   * @return mixed The spreadsheet key or false if not a valid url
   */
  function parse_spreadsheet_key( $url ){
    /** Example URL: https://spreadsheets.google.com/ccc?key=0AmNnyiAqu-JBdDhRMG16a09MN3d5OXJIcUR4M3o4a3c&hl=en#gid=0 */
    if( !preg_match( "/^.*key=([^#&]*)/i", $url, $t ) ){
      throw new Exception( "Invalid Google Spreadsheet URL. Remember to copy and paste directly from your address bar when viewing a Google Spreadsheet." );
    }
    return( $t[1] );
  }


  /*
   * This function parses a URL for the Google worksheet ID
   * @since 1.0
   * @param string $url The spreadsheet URL
   * @return string The worksheet ID
   */
  function parse_worksheet_key( $url ){
    /** Example URL: https://spreadsheets.google.com/feeds/worksheets/0AmNnyiAqu-JBdDhRMG16a09MN3d5OXJIcUR4M3o4a3c/private/full/od6 */
    if( !preg_match( "/^.*\/(.*)$/i", $url, $t ) ){
      throw new Exception( "Invalid Google Spreadsheet Worksheet key." );
    }
    return( $t[1] );
  }

  /*
   * This function is an funcition that takes a spreadsheet and grabs the worksheets in it
   * @param string $url The URL of the spreadsheet
   * @return mixed An assoc. array of worksheet and ID's / Or false upon error
   * @since 1.0
   */
  function get_worksheets( $url ){
    try{
      /** Parse the key */
      $spreadsheet_key = $this->parse_spreadsheet_key( $url );

      /** Connect to Gdata service */
      $this->gdata_connect();

      /** Setup the query */
      $query = new Zend_Gdata_Spreadsheets_DocumentQuery();
      $query->setSpreadsheetKey( $spreadsheet_key );
      $feed = $this->gdata['ss_service']->getWorksheetFeed( $query );

      /** Build the final array */
      $ret = array();
      foreach( $feed->entries AS $entry ){
        $title = $entry->getTitle();
        $ret[$this->parse_worksheet_key( $entry->getId() )] = $title->__toString();
      }
      return( $ret );
    } catch( Exception $e ){
      $err = $e->getMessage();
      return false;
    }
  }

  /**
   * This funciton uses the locally stored gdata information to connect to the service, we assume
   * the try/catch block is outside of this function
   * @since 1.0
   * @param string $user
   * @param string $pass
   * @return boolean True or false based upon connection
   */
  function gdata_connect( $user = "", $pass = "" ){
    if( empty( $user ) || empty( $pass ) ) return false;
    /** Connect to the Gdata service */
    if( !isset( $this->gdata['ss_service'] ) || empty( $this->gdata['ss_service'] ) ){
      $this->gdata['service'] = Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
      $this->gdata['client'] = Zend_Gdata_ClientLogin::getHttpClient( $user, $pass, $this->gdata['service'] );
      $this->gdata['ss_service'] = new Zend_Gdata_Spreadsheets( $this->gdata['client'] );
      return true;
    }
  }

  /**
   * This fuction is for debugging purposes, it returns the fieldnames
   * @since 1.0
   * @param string $url The URL of the spreadsheet
   * @param string $ws_id The ID of the worksheet
   * @return mixed False if failure, array if success
   */
  function get_field_names( $url, $ws_id ){
    try{
      $this->gdata_connect();
      $fields = array();

      //Setup the query
      $query = new Zend_Gdata_Spreadsheets_ListQuery();
      $query->setSpreadsheetKey( $this->parse_spreadsheet_key( $url ) );
      $query->setWorksheetId( $ws_id );
      $listFeed = $this->gdata['ss_service']->getListFeed( $query );

      $rowData = $listFeed->entries[0]->getCustom();
      foreach( $rowData as $customEntry ) $fields[] = $customEntry->getColumnName();

      return $fields;
    } catch( Exception $e ){
      $err[] = $e->getMessage();
      return false;
    }
  }
}
