<?php 
  class ewwwngg {

  static $plugins_ok = true; 

  function ewwwngg() {
    add_filter( 'ngg_manage_images_columns', array( &$this, 'ewww_manage_images_columns' ) );
    add_action( 'ngg_manage_image_custom_column', array( &$this, 'ewww_manage_image_custom_column' ), 10, 2 );
    add_action( 'ngg_added_new_image', array( &$this, 'ewww_added_new_image' ) );
  }

  /* ngg_added_new_image hook */
  function ewww_added_new_image( $image ) {
//    self::ewww_check_support();

    global $wpdb;
    $q = $wpdb->prepare( "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d LIMIT 1", $image['galleryID'] );
    $gallery_path = $wpdb->get_var($q);

    if ( $gallery_path ) {
      $file_path = trailingslashit($gallery_path) . $image['filename'];
      $res = ewww_image_optimizer(ABSPATH . $file_path);
      nggdb::update_image_meta($image['id'], array('ewww_image_optimizer' => $res[1]));
    }
  }

  /* ngg_manage_images_columns hook */
  function ewww_manage_images_columns( $columns ) {
//    self::ewww_check_support();
    $columns['ewww_image_optimizer'] = 'Image Optimizer';
    return $columns;
  }

  /* ngg_manage_image_custom_column hook */
  function ewww_manage_image_custom_column( $column_name, $id ) {
//    self::ewww_check_support();
    
    if( $column_name == 'ewww_image_optimizer' ) {    
      $meta = new nggMeta( $id );
      $status = $meta->get_META( 'ewww_image_optimizer' );
      if ( !$status || empty( $status ) ) {
        echo 'Not processed';
        // TODO: allow manual re-smushing
      } else {
        echo $status;
      }
    }
  }

  /* ensure NextGEN Gallery and WP Smush.it are installed */
  static function ewww_check_support() {
    if (self::$plugins_ok) {
      return true;
    }

/*    if ( !class_exists( 'nggdb' ) ) {
      wp_die("
        <h2>NextGEN Gallery Integration Error</h2>
        <p>It appears that the NextGEN Gallery plugin isn't installed or activated.</p>
        <p>Please install NextGEN Gallery</p>
      ");
    }*/

/*    if ( !function_exists( 'ewww-image-optimizer' ) ) {
      wp_die("
        <h2>WP Smush.it NextGEN Gallery Integration Error</h2>
        <p>It appears that the WP Smush.it plugin isn't installed or activated.</p>
        <p>Either install WP Smush.it or deactivate the WP Smush.it NextGEN Gallery Integration plugin.</p>
      ");
    }*/
    
    // check the WP Smush.it version number
/*    preg_match( '/\/([\d]+)\.([\d]+)\.([\d]+)/', WP_SMUSHIT_UA, $version );        

    if ( count($version) === 0 || intval($version[1]) < 1 || ( intval($version[1]) === 1 && intval($version[2]) < 5 ) ) {
      wp_die("
        <h2>WP Smush.it NextGEN Gallery Integration Error</h2>
        <p>WP Smush.it version 1.5 or higher is required.</p>
        <p>Either update WP Smush.it or deactivate the WP Smush.it NextGEN Gallery Integration plugin.</p>
      ");
    }*/
    
    // all the unsuccessful checks result in `wp_die` so if we've
    // made it this far we can assume everything is good
    self::$plugins_ok = true;
  }

}

add_action( 'init', 'ewwwngg' );

function ewwwngg() {
	global $ewwwngg;
	$ewwwngg = new ewwwngg();
}

