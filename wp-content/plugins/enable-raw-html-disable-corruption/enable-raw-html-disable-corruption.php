<?php
/*
Plugin Name: Enable RAW HTML - Disable Corruption
Plugin URI: http://netaction.de/enable-raw-html-and-execute-php-in-wordpress/
Description: Disables filters wpautop, convert_chars and wptexturize
Version: 0.3
Author: Thomas (netAction) Schmidt
Author URI: http://netaction.de
*/

remove_filter('the_content', 'wpautop');
remove_filter('the_content', 'convert_chars');
remove_filter('the_content', 'wptexturize');


// Disable the filters for the summarys too:
//remove_filter('the_excerpt', 'wpautop');

// Enable the filter to execude < ?php echo "Hello!" ? >
// add_filter('the_content', 'parse_as_php');

function parse_as_php($content) {
  ob_start();
  eval (" ?>".$content."<?php ");
  if($args['debug'] == 1){
    $content =(htmlspecialchars($content,ENT_QUOTES));
    echo ("<pre>".$content."</pre>");
  }
  return ob_get_clean();
}

?>
