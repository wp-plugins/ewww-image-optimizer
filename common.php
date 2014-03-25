<?php
// common functions for Standard and Cloud plugins

// initialize debug global
$disabled = ini_get('disable_functions');
if (preg_match('/get_current_user/', $disabled)) {
	$ewww_debug = '';
} else {
	$ewww_debug = get_current_user() . '<br>';
}

// check the WP version (mostly for debugging purposes
global $wp_version;
$my_version = substr($wp_version, 0, 3);
$ewww_debug .= "WP version: $wp_version<br>";
global $wpdb;
if (!isset($wpdb->ewwwio_images)) {
	$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
}

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 60, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
// variable for plugin settings link
$plugin = plugin_basename (EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE);
add_filter("plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link');
add_action('manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2);
add_action('admin_init', 'ewww_image_optimizer_admin_init');
add_action('admin_action_ewww_image_optimizer_manual_optimize', 'ewww_image_optimizer_manual');
add_action('admin_action_ewww_image_optimizer_manual_restore', 'ewww_image_optimizer_manual');
add_action('admin_action_ewww_image_optimizer_manual_convert', 'ewww_image_optimizer_manual');
add_action('delete_attachment', 'ewww_image_optimizer_delete');
add_action('admin_menu', 'ewww_image_optimizer_admin_menu', 60);
add_action('network_admin_menu', 'ewww_image_optimizer_network_admin_menu');
add_action('admin_head-upload.php', 'ewww_image_optimizer_add_bulk_actions_via_javascript'); 
add_action('admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler'); 
add_action('admin_action_-1', 'ewww_image_optimizer_bulk_action_handler'); 
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_media_scripts');
add_action('ewww_image_optimizer_auto', 'ewww_image_optimizer_auto');
add_action( 'wr2x_retina_file_added', 'ewww_image_optimizer_retina', 20, 2 );
add_filter('wp_image_editors', 'ewww_image_optimizer_load_editor', 60);
register_deactivation_hook(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_network_deactivate');

// need to include the plugin library for the is_plugin_active function
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
// include the file that loads the nextgen gallery optimization functions
if (is_plugin_active('nextgen-gallery/nggallery.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('nextgen-gallery/nggallery.php'))) {
	$nextgen_data = get_plugin_data(trailingslashit(WP_PLUGIN_DIR) . 'nextgen-gallery/nggallery.php', false, false);
		$ewww_debug .= 'Nextgen version: ' . $nextgen_data['Version'] . '<br>';
	if (preg_match('/^2\./', $nextgen_data['Version'])) { // for Nextgen 2
		require(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'nextgen2-integration.php');
	} else {
		require(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'nextgen-integration.php');
	}
}

// include the file that loads the grand flagallery optimization functions
if (is_plugin_active('flash-album-gallery/flag.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('flash-album-gallery/flag.php'))) {
	require( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'flag-integration.php' );
}

// sets all the tool constants to false
function ewww_image_optimizer_disable_tools() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_disable_tools()</b><br>";
	define('EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false);
	define('EWWW_IMAGE_OPTIMIZER_OPTIPNG', false);
	define('EWWW_IMAGE_OPTIMIZER_PNGOUT', false);
	define('EWWW_IMAGE_OPTIMIZER_GIFSICLE', false);
}

// determines the proper color to use for progressbars, then includes css inline
function ewww_image_optimizer_progressbar_style() {
	if (function_exists('wp_add_inline_style')) {
		$user_info = wp_get_current_user();
		switch($user_info->admin_color) {
			case 'midnight':
				$fill_style = ".ui-widget-header { background-color: #e14d43; }";
				break;
			case 'blue':
				$fill_style = ".ui-widget-header { background-color: #096484; }";
				break;
			case 'light':
				$fill_style = ".ui-widget-header { background-color: #04a4cc; }";
				break;
			case 'ectoplasm':
				$fill_style = ".ui-widget-header { background-color: #a3b745; }";
				break;
			case 'coffee':
				$fill_style = ".ui-widget-header { background-color: #c7a589; }";
				break;
			case 'ocean':
				$fill_style = ".ui-widget-header { background-color: #9ebaa0; }";
				break;
			case 'sunrise':
				$fill_style = ".ui-widget-header { background-color: #dd823b; }";
				break;
			default:
				$fill_style = ".ui-widget-header { background-color: #0074a2; }";
		}
		wp_add_inline_style('jquery-ui-progressbar', $fill_style);
	}
}

// tells WP to ignore the 'large network' detection by filtering the results of wp_is_large_network()
function ewww_image_optimizer_large_network() {
	return false;
}

// adds table to db for storing status of auxiliary images that have been optimized
function ewww_image_optimizer_install_table() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_install_table()</b><br>";
	global $wpdb;
	
	// create a table with 4 columns: an id, the file path, the md5sum, and the optimization results
	$sql = "CREATE TABLE $wpdb->ewwwio_images (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		path text NOT NULL,
		image_md5 VARCHAR(55),
		results VARCHAR(55) NOT NULL,
		gallery VARCHAR(30),
		image_size int UNSIGNED,
		orig_size int UNSIGNED,
		UNIQUE KEY id (id)
	);";

	// include the upgrade library to initialize a table
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	// remove extra decimals in ewwwio_images table
	/*$query = "SELECT id,results FROM $wpdb->ewwwio_images WHERE results LIKE '%.0&nbsp;B)%'";
	$old_records = $wpdb->get_results($query, ARRAY_A);
	foreach ($old_records as $record) {
			$ewww_debug .= 'converting record: ' . $record['id'] . '<br>';
			$savings = preg_replace('/\.0&nbsp;B/', '&nbsp;B', $record['results']);
			$ewww_debug .= 'using string: ' . $savings . '<br>';
			$wpdb->update($wpdb->ewwwio_images,
				array(
					'results' => $savings,
				),
				array(
					'id' => $record['id'],
				));
	}*/
			//	ewww_image_optimizer_debug_log();
	// make sure some of our options are not autoloaded (since they can be huge)
	$bulk_attachments = get_option('ewww_image_optimizer_bulk_attachments', '');
	delete_option('ewww_image_optimizer_bulk_attachments');
	add_option('ewww_image_optimizer_bulk_attachments', $bulk_attachments, '', 'no');
	$bulk_attachments = get_option('ewww_image_optimizer_flag_attachments', '');
	delete_option('ewww_image_optimizer_flag_attachments');
	add_option('ewww_image_optimizer_flag_attachments', $bulk_attachments, '', 'no');
	$bulk_attachments = get_option('ewww_image_optimizer_ngg_attachments', '');
	delete_option('ewww_image_optimizer_ngg_attachments');
	add_option('ewww_image_optimizer_ngg_attachments', $bulk_attachments, '', 'no');
	$bulk_attachments = get_option('ewww_image_optimizer_aux_attachments', '');
	delete_option('ewww_image_optimizer_aux_attachments');
	add_option('ewww_image_optimizer_aux_attachments', $bulk_attachments, '', 'no');
}

// lets the user know their network settings have been saved
function ewww_image_optimizer_network_settings_saved() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_network_settings_saved()</b><br>";
	echo "<div id='ewww-image-optimizer-settings-saved' class='updated fade'><p><strong>" . __('Settings saved', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</strong></p></div>";
}   

// load the class to extend WP_Image_Editor
function ewww_image_optimizer_load_editor($editors) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_load_editor()</b><br>";
	if (!class_exists('EWWWIO_GD_Editor') && !class_exists('EWWWIO_Imagick_Editor'))
		include_once(plugin_dir_path(__FILE__) . '/image-editor.php');
	if (!in_array('EWWWIO_GD_Editor', $editors))
		array_unshift($editors, 'EWWWIO_GD_Editor');
	if (!in_array('EWWWIO_Imagick_Editor', $editors))
		array_unshift($editors, 'EWWWIO_Imagick_Editor');
	if (!in_array('EWWWIO_Gmagick_Editor', $editors) && class_exists('WP_Image_Editor_Gmagick'))
		array_unshift($editors, 'EWWWIO_Gmagick_Editor');
	$ewww_debug .= "loading image editors: " . print_r($editors, true) . "<br>";
	return $editors;
}

// runs scheduled optimization of various auxiliary images
function ewww_image_optimizer_auto() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_auto()</b><br>";
	require_once(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php');
	require_once(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php');
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE) {
		$ewww_debug .= "running scheduled optimization<br>";
		update_option('ewww_image_optimizer_aux_resume', '');
		update_option('ewww_image_optimizer_aux_attachments', '');
		ewww_image_optimizer_aux_images_script('ewww-image-optimizer-auto');
		ewww_image_optimizer_aux_images_initialize(true);
		$delay = ewww_image_optimizer_get_option('ewww_image_optimizer_delay');		
		$attachments = get_option('ewww_image_optimizer_aux_attachments');
		foreach ($attachments as $attachment) {
			if (!get_option('ewww_image_optimizer_aux_resume')) {
				ewww_image_optimizer_debug_log();
				return;
			}
			ewww_image_optimizer_aux_images_loop($attachment, true);
			if (!empty($delay)) {
				sleep($delay);
			}
		}	
		ewww_image_optimizer_aux_images_cleanup(true);
		ewww_image_optimizer_debug_log();
	}
	return;
}

// removes the network settings when the plugin is deactivated
function ewww_image_optimizer_network_deactivate($network_wide) {
	global $wpdb;
	wp_clear_scheduled_hook('ewww_image_optimizer_auto');
	if ($network_wide) {
		$query = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE site_id = '$wpdb->siteid'");
		$blogs = $wpdb->get_results($query, ARRAY_A);
		foreach ($blogs as $blog) {
			switch_to_blog($blog['blog_id']);
			wp_clear_scheduled_hook('ewww_image_optimizer_auto');
		}
		restore_current_blog();
	}
}

// adds a global settings page to the network admin settings menu
function ewww_image_optimizer_network_admin_menu() {
	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(plugin_basename(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE))) {
		// add options page to the settings menu
		$ewww_network_options_page = add_submenu_page(
			'settings.php',				//slug of parent
			'EWWW Image Optimizer',			//Title
			'EWWW Image Optimizer',			//Sub-menu title
			'manage_network_options',		//Security
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,				//File to open
			'ewww_image_optimizer_options'	//Function to call
		);
		add_action('admin_footer-' . $ewww_network_options_page, 'ewww_image_optimizer_debug');
	} 
}

// adds the bulk optimize and settings page to the admin menu
function ewww_image_optimizer_admin_menu() {
	// adds bulk optimize to the media library menu
	$ewww_bulk_page = add_media_page(__('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'edit_others_posts', 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview');
	add_action('admin_footer-' . $ewww_bulk_page, 'ewww_image_optimizer_debug');
	if (!function_exists('is_plugin_active_for_network') || !is_plugin_active_for_network(plugin_basename(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE))) { 
		// add options page to the settings menu
		$ewww_options_page = add_options_page(
			'EWWW Image Optimizer',		//Title
			'EWWW Image Optimizer',		//Sub-menu title
			'manage_options',		//Security
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,			//File to open
			'ewww_image_optimizer_options'	//Function to call
		);
		add_action('admin_footer-' . $ewww_options_page, 'ewww_image_optimizer_debug');
	}
	if(is_plugin_active('image-store/ImStore.php') || is_plugin_active_for_network('image-store/ImStore.php')) {
		$ims_menu ='edit.php?post_type=ims_gallery';
		$ewww_ims_page = add_submenu_page($ims_menu, __('Image Store Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'ims_change_settings', 'ewww-ims-optimize', 'ewww_image_optimizer_ims');
		add_action('admin_footer-' . $ewww_ims_page, 'ewww_image_optimizer_debug');
	}
}

// check WP Retina images, fixes filenames in the database, and makes sure all derivatives are optimized
function ewww_image_optimizer_retina ( $id, $retina_path ) {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_retina()<b><br>";
	$file_info = pathinfo( $retina_path );
	$extension = '.' . $file_info['extension'];
	preg_match ('/-(\d+x\d+)@2x$/', $file_info['filename'], $fileresize);
	$dimensions = explode ( 'x', $fileresize[1]);
	$no_ext_path = $file_info['dirname'] . '/' . preg_replace('/\d+x\d+@2x$/', '', $file_info['filename']) . $dimensions[0] * 2 . 'x' . $dimensions[1] * 2 . '-tmp';
	$temp_path = $no_ext_path . $extension;
	$ewww_debug .= "temp path: $temp_path<br>";
	$ewww_debug .= "retina path: $retina_path<br>";
//	$retina_path = $no_ext_path . "@2x" . $extension;
	$opt_size = filesize($retina_path);
	$ewww_debug .= "retina size: $opt_size<br>";
	$query = $wpdb->prepare("SELECT id FROM $wpdb->ewwwio_images WHERE BINARY path = %s AND image_size = '$opt_size'", $temp_path);
	$already_optimized = $wpdb->get_var($query);
	if (!empty($already_optimized)) {
		// store info on the current image for future reference
		$wpdb->update( $wpdb->ewwwio_images,
			array(
				'path' => $retina_path,
			),
			array(
				'id' => $already_optimized,
			));
	} else {
		ewww_image_optimizer($retina_path, 7, false, false);
	}
}

// list IMS images and optimization status
// TODO: check out new hooks in 3.4.5 and see if they are useful to us
function ewww_image_optimizer_ims() {
	$ims_columns = get_column_headers('ims_gallery');
	echo "<h3>" . __('Image Store Optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</h3>";
	if (empty($_REQUEST['gid'])) {
		$galleries = get_posts( array(
	                'numberposts' => -1,
	                'post_type' => 'ims_gallery',
			'post_status' => 'any',
			'fields' => 'ids'
	        ));
		sort($galleries, SORT_NUMERIC);
		$gallery_string = implode(',', $galleries);
		echo "<p>" . __('Choose a gallery or', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gallery_string'>" . __('optimize all galleries', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></p>";
		echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . __('Gallery ID', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Gallery Name', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Images', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
			foreach ($galleries as $gid) {
		                $attachments = get_posts( array(
		                        'numberposts' => -1,
		                        'post_type' => 'ims_image',
					'post_status' => 'any',
		                        'post_mime_type' => 'image',
					'post_parent' => $gid,
					'fields' => 'ids'
		                ));
				$image_count = sizeof($attachments);
				$image_string = implode(',', $attachments);
				$gallery_name = get_the_title($gid);
				echo "<tr><td>$gid</td>";
				echo "<td><a href='edit.php?post_type=ims_gallery&page=ewww-ims-optimize&gid=$gid'>$gallery_name</a></td>";
				echo "<td>$image_count</td>";
				echo "<td><a href='upload.php?page=ewww-image-optimizer-bulk&ids=$image_string'>" . __('Optimize Gallery', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></td></tr>";
			}
			echo "</table>";
		} else {		
			$gid = $_REQUEST['gid'];
	                $attachments = get_posts( array(
	                        'numberposts' => -1,
	                        'post_type' => 'ims_image',
				'post_status' => 'any',
	                        'post_mime_type' => 'image',
				'post_parent' => $gid,
				'fields' => 'ids'
	                ));
			sort($attachments, SORT_NUMERIC);
			$image_string = implode(',', $attachments);
			echo "<p><a href='upload.php?page=ewww-image-optimizer-bulk&ids=$image_string'>" . __('Optimize Gallery', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></p>";
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>ID</th><th>&nbsp;</th><th>' . __('Title', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Gallery', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . __('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
			$alternate = true;
			foreach ($attachments as $ID) {
				$meta = get_metadata('post', $ID);
				$meta = unserialize($meta['_wp_attachment_metadata'][0]);
				$image_name = get_the_title($ID);
				$gallery_name = get_the_title($gid);
				$image_url = $meta['sizes']['mini']['url'];
				$echo_meta = print_r($meta, true);
				$echo_meta = preg_replace('/\n/', '<br>', $echo_meta);
				$echo_meta = preg_replace('/ /', '&nbsp;', $echo_meta);
				$echo_meta = '';
?>				<tr<?php if($alternate) echo " class='alternate'"; ?>><td><?php echo $ID; ?></td>
<?php				echo "<td style='width:80px' class='column-icon'><img src='$image_url' /></td>";
				echo "<td class='title'>$image_name</td>";
				echo "<td>$gallery_name</td><td>";
				ewww_image_optimizer_custom_column('ewww-image-optimizer', $ID);
				echo "</td></tr>";
				$alternate = !$alternate;
			}
			echo '</table>';
		}
	return;	
}

// enqueue custom jquery stylesheet for bulk optimizer
function ewww_image_optimizer_media_scripts($hook) {
	if ($hook == 'upload.php') {
		wp_enqueue_script('jquery-ui-tooltip');
		wp_enqueue_style('jquery-ui-tooltip-custom', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
	}
}

// used to output any debug messages available
function ewww_image_optimizer_debug() {
	global $ewww_debug;
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_debug')) echo '<div style="background-color:#ffff99;position:relative;bottom:60px;padding:5px 20px 10px;margin:0 0 15px 160px"><h3>Debug Log</h3>' . $ewww_debug . '</div>';
}

// used to output debug messages to a logfile in the plugin folder in cases where output to the screen is a bad idea
function ewww_image_optimizer_debug_log() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_debug_log()</b><br>";
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_debug')) {
		$timestamp = date('y-m-d h:i:s.u') . "  ";
		if (!file_exists(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log'))
			touch(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log');
		$ewww_debug_log = preg_replace('/<br>/', "\n", $ewww_debug);
		file_put_contents(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log', $timestamp . $ewww_debug_log, FILE_APPEND);
		$ewww_debug = '';
	}
}

// adds a link on the Plugins page for the EWWW IO settings
function ewww_image_optimizer_settings_link($links) {
	// load the html for the settings link
	$settings_link = '<a href="options-general.php?page=' . plugin_basename(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE) . '">' . __('Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a>';
	// load the settings link into the plugin links array
	array_unshift($links, $settings_link);
	// send back the plugin links array
	return $links;
}

// check for GD support of both PNG and JPG
function ewww_image_optimizer_gd_support() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_gd_support()</b><br>";
	if (function_exists('gd_info')) {
		$gd_support = gd_info();
		$ewww_debug .= "GD found, supports: <br>"; 
		foreach ($gd_support as $supports => $supported) {
			 $ewww_debug .= "$supports: $supported<br>";
		}
		if (($gd_support["JPEG Support"] || $gd_support["JPG Support"]) && $gd_support["PNG Support"]) {
			return TRUE;
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}

// makes sure the user isn't putting crap in the database
function ewww_image_optimizer_jpg_background_sanitize ($input) {
	return sanitize_text_field($input);
}

function ewww_image_optimizer_jpg_quality_sanitize ($input) {
	return sanitize_text_field($input);
}

function ewww_image_optimizer_aux_paths_sanitize ($input) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_aux_paths_santitize()</b><br>";
	if (empty($input)) {
		return '';
	}
	$path_array = array();
	$paths = explode("\n", $input);
	foreach ($paths as $path) {
		$path = sanitize_text_field($path);
		$ewww_debug .= "validating auxiliary path: $path <br>";
		// retrieve the location of the wordpress upload folder
		$upload_dir = wp_upload_dir();
		// retrieve the path of the upload folder
		$upload_path = str_replace($upload_dir['basedir'], '', $path);
		$upload_path_t = str_replace(trailingslashit($upload_dir['basedir']), '', $path);
		if (is_dir($path) && (strpos($path, trailingslashit(ABSPATH)) === 0 || strpos($path, $upload_path) === 0)) {
			$path_array[] = $path;
		}
	}
	return $path_array;
}

// replacement for escapeshellarg() that won't kill non-ASCII characters
function ewww_image_optimizer_escapeshellarg( $arg ) {
	global $ewww_debug;
	$safe_arg = "'" . str_replace("'", "'\"'\"'", $arg) . "'";
	return $safe_arg;
}

// Retrieves jpg background fill setting, or returns null for png2jpg conversions
function ewww_image_optimizer_jpg_background () {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_jpeg_background()</b><br>";
	// retrieve the user-supplied value for jpg background color
	$background = ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_background');
	//verify that the supplied value is in hex notation
	if (preg_match('/^\#*([0-9a-fA-F]){6}$/',$background)) {
		// we remove a leading # symbol, since we take care of it later
		preg_replace('/#/','',$background);
		// send back the verified, cleaned-up background color
		$ewww_debug .= "background: $background<br>";
		return $background;
	} else {
		// send back a blank value
		return NULL;
	}
}

// Retrieves the jpg quality setting for png2jpg conversion or returns null
function ewww_image_optimizer_jpg_quality () {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_jpg_quality()</b><br>";
	// retrieve the user-supplied value for jpg quality
	$quality = ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_quality');
	// verify that the quality level is an integer, 1-100
	if (preg_match('/^(100|[1-9][0-9]?)$/',$quality)) {
		// send back the valid quality level
		return $quality;
	} else {
		// send back nothing
		return NULL;
	}
}

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_manual()</b><br>";
	// check permissions of current user
	if ( FALSE === current_user_can('upload_files') ) {
		// display error message if insufficient permissions
		wp_die(__('You don\'t have permission to work with uploaded files.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	// make sure we didn't accidentally get to this page without an attachment to work on
	if ( FALSE === isset($_GET['attachment_ID'])) {
		// display an error message since we don't have anything to work on
		wp_die(__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	// store the attachment ID value
	$attachment_ID = intval($_GET['attachment_ID']);
	// retrieve the existing attachment metadata
	$original_meta = wp_get_attachment_metadata( $attachment_ID );
	// if the call was to optimize...
	if ($_REQUEST['action'] === 'ewww_image_optimizer_manual_optimize') {
		// call the optimize from metadata function and store the resulting new metadata
		$new_meta = ewww_image_optimizer_resize_from_meta_data($original_meta, $attachment_ID);
	} elseif ($_REQUEST['action'] === 'ewww_image_optimizer_manual_restore') {
		$new_meta = ewww_image_optimizer_restore_from_meta_data($original_meta, $attachment_ID);
	}
	// update the attachment metadata in the database
	wp_update_attachment_metadata( $attachment_ID, $new_meta );
	// store the referring webpage location
	$sendback = wp_get_referer();
	// sanitize the referring webpage location
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	// send the user back where they came from
	wp_redirect($sendback);
	// we are done, nothing to see here
	exit(0);
}

/**
 * Manually restore a converted image
 */
function ewww_image_optimizer_restore_from_meta_data($meta, $id) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_restore_from_meta_data()</b><br>";
	// get the filepath
	list($file_path, $upload_path) = ewww_image_optimizer_attachment_path($meta, $id);
	$file_path = get_attached_file($id);
	if (!empty($meta['converted'])) {
		if (file_exists($meta['orig_file'])) {
			// update the filename in the metadata
			$meta['file'] = $meta['orig_file'];
			// update the optimization results in the metadata
			$meta['ewww_image_optimizer'] = __('Original Restored', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			$meta['orig_file'] = $file_path;
			$meta['converted'] = 0;
			unlink($meta['orig_file']);
			$meta['file'] = str_replace($upload_path, '', $meta['file']);
			// if we don't already have the update attachment filter
			if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment'))
				// add the update attachment filter
				add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
		} else {
			remove_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10);
		}
	}
	if (isset($meta['sizes']) ) {
		// process each resized version
		$processed = array();
		// meta sizes don't contain a path, so we calculate one
		$base_dir = dirname($file_path) . '/';
		foreach($meta['sizes'] as $size => $data) {
			// check through all the sizes we've processed so far
			foreach($processed as $proc => $scan) {
				// if a previous resize had identical dimensions
				if ($scan['height'] == $data['height'] && $scan['width'] == $data['width'] && isset($meta['sizes'][$proc]['converted'])) {
					// point this resize at the same image as the previous one
					$meta['sizes'][$size]['file'] = $meta['sizes'][$proc]['file'];
				}
			}
			if (isset($data['converted'])) {
				// if this is a unique size
				if (file_exists($base_dir . $data['orig_file'])) {
					// update the filename
					$meta['sizes'][$size]['file'] = $data['orig_file'];
					// update the optimization results
					$meta['sizes'][$size]['ewww_image_optimizer'] = __('Original Restored', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$meta['sizes'][$size]['orig_file'] = $data['file'];
					$meta['sizes'][$size]['converted'] = 0;
						// if we don't already have the update attachment filter
						if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment'))
							// add the update attachment filter
							add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
					unlink($base_dir . $data['file']);
				}
				// store info on the sizes we've processed, so we can check the list for duplicate sizes
				$processed[$size]['width'] = $data['width'];
				$processed[$size]['height'] = $data['height'];
			}		
		}
	}
	return $meta;
}

// deletes 'orig_file' when an attachment is being deleted
function ewww_image_optimizer_delete ($id) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_delete()</b><br>";
	global $wpdb;
	// retrieve the image metadata
	$meta = wp_get_attachment_metadata($id);
	// if the attachment has an original file set
	if (!empty($meta['orig_file'])) {
		unset($rows);
		// get the filepath from the metadata
		$file_path = $meta['orig_file'];
		// get the filename
		$filename = basename($file_path);
		// retrieve any posts that link the original image
		$esql = "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%$filename%'";
		$rows = $wpdb->get_row($esql);
		// if the original file still exists and no posts contain links to the image
		if (file_exists($file_path) && empty($rows)) {
			unlink($file_path);
			$wpdb->delete($wpdb->ewwwio_images, array('path' => $file_path));
		}
	}
	// remove the regular image from the ewwwio_images tables
	list($file_path, $upload_path) = ewww_image_optimizer_attachment_path($meta, $id);
	$wpdb->delete($wpdb->ewwwio_images, array('path' => $file_path));
	// resized versions, so we can continue
	if (isset($meta['sizes']) ) {
		// one way or another, $file_path is now set, and we can get the base folder name
		$base_dir = dirname($file_path) . '/';
		// check each resized version
		foreach($meta['sizes'] as $size => $data) {
			$wpdb->delete($wpdb->ewwwio_images, array('path' => $base_dir . $data['file']));
			// if the original resize is set, and still exists
			if (!empty($data['orig_file']) && file_exists($base_dir . $data['orig_file'])) {
				unset($srows);
				// retrieve the filename from the metadata
				$filename = $data['orig_file'];
				// retrieve any posts that link the image
				$esql = "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%$filename%'";
				$rows = $wpdb->get_row($esql);
				// if there are no posts containing links to the original, delete it
				if(empty($srows)) {
					unlink($base_dir . $data['orig_file']);
					$wpdb->delete($wpdb->ewwwio_images, array('path' => $base_dir . $data['orig_file']));
				}
			}
		}
	}
	return;
}

// submits the api key for verification
function ewww_image_optimizer_cloud_verify ( $cache = true ) {
	global $ewww_debug;
	global $ewww_cloud_ip;
	$ewww_debug .= "<b>ewww_image_optimizer_cloud_verify()</b><br>";
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	if (empty($api_key)) {
		update_site_option('ewww_image_optimizer_cloud_jpg', '');
		update_site_option('ewww_image_optimizer_cloud_png', '');
		update_site_option('ewww_image_optimizer_cloud_gif', '');
		update_option('ewww_image_optimizer_cloud_jpg', '');
		update_option('ewww_image_optimizer_cloud_png', '');
		update_option('ewww_image_optimizer_cloud_gif', '');
		return false;
	}
	$prev_verified = get_option('ewww_image_optimizer_cloud_verified');
	$last_checked = get_option('ewww_image_optimizer_cloud_last');
	$ewww_cloud_ip = get_option('ewww_image_optimizer_cloud_ip');
	$servers = gethostbynamel('optimize.exactlywww.com');
	if ($cache && $prev_verified && $last_checked + 86400 > time() && !empty($ewww_cloud_ip)) {
		$ewww_debug .= "using cached IP: $ewww_cloud_ip<br>";
		return $prev_verified;	
	} elseif ( empty ( $servers ) ) {
		$ewww_debug .= "unable to resolve servers<br>";
		return false;
	} else {
		foreach ($servers as $ip) {
			$url = "http://$ip/verify/";
			$result = wp_remote_post($url, array(
				'timeout' => 20,
				'body' => array('api_key' => $api_key)
			));
			if (is_wp_error($result)) {
				$error_message = $result->get_error_message();
				$ewww_debug .= "verification failed: $error_message <br>";
			} elseif (!empty($result['body']) && preg_match('/(great|exceeded)/', $result['body'])) {
				$verified = $result['body'];
				$ewww_cloud_ip = $ip;
				$ewww_debug .= "verification success via: $ip <br>";
				/*if ( preg_match ( '/exceeded/', $result['body']) ) {
					global $ewww_exceed;
					$ewww_exceed = true;
				}*/
				break;
			} else {
				$ewww_debug .= "verification failed via: $ip <br>" . print_r($result, true) . "<br>";
			}
		}
	}
	if (empty($verified)) {
		update_site_option('ewww_image_optimizer_cloud_jpg', '');
		update_site_option('ewww_image_optimizer_cloud_png', '');
		update_site_option('ewww_image_optimizer_cloud_gif', '');
		update_option('ewww_image_optimizer_cloud_jpg', '');
		update_option('ewww_image_optimizer_cloud_png', '');
		update_option('ewww_image_optimizer_cloud_gif', '');
		return FALSE;
	} else {
		update_option ( 'ewww_image_optimizer_cloud_verified', $verified );
		update_option ( 'ewww_image_optimizer_cloud_last', time() );
		update_option ( 'ewww_image_optimizer_cloud_ip', $ewww_cloud_ip );
		$ewww_debug .= "verification body contents: " . $result['body'] . "<br>";
		return $verified;
	}
}

// checks the provided api key for quota information
function ewww_image_optimizer_cloud_quota() {
	global $ewww_debug;
	global $ewww_cloud_ip;
	$ewww_debug .= "<b>ewww_image_optimizer_cloud_quota()</b><br>";
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	$url = "http://$ewww_cloud_ip/quota/";
	$result = wp_remote_post($url, array(
		'timeout' => 10,
		'body' => array('api_key' => $api_key)
	));
	if (is_wp_error($result)) {
		$error_message = $result->get_error_message();
		$ewww_debug .= "quota request failed: $error_message <br>";
		return '';
	} elseif (!empty($result['body'])) {
		$ewww_debug .= "quota data retrieved: " . $result['body'] . "<br>";
		$quota = explode(' ', $result['body']);
		return sprintf(_n('used %1$d of %2$d, usage will reset in %3$d day.', 'used %1$d of %2$d, usage will reset in %3$d days.', $quota[2], EWWW_IMAGE_OPTIMIZER_DOMAIN), $quota[1], $quota[0], $quota[2]); 
	}
}

/* submits an image to the cloud optimizer and saves the optimized image to disk
 *
 * Returns an array of the $file, $results, $converted to tell us if an image changes formats, and the $original file if it did.
 *
 * @param   string $file		Full absolute path to the image file
 * @param   string $type		mimetype of $file
 * @param   boolean $convert		true says we want to attempt conversion of $file
 * @param   string $newfile		filename of new converted image
 * @param   string $newtype		mimetype of $newfile
 * @param   array $jpg_params		r, g, b values and jpg quality setting for conversion
 * @returns array
*/
function ewww_image_optimizer_cloud_optimizer($file, $type, $convert = false, $newfile = null, $newtype = null, $fullsize = false, $jpg_params = array('r' => '255', 'g' => '255', 'b' => '255', 'quality' => null)) {
	global $ewww_debug;
	global $ewww_exceed;
	global $ewww_cloud_ip;
	$ewww_debug .= "<b>ewww_image_optimizer_cloud_optimizer()</b><br>";
	if ( $ewww_exceed ) {
		$ewww_debug .= "license exceeded, image not processed<br>";
		return array($file, false, 'exceeded', 0);
	}
	if(ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
        	// don't copy metadata
                $metadata = 0;
        } else {
                // copy all the metadata
                $metadata = 1;
        }
	if (empty($convert)) {
		$convert = 0;
	} else {
		$convert = 1;
	}
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_png_lossy') && !$fullsize) {
		$lossy = 1;
	} else {
		$lossy = 0;
	}
	$ewww_debug .= "file: $file <br>";
	$ewww_debug .= "type: $type <br>";
	$ewww_debug .= "convert: $convert <br>";
	$ewww_debug .= "newfile: $newfile <br>";
	$ewww_debug .= "newtype: $newtype <br>";
	$ewww_debug .= "jpg_params: " . print_r($jpg_params, true) . " <br>";
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	$url = "http://$ewww_cloud_ip/";
	$boundary = wp_generate_password(24, false);

	$headers = array(
        	'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout' => 90,
		'httpversion' => '1.0',
		'blocking' => true
		);
	$post_fields = array(
		'oldform' => 1, 
		'convert' => $convert, 
		'metadata' => $metadata, 
		'api_key' => $api_key,
		'red' => $jpg_params['r'],
		'green' => $jpg_params['g'],
		'blue' => $jpg_params['b'],
		'quality' => $jpg_params['quality'],
		'compress' => ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png_compress'),
		'lossy' => $lossy,
	);

	$payload = '';
	foreach ($post_fields as $name => $value) {
        	$payload .= '--' . $boundary;
	        $payload .= "\r\n";
	        $payload .= 'Content-Disposition: form-data; name="' . $name .'"' . "\r\n\r\n";
	        $payload .= $value;
	        $payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents($file);
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	$response = wp_remote_post($url, array(
		'timeout' => 90,
		'headers' => $headers,
		'body' => $payload,
		));
	if (is_wp_error($response)) {
		$error_message = $response->get_error_message();
		$ewww_debug .= "verification failed: $error_message <br>";
		return array(0, false, null);
	} elseif (empty($response['body'])) {
		return array(0, false, null);
	} else {
		$tempfile = $file . ".tmp";
		file_put_contents($tempfile, $response['body']);
		$orig_size = filesize($file);
		$newsize = $orig_size;
		$ewww_debug .= "cloud results: $newsize (new) vs. $orig_size (original)<br>";
		$converted = false;
		$msg = '';
		if (preg_match('/exceeded/', $response['body'])) {
			$ewww_debug .= "License Exceeded<br>";
					global $ewww_exceed;
					$ewww_exceed = true;
			$msg = 'exceeded';
			unlink($tempfile);
		} elseif (ewww_image_optimizer_mimetype($tempfile, 'i') == $type) {
			$newsize = filesize($tempfile);
			rename($tempfile, $file);
		} elseif (ewww_image_optimizer_mimetype($tempfile, 'i') == $newtype) {
			$converted = true;
			$newsize = filesize($tempfile);
			rename($tempfile, $newfile);
			$file = $newfile;
		} else {
			unlink($tempfile);
		}
		return array($file, $converted, $msg, $newsize);
	}
}

// check the database to see if we've done this image before
function ewww_image_optimizer_check_table ($file, $orig_size) {
	global $wpdb;
	global $ewww_debug;
	$already_optimized = false;
	$ewww_debug .= "<b>ewww_image_optimizer_check_table()</b><br>";
	$query = $wpdb->prepare("SELECT results FROM $wpdb->ewwwio_images WHERE BINARY path = %s AND image_size = '$orig_size'", $file);
	$already_optimized = $wpdb->get_var($query);
	if ( preg_match( '/' . __('License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '/', $already_optimized ) ) {
		return;
	}
	if (!empty($already_optimized) && empty($_REQUEST['force'])) {
		$prev_string = " - " . __('Previously Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN);
		$already_optimized = preg_replace("/$prev_string/", '', $already_optimized);
		$already_optimized = $already_optimized . $prev_string;
		$ewww_debug .= "already optimized: $already_optimized<br>";
		return $already_optimized;
	}
}

// receives a path, results, optimized size, and an original size to insert into ewwwwio_images table
// if this is a $new image, copy the result stored in the database
function ewww_image_optimizer_update_table ($attachment, $opt_size, $orig_size, $preserve_results = false) {
	global $wpdb;
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_update_table()</b><br>";
	$query = $wpdb->prepare("SELECT id,orig_size,results FROM $wpdb->ewwwio_images WHERE BINARY path = %s", $attachment);
	$already_optimized = $wpdb->get_row($query, ARRAY_A);
	$ewww_debug .= "savings: $opt_size (new) vs. $orig_size (orig)<br>";
	if (!empty($already_optimized['results']) && $preserve_results && $opt_size === $orig_size) {
		$results_msg = $already_optimized['results'];
	} elseif ($opt_size === $orig_size) {
		$ewww_debug .= "original and new file are same size, no savings<br>";
		$results_msg = __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	} else {
		// calculate how much space was saved
		$savings = intval($orig_size) - intval($opt_size);
		// convert it to human readable format
		$savings_str = size_format($savings, 1);
		// replace spaces and extra decimals with proper html entity encoding
		$savings_str = preg_replace('/\.0 B /', ' B', $savings_str);
		$savings_str = str_replace(' ', '&nbsp;', $savings_str);
		// determine the percentage savings
		$percent = 100 - (100 * ($opt_size / $orig_size));
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf(__("Reduced by %01.1f%% (%s)", EWWW_IMAGE_OPTIMIZER_DOMAIN),
			$percent,
			$savings_str
		);
		$ewww_debug .= "original and new file are different size: $results_msg<br>";
	}
	if (empty($already_optimized)) {
		$ewww_debug .= "creating new record, path: $attachment, size: " . $opt_size . "<br>";
		// store info on the current image for future reference
		$wpdb->insert( $wpdb->ewwwio_images, array(
				'path' => $attachment,
				'image_size' => $opt_size,
				'orig_size' => $orig_size,
				'results' => $results_msg,
			));
	} else {
		$ewww_debug .= "updating existing record (" . $already_optimized['id'] . "), path: $attachment, size: " . $opt_size . "<br>";
		// store info on the current image for future reference
		$wpdb->update( $wpdb->ewwwio_images,
			array(
				'image_size' => $opt_size,
				'results' => $results_msg,
			),
			array(
				'id' => $already_optimized['id'],
			));
	}
	$wpdb->flush();
	return $results_msg;
}

// called by javascript to process each image in the loop
function ewww_image_optimizer_aux_images_loop($attachment = null, $auto = false) {
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
	$attachment = preg_replace( ":\\\':", "'", $attachment);
	// get the 'aux attachments' with a list of attachments remaining
	$attachments = get_option('ewww_image_optimizer_aux_attachments');
	// do the optimization for the current image
	$results = ewww_image_optimizer($attachment, 4, false, false);
	// remove the first element fromt the $attachments array
	if (!empty($attachments)) {
		array_shift($attachments);
	}
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

/**
 * Read the image paths from an attachment's meta data and process each image
 * with ewww_image_optimizer().
 *
 * This method also adds a `ewww_image_optimizer` meta key for use in the media library 
 * and may add a 'converted' and 'orig_file' key if conversion is enabled.
 *
 * Called after `wp_generate_attachment_metadata` is completed.
 */
function ewww_image_optimizer_resize_from_meta_data($meta, $ID = null, $log = true) {
	global $ewww_debug;
	global $wpdb;
	// may also need to track their attachment ID as well
	// TODO: also have some doo-dad that tracks total file savings
	$ewww_debug .= "<b>ewww_image_optimizer_resize_from_meta_data()</b><br>";
	$gallery_type = 1;
	$ewww_debug .= "attachment id: $ID<br>";
	if (!metadata_exists('post', $ID, '_wp_attachment_metadata')) {
		$ewww_debug .= "this is a newly uploaded image with no metadata yet<br>";
		$new_image = true;
	} else {
		$ewww_debug .= "this image already has metadata, so it is not new<br>";
		$new_image = false;
	}
	list($file_path, $upload_path) = ewww_image_optimizer_attachment_path($meta, $ID);
	// if the attachment has been uploaded via the image store plugin
	if ('ims_image' == get_post_type($ID)) {
		$gallery_type = 6;
	}
	// don't do anything else if the attachment path can't be retrieved
	if (!is_file($file_path)) {
		$ewww_debug .= "could not retrieve path<br>";
		return $meta;
	}
	$ewww_debug .= "retrieved file path: $file_path<br>";
	// see if this is a new image and Imsanity resized it (which means it could be already optimized)
	if (!empty($new_image) && function_exists('imsanity_get_max_width_height')) {
		list($maxW,$maxH) = imsanity_get_max_width_height(IMSANITY_SOURCE_LIBRARY);
		list($oldW, $oldH) = getimagesize($file_path);
		list($newW, $newH) = wp_constrain_dimensions($oldW, $oldH, $maxW, $maxH);
		$path_parts = pathinfo($file_path);
		$imsanity_path = trailingslashit($path_parts['dirname']) . $path_parts['filename'] . '-' . $newW . 'x' . $newH . '.' . $path_parts['extension'];
		$ewww_debug .= "imsanity path: $imsanity_path<br>";
		$image_size = filesize($file_path);
		$query = $wpdb->prepare("SELECT id FROM $wpdb->ewwwio_images WHERE BINARY path = %s AND image_size = '$image_size'", $imsanity_path);
		$already_optimized = $wpdb->get_results($query, ARRAY_A);
		$ewww_debug .= "updating existing record, path: $file_path, size: " . $image_size . "<br>";
		// store info on the current image for future reference
		$wpdb->update( $wpdb->ewwwio_images,
			array(
				'path' => $file_path,
			),
			array(
				'id' => $already_optimized[0]['id'],
			));
	}
	list($file, $msg, $conv, $original) = ewww_image_optimizer($file_path, $gallery_type, false, $new_image, ewww_image_optimizer_get_option('ewww_image_optimizer_lossy_skip_full'));
	// update the optimization results in the metadata
	$meta['ewww_image_optimizer'] = $msg;
	if ($file === false) {
		return $meta;
	}
	$meta['file'] = str_replace($upload_path, '', $file);
	// if the file was converted
	if ($conv) {
		// update the filename in the metadata
		$new_file = substr($meta['file'], 0, -3);
		// change extension
		$new_ext = substr($file, -3);
		$meta['file'] = $new_file . $new_ext;
		$ewww_debug .= "image was converted<br>";
		// if we don't already have the update attachment filter
		if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment'))
			// add the update attachment filter
			add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
		// store the conversion status in the metadata
		$meta['converted'] = 1;
		// store the old filename in the database
		$meta['orig_file'] = $original;
	} else {
		remove_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10);
	}
	// resized versions, so we can continue
	if (isset($meta['sizes']) ) {
		$ewww_debug .= "processing resizes<br>";
		// meta sizes don't contain a path, so we calculate one
		if ($gallery_type === 6) {
			$base_dir = dirname($file_path) . '/_resized/';
		} else {
			$base_dir = dirname($file_path) . '/';
		}
		// process each resized version
		$processed = array();
		foreach($meta['sizes'] as $size => $data) {
			// initialize $dup_size
			$dup_size = false;
			// check through all the sizes we've processed so far
			foreach($processed as $proc => $scan) {
				// if a previous resize had identical dimensions
				if ($scan['height'] == $data['height'] && $scan['width'] == $data['width']) {
					// found a duplicate resize
					$dup_size = true;
					// point this resize at the same image as the previous one
					$meta['sizes'][$size]['file'] = $meta['sizes'][$proc]['file'];
					// and tell the user we didn't do any further optimization
					$meta['sizes'][$size]['ewww_image_optimizer'] = __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
			}
			// if this is a unique size
			if (!$dup_size) {
				$resize_path = $base_dir . $data['file'];
				// run the optimization and store the results
				list($optimized_file, $results, $resize_conv, $original) = ewww_image_optimizer($resize_path, $gallery_type, $conv, $new_image);
				// if the resize was converted, store the result and the original filename in the metadata for later recovery
				if ($resize_conv) {
					// if we don't already have the update attachment filter
					if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment'))
						// add the update attachment filter
						add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
					$meta['sizes'][$size]['converted'] = 1;
					$meta['sizes'][$size]['orig_file'] = str_replace($base_dir, '', $original);
					$ewww_debug .= "original filename: $original<br>";
					$meta['sizes'][$size]['real_orig_file'] = str_replace($base_dir, '', $resize_path);
					$ewww_debug .= "resize path: $resize_path<br>";
				}
				// update the filename
				$meta['sizes'][$size]['file'] = str_replace($base_dir, '', $optimized_file);
				// update the optimization results
				$meta['sizes'][$size]['ewww_image_optimizer'] = $results;
			}
			// store info on the sizes we've processed, so we can check the list for duplicate sizes
			$processed[$size]['width'] = $data['width'];
			$processed[$size]['height'] = $data['height'];
		}
	}
	
	if (class_exists('Cloudinary') && Cloudinary::config_get("api_secret") && ewww_image_optimizer_get_option('ewww_image_optimizer_enable_cloudinary') && !empty($new_image)) {
		try {
			$result = CloudinaryUploader::upload($file,array('use_filename'=>True));
		} catch(Exception $e) {
			$error = $e->getMessage();
		}
		if (!empty($error)) {
			$ewww_debug .= "Cloudinary error: $error<br>";
		} else {
			$ewww_debug .= "successfully uploaded to Cloudinary<br>";
			// register the attachment in the database as a cloudinary attachment
			$old_url = wp_get_attachment_url($ID);
			wp_update_post(array('ID' => $ID,
				'guid' => $result['url']));
			update_attached_file($ID, $result['url']);
			$meta['cloudinary'] = TRUE;
			$errors = array();
			// update the image location for the attachment
			CloudinaryPlugin::update_image_src_all($ID, $result, $old_url, $result["url"], TRUE, $errors);
			if (count($errors) > 0) {
				$ewww_debug .= "Cannot migrate the following posts:<br>" . implode("<br>", $errors);
			}
		}
	}
	if ( $log ) {
		ewww_image_optimizer_debug_log();
	}
	// send back the updated metadata
	return $meta;
}

/**
 * Update the attachment's meta data after being converted 
 */
function ewww_image_optimizer_update_attachment($meta, $ID) {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_update_attachment()</b><br>";
	// update the file location in the post metadata based on the new path stored in the attachment metadata
	update_attached_file($ID, $meta['file']);
	// retrieve the post information based on the $ID
	$post = get_post($ID);
	// save the previous attachment address
	$old_guid = $post->guid;
	//$old_guid = esc_sql($post->guid);
	// construct the new guid based on the filename from the attachment metadata
	$guid = dirname($post->guid) . "/" . basename($meta['file']);
	// retrieve any posts that link the image
	$esql = $wpdb->prepare("SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%%%s%%'", $old_guid);
	// while there are posts to process
	$rows = $wpdb->get_results($esql, ARRAY_A);
	foreach ($rows as $row) {
		// replace all occurences of the old guid with the new guid
		$post_content = str_replace($old_guid, $guid, $row['post_content']);
		$ewww_debug .= "replacing $old_guid with $guid in post " . $row['ID'] . '<br>';
		// send the updated content back to the database
		$wpdb->update(
			$wpdb->posts,
			array('post_content' => $post_content),
			array('ID' => $row['ID'])
		);
	}
	if (isset($meta['sizes']) ) {
		// for each resized version
		foreach($meta['sizes'] as $size => $data) {
			// if the resize was converted
			if (isset($data['converted'])) {
				// generate the url for the old image
				if (empty($data['real_orig_file'])) {
					$old_sguid = dirname($post->guid) . "/" . basename($data['orig_file']);
				} else {
					$old_sguid = dirname($post->guid) . "/" . basename($data['real_orig_file']);
					unset ($meta['sizes'][$size]['real_orig_file'] );
				}
				$ewww_debug .= "processing: $size<br>";
				$ewww_debug .= "old guid: $old_sguid <br>";
				// generate the url for the new image
				$sguid = dirname($post->guid) . "/" . basename($data['file']);
				$ewww_debug .= "new guid: $sguid <br>";
				// retrieve any posts that link the resize
				$ersql = $wpdb->prepare("SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%%%s%%'", $old_sguid);
				$ewww_debug .= "using query: $ersql<br>";
				$rows = $wpdb->get_results($ersql, ARRAY_A);
				// while there are posts to process
				foreach ($rows as $row) {
					// replace all occurences of the old guid with the new guid
					$post_content = str_replace($old_sguid, $sguid, $row['post_content']);
					$ewww_debug .= "replacing $old_sguid with $sguid in post " . $row['ID'] . '<br>';
					// send the updated content back to the database
					$wpdb->update(
						$wpdb->posts,
						array('post_content' => $post_content),
						array('ID' => $row['ID'])
					);
				}
			}
		}
	}
	// if the new image is a JPG
	if (preg_match('/.jpg$/i', basename($meta['file']))) {
		// set the mimetype to JPG
		$mime = 'image/jpg';
	}
	// if the new image is a PNG
	if (preg_match('/.png$/i', basename($meta['file']))) {
		// set the mimetype to PNG
		$mime = 'image/png';
	}
	if (preg_match('/.gif$/i', basename($meta['file']))) {
		// set the mimetype to GIF
		$mime = 'image/gif';
	}
	// update the attachment post with the new mimetype and guid
	wp_update_post( array('ID' => $ID,
			      'post_mime_type' => $mime,
			      'guid' => $guid) );
	ewww_image_optimizer_debug_log();
	return $meta;
}

// retrieves path of an attachment via the $id and the $meta
// returns a $file_path and $upload_path
function ewww_image_optimizer_attachment_path($meta, $ID) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_attachment_path()</b><br>";
	// retrieve the location of the wordpress upload folder
	$upload_dir = wp_upload_dir();
	// retrieve the path of the upload folder
	$upload_path = trailingslashit($upload_dir['basedir']);
	// get the filepath
	$file_path = get_attached_file($ID);
	$ewww_debug .= "WP thinks the file is at: $file_path<br>";
	if (is_file($file_path))
		return array($file_path, $upload_path);
	if ('ims_image' == get_post_type($ID) && !empty($meta['file'])) {
		$ims_options = ewww_image_optimizer_get_option('ims_front_options');
		$ims_path = $ims_options['galleriespath'];
		if (is_dir($file_path)) {
			$upload_path = $file_path;
			$file_path = $meta['file'];
			// generate the absolute path
			$file_path =  $upload_path . $file_path;
		} elseif (is_file($meta['file'])) {
			$file_path = $meta['file'];
			$upload_path = '';
		} else {
			$upload_path = WP_CONTENT_DIR;
			if (strpos($meta['file'], $ims_path) === false) {
				$upload_path = trailingslashit(WP_CONTENT_DIR);
			}
			$file_path = $upload_path . $meta['file'];
		}
		return array($file_path, $upload_path);
	}
	$file_path = $meta['file'];
	if (is_file($file_path))
		return array($file_path, $upload_path);
	$file_path = $upload_path . $file_path;
	if (is_file($file_path))
		return array($file_path, $upload_path);
	return array('', $upload_path);
}

// takes a human-readable size, and generates an approximate byte-size
function ewww_image_optimizer_size_unformat ($formatted) {
	$size_parts = explode ( '&nbsp;', $formatted);
	switch ($size_parts[1]) {
		case 'B':
			return intval($size_parts[0]);
		case 'kB':
			return intval($size_parts[0] * 1024);
		case 'MB':
			return intval($size_parts[0] * 1048576);
		case 'GB':
			return intval($size_parts[0] * 1073741824);
		case 'TB':
			return intval($size_parts[0] * 1099511627776);
		default:
			return 0;
	}
}

// generate a unique filename for a converted image
function ewww_image_optimizer_unique_filename ($file, $fileext) {
	// strip the file extension
	$filename = preg_replace('/\.\w+$/', '', $file);
	// set the increment to 1 (we always rename converted files with an increment)
	$filenum = 1;
	// while a file exists with the current increment
	while (file_exists($filename . '-' . $filenum . $fileext)) {
		// increment the increment...
		$filenum++;
	}
	// all done, let's reconstruct the filename
	return array($filename . '-' . $filenum . $fileext, $filenum);
}

/**
 * Check the submitted PNG to see if it has transparency
 */
function ewww_image_optimizer_png_alpha ($filename){
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_png_alpha()</b><br>";
	// determine what color type is stored in the file
	$color_type = ord(@file_get_contents($filename, NULL, NULL, 25, 1));
	// if it is set to RGB alpha or Grayscale alpha
	if ($color_type == 4 || $color_type == 6) {
		$ewww_debug .= "transparency found<br>";
		return true;
	} else {
		$ewww_debug .= "no transparency<br>";
		return false;
	}
}

/**
 * Check the submitted GIF to see if it is animated
 */
function ewww_image_optimizer_is_animated($filename) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_is_animated()</b><br>";
	// if we can't open the file in read-only buffered mode
	if(!($fh = @fopen($filename, 'rb'))) {
		return false;
	}
	// initialize $count
	$count = 0;
   
	// We read through the file til we reach the end of the file, or we've found
	// at least 2 frame headers
	while(!feof($fh) && $count < 2) {
		$chunk = fread($fh, 1024 * 100); //read 100kb at a time
		$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
	}
	fclose($fh);
	// return TRUE if there was more than one frame, or FALSE if there was only one
	return $count > 1;
}

/**
 * Print column header for optimizer results in the media library using
 * the `manage_media_columns` hook.
 */
function ewww_image_optimizer_columns($defaults) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_optimizer_columns()</b><br>";
	$defaults['ewww-image-optimizer'] = 'Image Optimizer';
	return $defaults;
}

/**
 * Print column data for optimizer results in the media library using
 * the `manage_media_custom_column` hook.
 */
function ewww_image_optimizer_custom_column($column_name, $id) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_custom_column()</b><br>";
	// once we get to the EWWW IO custom column
	if ($column_name == 'ewww-image-optimizer') {
		// retrieve the metadata
		$meta = wp_get_attachment_metadata($id);
		if (ewww_image_optimizer_get_option('ewww_image_optimizer_debug')) {
			$print_meta = print_r($meta, TRUE);
			$print_meta = preg_replace(array('/ /', '/\n+/'), array('&nbsp;', '<br />'), $print_meta);
			echo '<div style="background-color:#ffff99;font-size: 10px;padding: 10px;margin:-10px -10px 10px;line-height: 1.1em">' . $print_meta . '</div>';
		}
		if(!empty($meta['cloudinary'])) {
			_e('Cloudinary image', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			return;
		}
		// if the filepath isn't set in the metadata
		if(empty($meta['file'])){
			if (isset($meta['file'])) {
				unset($meta['file']);
				if (strpos($meta['ewww_image_optimizer'], 'Could not find') === 0) {
					unset($meta['ewww_image_optimizer']);
				}
				wp_update_attachment_metadata($id, $meta);
			}
		}
		list($file_path, $upload_path) = ewww_image_optimizer_attachment_path($meta, $id);
		// if the file does not exist
		if (empty($file_path)) {
			_e('Could not retrieve file path.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			return;
		}
		$msg = '';
		// retrieve the mimetype of the attachment
		$type = ewww_image_optimizer_mimetype($file_path, 'i');
		// get a human readable filesize
		$file_size = size_format(filesize($file_path), 2);
		$file_size = preg_replace('/\.00 B /', ' B', $file_size);
		// run the appropriate code based on the mimetype
		switch($type) {
			case 'image/jpeg':
				// if jpegtran is missing, tell them that
				if(!EWWW_IMAGE_OPTIMIZER_JPEGTRAN && !EWWW_IMAGE_OPTIMIZER_CLOUD) {
					$valid = false;
					$msg = '<br>' . sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>jpegtran</em>');
				} else {
					$convert_link = __('JPG to PNG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$class_type = 'jpg';
					$convert_desc = __('WARNING: Removes metadata. Requires GD or ImageMagick. PNG is generally much better than JPG for logos and other images with a limited range of colors.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break; 
			case 'image/png':
				// if pngout and optipng are missing, tell the user
				if(!EWWW_IMAGE_OPTIMIZER_PNGOUT && !EWWW_IMAGE_OPTIMIZER_OPTIPNG && !EWWW_IMAGE_OPTIMIZER_CLOUD) {
					$valid = false;
					$msg = '<br>' . sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>optipng/pngout</em>');
				} else {
					$convert_link = __('PNG to JPG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$class_type = 'png';
					$convert_desc = __('WARNING: This is not a lossless conversion and requires GD or ImageMagick. JPG is much better than PNG for photographic use because it compresses the image and discards data. Transparent images will only be converted if a background color has been set.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break;
			case 'image/gif':
				// if gifsicle is missing, tell the user
				if(!EWWW_IMAGE_OPTIMIZER_GIFSICLE && !EWWW_IMAGE_OPTIMIZER_CLOUD) {
					$valid = false;
					$msg = '<br>' . sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>gifsicle</em>');
				} else {
					$convert_link = __('GIF to PNG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$class_type = 'gif';
					$convert_desc = __('PNG is generally better than GIF, but does not support animation. Animated images will not be converted.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break;
			default:
				// not a supported mimetype
				_e('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				return;
		}
		// if the optimizer metadata exists
		if (isset($meta['ewww_image_optimizer']) && !empty($meta['ewww_image_optimizer']) ) {
			// output the optimizer results
			echo $meta['ewww_image_optimizer'];
			// output the filesize
			echo "<br>" . sprintf(__('Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_size);
			// output a link to re-optimize manually
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;force=1&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_convert_links') && 'ims_image' != get_post_type($id))
				echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;attachment_ID=$id&amp;convert=1&amp;force=1'>$convert_link</a>";
			$restorable = false;
			if (!empty($meta['converted'])) {
				if (!empty($meta['orig_file']) && file_exists($meta['orig_file'])) {
					$restorable = true;
				}
			}
			if (isset($meta['sizes']) ) {
				// meta sizes don't contain a path, so we calculate one
				$base_dir = dirname($file_path) . '/';
				foreach($meta['sizes'] as $size => $data) {
					if (!empty($data['converted'])) {
						if (!empty($data['orig_file']) && file_exists($base_dir . $data['orig_file'])) {
							$restorable = true;
						}
					}		
				}
			}
			if ($restorable) {
				printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual_restore&amp;attachment_ID=%d\">%s</a>",
					$id,
					__('Restore original', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			}
		} else {
			// otherwise, this must be an image we haven't processed
			_e('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			// tell them the filesize
			echo "<br>" . sprintf(__('Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_size);
			// and give the user the option to optimize the image right now
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_convert_links') && 'ims_image' != get_post_type($id))
				echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;attachment_ID=$id&amp;convert=1&amp;force=1'>$convert_link</a>";
		}
	}
}

// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
// adds a bulk optimize action to the drop-down on the media library page
function ewww_image_optimizer_add_bulk_actions_via_javascript() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_add_bulk_actions_via_javascript()</b><br>";
?>
	<script type="text/javascript"> 
		jQuery(document).ready(function($){ 
			$('select[name^="action"] option:last-child').before('<option value="bulk_optimize"><?php _e('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></option>');
			$('.ewww-convert').tooltip();
		}); 
	</script>
<?php } 

// Handles the bulk actions POST 
// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ 
function ewww_image_optimizer_bulk_action_handler() { 
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_bulk_action_handler()</b><br>";
	// if the requested action is blank, or not a bulk_optimize, do nothing
	if ( empty( $_REQUEST['action'] ) || ( 'bulk_optimize' != $_REQUEST['action'] && 'bulk_optimize' != $_REQUEST['action2'] ) ) {
		return;
	}
	// if there is no media to optimize, do nothing
	if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
		return; 
	}
	// check the referring page
	check_admin_referer( 'bulk-media' ); 
	// prep the attachment IDs for optimization
	$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) ); 
	wp_redirect(add_query_arg(array('page' => 'ewww-image-optimizer-bulk', '_wpnonce' => wp_create_nonce('ewww-image-optimizer-bulk'), 'goback' => 1, 'ids' => $ids), admin_url('upload.php'))); 
	exit(); 
}

// retrieve an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function ewww_image_optimizer_get_option ($option_name) {
	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(plugin_basename(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE))) {
		$option_value = get_site_option($option_name);
	} else {
		$option_value = get_option($option_name);
	}
	return $option_value;
}
?>
