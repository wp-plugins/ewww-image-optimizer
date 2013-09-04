<?php
// displays the 'auxiliary images' optimizer page
function ewww_image_optimizer_aux_images () {
	// retrieve the attachment IDs that were pre-loaded in the database
	$attachments = get_option('ewww_image_optimizer_aux_attachments');
	// Retrieve the value of the 'aux resume' option and set the button text for the form to use
	$resume = get_option('ewww_image_optimizer_aux_resume');
	if (empty($resume)) {
		$button_text = 'Start optimizing';
	} else {
		$button_text = 'Resume previous optimization';
	}
	?>
	<div class="wrap">
	<div id="icon-themes" class="icon32"><br /></div><h2>EWWW Optimize Theme Images</h2>
		<div id="bulk-loading"></div>
		<div id="bulk-progressbar"></div>
		<div id="bulk-counter"></div>
		<div id="bulk-status"></div>
		<div id="bulk-forms"><p>This tool can optimize large batches of images from your wordpress install.</p>
		<p>We have <?php echo count($attachments); ?> images to optimize.</p>
		<form id="bulk-start" method="post" action="">
			<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
		</form>
<?php		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if (!empty($resume)) {
?>
			<p>If you would like to start over again, press the <b>Reset Status</b> button to reset the bulk operation status.</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-aux-images', '_wpnonce'); ?>
				<input type="hidden" name="reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action">Reset Status</button>
			</form>
<?php		} ?>
		</div>
	</div>
<?php
}

//scan a folder for images and return them as an array
function ewww_image_optimizer_image_scan($dir) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $path) {
		if ($path->isDir()) {
			continue;
		} else {
			$mimetype = ewww_image_optimizer_mimetype($path, 'i');
			if (preg_match('/^image/', $mimetype)) {
				$images[] = $path->getPathname();
			}
		}
	}
	return $images;
}
 
// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_aux_images_script($hook) {
	// initialize the $attachments variable for auxiliary images
	$attachments = null;
	$theme_images = false;
	$buddypress_images = false;
	$symposium_images = false;
	// find out what page we are being called from
	if ('appearance_page_ewww-image-optimizer-theme-images' === $hook) {
		$theme_images = true;
		update_option('ewww_image_optimizer_aux_type', 'theme');
	} else if ('media_page_ewww-image-optimizer-buddypress-images' === $hook) {
		$buddypress_images = true;
		update_option('ewww_image_optimizer_aux_type', 'buddypress');
	} else if ('symposium_page_ewww-image-optimizer-symposium-images' === $hook) {
		$symposium_images = true;
		update_option('ewww_image_optimizer_aux_type', 'symposium');
	} else {
		return;
	}
	// collect a list of images if we are working with a theme
	if ($theme_images) {
		$child_path = get_stylesheet_directory();
		$parent_path = get_template_directory();
		$attachments = ewww_image_optimizer_image_scan($child_path); 
		if ($child_path !== $parent_path) {
			$attachments = array_merge($attachments, ewww_image_optimizer_image_scan($parent_path));
		}
	}
	// collect a list of images for buddypress
	if ($buddypress_images) {
		// get the value of the wordpress upload directory
	        $upload_dir = wp_upload_dir();
	        // append the location of the buddypress avatars folder
//	        $avatars_path = $upload_dir['basedir'] . "/avatars";
	        // append the location of the buddypress group avatars folder
//	        $gavatars_path = $upload_dir['basedir'] . "/group-avatars";
		$attachments = array_merge(ewww_image_optimizer_image_scan($upload_dir['basedir'] . "/avatars"), ewww_image_optimizer_image_scan($upload_dir['basedir'] . "/group-avatars"));
	}

        // check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if (!empty($_REQUEST['reset']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-aux-images' )) {
		// set the 'bulk resume' option to an empty string to reset the bulk operation
		update_option('ewww_image_optimizer_aux_resume', '');
	}
	// check the 'bulk resume' option
	$resume = get_option('ewww_image_optimizer_aux_resume');
	global $wp_version;
	$my_version = $wp_version;
	$my_version = substr($my_version, 0, 3);
	// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
	update_option('ewww_image_optimizer_aux_attachments', $attachments);
        // load the auxiliary optimization javascript and dependencies
	// only re-register jquery on old versions of wordpress
	if ($my_version < 3) {
		wp_deregister_script('jquery');
		wp_register_script('jquery', plugins_url('/jquery-1.9.1.min.js', __FILE__), false, '1.9.1');
	}
	wp_enqueue_script('ewwwjuiscript', plugins_url('/jquery-ui-1.10.2.custom.min.js', __FILE__), false);
	wp_enqueue_script('ewwwbulkscript', plugins_url('/eio.js', __FILE__), array('jquery'));
	// submit a couple variables to the javascript to work with
	$attachments = json_encode($attachments);
	wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce('ewww-image-optimizer-aux-images'),
			'gallery' => 'aux',
			'attachments' => $attachments
		)
	);
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_aux_images_script');

// called by javascript to initialize some output
function ewww_image_optimizer_aux_images_initialize() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-aux-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// update the 'aux resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_aux_resume', 'true');
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>Optimizing&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}

// called by javascript to output filename of attachment in progress
function ewww_image_optimizer_aux_images_filename() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-aux-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	}
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>Optimizing <b>" . $_POST['attachment'] . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}
 
// called by javascript to process each image in the loop
function ewww_image_optimizer_aux_images_loop() {
	global $wpdb;
	global $ewww_debug;
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-aux-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	// allow 50 seconds for each image (this doesn't include any exec calls, only php processing time)
	set_time_limit (50);
	// get the path of the current attachment
	$attachment = $_POST['attachment'];
	// get the 'aux attachments' with a list of attachments remaining
	$attachments = get_option('ewww_image_optimizer_aux_attachments');
	// do the optimization for the current image
	$results = ewww_image_optimizer($attachment, 4, false, false);
	// store info on the current info for future reference
	$wpdb->insert( $wpdb->prefix . "ewwwio_images", array(
			'path' => $attachment,
			'image_md5' => md5_file($attachment),
			'results' => $results[1]
		));
	// output the path
	printf( "<p>Optimized image: <strong>%s</strong><br>", esc_html($attachment) );
	// tell the user what the results were for the original image
	printf( "%s<br>", $results[1] );
	// calculate how much time has elapsed since we started
	$elapsed = microtime(true) - $started;
	// output how much time has elapsed since we started
	echo "Elapsed: " . round($elapsed, 3) . " seconds</p>";
	// remove the first element fromt the $attachments array
	array_shift($attachments);
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option('ewww_image_optimizer_aux_attachments', $attachments);
	if (get_site_option('ewww_image_optimizer_debug')) echo '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
	die();
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_aux_images_cleanup() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-aux-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_aux_resume', '');
	update_option('ewww_image_optimizer_aux_attachments', '');
	// and let the user know we are done
	echo '<p><b>Finished</b> - <a href="themes.php">Return to Themes</a></p>';
	die();
}
add_action('wp_ajax_bulk_aux_images_init', 'ewww_image_optimizer_aux_images_initialize');
add_action('wp_ajax_bulk_aux_images_filename', 'ewww_image_optimizer_aux_images_filename');
add_action('wp_ajax_bulk_aux_images_loop', 'ewww_image_optimizer_aux_images_loop');
add_action('wp_ajax_bulk_aux_images_cleanup', 'ewww_image_optimizer_aux_images_cleanup');
?>
