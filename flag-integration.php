<?php 
class ewwwflag {
	// TODO: add bulk action routine to manage images page
	// TODO: add bulk action routine to manage galleries page (optimized whole galleries at once, nice...)
	/* initializes the flagallery integration functions */
	function ewwwflag() {
		add_filter('flag_manage_images_columns', array(&$this, 'ewww_manage_images_columns'));
		add_action('flag_manage_gallery_custom_column', array(&$this, 'ewww_manage_image_custom_column'), 10, 2);
//		add_action('flag_manage_images_bulkaction', array(&$this, 'ewww_manage_images_bulkaction'));
		add_action('flag_thumbnail_created', array(&$this, 'ewww_added_new_image'));
		add_action('flag_image_resized', array(&$this, 'ewww_added_new_image'));
//		add_action('flag_added_new_image', array( &$this, 'ewww_added_new_image'));
		add_action('admin_action_ewww_flag_manual', array(&$this, 'ewww_flag_manual'));
		add_action('admin_menu', array(&$this, 'ewww_flag_bulk_menu') );
	}

	/* adds the Bulk Optimize page to the menu */
	function ewww_flag_bulk_menu () {
		add_submenu_page('flag-overview', 'FlAG Bulk Optimize', 'Bulk Optimize', 'FlAG Manage gallery', 'flag-bulk-optimize', array (&$this, 'ewww_flag_bulk'));
	}

	/* add bulk optimize action to image management page */
	function ewww_manage_images_bulkaction () {
		echo '<option value="bulk_optimize_images">Bulk Optimize</option>';
	}
	/* flag_added_new_image hook */
	function ewww_added_new_image ($image) {
//		print_r ($image);
//		$meta = flagdb::find_image($image['id']);
		if (isset($image->imagePath)) {
			$res = ewww_image_optimizer($image->imagePath, 3, false, false);
			$tres = ewww_image_optimizer($image->thumbPath, 3, false, true);
			$pid = $image->pid;
			flagdb::update_image_meta($pid, array('ewww_image_optimizer' => $res[1]));
		}
	}

	/* Manually process an image from the gallery */
	function ewww_flag_manual() {
		if ( FALSE === current_user_can('upload_files') ) {
			wp_die(__('You don\'t have permission to work with uploaded files.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		}

		if ( FALSE === isset($_GET['attachment_ID'])) {
			wp_die(__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		}
		$id = intval($_GET['attachment_ID']);
		$meta = new flagMeta( $id );
		$file_path = $meta->image->imagePath;
		$res = ewww_image_optimizer($file_path, 3, false, false);
		flagdb::update_image_meta($id, array('ewww_image_optimizer' => $res[1]));
		$thumb_path = $meta->image->thumbPath;
		ewww_image_optimizer($thumb_path, 3, false, true);
		$sendback = wp_get_referer();
		$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
		wp_redirect($sendback);
		exit(0);
	}

	/* function to optimize all images in all galleries */
	function ewww_flag_bulk() {
		global $flag;
		$progress_file = ABSPATH . $flag->options['galleryPath'] . "ewww.tmp";
		$skip_attachments = false;
		if (isset($_REQUEST['resume'])) {
			$progress_contents = file($progress_file);
			$last_attachment = $progress_contents[0];
			$skip_attachments = true;
		}
		global $wpdb;
		$images = $wpdb->get_col("SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC");
		?>
		<div class="wrap"><div id="icon-upload" class="icon32"><br /></div><h2>GRAND FlAGallery Bulk Optimize</h2>
		<?php
		if ( sizeof($images) < 1 ):
			echo '<p>You don’t appear to have uploaded any images yet.</p>';
		else:
			if ( empty($_POST) ): // instructions page
				?>
				<p>This tool will run all of the images in your Galleries through the Linux image optimization programs.</p>
				<p>We found <?php echo sizeof($images); ?> images in your media library.</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'ewww-flag-bulk', '_wpnonce'); ?>
					<button type="submit" class="button-secondary action">Run all my images through image optimizers</button>
				</form>
				<?php
				// see if a previous optimization was interrupted
				if (file_exists($progress_file)):
?>
				<p>It appears that a previous bulk optimization was interrupted. Would you like to continue where we left off?</p>
                                        <form method="post" action="">
                                        <?php wp_nonce_field( 'ewww-flag-bulk', '_wpnonce'); ?>
                                        <input type="hidden" name="resume" value="1">
                                        <button type="submit" class="button-secondary action">Resume previous operation.</button>
                                        </form>

<?php
				endif;
			else: // run the script
				if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-flag-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
				wp_die( __( 'Cheatin&#8217; eh?' ) );
				}
				$current = 0;
				$started = time();
				$total = sizeof($images);
				ob_implicit_flush(true);
				ob_end_flush();
				foreach ($images as $id) {
					set_time_limit (50);
					$current++;
					if (isset($last_attachment)) {
						if ($last_attachment == $id) {$skip_attachments = false;}
					}
					if ($skip_attachments) {
						echo "<p>Skipping $current/$total <br>";
					} else {
					echo "<p>Processing $current/$total: ";
					$meta = new flagMeta( $id );
					printf( "<strong>%s</strong>&hellip;<br>", esc_html($meta->image->filename) );
					$file_path = $meta->image->imagePath;
					file_put_contents($progress_file, "$id");
					$fres = ewww_image_optimizer($file_path, 3, false, false);
					flagdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
					printf( "Full size – %s<br>", $fres[1] );
					$thumb_path = $meta->image->thumbPath;
					$tres = ewww_image_optimizer($thumb_path, 3, false, true);
					printf( "Thumbnail – %s<br>", $tres[1] );
					$elapsed = time() - $started;
					echo "Elapsed: $elapsed seconds</p>";
					@ob_flush();
					flush();
					}
				}
				unlink($progress_file);	
				echo '<p><b>Finished</b></p></div>';	
			endif;
		endif;
	}

	/* flag_manage_images_columns hook */
	function ewww_manage_images_columns( $columns ) {
		$columns['ewww_image_optimizer'] = 'Image Optimizer';
		return $columns;
	}

	/* flag_manage_image_custom_column hook */
	function ewww_manage_image_custom_column( $column_name, $id ) {
		if( $column_name == 'ewww_image_optimizer' ) {    
			$meta = new flagMeta( $id );
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
                	                if(EWWW_IMAGE_OPTIMIZER_JPEGTRAN == false) {
                        	                $valid = false;
	     	                                $msg = '<br>' . __('<em>jpegtran</em> is missing');
	                                }
					break;
				case 'image/png':
					if(EWWW_IMAGE_OPTIMIZER_PNGOUT == false && EWWW_IMAGE_OPTIMIZER_OPTIPNG == false) {
						$valid = false;
						$msg = '<br>' . __('<em>optipng/pngout</em> is missing');
					}
					break;
				case 'image/gif':
					if(EWWW_IMAGE_OPTIMIZER_GIFSICLE == false) {
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
				printf("<br><a href=\"admin.php?action=ewww_flag_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			} else {
				print __('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				print "<br>Image Size: $file_size";
				printf("<br><a href=\"admin.php?action=ewww_flag_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			}
		}
	}
}

add_action( 'init', 'ewwwflag' );
//add_action('admin_print_scripts-tools_page_flag-bulk-optimize', 'ewww_image_optimizer_scripts' );

function ewwwflag() {
	global $ewwwflag;
	$ewwwflag = new ewwwflag();
}

