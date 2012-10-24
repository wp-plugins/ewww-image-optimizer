<?php 
class ewwwngg {
	// pretty sure we don't need this anymore
	//static $plugins_ok = true; 

	/* initializes the nextgen integration functions */
	function ewwwngg() {
		add_filter( 'ngg_manage_images_columns', array( &$this, 'ewww_manage_images_columns' ) );
		add_action( 'ngg_manage_image_custom_column', array( &$this, 'ewww_manage_image_custom_column' ), 10, 2 );
		add_action( 'ngg_added_new_image', array( &$this, 'ewww_added_new_image' ) );
		add_action('admin_action_ewww_ngg_manual', array( &$this, 'ewww_ngg_manual') );
		add_action('admin_menu', array(&$this, 'ewww_ngg_bulk_menu') );
	}

	/* adds the Bulk Optimize page to the tools menu */
	function ewww_ngg_bulk_menu () {
		add_management_page('NextGEN Gallery Bulk Optimize', 'NextGEN Bulk Optimize', 'manage_options', 'ewww-ngg-bulk', array (&$this, 'ewww_ngg_bulk'));
	}
	//TODO: add a bulk optimize action to each gallery (when we have a hook)
	/* ngg_added_new_image hook */
	function ewww_added_new_image( $image ) {
		// query the filesystem path of the gallery from the database
		global $wpdb;
		$q = $wpdb->prepare( "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d LIMIT 1", $image['galleryID'] );
		$gallery_path = $wpdb->get_var($q);
		// if we have a path to work with
		if ( $gallery_path ) {
			// TODO: optimize thumbs (when we have a hook)
			// construct the absolute path of the current image
			$file_path = trailingslashit($gallery_path) . $image['filename'];
			// run the optimizer on the current image
			$res = ewww_image_optimizer(ABSPATH . $file_path, 2, false);
			// update the metadata for the optimized image
			nggdb::update_image_meta($image['id'], array('ewww_image_optimizer' => $res[1]));
		}
	}

	/* Manually process an image from the NextGEN Gallery */
	function ewww_ngg_manual() {
		// check permission of current user
		if ( FALSE === current_user_can('upload_files') ) {
			wp_die(__('You don\'t have permission to work with uploaded files.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		}
		// make sure function wasn't called without an attachment to work with
		if ( FALSE === isset($_GET['attachment_ID'])) {
			wp_die(__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		}
		// store the attachment $id
		$id = intval($_GET['attachment_ID']);
		// retrieve the metadata for the image
		$meta = new nggMeta( $id );
		// retrieve the image path
		$file_path = $meta->image->imagePath;
		// run the optimizer on the current image
		$res = ewww_image_optimizer($file_path, 2, false);
		// update the metadata for the optimized image
		nggdb::update_image_meta($id, array('ewww_image_optimizer' => $res[1]));
		// get the referring page, and send the user back there
		$sendback = wp_get_referer();
		$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
		wp_redirect($sendback);
		exit(0);
	}

	/* function to optimize all images in all NextGEN galleries. many lines are commented out from porting the 'resume' function in anticipation that we'll have a bulk action that lets us bulk optimize arbitrary images. */
	function ewww_ngg_bulk() {
		global $ngg;
		// create a temp file to store our progress
		$progress_file = ABSPATH . $ngg->options['gallerypath'] . "ewww.tmp";
//		$auto_start = false;
		// we aren't going to skip any images (for now)
	        $skip_attachments = false;
		// if we are resuming a previous bulk operation
		if (isset($_REQUEST['resume'])) {
			// retrieve the contents of the temp file
			$progress_contents = file($progress_file);
			// find out which attachment we need to start with
			$last_attachment = $progress_contents[0];
//			$images = unserialize($progress_contents[1]);
//			$auto_start = true;
			// tell the optimizer to skip attachments until we find the right one
			$skip_attachments = true;
		} //else {
			// retrieve a list of all the images in NextGEN
			global $wpdb;
			$images = $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC");
		//}
//		$images_ser = serialize($images);
		?>
		<div class="wrap"><div id="icon-nextgen-gallery" class="icon32"><br /></div><h2>Bulk NextGEN Gallery Optimize</h2>
		<?php
		// if there aren't any images to optimize
		if ( sizeof($images) < 1 ):
			echo '<p>You don’t appear to have uploaded any images yet.</p>';
		else:
			// first time, lets give the user some info
			if ( empty($_POST) ): // instructions page
				?>
				<p>This tool will run all of the images in your NextGEN Galleries through the Linux image optimization programs.</p>
				<p>We found <?php echo sizeof($images); ?> images in your media library.</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'ewww-ngg-bulk', '_wpnonce'); ?>
					<button type="submit" class="button-secondary action">Run all my images through image optimizers</button>
				</form>
<?php
				// see if a previous optimization was interrupted
				if (file_exists($progress_file)):
?>
					<p>It appears that a previous bulk optimization was interrupted. Would you like to continue where we left off?</p>
					<form method="post" action="">
					<?php wp_nonce_field( 'ewww-ngg-bulk', '_wpnonce'); ?>
					<input type="hidden" name="resume" value="1">
					<button type="submit" class="button-secondary action">Resume previous operation.</button>
					</form>
<?php
				endif;
			else: // run the optimization
				// verify some random person isn't running the bulk optimization
				if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-ngg-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
				wp_die( __( 'Cheatin&#8217; eh?' ) );
				}
				// initialize $current, and $started time
				$current = 0;
				$started = time();
				// find out how many images we have
				$total = sizeof($images);
				?>
				<script type="text/javascript">
					document.write('Bulk Optimization has taken <span id="endTime">0.0</span> seconds.');
					var loopTime=setInterval("currentTime()",100);
				</script>
				<?php
				// functions to flush HTML output buffers
				ob_implicit_flush(true);
				ob_end_flush();
				// process each image
				foreach ($images as $id) {
					// give each image 50 seconds (php only, doesn't include any commands issued by exec()
					set_time_limit (50);
					$current++;
					// if this is a resume, see if this is the attachment to continue with
					// if it is, stop skipping attachments
					if (isset($last_attachment)) {
						if ($last_attachment == $id) {$skip_attachments = false;}
					}
					// if we're skipping (during a resume)
					if ($skip_attachments) {
						echo "<p>Skipping $current/$total <br>";
					// if we're doing normal processing
					} else {
						echo "<p>Processing $current/$total: ";
						// get the metadata
						$meta = new nggMeta( $id );
						// output the current image name
						printf( "<strong>%s</strong>&hellip;<br>", esc_html($meta->image->filename) );
						// retrieve the filepath
						$file_path = $meta->image->imagePath;
						// update the temp file with our current status
						file_put_contents($progress_file, "$id");
						// run the optimizer on the current image
						$fres = ewww_image_optimizer($file_path, 2, false);
						// update the metadata of the optimized image
						nggdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
						// output the results of the optimization
						printf( "Full size – %s<br>", $fres[1] );
						// get the filepath of the thumbnail image
						$thumb_path = $meta->image->thumbPath;
						// run the optimization on the thumbnail
						$tres = ewww_image_optimizer($thumb_path, 2, false);
						// output the results of the thumb optimization
						printf( "Thumbnail – %s<br>", $tres[1] );
						// outupt how much time we've spent optimizing so far
						$elapsed = time() - $started;
						echo "Elapsed: $elapsed seconds</p>";
					}
					// flush the HTML output buffers
					@ob_flush();
					flush();
				}
				// all done, delete the temp file
				unlink ($progress_file);
				echo '<p><b>Finished</b></p></div>';	
			endif;
		endif;
	}

	/* ngg_manage_images_columns hook */
	function ewww_manage_images_columns( $columns ) {
		$columns['ewww_image_optimizer'] = 'Image Optimizer';
		return $columns;
	}

	/* ngg_manage_image_custom_column hook */
	function ewww_manage_image_custom_column( $column_name, $id ) {
		// once we've found our custom column
		if( $column_name == 'ewww_image_optimizer' ) {    
			// get the metadata for the image
			$meta = new nggMeta( $id );
			// get the optimization status for the image
			$status = $meta->get_META( 'ewww_image_optimizer' );
			$msg = '';
			// get the file path of the image
			$file_path = $meta->image->imagePath;
			// use getimagesize to find the mimetype
			if(function_exists('getimagesize')){
				$type = getimagesize($file_path);
				if(false !== $type){
					$type = $type['mime'];
				}
			// try mime_content_type to find the mimetype otherwise
			} elseif(function_exists('mime_content_type')) {
				$type = mime_content_type($file_path);
			// otherwise tell the user we just can't work under these conditions
			} else {
				$type = false;
				$msg = '<br>getimagesize() and mime_content_type() PHP functions are missing';
			}
			// retrieve the human-readable filesize of the image
			$file_size = ewww_image_optimizer_format_bytes(filesize($file_path));
			
			$valid = true;
	                switch($type) {
        	                case 'image/jpeg':
					// if jpegtran is missing, tell the user
                	                if(EWWW_IMAGE_OPTIMIZER_JPEGTRAN == false) {
                        	                $valid = false;
	     	                                $msg = '<br>' . __('<em>jpegtran</em> is missing');
	                                }
					break;
				case 'image/png':
					// if the PNG tools are missing, tell the user
					if(EWWW_IMAGE_OPTIMIZER_PNGOUT == false && EWWW_IMAGE_OPTIMIZER_OPTIPNG == false) {
						$valid = false;
						$msg = '<br>' . __('<em>optipng/pngout</em> is missing');
					}
					break;
				case 'image/gif':
					// if gifsicle is missing, tell the user
					if(EWWW_IMAGE_OPTIMIZER_GIFSICLE == false) {
						$valid = false;
						$msg = '<br>' . __('<em>gifsicle</em> is missing');
					}
					break;
				default:
					$valid = false;
			}
			// file isn't in a format we can work with, we don't work with strangers
			if($valid == false) {
				print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
				return;
			}
			// if we have a valid status, display it, the image size, and give a re-optimize link
			if ( $status && !empty( $status ) ) {
				echo $status;
				print "<br>Image Size: $file_size";
				printf("<br><a href=\"admin.php?action=ewww_ngg_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			// otherwise, give the image size, and a link to optimize right now
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
// initialize the plugin and the class
add_action( 'init', 'ewwwngg' );
add_action('admin_print_scripts-tools_page_ewww-ngg-bulk', 'ewww_image_optimizer_scripts' );

function ewwwngg() {
	global $ewwwngg;
	$ewwwngg = new ewwwngg();
}

