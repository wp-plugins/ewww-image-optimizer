<?php 
class ewwwflag {
	/* initializes the flagallery integration functions */
	function ewwwflag() {
		add_filter('flag_manage_images_columns', array(&$this, 'ewww_manage_images_columns'));
		add_action('flag_manage_gallery_custom_column', array(&$this, 'ewww_manage_image_custom_column'), 10, 2);
		add_action('flag_manage_images_bulkaction', array(&$this, 'ewww_manage_images_bulkaction'));
		add_action('flag_manage_galleries_bulkaction', array(&$this, 'ewww_manage_galleries_bulkaction'));
		add_action('flag_manage_post_processor_images', array(&$this, 'ewww_post_processor'));
		add_action('flag_manage_post_processor_galleries', array(&$this, 'ewww_post_processor'));
		add_action('flag_thumbnail_created', array(&$this, 'ewww_added_new_image'));
		add_action('flag_image_resized', array(&$this, 'ewww_added_new_image'));
//		add_action('flag_added_new_image', array( &$this, 'ewww_added_new_image'));
		add_action('admin_action_ewww_flag_manual', array(&$this, 'ewww_flag_manual'));
		add_action('admin_menu', array(&$this, 'ewww_flag_bulk_menu'));
		add_action('admin_enqueue_scripts', array(&$this, 'ewww_flag_bulk_script'));
		add_action('wp_ajax_bulk_flag_init', array(&$this, 'ewww_flag_bulk_init'));
		add_action('wp_ajax_bulk_flag_filename', array(&$this, 'ewww_flag_bulk_filename'));
		add_action('wp_ajax_bulk_flag_loop', array(&$this, 'ewww_flag_bulk_loop'));
		add_action('wp_ajax_bulk_flag_cleanup', array(&$this, 'ewww_flag_bulk_cleanup'));
		register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_flag_resume');
		register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_flag_attachments');
	}

	/* adds the Bulk Optimize page to the menu */
	function ewww_flag_bulk_menu () {
		add_submenu_page('flag-overview', 'FlAG Bulk Optimize', 'Bulk Optimize', 'FlAG Manage gallery', 'flag-bulk-optimize', array (&$this, 'ewww_flag_bulk'));
	}

	/* add bulk optimize action to image management page */
	function ewww_manage_images_bulkaction () {
		echo '<option value="bulk_optimize_images">Bulk Optimize</option>';
	}

	/* add bulk optimize action to gallery management page */
	function ewww_manage_galleries_bulkaction () {
		echo '<option value="bulk_optimize_galleries">Bulk Optimize</option>';
	}

	// Handles the bulk actions POST
	function ewww_post_processor ($bulk = NULL) {
		if (empty($bulk)) {
			// if there is no requested bulk action, do nothing
			if (empty($_REQUEST['bulkaction'])) {
				return;
			}
			// if there is no media to optimize, do nothing
			if (empty($_REQUEST['doaction']) || !is_array($_REQUEST['doaction'])) {
				return;
			}
		}
//		echo "--------------- $bulk ---------------";
		$attachments = get_option('ewww_image_optimizer_bulk_flag_attachments');
		if (count($attachments) < 1) {
			echo '<p>You don’t appear to have uploaded any images yet.</p>';
			return;
		}
		?>
		<div class="wrap"><div id="icon-upload" class="icon32"><br /></div><h2>GRAND FlAGallery Bulk Optimize</h2>
		<?php
		// Retrieve the value of the 'bulk resume' option and set the button text for the form to use
		$resume = get_option('ewww_image_optimizer_bulk_flag_resume');
		if (empty($resume)) {
			$button_text = 'Start optimizing';
		} else {
			$button_text = 'Resume previous bulk operation';
		}
		?>
		<div id="bulk-loading"></div>
		<div id="bulk-progressbar"></div>
		<div id="bulk-counter"></div>
		<div id="bulk-status"></div>
		<div id="bulk-forms"><p>This tool can optimize large batches (or all) of images from your media library.</p>
		<p>We have <?php echo count($attachments); ?> images to optimize.</p>
		<form id="bulk-start" method="post" action="">
			<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
		</form>
		<?php
		if (!empty($resume)):
		?>
			<p>If you would like to start over again, press the <b>Reset Status</b> button to reset the bulk operation status.</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action">Reset Status</button>
			</form>
		<?php
		endif;
		echo '</div></div>';
	}

	// prepares the bulk operation and includes the javascript functions
	function ewww_flag_bulk_script($hook) {
		if ($hook != 'flagallery_page_flag-bulk-optimize' && $hook != 'flagallery_page_flag-manage-gallery')
			return;
		// if there is no requested bulk action, do nothing
		if ($hook == 'flagallery_page_flag-manage-gallery' && empty($_REQUEST['bulkaction'])) {
			return;
		}
		// if there is no media to optimize, do nothing
		if ($hook == 'flagallery_page_flag-manage-gallery' && (empty($_REQUEST['doaction']) || !is_array($_REQUEST['doaction']))) {
			return;
		}
	
		//echo "------------ $hook ----------------";
		$ids = null;
		if (!empty($_REQUEST['reset'])) {
			update_option('ewww_image_optimizer_bulk_flag_resume', '');
		}
		$resume = get_option('ewww_image_optimizer_bulk_flag_resume');
		if (!empty($_REQUEST['doaction'])) {
			if ($_REQUEST['page'] == 'manage-images' && $_REQUEST['bulkaction'] == 'bulk_optimize_images') {
				// check the referring page
				check_admin_referer('flag_updategallery');
				update_option('ewww_image_optimizer_bulk_flag_resume', '');
				$ids = array_map( 'intval', $_REQUEST['doaction']);
				//$ewwwflag->ewww_flag_bulk($ids);
				//return;
			}
		
			if ($_REQUEST['page'] == 'manage-galleries' && $_REQUEST['bulkaction'] == 'bulk_optimize_galleries') {
				check_admin_referer('flag_bulkgallery');
				global $flagdb;
				update_option('ewww_image_optimizer_bulk_flag_resume', '');
				$ids = array();
				foreach ($_REQUEST['doaction'] as $gid) {
					$gallery_list = $flagdb->get_gallery($gid);
					foreach ($gallery_list as $image) {
						$ids[] = $image->pid;
					}	
				}
				//$ewwwflag->ewww_flag_bulk($ids);
				//return;
			}
		} elseif (!empty($resume)) {
			$ids = get_option('ewww_image_optimizer_bulk_flag_attachments');
		} elseif ($hook == 'flagallery_page_flag-bulk-optimize') {
			global $wpdb;
			$ids = $wpdb->get_col("SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC");
		}
		update_option('ewww_image_optimizer_bulk_flag_attachments', $ids);
		wp_deregister_script('jquery');
		wp_register_script('jquery', plugins_url('/jquery-1.9.1.min.js', __FILE__), false, '1.9.1');
		wp_enqueue_script('ewwwjuiscript', plugins_url('/jquery-ui-1.10.2.custom.min.js', __FILE__), false);
		wp_enqueue_script('ewwwbulkscript', plugins_url('/eio.js', __FILE__), array('jquery'));
//		print_r ($ids);
//		echo "<br><----- $ids ------>";
		$ids = json_encode($ids);
//		echo "<br><----------------->";
//		print_r ($ids);
		//echo "<br><----- $ids ------>";
		wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
				'_wpnonce' => wp_create_nonce('ewww-image-optimizer-bulk'),
				'gallery' => 'flag',
				'attachments' => $ids
			)
		);
		wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
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

	/* initialize bulk operation */
	function ewww_flag_bulk_init() {
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; eh?' ) );
		}
		update_option('ewww_image_optimizer_bulk_flag_resume', 'true');
		$loading_image = plugins_url('/wpspin.gif', __FILE__);
		echo "<p>Optimizing&nbsp;<img src='$loading_image' alt='loading'/></p>";
		die();
	}

	function ewww_flag_bulk_filename() {
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; eh?' ) );
		}
		require_once(WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php');
		$id = $_POST['attachment'];
		$meta = new flagMeta($id);
		$loading_image = plugins_url('/wpspin.gif', __FILE__);
		$file_name = esc_html($meta->image->filename);
		echo "<p>Optimizing... <b>" . $file_name . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		die();
	}
		

	function ewww_flag_bulk_loop() {
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; eh?' ) );
		}
		require_once(WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php');
		//global $flag;
		//global $flagdb;
		$started = microtime(true);
		$id = $_POST['attachment'];
		$meta = new flagMeta($id);
		$file_path = $meta->image->imagePath;
		//file_put_contents($progress_file, "$id\n");
		//file_put_contents($progress_file, $attach_ser, FILE_APPEND);
		$fres = ewww_image_optimizer($file_path, 3, false, false);
		flagdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
		printf( "<p>Optimized image: <strong>%s</strong><br>", esc_html($meta->image->filename) );
		printf( "Full size – %s<br>", $fres[1] );
		$thumb_path = $meta->image->thumbPath;
		$tres = ewww_image_optimizer($thumb_path, 3, false, true);
		printf( "Thumbnail – %s<br>", $tres[1] );
		$elapsed = microtime(true) - $started;
		echo "Elapsed: " .round($elapsed, 3) . " seconds</p>";
		$attachments = get_option('ewww_image_optimizer_bulk_flag_attachments');
		array_shift($attachments);
		update_option('ewww_image_optimizer_bulk_flag_attachments', $attachments);
		die();
	}

	function ewww_flag_bulk_cleanup() {
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; eh?' ) );
		}
		update_option('ewww_image_optimizer_bulk_flag_resume', '');
		update_option('ewww_image_optimizer_bulk_flag_attachments', '');
		echo '<p><b>Finished Optimization!</b></p>';
		die();
	}
	/* function to bulk optimize images */
	function ewww_flag_bulk($images = null) {
		global $ewwwflag;
		$ewwwflag->ewww_post_processor('1');
		/*global $flag;
		$auto_start = false;
		$progress_file = ABSPATH . $flag->options['galleryPath'] . "ewww.tmp";
		$skip_attachments = false;
		if (!empty($images)) {
//			$images = explode(',', $_REQUEST['ids']);
			$auto_start = true;
			//$_REQUEST['_wpnonce'] = wp_create_nonce('ewww-flag-bulk');
		} elseif (isset($_REQUEST['resume'])) {
			$progress_contents = file($progress_file);
			$last_attachment = trim($progress_contents[0]);
			$images = unserialize($progress_contents[1]);
			$skip_attachments = true;
		} else {
			global $wpdb;
			$images = $wpdb->get_col("SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC");
		}
		$attach_ser = serialize($images);
		?>
		<div class="wrap"><div id="icon-upload" class="icon32"><br /></div><h2>GRAND FlAGallery Bulk Optimize</h2>
		<?php
		if ( sizeof($images) < 1 ):
			echo '<p>You don’t appear to have uploaded any images yet.</p>';
		else:
			if (empty($_POST) && !$auto_start): // instructions page
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
				if ((!wp_verify_nonce($_REQUEST['_wpnonce'], 'ewww-flag-bulk') || !current_user_can('edit_others_posts')) && !$auto_start) {
				wp_die( __( 'Cheatin&#8217; eh?' ) );
				} ?>
				If the bulk optimize is interrupted, go to the bulk optimize page and press the appropriate button to resume.
				<?php
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
					$meta = new flagMeta($id);
					printf( "<strong>%s</strong>&hellip;<br>", esc_html($meta->image->filename) );
					$file_path = $meta->image->imagePath;
					file_put_contents($progress_file, "$id\n");
					file_put_contents($progress_file, $attach_ser, FILE_APPEND);
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
				echo '<p><b>Finished Optimization</b></p></div>';	
			endif;
		endif;*/
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
		        // use finfo functions when available
			if (function_exists('finfo_file') && defined('FILEINFO_MIME')) {
				// create a finfo resource
				$finfo = finfo_open(FILEINFO_MIME);
				// retrieve the mimetype
				$type = explode(';', finfo_file($finfo, $file_path));
				$type = $type[0];
				finfo_close($finfo);
			} elseif (function_exists('getimagesize')) {
				$type = getimagesize($file_path);
				if(false !== $type){
					$type = $type['mime'];
				}
			} elseif (function_exists('mime_content_type')) {
				$type = mime_content_type($file_path);
			} else {
				$type = false;
				$msg = '<br>missing finfo_file(), getimagesize(), and mime_content_type() PHP functions';
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

