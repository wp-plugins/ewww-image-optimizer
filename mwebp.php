<?php
function ewww_image_optimizer_webp_migrate_preview() {
	global $ewww_debug;
?>	<div class="wrap"> 
	<div id="icon-upload" class="icon32"><br /></div><h2><?php _e('Migrate WebP Images', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h2>
<?php		_e( 'The migration is split into two parts. First, the plugin needs to scan all folders for webp images. Once it has obtained the list of images to rename, it will proceed with the renaming' );
	$button_text = __('Start Migration', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// create the html for the bulk optimize form and status divs
?>
		<div id="webp-loading">
		</div>
		<div id="webp-progressbar"></div>
		<div id="webp-counter"></div>
<!--		<form id="bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php _e('Stop Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>" />
		</form>-->
		<div id="webp-status"></div>
			<div id="bulk-forms">
<?php			//if ( ! $resize_count && ! $unoptimized_count && ! $unoptimized_resize_count) { ?>
<?php		//	} else { ?>
<?php	//		} ?>
			<form id="webp-start" class="webp-form" method="post" action="">
				<input id="webp-first" type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
			</form>
	</div>
<?php		
}

// scan a folder for images and return them as an array, second parameter (optional) 
// indicates if we should check the database for already optimized images
function ewww_image_optimizer_webp_scan($dir = null) {
	global $ewww_debug;
//	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_webp_scan()</b><br>";
	$list = Array();
/*	if ( ! empty( $dir ) ) {
		$dir = get_home_path();
		$start = microtime(true);
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
		$file_counter = 0;
		foreach ($iterator as $path) {
			set_time_limit (50);
			if ($path->isDir()) {
				$file_counter++;
				$path = $path->getPathname();
				$list[] = $path;
			} else {
				continue;
			}
		}
	} else {*/
		$dir = get_home_path();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
		$start = microtime(true);
		$file_counter = 0;
		foreach ($iterator as $path) {
			set_time_limit (50);
			$skip_optimized = false;
			if ($path->isDir()) {
				continue;
			} else {
				$file_counter++;
				$path = $path->getPathname();
				//$mimetype = ewww_image_optimizer_mimetype($path, 'i');
				/*if (empty($mimetype) || !preg_match('/^image\/(jpeg|png|gif)/', $mimetype)) {
					$ewww_debug .= "not a usable mimetype: $path<br>";
					continue;
				}*/
				/*foreach($already_optimized as $optimized) {
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
				}*/
				$newwebpformat = preg_replace('/\.webp/', '', $path);
				if ( file_exists( $newwebpformat ) ) {
					continue;
				}
				if ( preg_match( '/\.webp$/', $path ) ) {
					$ewww_debug .= "queued $path<br>";
					$list[] = $path;
				}
			}
		}
	//}
	$end = microtime(true) - $start;
        $ewww_debug .= "query time for $file_counter files (seconds): $end <br>";
	return $list;
}
// retrieve image counts for the bulk process
/*function ewww_image_optimizer_count_optimized ($gallery) {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_count_optmized()</b><br>";
//	$ewww_debug .= "before counting memory usage: " . memory_get_usage(true) . "<br>";
	$full_count = 0;
	$unoptimized_full = 0;
	$unoptimized_re = 0;
	$resize_count = 0;
	$attachment_query = '';
	$ewww_debug .= "scanning for $gallery<br>";
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	$max_query = 3000;
	$attachment_query_count = 0;
	switch ($gallery) {
		case 'media':
			// see if we were given attachment IDs to work with via GET/POST
		        if ( ! empty($_REQUEST['ids']) || get_option('ewww_image_optimizer_bulk_resume')) {
				$ewww_debug .= 'we have preloaded attachment ids<br>';
				// retrieve the attachment IDs that were pre-loaded in the database
				$attachment_ids = get_option('ewww_image_optimizer_bulk_attachments');
				while ( $attachment_ids && $attachment_query_count < $max_query ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					$attachment_query_count++;
				}
				$attachment_query = 'AND metas.post_id IN (' . substr( $attachment_query, 0, -1 ) . ')';
			}
			$offset = 0;
			// retrieve all the image attachment metadata from the database
			while ( $attachments = $wpdb->get_results( "SELECT metas.meta_value FROM $wpdb->postmeta metas INNER JOIN $wpdb->posts posts ON posts.ID = metas.post_id WHERE posts.post_mime_type LIKE '%image%' AND metas.meta_key = '_wp_attachment_metadata' $attachment_query LIMIT $offset, $max_query", ARRAY_N ) ) {
				$ewww_debug .= "fetched " . count( $attachments ) . " attachments starting at $offset<br>";
				foreach ($attachments as $attachment) {
					$meta = unserialize($attachment[0]);
					if (empty($meta)) {
						continue;
					}
					if (empty($meta['ewww_image_optimizer'])) {
						$unoptimized_full++;
					}
					// resized versions, so we can continue
					if (isset($meta['sizes']) ) {
						foreach($meta['sizes'] as $size => $data) {
							$resize_count++;
							if (empty($meta['sizes'][$size]['ewww_image_optimizer'])) {
								$unoptimized_re++;
							}
						}
					}
				}
				$full_count += count($attachments);
				$offset += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query = '';
					$attachment_query_count = 0;
					$offset = 0;
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'AND metas.post_id IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			}
			break;
		case 'ngg':
			// see if we were given attachment IDs to work with via GET/POST
		        if ( ! empty($_REQUEST['inline']) || get_option('ewww_image_optimizer_bulk_ngg_resume')) {
				// retrieve the attachment IDs that were pre-loaded in the database
				$attachment_ids = get_option('ewww_image_optimizer_bulk_ngg_attachments');
				while ( $attachment_ids && $attachment_query_count < $max_query ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					$attachment_query_count++;
				}
				$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
			}
			// creating the 'registry' object for working with nextgen
			$registry = C_Component_Registry::get_instance();
			// creating a database storage object from the 'registry' object
			$storage  = $registry->get_utility('I_Gallery_Storage');
			// get an array of sizes available for the $image
			$sizes = $storage->get_image_sizes();
			$offset = 0;
			while ( $attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->nggpictures $attachment_query LIMIT $offset, $max_query" ) ) {
				foreach ($attachments as $attachment) {
					if (class_exists('Ngg_Serializable')) {
				        	$serializer = new Ngg_Serializable();
				        	$meta = $serializer->unserialize( $attachment );
					} else {
						$meta = unserialize( $attachment );
					}
					if ( ! is_array( $meta ) ) {
						continue;
					}
					if (empty($meta['ewww_image_optimizer'])) {
							$unoptimized_full++;
					}
					foreach ($sizes as $size) {
						if ($size !== 'full') {
							$resize_count++;
							if (empty($meta[$size]['ewww_image_optimizer'])) {
								$unoptimized_re++;
							}
						}
					}
				}
				$full_count += count($attachments);
				$offset += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query = '';
					$attachment_query_count = 0;
					$offset = 0;
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			}
			break;
		case 'flag':
			if ( ! empty( $_REQUEST['doaction'] ) || get_option( 'ewww_image_optimizer_bulk_flag_resume' ) ) {
				// retrieve the attachment IDs that were pre-loaded in the database
				$attachment_ids = get_option('ewww_image_optimizer_bulk_flag_attachments');
				while ( $attachment_ids && $attachment_query_count < $max_query ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					$attachment_query_count++;
				}
				$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
			}
			$offset = 0;
			while ( $attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->flagpictures $attachment_query LIMIT $offset, $max_query" ) ) {
				foreach ($attachments as $attachment) {
					$meta = unserialize( $attachment );
					if ( ! is_array( $meta ) ) {
						continue;
					}
					if (empty($meta['ewww_image_optimizer'])) {
						$unoptimized_full++;
					}
					if (!empty($meta['webview'])) {
						$resize_count++;
						if(empty($meta['webview']['ewww_image_optimizer'])) {
							$unoptimized_re++;
						}
					}
					if (!empty($meta['thumbnail'])) {
						$resize_count++;
						if(empty($meta['thumbnail']['ewww_image_optimizer'])) {
							$unoptimized_re++;
						}
					}
				}
				$full_count += count($attachments);
				$offset += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query = '';
					$attachment_query_count = 0;
					$offset = 0;
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			}
			break;
	}
	if ( empty( $full_count ) && ! empty( $attachment_ids ) ) {
//		return array( count( $attachment_ids ), '', '', '');
		$ewww_debug .= "query appears to have failed, just counting total images instead<br>";
		$full_count = count($attachment_ids);
	}
	$elapsed = microtime(true) - $started;
	$ewww_debug .= "counting images took $elapsed seconds<br>";
	$ewww_debug .= "found $full_count fullsize ($unoptimized_full unoptimized), and $resize_count resizes ($unoptimized_re unoptimized)<br>";
//	$ewww_debug .= "memory allowed: " . ini_get('memory_limit') . "<br>";
//	$ewww_debug .= "after counting memory usage: " . memory_get_usage(true) . "<br>";
	return array( $full_count, $unoptimized_full, $resize_count, $unoptimized_re );
}*/

// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_webp_script($hook) {
	global $ewww_debug;
	global $wpdb;
	// make sure we are being called from the bulk optimization page
	if ('admin_page_ewww-image-optimizer-webp-migrate' != $hook) {
		return;
	}
//	$ewww_debug .= "starting memory usage: " . memory_get_usage(true) . "<br>";
        // initialize the $attachments variable
        $folders = null;
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
/*                $attachments = get_posts( array(
                        'numberposts' => -1,
                        'post_type' => array('attachment', 'ims_image'),
			'post_status' => 'any',
                        'post_mime_type' => 'image',
			'fields' => 'ids'
                ));*/
        }
	$images = ewww_image_optimizer_webp_scan(); 
	// store the attachment IDs we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
	if ( get_option ( 'ewww_image_optimizer_webp_images' ) ) {
		delete_option('ewww_image_optimizer_webp_images');
	}	
	add_option('ewww_image_optimizer_webp_images', '', '', 'no');
	update_option('ewww_image_optimizer_webp_images', $images);
	wp_enqueue_script('ewwwwebpscript', plugins_url('/webp.js', __FILE__), array('jquery', 'jquery-ui-progressbar'));
	$image_count = count($images);
	// submit a couple variables to the javascript to work with
//	$folders = json_encode($folders);
	wp_localize_script('ewwwwebpscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce('ewww-image-optimizer-webp'),
//			'folders' => $folders,
//			'image_count' => $image_count,
		)
	);
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style('jquery-ui-progressbar', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
}

// find the number of images in the ewwwio_images table
/*function ewww_image_optimizer_aux_images_table_count() {
	global $wpdb;
	$count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->ewwwio_images");
	if (!empty($_REQUEST['inline'])) {
		echo $count;
		die();
	}
	return $count;
	
}*/

// called by javascript to initialize some output
function ewww_image_optimizer_webp_initialize() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-webp' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
/*	if (get_option( 'ewww_image_optimizer_webp_images' ) ) { 
		delete_option( 'ewww_image_optimizer_webp_images' );
	}
	add_option( 'ewww_image_optimizer_webp_images', array(), '', 'no' );*/
	if ( get_option( 'ewww_image_optimizer_webp_skipped' ) ) {
		delete_option( 'ewww_image_optimizer_webp_skipped' );
	}
	add_option( 'ewww_image_optimizer_webp_skipped', '', '', 'no' );
	// generate the WP spinner image for display
	$loading_image = plugins_url('/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	echo "<p>" . __('Scanning', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' /></p>";
	die();
}

// called by javascript to process each image in the loop
function ewww_image_optimizer_webp_loop() {
	global $ewww_debug;
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-webp' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	} 
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	// allow 50 seconds for each loop
	set_time_limit (50);
	// get the remaining folders list
//	$folders = get_option('ewww_image_optimizer_webp_folders');
//	$folder = array_pop( $folders );
	$folder = '';
	$images = array();
	if ($folder) {
		$ewww_debug .= 'scanning folders<br>';
		$new_images = ewww_image_optimizer_webp_scan($folder);
		$existing_images = get_option( 'ewww_image_optimizer_webp_images' );
		if ( ! empty( $new_images ) && is_array( $existing_images ) ) {
			$images = array_merge( get_option( 'ewww_image_optimizer_webp_images', ewww_image_optimizer_webp_scan( $folder ) ) );
		}
		update_option('ewww_image_optimizer_webp_images', $images);
		printf( __( 'Found %d Webp images so far.', EWWW_IMAGE_OPTIMIZER_DOMAIN), count( $images ) );
		echo "<br>";
		printf( __( '%d folders remaining to scan.', EWWW_IMAGE_OPTIMIZER_DOMAIN), count( $folders ) );
		$ewww_debug .= 'found ' . count( $images ) . 'webp images so far<br>';
		$ewww_debug .= count( $folders ) . ' left to scan<br>';
	} else {
		$ewww_debug .= 'renaming images now<br>';
		$images_processed = 0;
		$images_skipped = '';
		$images = get_option('ewww_image_optimizer_webp_images');
		if ($images) {
			printf( __( '%d Webp images left to rename.', EWWW_IMAGE_OPTIMIZER_DOMAIN), count( $images ) );
			echo "<br>";
		}
		while ($images) {
			$images_processed++;
			$ewww_debug .= "processed $images_processed images so far<br>";
			if ( $images_processed > 1000 ) {
				$ewww_debug .= "hit 1000, breaking loop";
				break;
			}
			$image = array_pop( $images );
			$replace_base = '';
			$skip = true;
			$pngfile = preg_replace('/webp$/', 'png', $image);
			$PNGfile = preg_replace('/webp$/', 'PNG', $image);
			$jpgfile = preg_replace('/webp$/', 'jpg', $image);
			$jpegfile = preg_replace('/webp$/', 'jpeg', $image);
			$JPGfile = preg_replace('/webp$/', 'JPG', $image);
			//$webpfile = preg_replace('/\.\w+$/', '.webp', $file_path);
			if ( file_exists( $pngfile ) ) {
				$replace_base = $pngfile;
				$skip = false;
			} if ( file_exists( $PNGfile ) ) {
				if ( empty( $replace_base ) ) {
					$replace_base = $PNGfile;
					$skip = false;
				} else {
					$skip = true;
				}
			} if ( file_exists( $jpgfile ) ) {
				if ( empty( $replace_base ) ) {
					$replace_base = $jpgfile;
					$skip = false;
				} else {
					$skip = true;
				}
			} if ( file_exists( $jpegfile ) ) {
				if ( empty( $replace_base ) ) {
					$replace_base = $jpegfile;
					$skip = false;
				} else {
					$skip = true;
				}
			} if ( file_exists( $JPGfile ) ) {
				if ( empty( $replace_base ) ) {
					$replace_base = $JPGfile;
					$skip = false;
				} else {
					$skip = true;
				}
			} 
			if ($skip) {
				if ($replace_base) {
					$ewww_debug .= "multiple replacement options for $image, not renaming<br>";
				} else {
					$ewww_debug .= "no match found for $image, strange...<br>";
				}
				$images_skipped .= "$image<br>";
			} else {
				$ewww_debug .= "renaming $image with match of $replace_base<br>";
				rename( $image, $replace_base . '.webp' );
			}
		}
		if ( $images_skipped ) {
			update_option( 'ewww_image_optimizer_webp_skipped', get_option( 'ewww_image_optimizer_webp_skipped' ) . $images_skipped );
		}
	}
	// calculate how much time has elapsed since we started
	$elapsed = microtime(true) - $started;
	$ewww_debug .= "took $elapsed seconds this time around<br>";
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option('ewww_image_optimizer_webp_images', $images);
//	update_option('ewww_image_optimizer_webp_folders', $folders);
//	$ewww_debug .= "peak memory usage: " . memory_get_peak_usage(true) . "<br>";
	ewww_image_optimizer_debug_log();
	die();
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_webp_cleanup() {
	// verify that an authorized user has started the optimizer
	if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww-image-optimizer-webp' ) || !current_user_can( 'edit_others_posts' ) ) {
		wp_die(__('Cheatin&#8217; eh?', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	$skipped = get_option( 'ewww_image_optimizer_webp_skipped' );
	// all done, so we can update the bulk options with empty values
	delete_option('ewww_image_optimizer_webp_images');
	delete_option('ewww_image_optimizer_webp_folders', '');
	delete_option('ewww_image_optimizer_webp_skipped', '');
	if ( $skipped ) {
		echo '<p><b>' . __('Skipped:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</b></p>';
		echo "<p>$skipped</p>";
	}
	// and let the user know we are done
	echo '<p><b>' . __('Finished', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</b></p>';
	die();
}
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_webp_script');
add_action('wp_ajax_webp_init', 'ewww_image_optimizer_webp_initialize');
//add_action('wp_ajax_webp_filename', 'ewww_image_optimizer_webp_filename');
add_action('wp_ajax_webp_loop', 'ewww_image_optimizer_webp_loop');
add_action('wp_ajax_webp_cleanup', 'ewww_image_optimizer_webp_cleanup');
?>
