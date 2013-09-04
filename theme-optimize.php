<?php
function ewww_image_optimizer_theme_images () {
	// retrieve the attachment IDs that were pre-loaded in the database
	$attachments = get_option('ewww_image_optimizer_theme_attachments');
	// Retrieve the value of the 'bulk resume' option and set the button text for the form to use
	$resume = get_option('ewww_image_optimizer_theme_resume');
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
		<div id="bulk-forms"><p>This tool can optimize large batches (or all) of images from your media library.</p>
		<p>We have <?php echo count($attachments); ?> images to optimize.</p>
		<form id="bulk-start" method="post" action="">
			<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
		</form>
<?php		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if (!empty($resume)) {
?>
			<p>If you would like to start over again, press the <b>Reset Status</b> button to reset the bulk operation status.</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-theme-images', '_wpnonce'); ?>
				<input type="hidden" name="reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action">Reset Status</button>
			</form>
<?php		} ?>
		</div>
	</div>
<?php
}
 
// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_theme_images_script($hook) {
	// make sure we are being called from the theme optimization page
	if ('appearance_page_ewww-image-optimizer-theme-images' != $hook)
		return;
        // initialize the $attachments variable
	$attachments = null;
	$child_path = get_stylesheet_directory();
	$parent_path = get_template_directory();
	if ($child_path === $parent_path) {
		$child = false;
	} else { 
		$child = true;
	} 
	$citerator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($child_path), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($citerator as $cpath) {
		if ($cpath->isDir()) {
			continue;
		} else {
			$mimetype = ewww_image_optimizer_mimetype($cpath, 'i');
			if (preg_match('/^image/', $mimetype)) {
				$attachments[] = $cpath->getPathname();
			}
		}
	}
	if ($child) {
		$piterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($parent_path), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($piterator as $ppath) {
			if ($ppath->isDir()) {
				continue;
			} else {
				$mimetype = ewww_image_optimizer_mimetype($ppath, 'i');
				if (preg_match('/^image/', $mimetype)) {
					$attachments[] = $ppath->getPathname();
				}
			}
		}
	}
	
        // check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if (!empty($_REQUEST['reset']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-theme-images' )) {
		// set the 'bulk resume' option to an empty string to reset the bulk operation
		update_option('ewww_image_optimizer_theme_resume', '');
	}
	// check the 'bulk resume' option
	$resume = get_option('ewww_image_optimizer_theme_resume');
	global $wp_version;
	$my_version = $wp_version;
	$my_version = substr($my_version, 0, 3);
	// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
	update_option('ewww_image_optimizer_theme_attachments', $attachments);
        // load the theme optimization javascript and dependencies
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
			'_wpnonce' => wp_create_nonce('ewww-image-optimizer-theme-images'),
			'gallery' => 'theme_images',
			'attachments' => $attachments
		)
	);
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_theme_images_script');

// called by javascript to initialize some output
function ewww_image_optimizer_theme_images_initialize() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-theme-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// update the 'theme resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_theme_resume', 'true');
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>Optimizing&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}

// called by javascript to output filename of attachment in progress
function ewww_image_optimizer_theme_images_filename() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-theme-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	}
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>Optimizing <b>" . $_POST['attachment'] . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}
 
// called by javascript to process each image in the loop
function ewww_image_optimizer_theme_images_loop() {
	global $ewww_debug;
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-theme-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	// allow 50 seconds for each image (this doesn't include any exec calls, only php processing time)
	set_time_limit (50);
	// get the path of the current attachment
	$attachment = $_POST['attachment'];
	// get the 'theme attachments' with a list of attachments remaining
	$attachments = get_option('ewww_image_optimizer_theme_attachments');
	// do the optimization for the current image
	$results = ewww_image_optimizer($attachment, 4, false, false);
	// output the filename (and path relative to 'uploads' folder
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
	update_option('ewww_image_optimizer_theme_attachments', $attachments);
	if (get_site_option('ewww_image_optimizer_debug')) echo '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
	die();
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_theme_images_cleanup() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-theme-images' ) || !current_user_can( 'edit_themes' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_theme_resume', '');
	update_option('ewww_image_optimizer_theme_attachments', '');
	// and let the user know we are done
	echo '<p><b>Finished</b> - <a href="themes.php">Return to Themes</a></p>';
	die();
}
add_action('wp_ajax_bulk_theme_images_init', 'ewww_image_optimizer_theme_images_initialize');
add_action('wp_ajax_bulk_theme_images_filename', 'ewww_image_optimizer_theme_images_filename');
add_action('wp_ajax_bulk_theme_images_loop', 'ewww_image_optimizer_theme_images_loop');
add_action('wp_ajax_bulk_theme_images_cleanup', 'ewww_image_optimizer_theme_images_cleanup');
?>
