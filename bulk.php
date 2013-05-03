<?php
// presents the bulk optimize form with the number of images, and runs it once they submit the button
function ewww_image_optimizer_bulk_preview() {
	// retrieve the attachment IDs that were pre-loaded in the database
	$attachments = get_option('ewww_image_optimizer_bulk_attachments');
	// make sure there are some attachments to optimize
	if (count($attachments) < 1) {
		echo '<p>You don’t appear to have uploaded any images yet.</p>';
		return;
	}
?>
	<div class="wrap"> 
	<div id="icon-upload" class="icon32"><br /></div><h2>Bulk EWWW Image Optimize </h2>
<?php 
	// Retrieve the value of the 'bulk resume' option and set the button text for the form to use
	$resume = get_option('ewww_image_optimizer_bulk_resume');
	if (empty($resume)) {
		$button_text = 'Start optimizing';
	} else {
		$button_text = 'Resume previous bulk operation';
	}
	// create the html for the bulk optimize form and status divs
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
		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if (!empty($resume)): 
?>
			<p>If you would like to start over again, press the <b>Reset Status</b> button to reset the bulk operation status.</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action">Reset Status</button>
			</form>
<?php		endif;
	echo '</div></div>';
}

// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_bulk_script($hook) {
	// make sure we are being called from the bulk optimization page
	if ('media_page_ewww-image-optimizer-bulk' != $hook)
		return;
        // initialize the $attachments variable
        $attachments = null;
        // check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if (!empty($_REQUEST['reset']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' )) {
		// set the 'bulk resume' option to an empty string to reset the bulk operation
		update_option('ewww_image_optimizer_bulk_resume', '');
	}
	// check the 'bulk resume' option
	$resume = get_option('ewww_image_optimizer_bulk_resume');
	// see if we were given attachment IDs to work with via GET/POST
        if (!empty($_REQUEST['ids'])) {
                // retrieve post IDs correlating to the IDs submitted to make sure they are all valid
                $attachments = get_posts( array(
                        'numberposts' => -1,
                        'include' => explode(',', $_REQUEST['ids']),
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
			'fields' => 'ids'
                ));
		// unset the 'bulk resume' option since we were given specific IDs to optimize
		update_option('ewww_image_optimizer_bulk_resume', '');
        // check if there is a previous bulk operation to resume
        } else if (!empty($resume)) {
		// retrieve the attachment IDs that have not been finished from the 'bulk attachments' option
		$attachments = get_option('ewww_image_optimizer_bulk_attachments');
	// since we aren't resuming, and weren't given a list of IDs, we will optimize everything
        } else {
                // load up all the image attachments we can find
                $attachments = get_posts( array(
                        'numberposts' => -1,
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
			'fields' => 'ids'
                ));
        }
	// the 'fields' option as added in 3.1, so (in older versions) we need to strip 
	// the excess data from attachments, since we only want the attachment IDs
	global $wp_version;
	$my_version = $wp_version;
	$my_version = substr($my_version, 0, 3);
	if ( $my_version < 3.1 ) {
		$i = 0;
		foreach( $attachments as $attachment ) {
			$new_attachments[$i] = $attachment->ID;
			$i++;
		}
		$attachments = $new_attachments;
	}
	// store the attachment IDs we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
	update_option('ewww_image_optimizer_bulk_attachments', $attachments);
        // load the bulk optimization javascript and dependencies
	// only re-register jquery on old versions of wordpress
	if ($my_version < 3) {
		wp_deregister_script('jquery');
		wp_register_script('jquery', plugins_url('/jquery-1.9.1.min.js', __FILE__), false, '1.9.1');
	}
	wp_enqueue_script('ewwwjuiscript', plugins_url('/jquery-ui-1.10.2.custom.min.js', __FILE__), false);
	wp_enqueue_script('ewwwbulkscript', plugins_url('/eio.js', __FILE__), array('jquery'));
	//}
	// submit a couple variables to the javascript to work with
	$attachments = json_encode($attachments);
	wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce('ewww-image-optimizer-bulk'),
			'attachments' => $attachments
		)
	);
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_bulk_script');

// called by javascript to initialize some output
function ewww_image_optimizer_bulk_initialize() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// update the 'bulk resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_bulk_resume', 'true');
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>Optimizing&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}

// called by javascript to output filename of attachment in progress
function ewww_image_optimizer_bulk_filename() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	}
	// get the attachment ID of the current attachment
	$attachment_ID = $_POST['attachment'];
	$meta = wp_get_attachment_metadata( $attachment_ID );
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>Optimizing <b>" . $meta['file'] . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}
 
// called by javascript to process each image in the loop
function ewww_image_optimizer_bulk_loop() {
	global $ewww_debug;
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	// allow 50 seconds for each image (this doesn't include any exec calls, only php processing time)
	set_time_limit (50);
	// get the attachment ID of the current attachment
	$attachment = $_POST['attachment'];
	// get the 'bulk attachments' with a list of IDs remaining
	$attachments = get_option('ewww_image_optimizer_bulk_attachments');
	// do the optimization for the current attachment (including resizes)
	$meta = ewww_image_optimizer_resize_from_meta_data (wp_get_attachment_metadata( $attachment, true ), $attachment);
	// output the filename (and path relative to 'uploads' folder
	printf( "<p>Optimized image: <strong>%s</strong><br>", esc_html($meta['file']) );
	// tell the user what the results were for the original image
	printf( "Full size – %s<br>", $meta['ewww_image_optimizer'] );
	// check to see if there are resized version of the image
	if (isset($meta['sizes']) && is_array($meta['sizes'])) {
		// cycle through each resize
		foreach ($meta['sizes'] as $size) {
			// output the results for the current resized version
			printf("%s – %s<br>", $size['file'], $size['ewww_image_optimizer']);
		}
	}
	// calculate how much time has elapsed since we started
	$elapsed = microtime(true) - $started;
	// output how much time has elapsed since we started
	echo "Elapsed: " . round($elapsed, 3) . " seconds</p>";
	// update the metadata for the current attachment
	wp_update_attachment_metadata( $attachment, $meta );
	// remove the first element fromt the $attachments array
	array_shift($attachments);
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option('ewww_image_optimizer_bulk_attachments', $attachments);
	if (get_site_option('ewww_image_optimizer_debug')) echo '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
	die();
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_bulk_cleanup() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die( __( 'Cheatin&#8217; eh?' ) );
	} 
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_bulk_resume', '');
	update_option('ewww_image_optimizer_bulk_attachments', '');
	// and let the user know we are done
	echo '<p><b>Finished</b> - <a href="upload.php">Return to Media Library</a></p>';
	die();
}
add_action('wp_ajax_bulk_init', 'ewww_image_optimizer_bulk_initialize');
add_action('wp_ajax_bulk_filename', 'ewww_image_optimizer_bulk_filename');
add_action('wp_ajax_bulk_loop', 'ewww_image_optimizer_bulk_loop');
add_action('wp_ajax_bulk_cleanup', 'ewww_image_optimizer_bulk_cleanup');
?>
