<?php
/**
 * Required by WordPress.
 *
 * Keep this file clean and only use it for requires.
 */

require_once locate_template('/lib/utils.php');           // Utility functions
require_once locate_template('/lib/init.php');            // Initial theme setup and constants
require_once locate_template('/lib/sidebar.php');         // Sidebar class
require_once locate_template('/lib/config.php');          // Configuration
require_once locate_template('/lib/activation.php');      // Theme activation
require_once locate_template('/lib/cleanup.php');         // Cleanup
require_once locate_template('/lib/nav.php');             // Custom nav modifications
require_once locate_template('/lib/htaccess.php');        // Rewrites for assets, H5BP .htaccess
require_once locate_template('/lib/widgets.php');         // Sidebars and widgets
require_once locate_template('/lib/scripts.php');         // Scripts and stylesheets
require_once locate_template('/lib/custom.php');          // Custom functions



if(!function_exists('property_overview_image')) {
  /**
   * Renders the overview image of current property
   *
   * Used for property_overview to render the overview image based on current query and global $property object
   *
   * @args return, image_type
   *
   * @since 1.17.3
   */
  function property_overview_image($args = '') {
    global $wpp_query, $property;
    $thumbnail_size = $wpp_query['thumbnail_size'];

    $defaults = array(
      'return' => 'false',
      'image_type' => $thumbnail_size,
    );
    $args = wp_parse_args( $args, $defaults );

    /* Make sure that a feature image URL exists prior to committing to fancybox */
    if($wpp_query['fancybox_preview'] == 'true' && !empty($property['featured_image_url'])) {
      $thumbnail_link = $property['featured_image_url'];
      $link_class = "fancybox_image";
    } else {
      $thumbnail_link = $property['permalink'];
    }

    $image = wpp_get_image_link($property['featured_image'], $thumbnail_size, array('return'=>'array'));


    if(!empty($image)) {
      ob_start();
      ?>
      <div class="property_image">
        <a href="<?php echo $property['permalink']; ?>" title="<?php echo $property['post_title'] . ($property['parent_title'] ? __(' of ', 'wpp') . $property['parent_title'] : "");?>"  class="property_overview_thumb">
          <img src="<?php echo $image['link']; ?>" alt="<?php echo $property['post_title'];?>" />
        </a>
      </div>
      <?php
      $html = ob_get_contents();
      ob_end_clean();
    } else {
      $html = '';
    }
    if($args['return'] == 'true') {
      return $html;
    } else {
      echo $html;
    }
  }
}


function the_breadcrumb() {
	if(!is_home()) {
		echo '<nav class="breadcrumb">';
		echo '<a href="'.home_url('/').'">Home </a><i class="icon-double-angle-right"></i>';
		if (is_category() || is_single()) {
			the_category();
			if (is_single()) {
				echo '<a href="'.home_url('/woningaanbod/').'"> Woningaanbod</a>';
				
				echo ' <i class="icon-double-angle-right"></i> ';
				the_title();
			}
		} elseif (is_page()) {
			echo the_title();
		}
		echo '</nav>';
	}
}

function get_custom_field($key, $echo = FALSE) {
	global $post;
	$custom_field = get_post_meta($post->ID, $key, true);
	if ($echo == FALSE) return $custom_field;
	echo $custom_field;
}

add_filter( 'wpcf7_form_class_attr', 'your_custom_form_class_attr' );

function your_custom_form_class_attr( $class ) {
  $class .= ' form-horizontal';
  return $class;
}

function new_excerpt_more( $more ) {
  return '...';
}
add_filter('excerpt_more', 'new_excerpt_more');  // Lees verder verwijderen uit blog

function create_post_type() {  
    register_post_type( 'gm_nieuwbouwtypes',  
        array(  
            'labels' => array(  
                'name' => __( 'Nieuwbouwtypes' ),  
                'singular_name' => __( 'Nieuwbouwtype' ),
                'menu_name' => __('Nieuwbouw'),
                'all_items' => __('Types'),
                'add_new_item' => __('Nieuw Type'),
                'new_item' => __('Nieuw Type'),
                'edit_item' => __('Bewerk Type'),
                'view_item' => __('Bekijk Type'),
                'search_items' => __('Zoek Type'),
                'not_found' => __('Geen Type Gevonden'),
                'not_found_in_trash' => __('Geen Type In Prullenbak')
            ),  
        'public' => true,  
        'menu_position' => 5,
        'menu_icon' =>  'http://www.gouwestadmakelaardij.nl/ontwikkel/wp-content/themes/gouwestadmakelaardij/assets/img/icon_nieuwbouwtypes.png',
        'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions' ),  
        'rewrite' => array('slug' => 'nieuwbouwtypes')  
        )  
    );  
}  
  
add_action( 'init', 'create_post_type' ); // Nieuwbouwtypes toevoegen in de admin

