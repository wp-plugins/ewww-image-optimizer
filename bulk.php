<div class="wrap"> 
<div id="icon-upload" class="icon32"><br /></div><h2>Bulk EWWW Image Optimize </h2>
<?php 
// make sure there are some attachments to optimize
if ( sizeof($attachments) < 1 ):
	echo '<p>You don’t appear to have uploaded any images yet.</p>';
else: 
	// we have attachments to work with, but need to ask for confirmation first
	if ( empty($_POST) && !$auto_start ):
?>
		<p>This tool will run all of the images in your media library through the Linux image optimization programs.</p>
		<p>We found <?php echo sizeof($attachments); ?> images in your media library.</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
			<button type="submit" class="button-secondary action">Run all my images through image optimizers right now</button>
		</form>
<?php
		// check for an abandoned temp file and offer an option to resume
		if (file_exists($progress_file)): 
?>
			<p>It appears that a previous bulk optimization was interrupted. Would you like to continue where we left off?</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="resume" value="1">
				<button type="submit" class="button-secondary action">Resume previous operation.</button>
			</form>
<?php
		endif;
	// start the actual optimization process
	else:
		// verify that an authorized user has started the optimizer
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; eh?' ) );
		} ?>
			<form method="post" action="">If the bulk optimize is interrupted, press
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
				<input type="hidden" name="resume" value="1">
				<button type="submit" class="button-secondary action">resume</button>. If the page is still loading, the bulk action is still running.
			</form>
		<?php
		// initialize $current
		$current = 0;
		// retrieve the time when the bulk optimizer starts
		$started = time();
		// find out how many attachments we are going to optimize
		$total = sizeof($attachments);
		// start a live javascript timer
		?>
		<!--<script type="text/javascript">
			document.write('Bulk Optimization has taken <span id="endTime">0.0</span> seconds.');
			var loopTime=setInterval("currentTime()",100);
		</script>-->
		<?php
		@apache_setenv('deflatebuffersize','100');
		// tells php to flush the buffers after every output call
		ob_implicit_flush(true);
		// flush the output buffer and turn off buffering
		ob_end_flush();
		// process each attachment in $attachments
		foreach( $attachments as $attachment ) {
			// allow 50 seconds for each image (this doesn't include any exec calls, only php processing time)
			set_time_limit (50);
			//bump $current
			$current++;
			// if we resumed a previous bulk operation, $last_attachment will be set
			if (isset($last_attachment)) {
				// once we find the last processed attachment, stop skipping the optimization process
				if ($last_attachment == $attachment->ID) {$skip_attachments = false;}
			}
			// let the user know we are skipping attachments until we find the right one
			if ($skip_attachments) {
				echo "<p>Skipping $current/$total <br>";
			// once we've found the right attachment
			} else {
				// output progress
				echo "<p>Processing $current/$total: ";
				// output attachment name
				printf( "<strong>%s</strong>&hellip;<br>", esc_html($attachment->post_name) );
				// dump current attachment to temp file
				file_put_contents($progress_file, "$attachment->ID\n");
				// dump post info for all attachments to temp file
				file_put_contents($progress_file, $attach_ser, FILE_APPEND);
				// do the optimization for the current attachment (including resizes)
				$meta = ewww_image_optimizer_resize_from_meta_data (wp_get_attachment_metadata( $attachment->ID, true ), $attachment->ID);
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
				wp_update_attachment_metadata( $attachment->ID, $meta );
			}
			// flushes the output buffers (yes, both are necessary)
			@ob_flush();
			flush();
		}
		// we've finished all the attachments, so delete the temp file
		unlink ($progress_file);
		// and let the user know we are done
		echo '<p><b>Finished</b> - <a href="upload.php">Return to Media Library</a></p>';
	endif;
endif; 
?>
</div>
