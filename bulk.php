<?php
// TODO: need a way to make sure all this stuff only loads on the bulk page(s) 
// presents the bulk optimize function with the number of images, and runs it once they submit the button (most of the html is in bulk.php)
function ewww_image_optimizer_bulk_preview() {
	$attachments = get_option('ewww_image_optimizer_bulk_attachments');
	if (count($attachments) < 1) {
		echo '<p>You don’t appear to have uploaded any images yet.</p>';
	return;
	}
?>
<div class="wrap"> 
<div id="icon-upload" class="icon32"><br /></div><h2>Bulk EWWW Image Optimize </h2>
<?php 
//$styles = $GLOBALS['wp_styles']->registered;
//print_r($styles);
// make sure there are some attachments to optimize
        // get the value of the wordpress upload directory
        $upload_dir = wp_upload_dir();
        // set the location of our temporary status file
        $progress_file = $upload_dir['basedir'] . "/ewww.tmp";
	// we have attachments to work with, but need to ask for confirmation first
	$resume = get_option('ewww_image_optimizer_bulk_resume');
	if (empty($resume)) {
		$button_text = 'Run all my images through image optimizers right now';
	} else {
		$button_text = 'Resume previous bulk operation';
	}
?>
		<div id="bulk-loading"></div>
		<div id="bulk-progressbar"></div>
		<div id="bulk-counter"></div>
		<div id="bulk-status"></div>
		<div id="bulk-forms"><p>This tool will run all of the images in your media library through the Linux image optimization programs.</p>
<!--		<p>We found <?php //echo sizeof($attachments); ?> images in your media library.</p>-->
		<form id="bulk-start" method="post" action="">
			<?php //wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
			<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
		</form>
<?php
		// check for an abandoned temp file and offer an option to resume
		if (!empty($resume)): 
?>
			<!--<p>It appears that a previous bulk optimization was interrupted. Would you like to continue where we left off?</p>-->
			<p>If you would like to start over again, press the <b>Reset Status</b> button to reset the bulk operation status.</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="reset" value="1">
				<button id="bulk-reset" type="submit" class="button-secondary action">Reset Status</button>
			</form>
<?php
		endif;
	// start the actual optimization process
//	else:
//	endif;
	echo '</div></div>';
}

// prepares the bulk operation and the pulls in the javascript 
function ewww_image_optimizer_bulk_script() {
        // initialize a few variables for the bulk operation
        $attachments = null;
      //  $auto_start = false;
      //  $skip_attachments = false;
        // get the value of the wordpress upload directory
      //  $upload_dir = wp_upload_dir();
        // set the location of our temporary status file
      //  $progress_file = $upload_dir['basedir'] . "/ewww.tmp";
        // check if the bulk operation was given any attachment IDs to work with
	if (!empty($_REQUEST['reset']) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' )) {
		update_option('ewww_image_optimizer_bulk_resume', '');
	}
	$resume = get_option('ewww_image_optimizer_bulk_resume');
        if (!empty($_REQUEST['ids'])) {
                // retrieve post information correlating to the IDs selected
                $attachments = get_posts( array(
                        'numberposts' => -1,
                        'include' => explode(',', $_REQUEST['ids']),
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
			'fields' => 'ids'
                ));
		update_option('ewww_image_optimizer_bulk_resume', '');
                // tell the bulk optimizer to proceed without confirmation
//                $auto_start = true;
        // check if the user asked us to resume a previous bulk operation
        } else if (!empty($resume)) {
		$attachments = get_option('ewww_image_optimizer_bulk_attachments');
                // get the contents of the temp file
//                $progress_contents = file($progress_file);
                // find out the last attachment that was optimized from the temp file
//                $last_attachment = $progress_contents[0];
                // load the post info from the temp file into $attachments
  //              $attachments = unserialize($progress_contents[1]);
                // tell the bulk optimizer to proceed without confirmation
  //              $auto_start = true;
                // tell the optimizer to skip each attachment (until we tell it otherwise)
    //            $skip_attachments = true;
        } else {
                // load up all the attachments we can find
                $attachments = get_posts( array(
                        'numberposts' => -1,
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
			'fields' => 'ids'
                ));
        }
	echo count($attachments);
	// TODO: if WP < 3.1, we will use the 'hacky' way below
/*	$i = 0;
	foreach( $attachments as $attachment ) {
		$new_attachments[$i]['ID'] = $attachment->ID;
		$new_attachments[$i]['post_name'] = $attachment->post_name;
		$i++;
	}
	$attachments = $new_attachments;*/
	update_option('ewww_image_optimizer_bulk_attachments', $attachments);
        // prep $attachments for storing in a file
	wp_enqueue_script('ewwwbulkscript', plugins_url('/pageload.js', __FILE__), array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-progressbar'));
	wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce('ewww-image-optimizer-bulk'),
			'attachments' => $attachments
		)
	);
	wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
//	wp_enqueue_style('jquery-ui');
//	echo "--------------------------------------<br>";
//	print_r($attachments);
//	echo "--------------------------------------<br>";
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_bulk_script');

// called by javascript to start some stuff (may not actually need this)
function ewww_image_optimizer_bulk_initialize() {
		// verify that an authorized user has started the optimizer
//		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
//			wp_die( __( 'Cheatin&#8217; eh?' ) );
//		} // TODO: just link back to the bulk optimize, instead of the resume button... or something
// also implement js counter/status
 ?>
<!--			<form method="post" action="">If the bulk optimize is interrupted, press
				<?php //wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="resume" value="1">
				<button type="submit" class="button-secondary action">resume</button>. If the page is still loading, the bulk action is still running.
			</form>-->
		<?php
//	echo 'whatever';
//	if (empty($_POST['resume'])) {
	//	$attachments = get_option('ewww_image_optimizer_bulk_attachments');
//		update_option('ewww_image_optimizer_bulk_resume', $attachments);
//	} else {
//		$attachments = get_option('ewww_image_optimizer_bulk_resume');
//	}
//	$attachments = $_POST['attachments'];
//        $attach_ser = serialize($attachments);
        // get the value of the wordpress upload directory
 //       $upload_dir = wp_upload_dir();
        // set the location of our temporary status file
   //     $progress_file = $upload_dir['basedir'] . "/ewww.tmp";
				// dump current attachment to temp file
//				file_put_contents($progress_file, "hold\n");
				// dump post info for all attachments to temp file
//				file_put_contents($progress_file, $attach_ser, FILE_APPEND);
	//	print_r($attachments);
		// initialize $current
		//$current = 0;
		update_option('ewww_image_optimizer_bulk_resume', 'true');
		// find out how many attachments we are going to optimize
	//	$total = count($attachments);
	//		print_r ($attachments);
		$loading_image = includes_url('images/wpspin.gif');
		echo "<p>Optimizing&nbsp;<img src='$loading_image' alt='loading'/></p>";
		// tells php to flush the buffers after every output call
//		ob_implicit_flush(true);
		// flush the output buffer and turn off buffering
//		ob_end_flush();
	die();
}

// called by javascript to process each image in the loop
function ewww_image_optimizer_bulk_loop() {
		// retrieve the time when the bulk optimizer starts
		$started = time();
        // get the value of the wordpress upload directory
        $upload_dir = wp_upload_dir();
        // set the location of our temporary status file
        $progress_file = $upload_dir['basedir'] . "/ewww.tmp";
		// process each attachment in $attachments
//		foreach( $attachments as $attachment ) {
			// allow 50 seconds for each image (this doesn't include any exec calls, only php processing time)
			set_time_limit (50);
			$attachment = $_POST['attachment'];
//			print_r($attachment);
//			echo "-----------------<br>";
		$attachments = get_option('ewww_image_optimizer_bulk_attachments');
			//bump $current
//			$current++;
			// if we resumed a previous bulk operation, $last_attachment will be set
//			if (isset($last_attachment)) {
				// once we find the last processed attachment, stop skipping the optimization process
//				if ($last_attachment == $attachment->ID) {$skip_attachments = false;}
//			}
			// let the user know we are skipping attachments until we find the right one
/*			if ($skip_attachments) {
				echo "<p>Skipping $current/$total <br>";
			// once we've found the right attachment
			} else {*/
				// output progress
//				echo "<p>Processing $current/$total: ";
				// output attachment name
				// get the contents of the temp file
//				$progress_contents = file($progress_file);
				// load the post info from the temp file into $attachments
//				$attach_ser = $progress_contents[1];
				// dump current attachment to temp file
//				file_put_contents($progress_file, "$attachment\n");
				// dump post info for all attachments to temp file
//				file_put_contents($progress_file, $attach_ser, FILE_APPEND);
				// do the optimization for the current attachment (including resizes)
				$meta = ewww_image_optimizer_resize_from_meta_data (wp_get_attachment_metadata( $attachment, true ), $attachment);
//				print_r ($meta);
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
				$elapsed = time() - $started;
				// output how much time has elapsed since we started
				echo "Elapsed: $elapsed seconds</p>";
				// update the metadata for the current attachment
				wp_update_attachment_metadata( $attachment, $meta );
		array_shift($attachments);
		update_option('ewww_image_optimizer_bulk_attachments', $attachments);
	//		}
	die();
			// flushes the output buffers (yes, both are necessary)
//			@ob_flush();
//			flush();
//		}
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_bulk_cleanup() {
		update_option('ewww_image_optimizer_bulk_resume', '');
		update_option('ewww_image_optimizer_bulk_attachments', '');
		// we've finished all the attachments, so delete the temp file
//		unlink ($progress_file);
		// and let the user know we are done
		echo '<p><b>Finished</b> - <a href="upload.php">Return to Media Library</a></p>';
	die();
}
add_action('wp_ajax_bulk_init', 'ewww_image_optimizer_bulk_initialize');
add_action('wp_ajax_bulk_loop', 'ewww_image_optimizer_bulk_loop');
add_action('wp_ajax_bulk_cleanup', 'ewww_image_optimizer_bulk_cleanup');
?>
