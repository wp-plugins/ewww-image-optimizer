<?php
// presents the bulk optimize form with the number of images, and runs it once they submit the button
function ewww_image_optimizer_bulk_preview() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_bulk_preview()</b><br>";
	// retrieve the attachment IDs that were pre-loaded in the database
	$attachments = get_option('ewww_image_optimizer_bulk_attachments');
	// make sure there are some attachments to optimize
	if (count($attachments) < 1) {
		echo '<p>' . __('You do not appear to have uploaded any images yet.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</p>';
		return;
	}
?>
	<div class="wrap"> 
	<div id="icon-upload" class="icon32"><br /></div><h2><?php _e('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h2>
<?php 
	// Retrieve the value of the 'bulk resume' option and set the button text for the form to use
	$resume = get_option('ewww_image_optimizer_bulk_resume');
	if (empty($resume)) {
		$button_text = __('Start optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	} else {
		$button_text = __('Resume previous bulk operation', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	}
	// create the html for the bulk optimize form and status divs
?>
		<div id="bulk-loading"></div>
		<div id="bulk-progressbar"></div>
		<div id="bulk-counter"></div>
		<div id="bulk-status"></div>
		<div id="bulk-forms">
		<p><?php printf(__('We have %d images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN), count($attachments)); ?></p>
		<form id="bulk-start" method="post" action="">
			<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
		</form>
<?php
		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if (!empty($resume)): 
?>
			<p><?php _e('If you would like to start over again, press the Reset Status button to reset the bulk operation status.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action"><?php _e('Reset Status', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></button>
			</form>
<?php		endif;
	echo '</div></div>';
}

// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_bulk_script($hook) {
	global $ewww_debug;
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
		$ids = explode(',', $_REQUEST['ids']);
		$ewww_debug .= "gallery ids: " . print_r($ids, true) . "<br>";
		$ewww_debug .= "post_type: " . get_post_type($ids[0]) . "<br>";
		if ('ims_gallery' == get_post_type($ids[0])) {
			$attachments = array();
			foreach ($ids as $gid) {
				$ewww_debug .= "gallery id: $gid<br>";
		                $ims_images = get_posts(array(
		                        'numberposts' => -1,
		                        'post_type' => 'ims_image',
					'post_status' => 'any',
		                        'post_mime_type' => 'image',
					'post_parent' => $gid,
					'fields' => 'ids'
		                ));
				$attachments = array_merge($attachments, $ims_images);
				$ewww_debug .= "attachment ids: " . print_r($attachments, true) . "<br>";
			}
		} else {
	                // retrieve post IDs correlating to the IDs submitted to make sure they are all valid
	                $attachments = get_posts( array(
	                        'numberposts' => -1,
	                        'include' => $ids,
	                        'post_type' => array('attachment', 'ims_image'),
				'post_status' => 'any',
	                        'post_mime_type' => 'image',
				'fields' => 'ids'
	                ));
		}
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
                        'post_type' => array('attachment', 'ims_image'),
			'post_status' => 'any',
                        'post_mime_type' => 'image',
			'fields' => 'ids'
                ));
        }
	// the 'fields' option was added in 3.1, so (in older versions) we need to strip 
	// the excess data from attachments, since we only want the attachment IDs
	$attachments = ewww_image_optimizer_clean_attachments($attachments);
	// store the attachment IDs we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
	update_option('ewww_image_optimizer_bulk_attachments', $attachments);
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

// called by javascript to initialize some output
function ewww_image_optimizer_bulk_initialize() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	// update the 'bulk resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_bulk_resume', 'true');
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>" . __('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}

// called by javascript to output filename of attachment in progress
function ewww_image_optimizer_bulk_filename() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	// get the attachment ID of the current attachment
	$attachment_ID = $_POST['attachment'];
	$meta = wp_get_attachment_metadata( $attachment_ID );
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	if(!empty($meta['file']))
		// let the user know the file we are currently optimizing
		echo "<p>" . __('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <b>" . $meta['file'] . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}
 
// called by javascript to process each image in the loop
function ewww_image_optimizer_bulk_loop() {
	global $wpdb;
	global $ewww_debug;
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
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
	if(!empty($meta['file'])) {
		// output the filename (and path relative to 'uploads' folder)
		printf( "<p>" . __('Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <strong>%s</strong><br>", esc_html($meta['file']) );
	} else {
		printf("<p>" . __('Skipped image, ID:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <strong>%s</strong><br>", $attachment );
	}
	if(!empty($meta['ewww_image_optimizer']))
		// tell the user what the results were for the original image
		printf(__('Full size – %s', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "<br>", $meta['ewww_image_optimizer']);
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
	printf(__('Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>", $elapsed);
	// update the metadata for the current attachment
	wp_update_attachment_metadata( $attachment, $meta );
	// remove the first element fromt the $attachments array
	if (!empty($attachments))
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
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_bulk_resume', '');
	update_option('ewww_image_optimizer_bulk_attachments', '');
	// and let the user know we are done
	echo '<p><b>' . __('Finished', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</b> - <a href="upload.php">' . __('Return to Media Library', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a></p>';
	die();
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_bulk_script');
add_action('wp_ajax_bulk_init', 'ewww_image_optimizer_bulk_initialize');
add_action('wp_ajax_bulk_filename', 'ewww_image_optimizer_bulk_filename');
add_action('wp_ajax_bulk_loop', 'ewww_image_optimizer_bulk_loop');
add_action('wp_ajax_bulk_cleanup', 'ewww_image_optimizer_bulk_cleanup');
?>
