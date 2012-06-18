<div class="wrap"> 
<div id="icon-upload" class="icon32"><br /></div><h2>Bulk EWWW Image Optimize </h2>
<?php 
if ( sizeof($attachments) < 1 ):
	echo '<p>You don’t appear to have uploaded any images yet.</p>';
else: 
	if ( empty($_POST) && !$auto_start ): // instructions page
?>
		<p>This tool will run all of the images in your media library through the Linux image optimization programs.</p>
		<p>We found <?php echo sizeof($attachments); ?> images in your media library.</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'ewww-image-optimizer-bulk', '_wpnonce'); ?>
			<button type="submit" class="button-secondary action">Run all my images through image optimizers right now</button>
		</form>
<?php
	else: // run the script
		if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'edit_others_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; uh?' ) );
		}
		foreach( $attachments as $attachment ) {
			printf( "<p>Processing <strong>%s</strong>&hellip;<br>", esc_html($attachment->post_name) );
			$meta = ewww_image_optimizer_resize_from_meta_data( wp_get_attachment_metadata( $attachment->ID, true ), $attachment->ID );
					printf( "Full size – %s<br>", $meta['ewww_image_optimizer'] );
			if(isset($meta['sizes']) && is_array($meta['sizes'])){
				foreach( $meta['sizes'] as $size ) {
					printf( "%s – %s<br>", $size['file'], $size['ewww_image_optimizer'] );
				}
			}
			echo "</p>";
			wp_update_attachment_metadata( $attachment->ID, $meta );
		}
		echo '<p><b>Finished</b> - <a href="upload.php">Return to Media Library</a></p>';
	endif;
endif; 
?>
</div>
