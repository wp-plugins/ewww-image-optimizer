<?php 
class ewwwngg {

	static $plugins_ok = true; 

	/* initializes the nextgen integration functions */
	function ewwwngg() {
		add_filter( 'ngg_manage_images_columns', array( &$this, 'ewww_manage_images_columns' ) );
		add_action( 'ngg_manage_image_custom_column', array( &$this, 'ewww_manage_image_custom_column' ), 10, 2 );
		add_action( 'ngg_added_new_image', array( &$this, 'ewww_added_new_image' ) );
		add_action('admin_action_ewww_ngg_manual', array( &$this, 'ewww_ngg_manual') );
		add_action('admin_menu', array(&$this, 'ewww_ngg_bulk_menu') );
	}

	/* adds the Bulk Optimize page to the menu */
	function ewww_ngg_bulk_menu () {
		add_management_page('NextGEN Gallery Bulk Optimize', 'NextGEN Bulk Optimize', 'manage_options', 'ewww-ngg-bulk', array (&$this, 'ewww_ngg_bulk'));
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

		$id = intval($_GET['attachment_ID']);
		$meta = new nggMeta( $id );
		$file_path = $meta->image->imagePath;
		$res = ewww_image_optimizer($file_path);
		nggdb::update_image_meta($id, array('ewww_image_optimizer' => $res[1]));

		$sendback = wp_get_referer();
		$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
		wp_redirect($sendback);
		exit(0);
	}

	/* function to optimize all images in all NextGEN galleries. many lines are commented out from porting the 'resume' function in anticipation that we'll have a bulk action that lets us bulk optimize arbitrary images. */
	function ewww_ngg_bulk() {
		global $ngg;
		$progress_file = ABSPATH . $ngg->options['gallerypath'] . "ewww.tmp";
//		$auto_start = false;
	        $skip_attachments = false;
		if (isset($_REQUEST['resume'])) {
			$progress_contents = file($progress_file);
			$last_attachment = $progress_contents[0];
//			$images = unserialize($progress_contents[1]);
//			$auto_start = true;
			$skip_attachments = true;
		} //else {
			global $wpdb;
			$images = $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC");
		//}
//		$images_ser = serialize($images);
		?>
		<div class="wrap"><div id="icon-nextgen-gallery" class="icon32"><br /></div><h2>Bulk NextGEN Gallery Optimize</h2>
		<?php
		if ( sizeof($images) < 1 ):
			echo '<p>You don’t appear to have uploaded any images yet.</p>';
		else:
			if ( empty($_POST) ): // instructions page
				?>
				<p>This tool will run all of the images in your NextGEN Galleries through the Linux image optimization programs.</p>
				<p>We found <?php echo sizeof($images); ?> images in your media library.</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'ewww-ngg-bulk', '_wpnonce'); ?>
					<button type="submit" class="button-secondary action">Run all my images through image optimizers</button>
				</form>
<?php
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
			else: // run the script
				if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-ngg-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
				wp_die( __( 'Cheatin&#8217; eh?' ) );
				}
				$current = 0;
				$started = time();
				$total = sizeof($images);
				?>
				<script type="text/javascript">
					document.write('Bulk Optimization has taken <span id="endTime">0.0</span> seconds.');
					var loopTime=setInterval("currentTime()",100);
				</script>
				<?php
				ob_implicit_flush(true);
				ob_end_flush();
				foreach ($images as $id) {
					set_time_limit (50);
					$current++;
					if ($last_attachment == $id) {$skip_attachments = false;}
					if ($skip_attachments) {
						echo "<p>Skipping $current/$total <br>";
					} else {
						echo "<p>Processing $current/$total: ";
						$meta = new nggMeta( $id );
						printf( "<strong>%s</strong>&hellip;<br>", esc_html($meta->image->filename) );
						$file_path = $meta->image->imagePath;
						file_put_contents($progress_file, "$id");
						$fres = ewww_image_optimizer($file_path);
						nggdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
						printf( "Full size – %s<br>", $fres[1] );
						$thumb_path = $meta->image->thumbPath;
						$tres = ewww_image_optimizer($thumb_path);
						printf( "Thumbnail – %s<br>", $tres[1] );
						$elapsed = time() - $started;
						echo "Elapsed: $elapsed seconds</p>";
					}
					@ob_flush();
					flush();
				}
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
add_action('admin_print_scripts-tools_page_ewww-ngg-bulk', 'ewww_image_optimizer_scripts' );

function ewwwngg() {
	global $ewwwngg;
	$ewwwngg = new ewwwngg();
}

