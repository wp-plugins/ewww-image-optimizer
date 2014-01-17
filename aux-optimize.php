<?php
// displays the 'auxiliary images' optimizer page
function ewww_image_optimizer_aux_images () {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images()</b><br>";
	// retrieve the attachment IDs that were pre-loaded in the database
//	$attachments = get_option('ewww_image_optimizer_aux_attachments');
	// Retrieve the value of the 'aux resume' option and set the button text for the form to use
	$aux_resume = get_option('ewww_image_optimizer_aux_resume');
	if (empty($aux_resume)) {
		$button_text = __('Start optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	} else {
		$button_text = __('Resume previous optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	}
	$paths = ewww_image_optimizer_get_option('ewww_image_optimizer_aux_paths');
	if (!empty($paths)) {
		foreach ($paths as $path) {
			// retrieve the location of the wordpress upload folder
			$upload_dir = wp_upload_dir();
			// retrieve the path of the upload folder
			$upload_path = str_replace($upload_dir['basedir'], '', $path);
			$upload_path_t = str_replace(trailingslashit($upload_dir['basedir']), '', $path);
			if (preg_match(':' . $upload_dir['basedir'] . ':', $path)) {
//			if (empty($upload_path) || empty($upload_path_t)) {
				$upload_import = true;
				break;
			} else {
				$upload_import = false;
			}
		}
	} else {
		$upload_import = false;
	}
	// find out if the auxiliary image table has anything in it
	$table = $wpdb->prefix . 'ewwwio_images';
	$query = "SELECT id FROM $table LIMIT 1";
	$already_optimized = $wpdb->get_results($query);
	$convert_query = "SELECT image_md5 FROM $table WHERE image_md5 <> ''";
	$db_convert = $wpdb->get_results($convert_query, ARRAY_N);
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// find out what kind of images we are optimizing
	?>
<!--	<div class="wrap">-->
	<div id="icon-themes" class="icon32"><br /></div><h3><?php _e('Optimize Everything Else', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
		<div id="aux-forms"><p class="bulk-info"><?php _e('Use this tool to optimize images outside of the Media Library and galleries where we have full integration. Examples: theme images, BuddyPress, WP Symposium, and any folders that you have specified on the settings page.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
		<?php if (!empty($db_convert)) { ?>
			<p class="bulk-info"><?php _e('The database schema has changed, you need to convert to the new format.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			<form method="post" id="aux-convert" class="bulk-form" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-aux-images', '_wpnonce'); ?>
				<input type="hidden" name="convert" value="1">
				<button id="table-convert" type="submit" class="button-secondary action"><?php _e('Convert Table', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></button>
			</form>
		<?php } ?>	
		<?php //if (empty($attachments)) { ?>
			<p id="ewww-nothing" class="bulk-info" style="display:none"><?php _e('There are no images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			<p id="ewww-scanning" class="bulk-info" style="display:none"><?php _e('Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>&nbsp;<img src='<?php echo $loading_image; ?>' alt='loading'/></p>
		<?php //} else { ?>
<!--			<p><?php //printf(__('We have %d images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN), count($attachments)); ?></p>-->
			<form id="aux-start" class="bulk-form" method="post" action="">
				<input type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
			</form>
			<?php if ($upload_import) { ?>
				<p class="bulk-info"><?php _e('You should import Media Library images into the table to prevent duplicate optimization.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
				<form id="import-start" class="bulk-form" method="post" action="">
					<input type="submit" class="button-secondary action" value="<?php _e('Import Images', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>" />
				</form>
<?php			}
		//}
		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if (!empty($aux_resume)) {
?>
			<p class="bulk-info"><?php _e('If you would like to start over again, press the Reset Status button to reset the bulk operation status.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			<form id="aux-reset" class="bulk-form" method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-aux-images', '_wpnonce'); ?>
				<input type="hidden" name="reset-aux" value="1">
				<button type="submit" class="button-secondary action"><?php _e('Reset Status', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></button>
			</form>
<?php		} 
		if (empty($already_optimized)) {
			$display = ' style="display:none"';
		} else {
			$display = '';
		}
?>
			<p id="table-info" class="bulk-info"<?php echo "$display>" . __('The plugin keeps track of already optimized images to prevent re-optimization. If you would like to re-optimize images, or flush the table for some reason, press the Empty Table button to reset the bulk operation status.'); ?></p>
			<form id="empty-table" class="bulk-form" method="post" action=""<?php echo $display; ?>>
				<?php wp_nonce_field( 'ewww-image-optimizer-aux-images', '_wpnonce'); ?>
				<input type="hidden" name="empty" value="1">
				<button type="submit" class="button-secondary action"><?php _e('Empty Table', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></button>
			</form><br />
			<form id="show-table" class="bulk-form" method="post" action=""<?php echo $display; ?>>
				<button type="submit" class="button-secondary action"><?php _e('Show Optimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></button>
			</form>
			<div class="tablenav aux-table" style="display:none">
			<div class="tablenav-pages aux-table">
			<span class="displaying-num aux-table"></span>
			<span id="paginator" class="pagination-links aux-table">
				<a id="first-images" class="first-page" style="display:none">&laquo;</a>
				<a id="prev-images" class="prev-page" style="display:none">&lsaquo;</a>
				<?php _e('page', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> <span class="current-page"></span> <?php _e('of', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> 
				<span class="total-pages"></span>
				<a id="next-images" class="next-page" style="display:none">&rsaquo;</a>
				<a id="last-images" class="last-page" style="display:none">&raquo;</a>
			</span>
			</div>
			</div>
			<div id="bulk-table" class="aux-table"></div>
			<span id="pointer" style="display:none">0</span>
		</div>
	</div>
<?php
}

// outputs simple messages to the user via javascript
/*function ewww_image_optimizer_aux_images_loading() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_loading()</b><br>";
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning, with an appropriate message
	switch ($_REQUEST['loading_text']) {
		case 'importing':
			echo "<p>" . __('Importing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			echo "<p>" . __('Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			break;
		case 'scanning':
			echo "<p>" . __('Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
			break;
		case 'nothing':
			echo "<p>" . __('Nothing to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>";
			break;
	}
	die();
}*/

function ewww_image_optimizer_aux_images_import() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk')) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	global $wpdb;
	global $ewww_debug;
        // load up all the image attachments we can find
        $attachments = get_posts( array(
		'numberposts' => -1,
		'post_type' => array('attachment', 'ims_image'),
		'post_status' => 'any',
		'post_mime_type' => 'image',
		'fields' => 'ids'
	));
	if (empty($attachments)) {
		_e('Nothing to import', EWWW_IMAGE_OPTIMIZER_DOMAIN);
		die;
	}
	// we need to strip the excess data from attachments, since we only want the attachment IDs
//	$attachments = ewww_image_optimizer_clean_attachments($attachments);
	$ewww_debug .= "importing " . count($attachments) . " attachments<br>";
	foreach ($attachments as $id) {
		// allow 50 seconds for each import
		set_time_limit (50);
		$already_optimized = '';
		$gallery_type = 0;
		$meta = wp_get_attachment_metadata($id);
		list($attachment, $upload_path) = ewww_image_optimizer_attachment_path($meta, $id);
		if (empty($attachment)) continue;
		if ('ims_image' == get_post_type($id)) {
			$gallery_type = 6;
		}
		if (!empty($meta['ewww_image_optimizer']) ) {
			$results = $meta['ewww_image_optimizer'];
		} else {
			$results = '';
		}
		$query = "SELECT id,image_size FROM " . $wpdb->prefix . "ewwwio_images WHERE BINARY path = '$attachment'";
		$already_optimized = $wpdb->get_row($query, ARRAY_A);
		$image_size = filesize($attachment);
		$ewww_debug .= "current attachment: $attachment<br>";
		$ewww_debug .= "current size: $image_size<br>";
		if (!empty($already_optimized))
			$ewww_debug .= "stored size:  " . $already_optimized['image_size'] . "<br>";
		if (empty($already_optimized)) {
			$ewww_debug .= "creating record<br>";
			// store info on the current image for future reference
			$wpdb->insert( $wpdb->prefix . "ewwwio_images", array(
					'path' => $attachment,
					'image_size' => $image_size,
					'results' => $results,
				));
		} elseif ($image_size != $already_optimized['image_size']) {
			$ewww_debug .= "updating record<br>";
			// store info on the current image for future reference
			$wpdb->update( $wpdb->prefix . "ewwwio_images",
				array(
					'image_size' => $image_size,
					'results' => $results,
				),
				array(
					'id' => $already_optimized->id
				));
		}
		// resized versions, so we can continue
		if (isset($meta['sizes']) ) {
			$ewww_debug .= "processing resizes<br>";
			// meta sizes don't contain a path, so we calculate one
			if ($gallery_type === 6) {
				$base_dir = dirname($attachment) . '/_resized/';
			} else {
				$base_dir = dirname($attachment) . '/';
			}
			foreach($meta['sizes'] as $size => $data) {
				$already_optimized = '';
				$resize_path = $base_dir . $data['file'];
				$ewww_debug .= "current resize: $resize_path<br>";
				if (!empty($meta['ewww_image_optimizer']) ) {
					$results = $data['ewww_image_optimizer'];
				} else {
					$results = '';
				}
				$query = "SELECT id,image_size FROM " . $wpdb->prefix . "ewwwio_images WHERE BINARY path = '$resize_path'";
				$already_optimized = $wpdb->get_row($query, ARRAY_A);
				$image_size = filesize($resize_path);
				$ewww_debug .= "current size: $image_size<br>";
				if (!empty($already_optimized))
					$ewww_debug .= "stored size:  " . $already_optimized['image_size'] . "<br>";
				if (empty($already_optimized)) {
					$ewww_debug .= "creating record<br>";
					// store info on the current image for future reference
					$wpdb->insert( $wpdb->prefix . "ewwwio_images", array(
							'path' => $resize_path,
							'image_size' => $image_size,
							'results' => $results,
						));
				} elseif ($image_size != $already_optimized['image_size']) {
					$ewww_debug .= "updating record<br>";
					// store info on the current image for future reference
					$wpdb->update( $wpdb->prefix . "ewwwio_images",
						array(
							'image_size' => $image_size,
							'results' => $results,
						),
						array(
							'id' => $already_optimized->id
						));
				}
			}
		}
		ewww_image_optimizer_debug_log();
	}
	echo "<b>" . __('Finished importing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b>";
	die();
}

function ewww_image_optimizer_aux_images_table() {
	// verify that an authorized user has called function
	if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk')) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	global $wpdb;
	$offset = 50 * $_POST['offset'];
	$query = "SELECT path,results,gallery,id FROM " . $wpdb->prefix . "ewwwio_images ORDER BY id DESC LIMIT $offset,50";
	$already_optimized = $wpdb->get_results($query, ARRAY_N);
        $upload_info = wp_upload_dir();
	$upload_path = $upload_info['basedir'];
	echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>&nbsp;</th><th>' . __('Filename', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Image Type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
	$alternate = true;
	foreach ($already_optimized as $optimized_image) {
		$image_name = str_replace(ABSPATH, '', $optimized_image[0]);
		$image_url = trailingslashit(get_site_url()) . $image_name;
		$savings = $optimized_image[1];
		// if the path given is not the absolute path
		if (file_exists($optimized_image[0])) {
			// retrieve the mimetype of the attachment
			$type = ewww_image_optimizer_mimetype($optimized_image[0], 'i');
			// get a human readable filesize
			$file_size = size_format(filesize($optimized_image[0]), 2);
			$file_size = str_replace('B ', 'B', $file_size);
?>			<tr<?php if($alternate) echo " class='alternate'"; ?> id="image-<?php echo $optimized_image[3]; ?>">
				<td style='width:80px' class='column-icon'><img width='50' height='50' src='<?php echo $image_url; ?>' /></td>
				<td class='title'>...<?php echo $image_name; ?></td>
				<td><?php echo $type; ?></td>
				<td><?php echo "$savings <br>" . sprintf(__('Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_size); ?><br><a class="removeimage" onclick="ewwwRemoveImage(<?php echo $optimized_image[3]; ?>)"><?php _e('Remove from table', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></td>
			</tr>
<?php			$alternate = !$alternate;
		}
	}
	echo '</table>';
	die();
}

function ewww_image_optimizer_aux_images_remove() {
	// verify that an authorized user has called function
	if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk')) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	global $wpdb;
	if ($wpdb->query("DELETE FROM " . $wpdb->prefix . "ewwwio_images WHERE id = " . $_POST['image_id'])) {
		echo "1";
	}
	die();
}

// scan a folder for images and return them as an array, second parameter (optional) 
// indicates if we should check the database for already optimized images
function ewww_image_optimizer_image_scan($dir) {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_image_scan()</b><br>";
	$images = Array();
	if (!is_dir($dir))
		return $images;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
	$start = microtime(true);
	$query = 'SELECT path,image_size FROM ' . $wpdb->prefix . 'ewwwio_images';
	$already_optimized = $wpdb->get_results($query, ARRAY_A);
	$file_counter = 0;
	foreach ($iterator as $path) {
		set_time_limit (50);
		$file_counter++;
		$skip_optimized = false;
		if ($path->isDir()) {
			continue;
		} else {
			$path = $path->getPathname();
			$mimetype = ewww_image_optimizer_mimetype($path, 'i');
			if (empty($mimetype) || !preg_match('/^image\/(jpeg|png|gif)/', $mimetype)) {
				$ewww_debug .= "not a usable mimetype: $path<br>";
				continue;
			}
			foreach($already_optimized as $optimized) {
				if ($optimized['path'] === $path) {
					$image_size = filesize($path);
					if ($optimized['image_size'] == $image_size) {
						$ewww_debug .= "match found for $path<br>";
						$skip_optimized = true;
						break;
					} else {
						$ewww_debug .= "mismatch found for $path, db says " . $optimized['image_size'] . " vs. current $image_size<br>";
					}
				}
			}
			if (empty($skip_optimized)) {
				$ewww_debug .= "queued $path<br>";
				$images[] = $path;
			}
		}
	}
	$end = microtime(true) - $start;
        $ewww_debug .= "query time for $file_counter files (seconds): $end <br>";
	return $images;
}

// convert all records in table to use filesize rather than md5sum
function ewww_image_optimizer_aux_images_convert() {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_convert()</b><br>";
	$query = 'SELECT id,path,image_md5 FROM ' . $wpdb->prefix . 'ewwwio_images';
	$old_records = $wpdb->get_results($query, ARRAY_A);
	foreach ($old_records as $record) {
		if (empty($record['image_md5'])) continue;
		$image_md5 = md5_file($record['path']);
		if ($image_md5 === $record['image_md5']) {
			$ewww_debug .= 'converting record for: ' . $record['path'] . '<br>';
			$filesize = filesize($record['path']);
			$ewww_debug .= 'using size: ' . $filesize . '<br>';
			$wpdb->update($wpdb->prefix . "ewwwio_images",
				array(
					'image_md5' => null,
					'image_size' => $filesize,
				),
				array(
					'id' => $record['id'],
				));
		} else {
			$ewww_debug .= 'deleting record for: ' . $record['path'] . '<br>';
			$wpdb->delete($wpdb->prefix . "ewwwio_images",
				array(
					'id' => $record['id'],
				));
		}
	}
}
 
// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_aux_images_script($hook) {
	// make sure we are being called from the proper page
	if ('appearance_page_ewww-image-optimizer-theme-images' !== $hook && 'media_page_ewww-image-optimizer-buddypress-images' !== $hook && 'media_page_ewww-image-optimizer-symposium-images' !== $hook && 'tools_page_ewww-image-optimizer-aux-images' !== $hook && empty($_REQUEST['scan'])) {
		return;
	}
	global $ewww_debug;
	global $wpdb;
	// allow 150 seconds for import
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_script()</b><br>";
	// initialize the $attachments variable for auxiliary images
	$attachments = null;
	// check the 'bulk resume' option
	$resume = get_option('ewww_image_optimizer_aux_resume');
        // check if there is a previous bulk operation to resume
        if (!empty($resume)) {
		// retrieve the attachment IDs that have not been finished from the 'bulk attachments' option
		$attachments = get_option('ewww_image_optimizer_aux_attachments');
	} else {
		// collect a list of images from the current theme
		$child_path = get_stylesheet_directory();
		$parent_path = get_template_directory();
		$attachments = ewww_image_optimizer_image_scan($child_path); 
		if ($child_path !== $parent_path) {
			$attachments = array_merge($attachments, ewww_image_optimizer_image_scan($parent_path));
		}
		// collect a list of images in auxiliary folders provided by user
		if ($aux_paths = get_site_option('ewww_image_optimizer_aux_paths')) {
			foreach ($aux_paths as $aux_path) {
				$attachments = array_merge($attachments, ewww_image_optimizer_image_scan($aux_path));
			}
		}
	
		// collect a list of images for buddypress
		if (is_plugin_active('buddypress/bp-loader.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('buddypress/bp-loader.php'))) {
			// get the value of the wordpress upload directory
		        $upload_dir = wp_upload_dir();
			// scan the 'avatars' and 'group-avatars' folders for images
			$attachments = array_merge($attachments, ewww_image_optimizer_image_scan($upload_dir['basedir'] . '/avatars'), ewww_image_optimizer_image_scan($upload_dir['basedir'] . '/group-avatars'));
		}
		if (is_plugin_active('buddypress-activity-plus/bpfb.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('buddypress-activity-plus/bpfb.php'))) {
			// get the value of the wordpress upload directory
		        $upload_dir = wp_upload_dir();
			// scan the 'avatars' and 'group-avatars' folders for images
			$attachments = array_merge($attachments, ewww_image_optimizer_image_scan($upload_dir['basedir'] . '/bpfb'));
		}
		if (is_plugin_active('wp-symposium/wp-symposium.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('wp-symposium/wp-symposium.php'))) {
			$attachments = array_merge($attachments, ewww_image_optimizer_image_scan(get_option('symposium_img_path')));
		}
		if (is_plugin_active('ml-slider/ml-slider.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ml-slider/ml-slider.php'))) {
			$slide_paths = array();
	                $sliders = get_posts(array(
	                        'numberposts' => -1,
	                        'post_type' => 'ml-slider',
				'post_status' => 'any',
				'fields' => 'ids'
	                ));
			// we need to strip the excess data from attachments, since we only want the attachment IDs
//			$sliders = ewww_image_optimizer_clean_attachments($sliders);
			foreach ($sliders as $slider) {
				$slides = get_posts(array(
	                        	'numberposts' => -1,
					'orderby' => 'menu_order',
					'order' => 'ASC',
					'post_type' => 'attachment',
					'post_status' => 'inherit',
					'fields' => 'ids',
					'tax_query' => array(
							array(
								'taxonomy' => 'ml-slider',
								'field' => 'slug',
								'terms' => $slider
							)
						)
					)
				);
				// we need to strip the excess data from attachments, since we only want the attachment IDs
//				$slides = ewww_image_optimizer_clean_attachments($slides);
				foreach ($slides as $slide) {
					$backup_sizes = get_post_meta($slide, '_wp_attachment_backup_sizes', true);
					$type = get_post_meta($slide, 'ml-slider_type', true);
					$type = $type ? $type : 'image'; // backwards compatibility, fall back to 'image'
					if ($type === 'image') {
						foreach ($backup_sizes as $backup_size => $meta) {
							if (preg_match('/resized-/', $backup_size)) {
								$path = $meta['path'];
								$image_size = filesize($path);
								$query = "SELECT id FROM " . $wpdb->prefix . 'ewwwio_images' . " WHERE BINARY path LIKE '$path' AND image_size LIKE '$image_size'";
								$already_optimized = $wpdb->get_results($query);
								$mimetype = ewww_image_optimizer_mimetype($path, 'i');
								if (preg_match('/^image\/(jpeg|png|gif)/', $mimetype) && empty($already_optimized)) {
									$slide_paths[] = $path;
								}
							}
						}
					}
				}
			}
			$attachments = array_merge($attachments, $slide_paths);
		}
		// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
		update_option('ewww_image_optimizer_aux_attachments', $attachments);
	}
	wp_enqueue_script('ewwwjuiscript', plugins_url('/jquery-ui-1.10.2.custom.min.js', __FILE__), false);
	wp_enqueue_script('ewwwbulkscript', plugins_url('/eio.js', __FILE__), array('jquery'));
	// submit a couple variables to the javascript to work with
	$attachments = json_encode($attachments);
	if (!empty($_REQUEST['scan'])) {
		if (empty($attachments)) {
			_e('Nothing to optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN);
		} else {
			echo $attachments;
		}
		die();
	} else {
		wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
				'_wpnonce' => wp_create_nonce('ewww-image-optimizer-aux-images'),
				'gallery' => 'aux',
				'attachments' => $attachments,
				'image_count' => $image_count,
			)
		);
	}
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
	wp_enqueue_style('colors-css');
}

// called by javascript to initialize some output
function ewww_image_optimizer_aux_images_initialize($auto = false) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_initialize()</b><br>";
	// verify that an authorized user has started the optimizer
	if (!$auto && (!wp_verify_nonce($_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk') || !current_user_can('install_themes'))) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	// update the 'aux resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_aux_resume', 'true');
	// let the user know that we are beginning
	if (!$auto) {
		// generate the WP spinner image for display
		$loading_image = plugins_url('/wpspin.gif', __FILE__);
		echo "<p>" . __('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' alt='loading'/></p>";
		die();
	}
}

// called by javascript to output filename of attachment in progress
function ewww_image_optimizer_aux_images_filename() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_filename()</b><br>";
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'install_themes' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>" . __('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <b>" . $_POST['attachment'] . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
	die();
}
 
// called by javascript to process each image in the loop
function ewww_image_optimizer_aux_images_loop($attachment = null, $auto = false) {
	global $wpdb;
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_loop()</b><br>";
	// verify that an authorized user has started the optimizer
	if (!$auto && (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'install_themes' ))) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	if (!empty($_REQUEST['sleep'])) {
		sleep($_REQUEST['sleep']);
	}
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	// allow 50 seconds for each image (this doesn't include any exec calls, only php processing time)
	set_time_limit (50);
	// get the path of the current attachment
	if (empty($attachment)) $attachment = $_POST['attachment'];
	// get the 'aux attachments' with a list of attachments remaining
	$attachments = get_option('ewww_image_optimizer_aux_attachments');
	// do the optimization for the current image
	$results = ewww_image_optimizer($attachment, 4, false, false);
	$query = "SELECT id FROM " . $wpdb->prefix . "ewwwio_images WHERE BINARY path = '$attachment'";
	$already_optimized = $wpdb->get_row($query);
	if (empty($already_optimized)) {
		$ewww_debug .= "creating new record, path: $attachment, size: " . filesize($attachment) . "<br>";
		// store info on the current image for future reference
		$wpdb->insert( $wpdb->prefix . "ewwwio_images", array(
				'path' => $attachment,
				'image_size' => filesize($attachment),
				'results' => $results[1],
			));
	} else {
		$ewww_debug .= "updating existing record, path: $attachment, size: " . filesize($attachment) . "<br>";
		// store info on the current image for future reference
		$wpdb->update( $wpdb->prefix . "ewwwio_images",
			array(
				'image_size' => filesize($attachment),
				'results' => $results[1],
			),
			array(
				'id' => $already_optimized->id
			));
	}
	// remove the first element fromt the $attachments array
	if (!empty($attachments))
		array_shift($attachments);
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option('ewww_image_optimizer_aux_attachments', $attachments);
	if (!$auto) {
		// output the path
		printf( "<p>" . __('Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <strong>%s</strong><br>", esc_html($attachment) );
		// tell the user what the results were for the original image
		printf( "%s<br>", $results[1] );
		// calculate how much time has elapsed since we started
		$elapsed = microtime(true) - $started;
		// output how much time has elapsed since we started
		printf(__('Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>", $elapsed);
		if (get_site_option('ewww_image_optimizer_debug')) echo '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
		die();
	}
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_aux_images_cleanup($auto = false) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_images_cleanup()</b><br>";
	// verify that an authorized user has started the optimizer
	if (!$auto && (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-bulk' ) || !current_user_can( 'install_themes' ))) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_aux_resume', '');
	update_option('ewww_image_optimizer_aux_attachments', '');
	if (!$auto) {
		// and let the user know we are done
		echo '<p><b>' . __('Finished', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</b></p>';
		die();
	}
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_aux_images_script');
add_action('wp_ajax_bulk_aux_images_scan', 'ewww_image_optimizer_aux_images_script');
//add_action('wp_ajax_bulk_aux_images', 'ewww_image_optimizer_aux_images');
add_action('wp_ajax_bulk_aux_images_table', 'ewww_image_optimizer_aux_images_table');
add_action('wp_ajax_bulk_aux_images_table_count', 'ewww_image_optimizer_aux_images_table_count');
add_action('wp_ajax_bulk_aux_images_remove', 'ewww_image_optimizer_aux_images_remove');
add_action('wp_ajax_bulk_aux_images_import', 'ewww_image_optimizer_aux_images_import');
add_action('wp_ajax_bulk_aux_images_loading', 'ewww_image_optimizer_aux_images_loading');
add_action('wp_ajax_bulk_aux_images_init', 'ewww_image_optimizer_aux_images_initialize');
add_action('wp_ajax_bulk_aux_images_filename', 'ewww_image_optimizer_aux_images_filename');
add_action('wp_ajax_bulk_aux_images_loop', 'ewww_image_optimizer_aux_images_loop');
add_action('wp_ajax_bulk_aux_images_cleanup', 'ewww_image_optimizer_aux_images_cleanup');
?>
