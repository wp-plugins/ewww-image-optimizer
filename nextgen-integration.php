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

/* Manually process an image from the NextGEN Gallery */
function ewww_ngg_manual() {
        if ( FALSE === current_user_can('upload_files') ) {
                wp_die(__('You don\'t have permission to work with uploaded files.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
        }

        if ( FALSE === isset($_GET['attachment_ID'])) {
                wp_die(__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
        }

        $attachment_ID = intval($_GET['attachment_ID']);

        $original_meta = wp_get_attachment_metadata( $attachment_ID );
        $new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_ID );
        wp_update_attachment_metadata( $attachment_ID, $new_meta );

        $sendback = wp_get_referer();
        $sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
        wp_redirect($sendback);
        exit(0);
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
                $msg = '';

		$file_path = $meta->image->imagePath;
                if(function_exists('getimagesize')){
                        $type = getimagesize($file_path);
                        if(false !== $type){
                                $type = $type['mime'];
                        }
                } elseif(function_exists('mime_content_type')) {
                        $type = mime_content_type($file_path);
                } else {
                        $type = false;
                        $msg = '<br>getimagesize() and mime_content_type() PHP functions are missing';
                }
                $file_size = ewww_image_optimizer_format_bytes(filesize($file_path));

                $valid = true;
                switch($type) {
                        case 'image/jpeg':
                                if(EWWW_IMAGE_OPTIMIZER_JPG == false) {
                                        $valid = false;
                                        $msg = '<br>' . __('<em>jpegtran</em> is missing');
                                }
                                break;
                        case 'image/png':
                                if(EWWW_IMAGE_OPTIMIZER_PNG == false) {
                                        $valid = false;
                                        $msg = '<br>' . __('<em>optipng</em> is missing');
                                }
                                break;
                        case 'image/gif':
                                if(EWWW_IMAGE_OPTIMIZER_GIF == false) {
                                        $valid = false;
                                        $msg = '<br>' . __('<em>gifsicle</em> is missing');
                                }
                                break;
                        default:
                                $valid = false;
                }

                if($valid == false) {
                        print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
                        return;
		}
//print_r ($meta);
      if ( $status && !empty( $status ) ) {
        echo $status;
	print "<br>Image Size: $file_size";
                        printf("<br><a href=\"admin.php?action=ewww_ngg_manual&amp;attachment_ID=%d\">%s</a>",
                                $id,
                                __('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
      } else {
                       print __('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
                        print "<br>Image Size: $file_size";
                        printf("<br><a href=\"admin.php?action=ewww_ngg_manual&amp;attachment_ID=%d\">%s</a>",
                                $id,
                                __('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
      }
    }
  }
}

add_action( 'init', 'ewwwngg' );

function ewwwngg() {
	global $ewwwngg;
	$ewwwngg = new ewwwngg();
}

