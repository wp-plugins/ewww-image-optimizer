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
    $columns['ewww_image_optimizer'] = 'Image Optimizer';
    return $columns;
  }

  /* ngg_manage_image_custom_column hook */
  function ewww_manage_image_custom_column( $column_name, $id ) {
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
}

add_action( 'init', 'ewwwngg' );

function ewwwngg() {
	global $ewwwngg;
	$ewwwngg = new ewwwngg();
}

