<?php 
class ewwwngg {
	// pretty sure we don't need this anymore
	//static $plugins_ok = true; 

	/* initializes the nextgen integration functions */
	function ewwwngg() {
		add_filter('ngg_manage_images_columns', array(&$this, 'ewww_manage_images_columns'));
		add_action('ngg_manage_image_custom_column', array(&$this, 'ewww_manage_image_custom_column'), 10, 2);
		add_action('ngg_added_new_image', array(&$this, 'ewww_added_new_image'));
		add_action('admin_action_ewww_ngg_manual', array(&$this, 'ewww_ngg_manual'));
		add_action('admin_menu', array(&$this, 'ewww_ngg_bulk_menu'));
		add_action('admin_head-gallery_page_nggallery-manage-gallery', array(&$this, 'ewww_ngg_bulk_actions_script'));
		add_action('admin_enqueue_scripts', array(&$this, 'ewww_ngg_bulk_script'));
		add_action('wp_ajax_bulk_ngg_preview', array(&$this, 'ewww_ngg_bulk_preview'));
		add_action('wp_ajax_bulk_ngg_init', array(&$this, 'ewww_ngg_bulk_init'));
		add_action('wp_ajax_bulk_ngg_filename', array(&$this, 'ewww_ngg_bulk_filename'));
		add_action('wp_ajax_bulk_ngg_loop', array(&$this, 'ewww_ngg_bulk_loop'));
		add_action('wp_ajax_bulk_ngg_cleanup', array(&$this, 'ewww_ngg_bulk_cleanup'));
		add_action('wp_ajax_ewww_ngg_thumbs', array(&$this, 'ewww_ngg_thumbs_only'));
		add_action('ngg_after_new_images_added', array(&$this, 'ewww_ngg_new_thumbs'), 10, 2);
		register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_ngg_resume');
		register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_ngg_attachments');
	}

	/* adds the Bulk Optimize page to the tools menu */
	function ewww_ngg_bulk_menu () {
			add_submenu_page(NGGFOLDER, 'NextGEN Bulk Optimize', 'Bulk Optimize', 'NextGEN Manage gallery', 'ewww-ngg-bulk', array (&$this, 'ewww_ngg_bulk_preview'));
			$hook = add_submenu_page(null, 'NextGEN Bulk Thumbnail Optimize', 'Bulk Thumbnail Optimize', 'NextGEN Manage gallery', 'ewww-ngg-thumb-bulk', array (&$this, 'ewww_ngg_thumb_bulk'));
	}

	/* ngg_added_new_image hook */
	function ewww_added_new_image( $image ) {
		// query the filesystem path of the gallery from the database
		global $wpdb;
		$q = $wpdb->prepare( "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d LIMIT 1", $image['galleryID'] );
		$gallery_path = $wpdb->get_var($q);
		// if we have a path to work with
		if ( $gallery_path ) {
			// TODO: optimize thumbs automatically 
			// construct the absolute path of the current image
			$file_path = trailingslashit($gallery_path) . $image['filename'];
			// run the optimizer on the current image
			$res = ewww_image_optimizer(ABSPATH . $file_path, 2, false, false);
			// update the metadata for the optimized image
			nggdb::update_image_meta($image['id'], array('ewww_image_optimizer' => $res[1]));
		}
	}

	function ewww_ngg_new_thumbs($gid, $images) {
		$gallery = $gid;
//		print_r ($gid);
		$images = serialize($images);
	//print_r ($images);
		echo "<br>"; ?>
                <div id="bulk-forms"><p>The thumbnails for your new images have not been optimized. If you would like this step to be automatic in the future, bug the NextGEN developers to add in a hook.</p>
                <form id="thumb-optimize" method="post" action="http://bob/wordpress/wp-admin/admin.php?page=ewww-ngg-thumb-bulk">
			<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
			<input type="hidden" name="attachments" value="<?php echo $images; ?>">
                        <input type="submit" class="button-secondary action" value="Optimize Thumbs" />
                </form> 
<?php	}

	function ewww_ngg_thumb_bulk() {
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; eh?' ) );
		}?> 
		<div class="wrap">
                <div id="icon-upload" class="icon32"><br /></div><h2>Bulk Thumbnail Optimize</h2>
<?php		$images = unserialize ($_POST['attachments']);
		$started = time();
		// initialize $current, and $started time
		$current = 0;
		// find out how many images we have
		$total = sizeof($images);
		ob_implicit_flush(true);
		ob_end_flush();
		foreach ($images as $id) {
			// give each image 50 seconds (php only, doesn't include any commands issued by exec()
			set_time_limit (50);
			$current++;
			echo "<p>Processing $current/$total: ";
			// get the metadata
			$meta = new nggMeta( $id );
			// output the current image name
			printf( "<strong>%s</strong>&hellip;<br>", esc_html($meta->image->filename) );
			// get the filepath of the thumbnail image
			$thumb_path = $meta->image->thumbPath;
			// run the optimization on the thumbnail
			$tres = ewww_image_optimizer($thumb_path, 2, false, true);
			// output the results of the thumb optimization
			printf( "Thumbnail – %s<br>", $tres[1] );
			// outupt how much time we've spent optimizing so far
			$elapsed = time() - $started;
			echo "Elapsed: $elapsed seconds</p>";
			// flush the HTML output buffers
			@ob_flush();
			flush();
		}
		echo '<p><b>Finished</b></p></div>';	
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
		$res = ewww_image_optimizer($file_path, 2, false, false);
		// update the metadata for the optimized image
		nggdb::update_image_meta($id, array('ewww_image_optimizer' => $res[1]));
		// get the filepath of the thumbnail image
		$thumb_path = $meta->image->thumbPath;
		// run the optimization on the thumbnail
		ewww_image_optimizer($thumb_path, 2, false, true);
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
				} ?>
				<form method="post" action="">If the bulk optimize is interrupted, press
					<?php wp_nonce_field( 'ewww-ngg-bulk', '_wpnonce'); ?>
					<input type="hidden" name="resume" value="1">
					<button type="submit" class="button-secondary action">resume</button>. If the page is still loading, the bulk action is still running.
				</form>
				<?php
				// initialize $current, and $started time
				$current = 0;
				$started = time();
				// find out how many images we have
				$total = sizeof($images);
				?>
				<!--<script type="text/javascript">
					document.write('Bulk Optimization has taken <span id="endTime">0.0</span> seconds.');
					var loopTime=setInterval("currentTime()",100);
				</script>-->
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
						$fres = ewww_image_optimizer($file_path, 2, false, false);
						// update the metadata of the optimized image
						nggdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
						// output the results of the optimization
						printf( "Full size – %s<br>", $fres[1] );
						// get the filepath of the thumbnail image
						$thumb_path = $meta->image->thumbPath;
						// run the optimization on the thumbnail
						$tres = ewww_image_optimizer($thumb_path, 2, false, true);
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
		        // use finfo functions when available
			if (function_exists('finfo_file') && defined('FILEINFO_MIME')) {
				// create a finfo resource
				$finfo = finfo_open(FILEINFO_MIME);
				// retrieve the mimetype
				$type = explode(';', finfo_file($finfo, $file_path));
				$type = $type[0];
				finfo_close($finfo);
			// use getimagesize to find the mimetype
			} elseif (function_exists('getimagesize')) {
				$type = getimagesize($file_path);
				if(false !== $type){
					$type = $type['mime'];
				}
			// try mime_content_type to find the mimetype otherwise
			} elseif (function_exists('mime_content_type')) {
				$type = mime_content_type($file_path);
			// otherwise tell the user we just can't work under these conditions
			} else {
				$type = false;
				$msg = '<br>missing finfo_file(), getimagesize() and mime_content_type() PHP functions';
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
	function ewww_ngg_bulk_preview() {
		if (!empty($_POST['wrapped'])) {
                        // if there is no requested bulk action, do nothing
                        if (empty($_REQUEST['bulkaction'])) {
                        //        return;
                        }
                        // if there is no media to optimize, do nothing
                        if (empty($_REQUEST['doaction']) || !is_array($_REQUEST['doaction'])) {
                        //      return;
                        }
                }
                $attachments = get_option('ewww_image_optimizer_bulk_ngg_attachments');
                if (count($attachments) < 1) {
                        echo '<p>You don’t appear to have uploaded any images yet.</p>';
                        return;
                }
                ?>
		<div class="wrap">
                <div id="icon-upload" class="icon32"><br /></div><h2>NextGEN Gallery Bulk Optimize</h2>
                <?php
                // Retrieve the value of the 'bulk resume' option and set the button text for the form to use
                $resume = get_option('ewww_image_optimizer_bulk_ngg_resume');
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
		//if (empty($_POST['wrapped'])) {
	                echo '</div></div>';
		//} else {
		//	echo '</div>';
		//}
		//die();
	}

	function ewww_ngg_bulk_script($hook) { 
//	global $hook_suffix;
//	print_r ($hook_suffix); 
//		print_r($_POST);
//		echo "<br>$hook<br>";
		if ($hook != 'gallery_page_ewww-ngg-bulk' && $hook != 'gallery_page_nggallery-manage-gallery')
				return;
		if ($hook == 'gallery_page_nggallery-manage-gallery' && empty($_REQUEST['bulkaction']))
				return;
		if ($hook == 'gallery_page_nggallery-manage-gallery' && (empty($_REQUEST['doaction']) || !is_array($_REQUEST['doaction'])))
				return;
		$images = null;
//		$images = get_option('ewww_image_optimizer_bulk_ngg_attachments');
		if (!empty($_REQUEST['reset']))
			update_option('ewww_image_optimizer_bulk_ngg_resume', '');
		$resume = get_option('ewww_image_optimizer_bulk_ngg_resume');
		if (!empty($_REQUEST['doaction'])) {
			if ($_REQUEST['page'] == 'manage-images' && $_REQUEST['bulkaction'] == 'bulk_optimize') {
				check_admin_referer('ngg_updategallery');
				update_option('ewww_image_optimizer_bulk_ngg_resume', '');
				$images = array_map( 'intval', $_REQUEST['doaction']);
			}
			if ($_REQUEST['page'] == 'manage-galleries' && $_REQUEST['bulkaction'] == 'bulk_optimize') {
				check_admin_referer('ngg_bulkgallery');
				global $nggdb;
				update_option('ewww_image_optimizer_bulk_ngg_resume', '');
				$ids = array();
				foreach ($_REQUEST['doaction'] as $gid) {
					$gallery_list = $nggdb->get_gallery($gid);
					foreach ($gallery_list as $image) {
						$images[] = $image->pid;
					}
				}
			}
		} elseif (!empty($resume)) {
			$images = get_option('ewww_image_optimizer_bulk_ngg_attachments');
		} elseif ($hook == 'gallery_page_ewww-ngg-bulk') {
			global $wpdb;
			$images = $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC");
		}
		update_option('ewww_image_optimizer_bulk_ngg_attachments', $images);
		wp_deregister_script('jquery');
		wp_register_script('jquery', plugins_url('/jquery-1.9.1.min.js', __FILE__), false, '1.9.1');
		wp_enqueue_script('ewwwjuiscript', plugins_url('/jquery-ui-1.10.2.custom.min.js', __FILE__), false);
		wp_enqueue_script('ewwwbulkscript', plugins_url('/eio.js', __FILE__), array('jquery'), '1.4.1');
		wp_register_style( 'ngg-jqueryui', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
//		wp_dequeue_style( 'ngg-jqueryui' ); 
		wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
		$images = json_encode($images);
		wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
				'_wpnonce' => wp_create_nonce('ewww-image-optimizer-bulk'),
				'gallery' => 'nextgen',
				'attachments' => $images
			)
		);
	}

	function ewww_ngg_bulk_init() {
                if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
                        wp_die( __( 'Cheatin&#8217; eh?' ) );
                }
                update_option('ewww_image_optimizer_bulk_ngg_resume', 'true');
                $loading_image = plugins_url('/wpspin.gif', __FILE__);
                echo "<p>Optimizing&nbsp;<img src='$loading_image' alt='loading'/></p>";
                die();
        }

	function ewww_ngg_bulk_filename() {
                if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
                        wp_die( __( 'Cheatin&#8217; eh?' ) );
                }
		require_once(WP_CONTENT_DIR . '/plugins/nextgen-gallery/lib/meta.php');
		$id = $_POST['attachment'];
		$meta = new nggMeta($id);
		$loading_image = plugins_url('/wpspin.gif', __FILE__);
		$file_name = esc_html($meta->image->filename);
		echo "<p>Optimizing... <b>" . $file_name . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		die();
	}

	function ewww_ngg_bulk_loop() {
                if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
                        wp_die( __( 'Cheatin&#8217; eh?' ) );
                }
		require_once(WP_CONTENT_DIR . '/plugins/nextgen-gallery/lib/meta.php');
		$started = microtime(true);
		$id = $_POST['attachment'];
		// get the metadata
		$meta = new nggMeta($id);
		// output the current image name
		//printf( "<strong>%s</strong>&hellip;<br>", esc_html($meta->image->filename) );
		// retrieve the filepath
		$file_path = $meta->image->imagePath;
		// update the temp file with our current status
		//file_put_contents($progress_file, "$id");
		// run the optimizer on the current image
		$fres = ewww_image_optimizer($file_path, 2, false, false);
		// update the metadata of the optimized image
		nggdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
		// output the results of the optimization
		printf("<p>Optimized image: <strong>%s</strong><br>", $meta->image->filename);
		printf("Full size - %s<br>", $fres[1] );
		// get the filepath of the thumbnail image
		$thumb_path = $meta->image->thumbPath;
		// run the optimization on the thumbnail
		$tres = ewww_image_optimizer($thumb_path, 2, false, true);
		// output the results of the thumb optimization
		printf( "Thumbnail - %s<br>", $tres[1] );
		// outupt how much time we've spent optimizing so far
		$elapsed = microtime(true) - $started;
		echo "Elapsed: " . round($elapsed, 3) . " seconds</p>";
		$attachments = get_option('ewww_image_optimizer_bulk_ngg_attachments');
		array_shift($attachments);
		update_option('ewww_image_optimizer_bulk_flag_attachments', $attachments);
		die();
	}

	function ewww_ngg_bulk_cleanup() {
                if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
                        wp_die( __( 'Cheatin&#8217; eh?' ) );
                }
		update_option('ewww_image_optimizer_bulk_ngg_resume', '');
		update_option('ewww_image_optimizer_bulk_ngg_attachments', '');
		echo '<p><b>Finished Optimization!</b></p>';
		die();
	}
	
	function ewww_ngg_bulk_actions_script() {?>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('select[name^="bulkaction"] option:last-child').after('<option value="bulk_optimize">Bulk Optimize</option>');
			});
		</script>
<?php	}
}
// initialize the plugin and the class
add_action('init', 'ewwwngg');
//add_action('admin_print_scripts-tools_page_ewww-ngg-bulk', 'ewww_image_optimizer_scripts');

function ewwwngg() {
	global $ewwwngg;
	$ewwwngg = new ewwwngg();
}

