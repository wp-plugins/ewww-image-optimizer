<?php
/**
 * Integrate image optimizers into WordPress.
 * @version 1.9.2
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://wordpress.org/extend/plugins/ewww-image-optimizer/
Description: Reduce file sizes for images within WordPress including NextGEN Gallery and GRAND FlAGallery. Uses jpegtran, optipng/pngout, and gifsicle.
Author: Shane Bishop
Text Domain: ewww-image-optimizer
Version: 1.9.2
Author URI: http://www.shanebishop.net/
License: GPLv3
*/

// Constants
define('EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww-image-optimizer');
// the folder where we install optimization tools
define('EWWW_IMAGE_OPTIMIZER_TOOL_PATH', WP_CONTENT_DIR . '/ewww/');
// this is the full path of the plugin file itself
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__);
// this is the full system path to the plugin folder
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EWWW_IMAGE_OPTIMIZER_VERSION', '192');

require_once(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'common.php');

$ewww_debug .= 'EWWW IO version: ' . EWWW_IMAGE_OPTIMIZER_VERSION . '<br>';

// Hooks
add_action('admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout');

/**
 * Plugin initialization function
 */
function ewww_image_optimizer_init() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_init()</b><br>";
	if (get_option('ewww_image_optimizer_version') < EWWW_IMAGE_OPTIMIZER_VERSION) {
		ewww_image_optimizer_install_table();
		ewww_image_optimizer_set_defaults();
		update_option('ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION);
	}
	if (!defined('EWWW_IMAGE_OPTIMIZER_CLOUD') && ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg') && ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') && ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif')) {
		define('EWWW_IMAGE_OPTIMIZER_CLOUD', TRUE);
		wp_enqueue_style('ewww-nocloud', plugins_url('nocloud.css', __FILE__));
	} elseif (!defined('EWWW_IMAGE_OPTIMIZER_CLOUD')) {
		define('EWWW_IMAGE_OPTIMIZER_CLOUD', FALSE);
	}
	load_plugin_textdomain(EWWW_IMAGE_OPTIMIZER_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Plugin initialization for admin area
function ewww_image_optimizer_admin_init() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_admin_init()</b><br>";
	// make sure the bundled tools are installed
	if(!ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle')) {
		ewww_image_optimizer_install_tools ();
	}
	ewww_image_optimizer_init();
/*	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! get_option( 'ewww_image_optimizer_cloud_verified' ) && ! ewww_image_optimizer_cloud_verify( false ) ) {
		add_action('network_admin_notices', 'ewww_image_optimizer_notice_cloud_failed');
		add_action('admin_notices', 'ewww_image_optimizer_notice_cloud_failed');
	}*/
	// Check if this is an unsupported OS (not Linux or Mac OSX or FreeBSD or Windows or SunOS)
	if(EWWW_IMAGE_OPTIMIZER_CLOUD) {
		ewww_image_optimizer_disable_tools();
	} elseif('Linux' != PHP_OS && 'Darwin' != PHP_OS && 'FreeBSD' != PHP_OS && 'WINNT' != PHP_OS && 'SunOS' != PHP_OS) {
		// call the function to display a notice
		add_action('network_admin_notices', 'ewww_image_optimizer_notice_os');
		add_action('admin_notices', 'ewww_image_optimizer_notice_os');
		// turn off all the tools
		ewww_image_optimizer_disable_tools();
	} else {
		//Otherwise, we run the function to check for optimization utilities
		add_action('network_admin_notices', 'ewww_image_optimizer_notice_utils');
		add_action('admin_notices', 'ewww_image_optimizer_notice_utils');
	} 

	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) {
		// network version is simply incremented any time we need to make changes to this section for new defaults
		if (get_site_option('ewww_image_optimizer_network_version') < 1) {
			add_site_option('ewww_image_optimizer_disable_pngout', TRUE);
			add_site_option('ewww_image_optimizer_optipng_level', 2);
			add_site_option('ewww_image_optimizer_pngout_level', 2);
			update_site_option('ewww_image_optimizer_network_version', '1');
		}
		// set network settings if they have been POSTed
		if (!empty($_POST['ewww_image_optimizer_optipng_level'])) {
			if (empty($_POST['ewww_image_optimizer_skip_check'])) $_POST['ewww_image_optimizer_skip_check'] = '';
			update_site_option('ewww_image_optimizer_skip_check', $_POST['ewww_image_optimizer_skip_check']);
			if (empty($_POST['ewww_image_optimizer_skip_bundle'])) $_POST['ewww_image_optimizer_skip_bundle'] = '';
			update_site_option('ewww_image_optimizer_skip_bundle', $_POST['ewww_image_optimizer_skip_bundle']);
			if (empty($_POST['ewww_image_optimizer_debug'])) $_POST['ewww_image_optimizer_debug'] = '';
			update_site_option('ewww_image_optimizer_debug', $_POST['ewww_image_optimizer_debug']);
			if (empty($_POST['ewww_image_optimizer_jpegtran_copy'])) $_POST['ewww_image_optimizer_jpegtran_copy'] = '';
			update_site_option('ewww_image_optimizer_jpegtran_copy', $_POST['ewww_image_optimizer_jpegtran_copy']);
			if (empty($_POST['ewww_image_optimizer_png_lossy'])) $_POST['ewww_image_optimizer_png_lossy'] = '';
			update_site_option('ewww_image_optimizer_png_lossy', $_POST['ewww_image_optimizer_png_lossy']);
			if (empty($_POST['ewww_image_optimizer_lossy_skip_full'])) $_POST['ewww_image_optimizer_lossy_skip_full'] = '';
			update_site_option('ewww_image_optimizer_lossy_skip_full', $_POST['ewww_image_optimizer_lossy_skip_full']);
			update_site_option('ewww_image_optimizer_optipng_level', $_POST['ewww_image_optimizer_optipng_level']);
			update_site_option('ewww_image_optimizer_pngout_level', $_POST['ewww_image_optimizer_pngout_level']);
			if (empty($_POST['ewww_image_optimizer_disable_jpegtran'])) $_POST['ewww_image_optimizer_disable_jpegtran'] = '';
			update_site_option('ewww_image_optimizer_disable_jpegtran', $_POST['ewww_image_optimizer_disable_jpegtran']);
			if (empty($_POST['ewww_image_optimizer_disable_optipng'])) $_POST['ewww_image_optimizer_disable_optipng'] = '';
			update_site_option('ewww_image_optimizer_disable_optipng', $_POST['ewww_image_optimizer_disable_optipng']);
			if (empty($_POST['ewww_image_optimizer_disable_gifsicle'])) $_POST['ewww_image_optimizer_disable_gifsicle'] = '';
			update_site_option('ewww_image_optimizer_disable_gifsicle', $_POST['ewww_image_optimizer_disable_gifsicle']);
			if (empty($_POST['ewww_image_optimizer_disable_pngout'])) $_POST['ewww_image_optimizer_disable_pngout'] = '';
			update_site_option('ewww_image_optimizer_disable_pngout', $_POST['ewww_image_optimizer_disable_pngout']);
			if (empty($_POST['ewww_image_optimizer_delete_originals'])) $_POST['ewww_image_optimizer_delete_originals'] = '';
			update_site_option('ewww_image_optimizer_delete_originals', $_POST['ewww_image_optimizer_delete_originals']);
			if (empty($_POST['ewww_image_optimizer_jpg_to_png'])) $_POST['ewww_image_optimizer_jpg_to_png'] = '';
			update_site_option('ewww_image_optimizer_jpg_to_png', $_POST['ewww_image_optimizer_jpg_to_png']);
			if (empty($_POST['ewww_image_optimizer_png_to_jpg'])) $_POST['ewww_image_optimizer_png_to_jpg'] = '';
			update_site_option('ewww_image_optimizer_png_to_jpg', $_POST['ewww_image_optimizer_png_to_jpg']);
			if (empty($_POST['ewww_image_optimizer_gif_to_png'])) $_POST['ewww_image_optimizer_gif_to_png'] = '';
			update_site_option('ewww_image_optimizer_gif_to_png', $_POST['ewww_image_optimizer_gif_to_png']);
			if (empty($_POST['ewww_image_optimizer_jpg_background'])) $_POST['ewww_image_optimizer_jpg_background'] = '';
			update_site_option('ewww_image_optimizer_jpg_background', $_POST['ewww_image_optimizer_jpg_background']);
			if (empty($_POST['ewww_image_optimizer_jpg_quality'])) $_POST['ewww_image_optimizer_jpg_quality'] = '';
			update_site_option('ewww_image_optimizer_jpg_quality', $_POST['ewww_image_optimizer_jpg_quality']);
			if (empty($_POST['ewww_image_optimizer_disable_convert_links'])) $_POST['ewww_image_optimizer_disable_convert_links'] = '';
			update_site_option('ewww_image_optimizer_disable_convert_links', $_POST['ewww_image_optimizer_disable_convert_links']);
			if (empty($_POST['ewww_image_optimizer_cloud_key'])) $_POST['ewww_image_optimizer_cloud_key'] = '';
			update_site_option('ewww_image_optimizer_cloud_key', $_POST['ewww_image_optimizer_cloud_key']);
			if (empty($_POST['ewww_image_optimizer_cloud_jpg'])) $_POST['ewww_image_optimizer_cloud_jpg'] = '';
			update_site_option('ewww_image_optimizer_cloud_jpg', $_POST['ewww_image_optimizer_cloud_jpg']);
			if (empty($_POST['ewww_image_optimizer_cloud_png'])) $_POST['ewww_image_optimizer_cloud_png'] = '';
			update_site_option('ewww_image_optimizer_cloud_png', $_POST['ewww_image_optimizer_cloud_png']);
			if (empty($_POST['ewww_image_optimizer_cloud_png_compress'])) $_POST['ewww_image_optimizer_cloud_png_compress'] = '';
			update_site_option('ewww_image_optimizer_cloud_png_compress', $_POST['ewww_image_optimizer_cloud_png_compress']);
			if (empty($_POST['ewww_image_optimizer_cloud_gif'])) $_POST['ewww_image_optimizer_cloud_gif'] = '';
			update_site_option('ewww_image_optimizer_cloud_gif', $_POST['ewww_image_optimizer_cloud_gif']);
			if (empty($_POST['ewww_image_optimizer_auto'])) $_POST['ewww_image_optimizer_auto'] = '';
			update_site_option('ewww_image_optimizer_auto', $_POST['ewww_image_optimizer_auto']);
			if (empty($_POST['ewww_image_optimizer_aux_paths'])) $_POST['ewww_image_optimizer_aux_paths'] = '';
			update_site_option('ewww_image_optimizer_aux_paths', ewww_image_optimizer_aux_paths_sanitize($_POST['ewww_image_optimizer_aux_paths']));
			if (empty($_POST['ewww_image_optimizer_enable_cloudinary'])) $_POST['ewww_image_optimizer_enable_cloudinary'] = '';
			update_site_option('ewww_image_optimizer_enable_cloudinary', $_POST['ewww_image_optimizer_enable_cloudinary']);
			if (empty($_POST['ewww_image_optimizer_delay'])) $_POST['ewww_image_optimizer_delay'] = '';
			update_site_option('ewww_image_optimizer_delay', intval($_POST['ewww_image_optimizer_delay']));
			if (empty($_POST['ewww_image_optimizer_interval'])) $_POST['ewww_image_optimizer_interval'] = '';
			update_site_option('ewww_image_optimizer_interval', intval($_POST['ewww_image_optimizer_interval']));
			if (empty($_POST['ewww_image_optimizer_skip_size'])) $_POST['ewww_image_optimizer_skip_size'] = '';
			update_site_option('ewww_image_optimizer_skip_size', intval($_POST['ewww_image_optimizer_skip_size']));
			add_action('network_admin_notices', 'ewww_image_optimizer_network_settings_saved');
		}
	}
	// register all the EWWW IO settings
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_check');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_bundle');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_debug');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_copy');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_png_lossy');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_lossy_skip_full');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_optipng_level');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_pngout_level');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_path');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_optipng_path');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_gifsicle_path');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_jpegtran');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_optipng');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_gifsicle');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_pngout');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_delete_originals');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_to_png');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_png_to_jpg');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_gif_to_png');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_background', 'ewww_image_optimizer_jpg_background_sanitize');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality', 'ewww_image_optimizer_jpg_quality_sanitize');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_convert_links');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_resume');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_attachments');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_aux_resume');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_aux_attachments');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_aux_type');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_key', 'ewww_image_optimizer_cloud_key_sanitize');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_jpg');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_png');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_png_compress');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_gif');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_auto');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_aux_paths', 'ewww_image_optimizer_aux_paths_sanitize');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_enable_cloudinary');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_delay', 'intval');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_interval', 'intval');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_size', 'intval');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_import_status');
	// setup scheduled optimization if the user has enabled it, and it isn't already scheduled
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE && !wp_next_scheduled('ewww_image_optimizer_auto')) {
		$ewww_debug .= "scheduling auto-optimization<br>";
		wp_schedule_event(time(), 'hourly', 'ewww_image_optimizer_auto');
	} elseif (ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE) {
		$ewww_debug .= "auto-optimization already scheduled: " . wp_next_scheduled('ewww_image_optimizer_auto') . "<br>";
	} elseif (wp_next_scheduled('ewww_image_optimizer_auto')) {
		$ewww_debug .= "un-scheduling auto-optimization<br>";
		wp_clear_scheduled_hook('ewww_image_optimizer_auto');
		if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) {
			global $wpdb;
			if (function_exists('wp_get_sites')) {
				add_filter('wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0);
				$blogs = wp_get_sites(array(
					'network_id' => $wpdb->siteid,
					'limit' => 10000
				));
				remove_filter('wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0);
			} else {
				$query = "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";
				$blogs = $wpdb->get_results($query, ARRAY_A);
			}
			foreach ($blogs as $blog) {
				switch_to_blog($blog['blog_id']);
				wp_clear_scheduled_hook('ewww_image_optimizer_auto');
			}
			restore_current_blog();
		}
	}
	// require the files that do the bulk processing
	require_once(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php');
	require_once(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php');
	// queue the function that contains custom styling for our progressbars, but only in wp 3.8+
	global $wp_version;
	if ( substr($wp_version, 0, 3) >= 3.8 ) { 
		add_action('admin_enqueue_scripts', 'ewww_image_optimizer_progressbar_style');
	}
}

// tells the user they are on an unsupported operating system
function ewww_image_optimizer_notice_os() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_notice_os()</b><br>";
	echo "<div id='ewww-image-optimizer-warning-os' class='error'><p><strong>" . __('EWWW Image Optimizer is supported on Linux, FreeBSD, Mac OSX, and Windows', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</strong> " . sprintf(__('Unfortunately, the EWWW Image Optimizer plugin does not work with %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities(PHP_OS)) . ".</p></div>";
}   

// set some default option values
function ewww_image_optimizer_set_defaults() {
	// set a few defaults
	add_option('ewww_image_optimizer_disable_pngout', TRUE);
	add_option('ewww_image_optimizer_optipng_level', 2);
	add_option('ewww_image_optimizer_pngout_level', 2);
}

// checks the binary at $path against a list of valid md5sums
function ewww_image_optimizer_md5check($path) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_md5check()</b><br>";
	$ewww_debug .= "$path: " . md5_file($path) . "<br>";
	$valid_md5sums = array(
		//jpegtran
		'e2ba2985107600ebb43f85487258f6a3',
		'67c1dbeab941255a4b2b5a99db3c6ef5',
		'4a78fdeac123a16d2b9e93b6960e80b1',
		'a3f65d156a4901226cb91790771ca73f',
		'98cca712e6c162f399e85aec740bf560',
		'2dab67e5f223b70c43b2fef355b39d3f',
		'4da4092708650ceb79df19d528e7956b',
		'9d482b93d4129f7e87ce36c5e650de0c',
		'1c251658834162b01913702db0013c08',
		'dabf8173725e15d866f192f77d9e3883',
		'e4f7809c84a0722abe2b1d003c98a181',
		//optipng
		'4eb91937291ce5038d0c68f5f2edbcfd',
		'899e3c569080a55bcc5de06a01c8e23a',
		'0467bd0c73473221d21afbc5275503e4',
		'293e26924a274c6185a06226619d8e02',
		'bcb27d22377f8abf3e9fe88a60030885',
		//gifsicle
		'2384f770d307c42b9c1e53cdc8dd662d',
		'24fc5f33b33c0d11fb2e88f5a93949d0',
		'e4a14bce92755261fe21798c295d06db',
		'9ddef564fed446700a3a7303c39610a3',
		'aad47bafdb2bc8a9f0755f57f94d6eaf',
		'46360c01622ccb514e9e7ef1ac5398f0',
		'44273fad7b3fd1145bfcf35189648f66',
		'4568ef450ec9cd73bab55d661fb167ec',
		'f8d8baa175977a23108c84603dbfcc78',
		'3b592b6398dd7f379740c0b63e83825c',
		'ce935b38b1fd7b3c47d5de57f05c8cd2',
		'fe94420e6c49c29861b95ad93a3a4805',
		'151e395e2efa0e7845b18984d0f092af',
		'7ae972062cf3f99218057b055a4e1e9c',
		'c0bf45a291b93fd0a52318eddeaf5791',
		//pngout
		'2b62778559e31bc750dc2dcfd249be32', 
		'ea8655d1a1ef98833b294fb74f349c3e',
		'a30517e045076cab1bb5b5f3a57e999e',
		'6e60aafca40ecc0e648c442f83fa9688',
		'1882ae8efb503c4abdd0d18d974d5fa3',
		'aad1f8107955876efb0b0d686450e611',
		'991f9e7d2c39cb1f658684971d583468',
		'5de47b8cc0943eeceaf1683cb544b4a0',
		'c30de32f31259b79ffb13ca0d9d7a77d',
		'670a0924e9d042be2c60cd4f3ce1d975',
		'c77c5c870755e9732075036a548d8e61',
		'37cdbfcdedc9079f23847f0349efa11c',
		'8bfc5e0e6f0f964c7571988b0e9e2017',
		'b8ead81e0ed860d6461f67d60224ab7b',
		'f712daee5048d5d70197c5f339ac0b02',
		'e006b880f9532af2af0811515218bbd4',
		'b175b4439b054a61e8a41eca9a6e3505',
		'eabcbabde6c7c568e95afd73b7ed096e',
		//pngquant
		'6eff276339f9ad818eecd347a5fa99e2',
		'79d8c4f5ff2dbb36068c3e3de42fdb1e',
		'90ea1271c54ce010afba478c5830a75f',
		'3ad57a9c3c9093d65664f3260f44df60',
		);
	foreach ($valid_md5sums as $md5_sum) {
		if ($md5_sum == md5_file($path)) {
			return TRUE;
		}
	}
	return FALSE;
}

// check the mimetype of the given file ($path) with various methods
// valid values for $type are 'b' for binary or 'i' for image
function ewww_image_optimizer_mimetype($path, $case) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_mimetype()</b><br>";
	$ewww_debug .= "testing mimetype: $path <br>";
	if (function_exists('finfo_file') && defined('FILEINFO_MIME')) {
		// create a finfo resource
		$finfo = finfo_open(FILEINFO_MIME);
		// retrieve the mimetype
		$type = explode(';', finfo_file($finfo, $path));
		$type = $type[0];
		finfo_close($finfo);
		$ewww_debug .= "finfo_file: $type <br>";
	}
	// see if we can use the getimagesize function
	if (empty($type) && function_exists('getimagesize') && $case === 'i') {
		// run getimagesize on the file
		$type = getimagesize($path);
		// make sure we have results
		if(false !== $type){
			// store the mime-type
			$type = $type['mime'];
		}
		$ewww_debug .= "getimagesize: $type <br>";
	}
	// see if we can use mime_content_type
	if (empty($type) && function_exists('mime_content_type')) {
		// retrieve and store the mime-type
		$type = mime_content_type($path);
		$ewww_debug .= "mime_content_type: $type <br>";
	}
	// if nothing else has worked, try the 'file' command
	if ((empty($type) || $type != 'application/x-executable') && $case == 'b') {
		// find the 'file' command
		if ($file = ewww_image_optimizer_find_binary('file', 'f')) {
			// run 'file' on the file in question
			exec("$file $path", $filetype);
			$ewww_debug .= "file command: $filetype[0] <br>";
			// if we've found a proper binary
			if ((strpos($filetype[0], 'ELF') && strpos($filetype[0], 'executable')) || strpos($filetype[0], 'Mach-O universal binary')) {
				$type = 'application/x-executable';
			}
		}
	}
	// if we are dealing with a binary, and found an executable
	if ($case == 'b' && preg_match('/executable|octet-stream/', $type)) {
		return $type;
	// otherwise, if we are dealing with an image
	} elseif ($case == 'i') {
		return $type;
	// if all else fails, bail
	} else {
		return false;
	}
}

// test the given path ($path) to see if it returns a valid version string
// returns: version string if found, FALSE if not
function ewww_image_optimizer_tool_found($path, $tool) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_tool_found()</b><br>";
	$ewww_debug .= "testing case: $tool at $path<br>";
	switch($tool) {
		case 'j': // jpegtran
			exec($path . ' -v ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'sample.jpg 2>&1', $jpegtran_version);
			if (!empty($jpegtran_version)) $ewww_debug .= "$path: $jpegtran_version[0]<br>";
			foreach ($jpegtran_version as $jout) { 
				if (preg_match('/Independent JPEG Group/', $jout)) {
					return $jout;
				}
			}
			break;
		case 'o': // optipng
			exec($path . ' -v 2>&1', $optipng_version);
			if (!empty($optipng_version)) $ewww_debug .= "$path: $optipng_version[0]<br>";
			if (!empty($optipng_version) && strpos($optipng_version[0], 'OptiPNG') === 0) {
				return $optipng_version[0];
			}
			break;
		case 'g': // gifsicle
			exec($path . ' --version 2>&1', $gifsicle_version);
			if (!empty($gifsicle_version)) $ewww_debug .= "$path: $gifsicle_version[0]<br>";
			if (!empty($gifsicle_version) && strpos($gifsicle_version[0], 'LCDF Gifsicle') === 0) {
				return $gifsicle_version[0];
			}
			break;
		case 'p': // pngout
			exec("$path 2>&1", $pngout_version);
			if (!empty($pngout_version)) $ewww_debug .= "$path: $pngout_version[0]<br>";
			if (!empty($pngout_version) && strpos($pngout_version[0], 'PNGOUT') === 0) {
				return $pngout_version[0];
			}
			break;
		case 'q': // pngquant
			exec($path . ' -V 2>&1', $pngquant_version);
			if ( ! empty( $pngquant_version ) ) $ewww_debug .= "$path: $pngquant_version[0]<br>";
			if ( ! empty( $pngquant_version ) && substr( $pngquant_version[0], 0, 3 ) >= 2.0 ) {
			//if (!empty($pngquant_version) && strpos($pngquant_version[0], '2.0') === 0) {
				return $pngquant_version[0];
			}
			break;
		case 'i': // ImageMagick
			exec("$path -version 2>&1", $convert_version);
			if (!empty($convert_version)) $ewww_debug .= "$path: $convert_version[0]<br>";
			if (!empty($convert_version) && strpos($convert_version[0], 'ImageMagick')) {
				return $convert_version[0];
			}
			break;
		case 'f': // file
			exec("$path -v 2>&1", $file_version);
			if (!empty($file_version[1])) $ewww_debug .= "$path: $file_version[1]<br>";
			if (!empty($file_version[1]) && preg_match('/magic/', $file_version[1])) {
				return $file_version[0];
			} elseif (!empty($file_version[1]) && preg_match('/usage: file/', $file_version[1])) {
				return $file_version[0];
			}
			break;
		case 'n': // nice
			exec("$path 2>&1", $nice_output);
			if (isset($nice_output)) $ewww_debug .= "$path: $nice_output[0]<br>";
			if (isset($nice_output) && preg_match('/usage/', $nice_output[0])) {
				return TRUE;
			} elseif (isset($nice_output) && preg_match('/^\d+$/', $nice_output[0])) {
				return TRUE;
			}
			break;
		case 't': // tar
			exec("$path --version 2>&1", $tar_version);
			if (!empty($tar_version[0])) $ewww_debug .= "$path: $tar_version[0]<br>";
			if (!empty($tar_version[0]) && preg_match('/bsdtar/', $tar_version[0])) {
				return $tar_version[0];
			} elseif (!empty($tar_version[0]) && preg_match('/GNU tar/i', $tar_version[0])) {
				return $tar_version[0];
			}
			break;
	}
	return FALSE;
}

// escape any spaces in the filename, not sure any more than that is necessary for unixy systems
function ewww_image_optimizer_escapeshellcmd ($path) {
	return (preg_replace('/ /', '\ ', $path));
}

// If the utitilites are in the content folder, we use that. Otherwise, we check system paths. We also do a basic check to make sure we weren't given a malicious path.
function ewww_image_optimizer_path_check ( $j = true, $o = true, $g = true, $p = true, $q = true) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_path_check()</b><br>";
	$jpegtran = false;
	$optipng = false;
	$gifsicle = false;
	$pngout = false;
	$pngquant = false;
	// for Windows, everything must be in the wp-content/ewww folder, so that is all we check
	if ('WINNT' == PHP_OS) {
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe') && $j) {
			$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe';
			$ewww_debug .= "found $jpt, testing...<br>";
			if (ewww_image_optimizer_tool_found('"' . $jpt . '"', 'j') && ewww_image_optimizer_md5check($jpt)) {
				$jpegtran = '"' . $jpt . '"';
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe') && $o) {
			$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe';
			$ewww_debug .= "found $opt, testing...<br>";
			if (ewww_image_optimizer_tool_found('"' . $opt . '"', 'o') && ewww_image_optimizer_md5check($opt)) {
				$optipng = '"' . $opt . '"';
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe') && $g) {
			$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe';
			$ewww_debug .= "found $gpt, testing...<br>";
			if (ewww_image_optimizer_tool_found('"' . $gpt . '"', 'g') && ewww_image_optimizer_md5check($gpt)) {
				$gifsicle = '"' . $gpt . '"';
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe') && $p) {
			$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe';
			$ewww_debug .= "found $ppt, testing...<br>";
			if (ewww_image_optimizer_tool_found('"' . $ppt . '"', 'p') && ewww_image_optimizer_md5check($ppt)) {
				$pngout = '"' . $ppt . '"';
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant.exe') && $q) {
			$qpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant.exe';
			$ewww_debug .= "found $qpt, testing...<br>";
			if (ewww_image_optimizer_tool_found('"' . $qpt . '"', 'q') && ewww_image_optimizer_md5check($qpt)) {
				$pngquant = '"' . $qpt . '"';
			}
		}
	} else {
		// check to see if the user has disabled using bundled binaries
		$use_system = ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle');
		if ($j) {
			// first check for the jpegtran binary in the ewww tool folder
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran') && !$use_system) {
				$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
				$ewww_debug .= "found $jpt, testing...<br>";
				if (ewww_image_optimizer_md5check($jpt) && ewww_image_optimizer_mimetype($jpt, 'b')) {
					$jpt = ewww_image_optimizer_escapeshellcmd ( $jpt );
					if (ewww_image_optimizer_tool_found($jpt, 'j')) {
						$jpegtran = $jpt;
					}
				}
			}
			// if the standard jpegtran binary didn't work, see if the user custom compiled one and check that
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom') && !$jpegtran && !$use_system) {
				$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom';
				$ewww_debug .= "found $jpt, testing...<br>";
				if (filesize($jpt) > 15000 && ewww_image_optimizer_mimetype($jpt, 'b')) {
					$jpt = ewww_image_optimizer_escapeshellcmd ( $jpt );
					if (ewww_image_optimizer_tool_found($jpt, 'j')) {
						$jpegtran = $jpt;
					}
				}
			}
			// if we still haven't found a usable binary, try a system-installed version
			if (!$jpegtran) {
				$jpegtran = ewww_image_optimizer_find_binary('jpegtran', 'j');
			}
		}
		if ($o) {
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng') && !$use_system) {
				$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
				$ewww_debug .= "found $opt, testing...<br>";
				if (ewww_image_optimizer_md5check($opt) && ewww_image_optimizer_mimetype($opt, 'b')) {
					$opt = ewww_image_optimizer_escapeshellcmd ( $opt );
					if (ewww_image_optimizer_tool_found($opt, 'o')) {
						$optipng = $opt;
					}
				}
			}
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') && !$optipng && !$use_system) {
				$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom';
				$ewww_debug .= "found $opt, testing...<br>";
				if (filesize($opt) > 15000 && ewww_image_optimizer_mimetype($opt, 'b')) {
					$opt = ewww_image_optimizer_escapeshellcmd ( $opt );
					if (ewww_image_optimizer_tool_found($opt, 'o')) {
						$optipng = $opt;
					}
				}
			}
			if (!$optipng) {
				$optipng = ewww_image_optimizer_find_binary('optipng', 'o');
			}
		}
		if ($g) {
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle') && !$use_system) {
				$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
				$ewww_debug .= "found $gpt, testing...<br>";
				if (ewww_image_optimizer_md5check($gpt) && ewww_image_optimizer_mimetype($gpt, 'b')) {
					$gpt = ewww_image_optimizer_escapeshellcmd ( $gpt );
					if (ewww_image_optimizer_tool_found($gpt, 'g')) {
						$gifsicle = $gpt;
					}
				}
			}
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') && !$gifsicle && !$use_system) {
				$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom';
				$ewww_debug .= "found $gpt, testing...<br>";
				if (filesize($gpt) > 15000 && ewww_image_optimizer_mimetype($gpt, 'b')) {
					$gpt = ewww_image_optimizer_escapeshellcmd ( $gpt );
					if (ewww_image_optimizer_tool_found($gpt, 'g')) {
						$gifsicle = $gpt;
					}
				}
			}
			if (!$gifsicle) {
				$gifsicle = ewww_image_optimizer_find_binary('gifsicle', 'g');
			}
		}
		if ($p) {
			// pngout is special and has a dynamic and static binary to check
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static') && !$use_system) {
				$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static';
				$ewww_debug .= "found $ppt, testing...<br>";
				if (ewww_image_optimizer_md5check($ppt) && ewww_image_optimizer_mimetype($ppt, 'b')) {
					$ppt = ewww_image_optimizer_escapeshellcmd ( $ppt );
					if (ewww_image_optimizer_tool_found($ppt, 'p')) {
						$pngout = $ppt;
					}
				}
			}
			if (!$pngout) {
				$pngout = ewww_image_optimizer_find_binary('pngout', 'p');
			}
			if (!$pngout) {
				$pngout = ewww_image_optimizer_find_binary('pngout-static', 'p');
			}
		}
		if ($q) {
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant') && !$use_system) {
				$qpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant';
				$ewww_debug .= "found $qpt, testing...<br>";
				if (ewww_image_optimizer_md5check($qpt) && ewww_image_optimizer_mimetype($qpt, 'b')) {
					$qpt = ewww_image_optimizer_escapeshellcmd ( $qpt );
					if (ewww_image_optimizer_tool_found($qpt, 'q')) {
						$pngquant = $qpt;
					}
				}
			}
			if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant-custom') && !$pngquant && !$use_system) {
				$qpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant-custom';
				$ewww_debug .= "found $qpt, testing...<br>";
				if (filesize($qpt) > 15000 && ewww_image_optimizer_mimetype($qpt, 'b')) {
					$qpt = ewww_image_optimizer_escapeshellcmd ( $qpt );
					if (ewww_image_optimizer_tool_found($qpt, 'q')) {
						$pngquant = $qpt;
					}
				}
			}
			if (!$pngquant) {
				$pngquant = ewww_image_optimizer_find_binary('pngquant', 'q');
			}
		}
	}
	if ($jpegtran) $ewww_debug .= "using: $jpegtran<br>";
	if ($optipng) $ewww_debug .= "using: $optipng<br>";
	if ($gifsicle) $ewww_debug .= "using: $gifsicle<br>";
	if ($pngout) $ewww_debug .= "using: $pngout<br>";
	if ($pngquant) $ewww_debug .= "using: $pngquant<br>";
	return array(
		'JPEGTRAN' => $jpegtran,
		'OPTIPNG' => $optipng,
		'GIFSICLE' => $gifsicle,
		'PNGOUT' => $pngout,
		'PNGQUANT' => $pngquant,
	);
}

// generates the source and destination paths for the executables that we bundle with the plugin based on the operating system
function ewww_image_optimizer_install_paths () {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_install_paths()</b><br>";
	if (PHP_OS == 'WINNT') {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle.exe';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng.exe';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran.exe';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngquant.exe';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe';
		$pngquant_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant.exe';
	}
	if (PHP_OS == 'Darwin') {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-mac';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-mac';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-mac';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngquant-mac';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
		$pngquant_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant';
	}
	if (PHP_OS == 'SunOS') {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-sol';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-sol';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-sol';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngquant-sol';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
		$pngquant_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant';
	}
	if (PHP_OS == 'FreeBSD') {
		$arch_type = php_uname('m');
		$ewww_debug .= "CPU architecture: $arch_type<br>";
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-fbsd';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-fbsd';
		if ($arch_type == 'amd64') {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-fbsd64';
		} else {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-fbsd';
		}
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngquant-fbsd';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
		$pngquant_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant';
	}
	if (PHP_OS == 'Linux') {
		$arch_type = php_uname('m');
		$ewww_debug .= "CPU architecture: $arch_type<br>";
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-linux';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-linux';
		if ($arch_type == 'x86_64') {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-linux64';
		} else {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-linux';
		}
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngquant-linux';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
		$pngquant_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngquant';
	}
	$ewww_debug .= "generated paths:<br>$jpegtran_src<br>$optipng_src<br>$gifsicle_src<br>$pngquant_src<br>$jpegtran_dst<br>$optipng_dst<br>$gifsicle_dst<br>$pngquant_dst<br>";
	return array($jpegtran_src, $optipng_src, $gifsicle_src, $pngquant_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst, $pngquant_dst);
}

// searches system paths for the given $binary and passes along the $switch
function ewww_image_optimizer_find_binary ($binary, $switch) {
	if (ewww_image_optimizer_tool_found($binary, $switch)) {
		return $binary;
	} elseif (ewww_image_optimizer_tool_found('/usr/bin/' . $binary, $switch)) {
		return '/usr/bin/' . $binary;
	} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/' . $binary, $switch)) {
		return '/usr/local/bin/' . $binary;
	} elseif (ewww_image_optimizer_tool_found('/usr/gnu/bin/' . $binary, $switch)) {
		return '/usr/gnu/bin/' . $binary;
	} elseif (ewww_image_optimizer_tool_found('/usr/syno/bin/' . $binary, $switch)) { // for synology diskstation OS
		return '/usr/syno/bin/' . $binary;
	} else {
		return '';
	}
}

// installs the executables that are bundled with the plugin
function ewww_image_optimizer_install_tools () {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_install_tools()</b><br>";
	$ewww_debug .= "Checking/Installing tools in " . EWWW_IMAGE_OPTIMIZER_TOOL_PATH . "<br>";
	$toolfail = false;
	if (!is_dir(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) {
		$ewww_debug .= "Folder doesn't exist, creating...<br>";
		if (!mkdir(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) {
			echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>" . __('EWWW Image Optimizer could not create the tool folder', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ": " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . ".</strong> " . __('Please adjust permissions or create the folder', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</p></div>";
			$ewww_debug .= "Couldn't create folder<br>";
		}
	} else {
		$ewww_perms = substr(sprintf('%o', fileperms(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)), -4);
		$ewww_debug .= "wp-content/ewww permissions: $ewww_perms <br />";
	}
	list ($jpegtran_src, $optipng_src, $gifsicle_src, $pngquant_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst, $pngquant_dst) = ewww_image_optimizer_install_paths();
	if (!file_exists($jpegtran_dst)) {
		$ewww_debug .= "jpegtran not found, installing<br>";
		if (!copy($jpegtran_src, $jpegtran_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy jpegtran<br>";
		}
	} else if (filesize($jpegtran_dst) != filesize($jpegtran_src)) {
		$ewww_debug .= "jpegtran found, different size, attempting to replace<br>";
		if (!copy($jpegtran_src, $jpegtran_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy jpegtran<br>";
		}
	}
	// install 32-bit jpegtran at jpegtran-custom for some weird 64-bit hosts
	$arch_type = php_uname('m');
	if (PHP_OS == 'Linux' && $arch_type == 'x86_64') {
		$ewww_debug .= "64-bit linux detected while installing tools<br>";
		$jpegtran32_src = substr($jpegtran_src, 0, -2);
		$jpegtran32_dst = $jpegtran_dst . '-custom';
		if (!file_exists($jpegtran32_dst) || (ewww_image_optimizer_md5check($jpegtran32_dst) && filesize($jpegtran32_dst) != filesize($jpegtran32_src))) {
			$ewww_debug .= "copying $jpegtran32_src to $jpegtran32_dst<br>";
			if (!copy($jpegtran32_src, $jpegtran32_dst)) {
				// this isn't a fatal error, besides we'll see it in the debug if needed
				$ewww_debug .= "Couldn't copy 32-bit jpegtran to jpegtran-custom<br>";
			}
			$jpegtran32_perms = substr(sprintf('%o', fileperms($jpegtran32_dst)), -4);
			$ewww_debug .= "jpegtran-custom (32-bit) permissions: $jpegtran32_perms<br>";
			if ($jpegtran32_perms != '0755') {
				if (!chmod($jpegtran32_dst, 0755)) {
					$ewww_debug .= "couldn't set jpegtran-custom permissions<br>";
				}
			}
		}
	}
	if (!file_exists($gifsicle_dst)) {
		$ewww_debug .= "gifsicle not found, installing<br>";
		if (!copy($gifsicle_src, $gifsicle_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy gifsicle<br>";
		}
	} else if (filesize($gifsicle_dst) != filesize($gifsicle_src)) {
		$ewww_debug .= "gifsicle found, different size, attempting to replace<br>";
		if (!copy($gifsicle_src, $gifsicle_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy gifsicle<br>";
		}
	}
	if (!file_exists($optipng_dst)) {
		$ewww_debug .= "optipng not found, installing<br>";
		if (!copy($optipng_src, $optipng_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy optipng<br>";
		}
	} else if (filesize($optipng_dst) != filesize($optipng_src)) {
		$ewww_debug .= "optipng found, different size, attempting to replace<br>";
		if (!copy($optipng_src, $optipng_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy optipng<br>";
		}
	}
	if (!file_exists($pngquant_dst)) {
		$ewww_debug .= "pngquant not found, installing<br>";
		if (!copy($pngquant_src, $pngquant_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy pngquant<br>";
		}
	} else if (filesize($pngquant_dst) != filesize($pngquant_src)) {
		$ewww_debug .= "pngquant found, different size, attempting to replace<br>";
		if (!copy($pngquant_src, $pngquant_dst)) {
			$toolfail = true;
			$ewww_debug .= "Couldn't copy pngquant<br>";
		}
	}
	if (PHP_OS != 'WINNT') {
		$ewww_debug .= "Linux/UNIX style OS, checking permissions<br>";
		$jpegtran_perms = substr(sprintf('%o', fileperms($jpegtran_dst)), -4);
		$ewww_debug .= "jpegtran permissions: $jpegtran_perms<br>";
		if ($jpegtran_perms != '0755') {
			if (!chmod($jpegtran_dst, 0755)) {
				$toolfail = true;
				$ewww_debug .= "couldn't set jpegtran permissions<br>";
			}
		}
		$gifsicle_perms = substr(sprintf('%o', fileperms($gifsicle_dst)), -4);
		$ewww_debug .= "gifsicle permissions: $gifsicle_perms<br>";
		if ($gifsicle_perms != '0755') {
			if (!chmod($gifsicle_dst, 0755)) {
				$toolfail = true;
				$ewww_debug .= "couldn't set gifsicle permissions<br>";
			}
		}
		$optipng_perms = substr(sprintf('%o', fileperms($optipng_dst)), -4);
		$ewww_debug .= "optipng permissions: $optipng_perms<br>";
		if ($optipng_perms != '0755') {
			if (!chmod($optipng_dst, 0755)) {
				$toolfail = true;
				$ewww_debug .= "couldn't set optipng permissions<br>";
			}
		}
		$pngquant_perms = substr(sprintf('%o', fileperms($pngquant_dst)), -4);
		$ewww_debug .= "pngquant permissions: $pngquant_perms<br>";
		if ($pngquant_perms != '0755') {
			if (!chmod($pngquant_dst, 0755)) {
				$toolfail = true;
				$ewww_debug .= "couldn't set pngquant permissions<br>";
			}
		}
	}
	if ($toolfail) {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>" . sprintf(__('EWWW Image Optimizer could not install tools in %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) . ".</strong> " . sprintf(__('Please adjust permissions or create the folder. If you have installed the tools elsewhere on your system, check the option to %s.', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('Use System Paths', EWWW_IMAGE_OPTIMIZER_DOMAIN)) . " " . sprintf(__('For more details, visit the %1$s or the %2$s.', EWWW_IMAGE_OPTIMIZER_DOMAIN), "<a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>" . __('Settings Page', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>", "<a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>" . __('Installation Instructions', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>.</p></div>");
	}
	$migrate_fail = false;
	if ($jpegtran_path = ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_path')) {
		$ewww_debug .= "found path setting for jpegtran, migrating<br>";
		if (file_exists($jpegtran_path)) {
			$ewww_debug .= "found custom jpegtran binary<br>";
			if (!copy($jpegtran_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom', 0755)) {
				$ewww_debug .= "unable to copy custom jpegtran binary or set permissions<br>";
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_jpegtran_path');
				$ewww_debug .= "migration successful, deleting path setting<br>";
			}
		}
	}
	if ($optipng_path = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_path')) {
		$ewww_debug .= "found path setting for optipng, migrating<br>";
		if (file_exists($optipng_path)) {
			$ewww_debug .= "found custom optipng binary<br>";
			if (!copy($optipng_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom', 0755)) {
				$ewww_debug .= "unable to copy custom optipng binary or set permissions<br>";
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_optipng_path');
				$ewww_debug .= "migration successful, deleting path setting<br>";
			}
		}
	}
	if ($gifsicle_path = ewww_image_optimizer_get_option('ewww_image_optimizer_gifsicle_path')) {
		$ewww_debug .= "found path setting for gifsicle, migrating<br>";
		if (file_exists($gifsicle_path)) {
			$ewww_debug .= "found custom gifsicle binary<br>";
			if (!copy($gifsicle_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom', 0755)) {
				$ewww_debug .= "unable to copy custom gifislce binary or set permissions<br>";
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_gifsicle_path');
				$ewww_debug .= "migration successful, deleting path setting<br>";
			}
		}
	}
	if ($migrate_fail) {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>" . sprintf(__('EWWW Image Optimizer attempted to move your custom-built binaries to %s but the operation was unsuccessful', EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) . ".</strong> " . __('Please adjust the permissions on this folder', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</p></div>";
	}
}

// function to check if exec() is disabled
function ewww_image_optimizer_exec_check() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_exec_check()</b><br>";
	$disabled = ini_get('disable_functions');
	$ewww_debug .= "disable_functions = $disabled <br>";
	$suhosin_disabled = ini_get('suhosin.executor.func.blacklist');
	$ewww_debug .= "suhosin_blacklist = $suhosin_disabled <br>";
	if(preg_match('/([\s,]+exec|^exec)/', $disabled) || preg_match('/([\s,]+exec|^exec)/', $suhosin_disabled)) {
		return true;
	} else {
		return false;
	}
}

// function to check if safe mode is on
function ewww_image_optimizer_safemode_check() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_safemode_check()</b><br>";
	$safe_mode = ini_get('safe_mode');
	$ewww_debug .= "safe_mode = $safe_mode<br>";
	switch (strtolower($safe_mode)) {
		case 'off':
			return false;
		case 'on':
		case true:
			return true;
		default:
			return false;
	}
}

// we check for safe mode and exec, then also direct the user where to go if they don't have the tools installed
function ewww_image_optimizer_notice_utils() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_notice_utils()</b><br>";
	// Check if exec is disabled
	if(ewww_image_optimizer_exec_check()) {
		//display a warning if exec() is disabled, can't run much of anything without it
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p>" . __('EWWW Image Optimizer requires exec(). Your system administrator has disabled this function.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></div>";
		define('EWWW_IMAGE_OPTIMIZER_NOEXEC', true);
		ewww_image_optimizer_disable_tools();
		return;
		// otherwise, query the php settings for safe mode
	} elseif (ewww_image_optimizer_safemode_check()) {
		// display a warning to the user
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p>" . __('Safe Mode is turned on for PHP. This plugin cannot operate in Safe Mode.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></div>";
		define('EWWW_IMAGE_OPTIMIZER_NOEXEC', true);
		ewww_image_optimizer_disable_tools();
		return;
	} else {
		define('EWWW_IMAGE_OPTIMIZER_NOEXEC', false);
	}

	// attempt to retrieve values for utility paths, and store them in the appropriate variables
	$required = ewww_image_optimizer_path_check();
	// set the variables false otherwise
	$skip_jpegtran_check = false;
	$skip_optipng_check = false;
	$skip_gifsicle_check = false;
	$skip_pngout_check = false;
	$skip_pngquant_check = true;
	// if the user has disabled a variable, we aren't going to bother checking to see if it is there
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_jpegtran')) {
		$skip_jpegtran_check = true;
	}
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng')) {
		$skip_optipng_check = true;
	}
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_gifsicle')) {
		$skip_gifsicle_check = true;
	}
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
		$skip_pngout_check = true;
	}
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_png_lossy')) {
		$skip_pngquant_check = false;
	}
	// we are going to store our validation results in $missing
	$missing = array();
	// go through each of the required tools
	foreach($required as $key => $req){
		// if the tool wasn't found, add it to the $missing array if we are supposed to check the tool in question
		switch($key) {
			case 'JPEGTRAN':
				if (!$skip_jpegtran_check && empty($req)) {
					$missing[] = 'jpegtran';
					$req = false;
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $req);
				break; 
			case 'OPTIPNG':
				if (!$skip_optipng_check && empty($req)) {
					$missing[] = 'optipng';
					$req = false;
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $req);
				break;
			case 'GIFSICLE':
				if (!$skip_gifsicle_check && empty($req)) {
					$missing[] = 'gifsicle';
					$req = false;
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $req);
				break;
			case 'PNGOUT':
				if (!$skip_pngout_check && empty($req)) {
					$missing[] = 'pngout';
					$req = false;
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $req);
				break;
			case 'PNGQUANT':
				if (!$skip_pngquant_check && empty($req)) {
					$missing[] = 'pngquant';
					$req = false;
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $req);
				break;
		}
	}
	// expand the missing utilities list for use in the error message
	$msg = implode(', ', $missing);
	// if there is a message, display the warning
	if(!empty($msg)){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p>" . sprintf(__('EWWW Image Optimizer uses %1$s, %2$s, %3$s, %4$s, and %5$s. You are missing: %6$s. Please install via the %7$s or the %8$s.', EWWW_IMAGE_OPTIMIZER_DOMAIN), "<a href='http://jpegclub.org/jpegtran/'>jpegtran</a>", "<a href='http://optipng.sourceforge.net/'>optipng</a>", "<a href='http://advsys.net/ken/utils.htm'>pngout</a>", "<a href='http://pngquant.org/'>pngquant</a>", "<a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>", $msg, "<a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>" . __('Settings Page', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>", "<a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>" . __('Installation Instructions', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>") . "</p></div>";
	}
}

/**
 * Process an image.
 *
 * Returns an array of the $file, $results, $converted to tell us if an image changes formats, and the $original file if it did.
 *
 * @param   string $file		Full absolute path to the image file
 * @param   int $gallery_type		1=wordpress, 2=nextgen, 3=flagallery, 4=aux_images, 5=image editor, 6=imagestore, 7=retina
 * @param   boolean $converted		tells us if this is a resize and the full image was converted to a new format
 * @returns array
 */
function ewww_image_optimizer($file, $gallery_type, $converted, $new, $fullsize = false) {
//	global $wpdb;
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer()</b><br>";
	// if the plugin gets here without initializing, we need to run through some things first
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
		ewww_image_optimizer_init();
	}
	$bypass_optimization = apply_filters( 'ewww_image_optimizer_bypass', false, $file );
	if (true === $bypass_optimization) {
		// tell the user optimization was skipped
		$msg = __( "Optimization skipped", EWWW_IMAGE_OPTIMIZER_DOMAIN );
		$ewww_debug .= "optimization bypassed: $file <br>";
		// send back the above message
		return array(false, $msg, $converted, $file);
	}
	// initialize the original filename 
	$original = $file;
	$result = '';
	// check that the file exists
	if (FALSE === file_exists($file)) {
		// tell the user we couldn't find the file
		$msg = sprintf(__("Could not find <span class='code'>%s</span>", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		$ewww_debug .= "file doesn't appear to exist: $file <br>";
		// send back the above message
		return array(false, $msg, $converted, $original);
	}
	// check that the file is writable
	if ( FALSE === is_writable($file) ) {
		// tell the user we can't write to the file
		$msg = sprintf(__("<span class='code'>%s</span> is not writable", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		$ewww_debug .= "couldn't write to the file<br>";
		// send back the above message
		return array(false, $msg, $converted, $original);
	}
	if (function_exists('fileperms'))
		$file_perms = substr(sprintf('%o', fileperms($file)), -4);
	$file_owner = 'unknown';
	$file_group = 'unknown';
	if (function_exists('posix_getpwuid')) {
		$file_owner = posix_getpwuid(fileowner($file));
		$file_owner = $file_owner['name'];
	}
	if (function_exists('posix_getgrgid')) {
		$file_group = posix_getgrgid(filegroup($file));
		$file_group = $file_group['name'];
	}
	$ewww_debug .= "permissions: $file_perms, owner: $file_owner, group: $file_group <br>";
	$type = ewww_image_optimizer_mimetype($file, 'i');
	if (!$type) {
		//otherwise we store an error message since we couldn't get the mime-type
		$msg = __('Missing finfo_file(), getimagesize() and mime_content_type() PHP functions', EWWW_IMAGE_OPTIMIZER_DOMAIN);
		$ewww_debug .= "couldn't find any functions for mimetype detection<br>";
		return array(false, $msg, $converted, $original);
	}
	if (!EWWW_IMAGE_OPTIMIZER_CLOUD) {
		// check to see if 'nice' exists
		$nice = ewww_image_optimizer_find_binary('nice', 'n');
	}
	// if the user has disabled the utility checks
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_skip_check') == TRUE || EWWW_IMAGE_OPTIMIZER_CLOUD) {
		$skip_jpegtran_check = true;
		$skip_optipng_check = true;
		$skip_gifsicle_check = true;
		$skip_pngout_check = true;
	} else {
		// otherwise we set the variables to false
		$skip_jpegtran_check = false;
		$skip_optipng_check = false;
		$skip_gifsicle_check = false;
		$skip_pngout_check = false;
	}
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')) {
		$skip_jpegtran_check = true;
	}	
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png')) {
		$skip_optipng_check = true;
		$skip_pngout_check = true;
	}	
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif')) {
		$skip_gifsicle_check = true;
	}	
	// if the full-size image was converted
	if ($converted) {
		$ewww_debug .= "full-size image was converted, need to rebuild filename for meta<br>";
		$filenum = $converted;
		// grab the file extension
		preg_match('/\.\w+$/', $file, $fileext);
		// strip the file extension
		$filename = str_replace($fileext[0], '', $file);
		// grab the dimensions
		preg_match('/-\d+x\d+(-\d+)*$/', $filename, $fileresize);
		// strip the dimensions
		$filename = str_replace($fileresize[0], '', $filename);
		// reconstruct the filename with the same increment (stored in $converted) as the full version
		$refile = $filename . '-' . $filenum . $fileresize[0] . $fileext[0];
		// rename the file
		rename($file, $refile);
		$ewww_debug .= "moved $file to $refile<br>";
		// and set $file to the new filename
		$file = $refile;
		$original = $file;
	}
	// get the original image size
	$orig_size = filesize($file);
	$ewww_debug .= "original filesize: $orig_size<br>";
	if ( $orig_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
		// tell the user optimization was skipped
		$msg = __( "Optimization skipped", EWWW_IMAGE_OPTIMIZER_DOMAIN );
		$ewww_debug .= "optimization bypassed due to filesize: $file <br>";
		// send back the above message
		return array(false, $msg, $converted, $file);
	}
	// initialize $new_size with the original size, HOW ABOUT A ZERO...
	//$new_size = $orig_size;
	$new_size = 0;
	// set the optimization process to OFF
	$optimize = false;
	// toggle the convert process to ON
	$convert = true;
	// run the appropriate optimization/conversion for the mime-type
	switch($type) {
		case 'image/jpeg':
			$png_size = 0;
			// if jpg2png conversion is enabled, and this image is in the wordpress media library
			if ((ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_to_png') && $gallery_type == 1) || !empty($_GET['convert'])) {
				// generate the filename for a PNG
				// if this is a resize version
				if ($converted) {
					// just change the file extension
					$pngfile = preg_replace('/\.\w+$/', '.png', $file);
				// if this is a full size image
				} else {
					// get a unique filename for the png image
					list($pngfile, $filenum) = ewww_image_optimizer_unique_filename($file, '.png');
				}
			} else {
				// otherwise, set it to OFF
				$convert = false;
				$pngfile = '';
			}
			// check for previous optimization, so long as the force flag is on and this isn't a new image that needs converting
			if ( empty( $_REQUEST['force'] ) && ! ( $new && $convert ) ) {
				if ( $results_msg = ewww_image_optimizer_check_table( $file, $orig_size ) ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')) {
				list($file, $converted, $result, $new_size) = ewww_image_optimizer_cloud_optimizer($file, $type, $convert, $pngfile, 'image/png', $fullsize);
				if ($converted) $converted = $filenum;
				break;
			}
			if ($convert) {
				$tools = ewww_image_optimizer_path_check(true, true, false, true);
			} else {
				$tools = ewww_image_optimizer_path_check(true, false, false, false);
			}
			// if jpegtran optimization is disabled
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_jpegtran')) {
				// store an appropriate message in $result
				$result = sprintf(__('%s is disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'jpegtran');
			// otherwise, if we aren't skipping the utility verification and jpegtran doesn't exist
			} elseif (!$skip_jpegtran_check && !$tools['JPEGTRAN']) {
				// store an appropriate message in $result
				$result = sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>jpegtran</em>');
			// otherwise, things should be good, so...
			} else {
				// set the optimization process to ON
				$optimize = true;
			}
			// if optimization is turned ON
			if ($optimize && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')) {
				$ewww_debug .= "attempting to optimize JPG...<br>";
				// generate temporary file-names:
				$tempfile = $file . ".tmp"; //non-progressive jpeg
				$progfile = $file . ".prog"; // progressive jpeg
				// check to see if we are supposed to strip metadata (badly named)
				if(ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
					// don't copy metadata
					$copy_opt = 'none';
				} else {
					// copy all the metadata
					$copy_opt = 'all';
				}
				// run jpegtran - non-progressive
				exec( "$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -outfile " . ewww_image_optimizer_escapeshellarg( $tempfile ) . " " . ewww_image_optimizer_escapeshellarg( $file ) );
				// run jpegtran - progressive
				exec( "$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -progressive -outfile " . ewww_image_optimizer_escapeshellarg( $progfile ) . " " . ewww_image_optimizer_escapeshellarg( $file ) );
				if (is_file($tempfile)) {
					// check the filesize of the non-progressive JPG
					$non_size = filesize($tempfile);
				} else {
					$non_size = 0;
				}
				if (is_file($progfile)) {
					// check the filesize of the progressive JPG
					$prog_size = filesize($progfile);
				} else {
					$prog_size = 0;
				}
				$ewww_debug .= "optimized JPG (non-progresive) size: $non_size<br>";
				$ewww_debug .= "optimized JPG (progresive) size: $prog_size<br>";
				if ($non_size === false || $prog_size === false) {
					$result = __('Unable to write file', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$new_size = 0;
				} elseif (!$non_size || !$prog_size) {
					$result = __('Optimization failed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$new_size = 0;
				} else {
					// if the progressive file is bigger
					if ($prog_size > $non_size) {
						// store the size of the non-progessive JPG
						$new_size = $non_size;
						if (is_file($progfile)) {
							// delete the progressive file
							unlink($progfile);
						}
					// if the progressive file is smaller or the same
					} else {
						// store the size of the progressive JPG
						$new_size = $prog_size;
						// replace the non-progressive with the progressive file
						rename($progfile, $tempfile);
					}
				}
				$ewww_debug .= "optimized JPG size: $new_size<br>";
				// if the best-optimized is smaller than the original JPG, and we didn't create an empty JPG
				if ($orig_size > $new_size && $new_size != 0) {
					// replace the original with the optimized file
					rename($tempfile, $file);
					// store the results of the optimization
					$result = "$orig_size vs. $new_size";
				// if the optimization didn't produce a smaller JPG
				} else {
					if (is_file($tempfile)) {
						// delete the optimized file
						unlink($tempfile);
					}
					// store the results
					$result = 'unchanged';
					$new_size = $orig_size;
				}
			// if conversion and optimization are both turned OFF, finish the JPG processing
			} elseif (!$convert) {
				break;
			}
			// if the conversion process is turned ON, or if this is a resize and the full-size was converted
			if ($convert && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')) {
				$ewww_debug .= "attempting to convert JPG to PNG: $pngfile <br>";
				if (empty($new_size)) {
					$new_size = $orig_size;
				}
				// retrieve version info for ImageMagick
				$convert_path = ewww_image_optimizer_find_binary('convert', 'i');
				// convert the JPG to PNG
				if (!empty($convert_path)) {
					$ewww_debug .= "converting with ImageMagick<br>";
					exec( $convert_path . " " . ewww_image_optimizer_escapeshellarg( $file ) . " -strip " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
				} elseif (ewww_image_optimizer_gd_support()) {
					$ewww_debug .= "converting with GD<br>";
					imagepng(imagecreatefromjpeg($file), $pngfile);
				}
				// if lossy optimization is ON and full-size exclusion is not active
				if (ewww_image_optimizer_get_option('ewww_image_optimizer_png_lossy') && $tools['PNGQUANT'] && !$fullsize) {
					$ewww_debug .= "attempting lossy reduction<br>";
					exec( "$nice " . $tools['PNGQUANT'] . " " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					$quantfile = preg_replace('/\.\w+$/', '-fs8.png', $pngfile);
					if ( file_exists( $quantfile ) && filesize( $pngfile ) > filesize( $quantfile ) ) {
						$ewww_debug .= "lossy reduction is better: original - " . filesize( $pngfile ) . " vs. lossy - " . filesize( $quantfile ) . "<br>";
						rename( $quantfile, $pngfile );
					} elseif ( file_exists( $quantfile ) ) {
						$ewww_debug .= "lossy reduction is worse: original - " . filesize( $pngfile ) . " vs. lossy - " . filesize( $quantfile ) . "<br>";
						unlink( $quantfile );
					} else {
						$ewww_debug .= "pngquant did not produce any output<br>";
					}
				}
				// if optipng isn't disabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optipng optimization level
					$optipng_level = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level');
					if (ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') && preg_match('/0.7/', ewww_image_optimizer_tool_found($tools['OPTIPNG'], 'o'))) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// if the PNG file was created
					if (file_exists($pngfile)) {
						$ewww_debug .= "optimizing converted PNG with optipng<br>";
						// run optipng on the new PNG
						exec( "$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					}
				}
				// if pngout isn't disabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the pngout optimization level
					$pngout_level = ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						$ewww_debug .= "optimizing converted PNG with pngout<br>";
						// run pngout on the new PNG
						exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					}
				}
				if (is_file($pngfile)) {
					// find out the size of the new PNG file
					$png_size = filesize($pngfile);
				} else {
					$png_size = 0;
				}
				$ewww_debug .= "converted PNG size: $png_size<br>";
				// if the PNG is smaller than the original JPG, and we didn't end up with an empty file
				if ($new_size > $png_size && $png_size != 0) {
					$ewww_debug .= "converted PNG is better: $png_size vs. $new_size<br>";
					// store the size of the converted PNG
					$new_size = $png_size;
					// check to see if the user wants the originals deleted
					if (ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE) {
						// delete the original JPG
						unlink($file);
					}
					// store the location of the PNG file
					$file = $pngfile;
					// successful conversion and we store the increment
					$converted = $filenum;
				} else {
					$ewww_debug .= "converted PNG is no good<br>";
					// otherwise delete the PNG
					$converted = FALSE;
					if (is_file($pngfile)) {
						unlink ($pngfile);
					}
				}
			}
			break;
		case 'image/png':
			// png2jpg conversion is turned on, and the image is in the wordpress media library
			if ( ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) || ! empty( $_GET['convert'] ) ) && $gallery_type == 1 && ! $fullsize ) {
				$ewww_debug .= "PNG to JPG conversion turned on<br>";
				// if the user set a fill background for transparency
				$background = '';
				if ($background = ewww_image_optimizer_jpg_background()) {
					// set background color for GD
					$r = hexdec('0x' . strtoupper(substr($background, 0, 2)));
                                        $g = hexdec('0x' . strtoupper(substr($background, 2, 2)));
					$b = hexdec('0x' . strtoupper(substr($background, 4, 2)));
					// set the background flag for 'convert'
					$background = "-background " . '"' . "#$background" . '"';
				} else {
					$r = '';
					$g = '';
					$b = '';
				}
				// if the user manually set the JPG quality
				if ($quality = ewww_image_optimizer_jpg_quality()) {
					// set the quality for GD
					$gquality = $quality;
					// set the quality flag for 'convert'
					$cquality = "-quality $quality";
				} else {
					$cquality = '';
					$gquality = '92';
				}
				// if this is a resize version
				if ($converted) {
					// just replace the file extension with a .jpg
					$jpgfile = preg_replace('/\.\w+$/', '.jpg', $file);
				// if this is a full version
				} else {
					// construct the filename for the new JPG
					list($jpgfile, $filenum) = ewww_image_optimizer_unique_filename($file, '.jpg');
				}
			} else {
				$ewww_debug .= "PNG to JPG conversion turned off<br>";
				// turn the conversion process OFF
				$convert = false;
				$jpgfile = '';
				$r = null;
				$g = null;
				$b = null;
				$gquality = null;
			}
			// check for previous optimization, so long as the force flag is on and this isn't a new image that needs converting
			if ( empty( $_REQUEST['force'] ) && ! ( $new && $convert ) ) {
				if ( $results_msg = ewww_image_optimizer_check_table( $file, $orig_size ) ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png')) {
				list($file, $converted, $result, $new_size) = ewww_image_optimizer_cloud_optimizer($file, $type, $convert, $jpgfile, 'image/jpeg', $fullsize, array('r' => $r, 'g' => $g, 'b' => $b, 'quality' => $gquality));
				if ($converted) $converted = $filenum;
				break;
			}
			if ($convert) {
				$tools = ewww_image_optimizer_path_check(true, true, false, true);
			} else {
				$tools = ewww_image_optimizer_path_check(false, true, false, true);
			}
			// if pngout and optipng are disabled
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') && ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
				// tell the user all PNG tools are disabled
				$result = __('png tools are disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			// if the utility checking is on, optipng is enabled, but optipng cannot be found
			} elseif (!$skip_optipng_check && !$tools['OPTIPNG'] && !ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng')) {
				// tell the user optipng is missing
				$result = sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>optipng</em>');
			// if the utility checking is on, pngout is enabled, but pngout cannot be found
			} elseif (!$skip_pngout_check && !$tools['PNGOUT'] && !ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
				// tell the user pngout is missing
				$result = sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>pngout</em>');
			} else {
				// turn optimization on if we made it through all the checks
				$optimize = true;
			}
			// if optimization is turned on
			if ($optimize) {
				// if lossy optimization is ON and full-size exclusion is not active
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && $tools['PNGQUANT'] && !$fullsize) {
					$ewww_debug .= "attempting lossy reduction<br>";
					exec( "$nice " . $tools['PNGQUANT'] . " " . ewww_image_optimizer_escapeshellarg( $file ) );
					$quantfile = preg_replace( '/\.\w+$/', '-fs8.png', $file );
					if ( file_exists( $quantfile ) && filesize( $file ) > filesize( $quantfile ) ) {
						$ewww_debug .= "lossy reduction is better: original - " . filesize( $file ) . " vs. lossy - " . filesize( $quantfile ) . "<br>";
						rename($quantfile, $file);
					} elseif ( file_exists( $quantfile ) ) {
						$ewww_debug .= "lossy reduction is worse: original - " . filesize( $file ) . " vs. lossy - " . filesize( $quantfile ) . "<br>";
						unlink($quantfile);
					} else {
						$ewww_debug .= "pngquant did not produce any output<br>";
					}
				}
				// if optipng is enabled
				if(!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optimization level for optipng
					$optipng_level = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level');
					if (ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') && preg_match('/0.7/', ewww_image_optimizer_tool_found($tools['OPTIPNG'], 'o'))) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// run optipng on the PNG file
					exec( "$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $file ) );
				}
				// if pngout is enabled
				if(!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the optimization level for pngout
					$pngout_level = ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the PNG file
					exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $file ) );
				}
			// if conversion and optimization are both disabled we are done here
			} elseif (!$convert) {
				$ewww_debug .= "not going to process as we can neither convert or optimize<br>";
				break;
			}
			// flush the cache for filesize
			clearstatcache();
			// retrieve the new filesize of the PNG
			$new_size = filesize($file);
			// if conversion is on and the PNG doesn't have transparency or the user set a background color to replace transparency
			if ($convert && (!ewww_image_optimizer_png_alpha($file) || ewww_image_optimizer_jpg_background())) {
				$ewww_debug .= "attempting to convert PNG to JPG: $jpgfile <br>";
				if (empty($new_size)) {
					$new_size = $orig_size;
				}
				// retrieve version info for ImageMagick
				$convert_path = ewww_image_optimizer_find_binary('convert', 'i');
				// convert the PNG to a JPG with all the proper options
				if (!empty($convert_path)) {
					$ewww_debug .= "converting with ImageMagick<br>";
					$ewww_debug .= "using command: $convert_path $background -flatten $cquality $file $jpgfile";
					exec ( "$convert_path $background -flatten $cquality " . ewww_image_optimizer_escapeshellarg( $file ) . " " . ewww_image_optimizer_escapeshellarg( $jpgfile ) );
				} elseif (ewww_image_optimizer_gd_support()) {
					$ewww_debug .= "converting with GD<br>";
					// retrieve the data from the PNG
					$input = imagecreatefrompng($file);
					// retrieve the dimensions of the PNG
					list($width, $height) = getimagesize($file);
					// create a new image with those dimensions
					$output = imagecreatetruecolor($width, $height);
					if ($r === '') {
						$r = 255;
						$g = 255;
						$b = 255;
					}
					// allocate the background color
					$rgb = imagecolorallocate($output, $r, $g, $b);
					// fill the new image with the background color 
					imagefilledrectangle($output, 0, 0, $width, $height, $rgb);
					// copy the original image to the new image
					imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
					// output the JPG with the quality setting
					imagejpeg($output, $jpgfile, $gquality);
				}
				if (is_file($jpgfile)) {
					// retrieve the filesize of the new JPG
					$jpg_size = filesize($jpgfile);
					$ewww_debug .= "converted JPG filesize: $jpg_size<br>";
				} else {
					$jpg_size = 0;
					$ewww_debug .= "unable to convert to JPG<br>";
				}
				// next we need to optimize that JPG if jpegtran is enabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_jpegtran') && file_exists($jpgfile)) {
					// generate temporary file-names:
					$tempfile = $jpgfile . ".tmp"; //non-progressive jpeg
					$progfile = $jpgfile . ".prog"; // progressive jpeg
					// check to see if we are supposed to strip metadata (badly named)
					if(ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
						// don't copy metadata
						$copy_opt = 'none';
					} else {
						// copy all the metadata
						$copy_opt = 'all';
					}
					// run jpegtran - non-progressive
					exec( "$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -outfile " . ewww_image_optimizer_escapeshellarg( $tempfile ) . " " . ewww_image_optimizer_escapeshellarg( $jpgfile ) );
					// run jpegtran - progressive
					exec( "$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -progressive -outfile " . ewww_image_optimizer_escapeshellarg( $progfile ) . " " . ewww_image_optimizer_escapeshellarg( $jpgfile ) );
					if (is_file($tempfile)) {
						// check the filesize of the non-progressive JPG
						$non_size = filesize($tempfile);
						$ewww_debug .= "non-progressive JPG filesize: $non_size<br>";
					} else {
						$non_size = 0;
					}
					if (is_file($progfile)) {
						// check the filesize of the progressive JPG
						$prog_size = filesize($progfile);
						$ewww_debug .= "progressive JPG filesize: $prog_size<br>";
					} else {
						$prog_size = 0;
					}
					// if the progressive file is bigger
					if ($prog_size > $non_size) {
						// store the size of the non-progessive JPG
						$opt_jpg_size = $non_size;
						if (is_file($progfile)) {
							// delete the progressive file
							unlink($progfile);
						}
						$ewww_debug .= "keeping non-progressive JPG<br>";
					// if the progressive file is smaller or the same
					} else {
						// store the size of the progressive JPG
						$opt_jpg_size = $prog_size;
						// replace the non-progressive with the progressive file
						rename($progfile, $tempfile);
						$ewww_debug .= "keeping progressive JPG<br>";
					}
					// if the best-optimized is smaller than the original JPG, and we didn't create an empty JPG
					if ($jpg_size > $opt_jpg_size && $opt_jpg_size != 0) {
						// replace the original with the optimized file
						rename($tempfile, $jpgfile);
						// store the size of the optimized JPG
						$jpg_size = $opt_jpg_size;
						$ewww_debug .= "optimized JPG was smaller than un-optimized version<br>";
					// if the optimization didn't produce a smaller JPG
					} elseif (is_file($tempfile)) {
						// delete the optimized file
						unlink($tempfile);
					}
				} 
				$ewww_debug .= "converted JPG size: $jpg_size<br>";
				// if the new JPG is smaller than the original PNG
				if ($new_size > $jpg_size && $jpg_size != 0) {
					// store the size of the JPG as the new filesize
					$new_size = $jpg_size;
					// if the user wants originals delted after a conversion
					if (ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE) {
						// delete the original PNG
						unlink($file);
					}
					// update the $file location to the new JPG
					$file = $jpgfile;
					// successful conversion, so we store the increment
					$converted = $filenum;
				} else {
					$converted = FALSE;
					if (is_file($jpgfile)) {
						// otherwise delete the new JPG
						unlink ($jpgfile);
					}
				}
			}
			break;
		case 'image/gif':
			// if gif2png is turned on, and the image is in the wordpress media library
			if ((ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png') && $gallery_type == 1) || !empty($_GET['convert'])) {
				// generate the filename for a PNG
				// if this is a resize version
				if ($converted) {
					// just change the file extension
					$pngfile = preg_replace('/\.\w+$/', '.png', $file);
				// if this is the full version
				} else {
					// construct the filename for the new PNG
					list($pngfile, $filenum) = ewww_image_optimizer_unique_filename($file, '.png');
				}
			} else {
				// turn conversion OFF
				$convert = false;
				$pngfile = '';
			}
			// check for previous optimization, so long as the force flag is on and this isn't a new image that needs converting
			if ( empty( $_REQUEST['force'] ) && ! ( $new && $convert ) ) {
				if ( $results_msg = ewww_image_optimizer_check_table( $file, $orig_size ) ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif')) {
				list($file, $converted, $result, $new_size) = ewww_image_optimizer_cloud_optimizer($file, $type, $convert, $pngfile, 'image/png', $fullsize);
				if ($converted) $converted = $filenum;
				break;
			}
			if ($convert) {
				$tools = ewww_image_optimizer_path_check(false, true, true, true);
			} else {
				$tools = ewww_image_optimizer_path_check(false, false, true, false);
			}
			// if gifsicle is disabled
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_gifsicle')) {
				// return an appropriate message
				$result = sprintf(__('%s is disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'gifsicle');
			// if utility checking is on, and gifsicle is not installed
			} elseif (!$skip_gifsicle_check && !$tools['GIFSICLE']) {
				// return an appropriate message
				$result = sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>gifsicle</em>');
			} else {
				// otherwise, turn optimization ON
				$optimize = true;
			}
			// if optimization is turned ON
			if ($optimize) {
				$tempfile = $file . ".tmp"; //temporary GIF output
				// run gifsicle on the GIF
				exec( "$nice " . $tools['GIFSICLE'] . " -b -O3 --careful -o $tempfile" . ewww_image_optimizer_escapeshellarg( $file ) );
				if (file_exists($tempfile)) {
					// retrieve the filesize of the temporary GIF
					$new_size = filesize($tempfile);
					// if the new GIF is smaller
					if ($orig_size > $new_size && $new_size != 0) {
						// replace the original with the optimized file
						rename($tempfile, $file);
						// store the results of the optimization
						$result = "$orig_size vs. $new_size";
					// if the optimization didn't produce a smaller GIF
					} else {
						if (is_file($tempfile)) {
							// delete the optimized file
							unlink($tempfile);
						}
						// store the results
						$result = 'unchanged';
						$new_size = $orig_size;
					}
				}
			// if conversion and optimization are both turned OFF, we are done here
			} elseif (!$convert) {
				break;
			}
			// flush the cache for filesize
			clearstatcache();
			// get the new filesize for the GIF
			$new_size = filesize($file);
			// if conversion is ON and the GIF isn't animated
			if ($convert && !ewww_image_optimizer_is_animated($file)) {
				if (empty($new_size)) {
					$new_size = $orig_size;
				}
				// if optipng is enabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') && $tools['OPTIPNG']) {
					// retrieve the optipng optimization level
					$optipng_level = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level');
					if (ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') && preg_match('/0.7/', ewww_image_optimizer_tool_found($tools['OPTIPNG'], 'o'))) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// run optipng on the GIF file
					exec( "$nice " . $tools['OPTIPNG'] . " -out " . ewww_image_optimizer_escapeshellarg( $pngfile ) . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $file ) );
				}
				// if pngout is enabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') && $tools['PNGOUT']) {
					// retrieve the pngout optimization level
					$pngout_level = ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level');
					// if $pngfile exists (which means optipng was run already)
					if (file_exists($pngfile)) {
						// run pngout on the PNG file
						exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					} else {
						// run pngout on the GIF file
						exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $file ) . " " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					}
				}
				// if a PNG file was created
				if (file_exists($pngfile)) {
					// retrieve the filesize of the PNG
					$png_size = filesize($pngfile);
					// if the new PNG is smaller than the original GIF
					if ($new_size > $png_size && $png_size != 0) {
						// store the PNG size as the new filesize
						$new_size = $png_size;
						// if the user wants original GIFs deleted after successful conversion
						if (ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE) {
							// delete the original GIF
							unlink($file);
						}
						// update the $file location with the new PNG
						$file = $pngfile;
						// successful conversion (for now), so we store the increment
						$converted = $filenum;
					} else {
						$converted = FALSE;
						if (is_file($pngfile)) {
							unlink ($pngfile);
						}
					}
				}
			}
			break;
		default:
			// if not a JPG, PNG, or GIF, tell the user we don't work with strangers
			return array($file, __('Unknown type: ' . $type, EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted, $original);
	}
	// if their cloud api license limit has been exceeded
	if ($result == 'exceeded') {
		return array($file, __('License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted, $original);
	}
	if (!empty($new_size)) {
		$results_msg = ewww_image_optimizer_update_table ($file, $new_size, $orig_size, $new);
		return array($file, $results_msg, $converted, $original);
	}
	// otherwise, send back the filename, the results (some sort of error message), the $converted flag, and the name of the original image
	return array($file, $result, $converted, $original);
}

// retrieves the pngout linux package with wget, unpacks it with tar, 
// copies the appropriate version to the plugin folder, and sends the user back where they came from
function ewww_image_optimizer_install_pngout() {
	if (FALSE === current_user_can('install_plugins')) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	if (PHP_OS != 'WINNT') {
		$tar = ewww_image_optimizer_find_binary('tar', 't');
	}
	if (empty($tar) && PHP_OS != 'WINNT') $pngout_error = __('tar command not found', EWWW_IMAGE_OPTIMIZER_DOMAIN);
	if (PHP_OS == 'Linux') {
		$os_string = 'linux';
	}
	if (PHP_OS == 'FreeBSD') {
		$os_string = 'bsd';
	}
	$latest = '20130221';
	if (empty($pngout_error)) {
		if (PHP_OS == 'Linux' || PHP_OS == 'FreeBSD') {
			$download_result = ewww_image_optimizer_escapeshellarg ( download_url ( 'http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-' . $os_string . '-static.tar.gz' ) );
			if (is_wp_error($download_result)) {
				$pngout_error = $download_result->get_error_message();
			} else {
				$arch_type = php_uname('m');
				exec("$tar xzf $download_result -C " . ewww_image_optimizer_escapeshellarg ( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH ) . ' pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static');
				if (!rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static'))
					if (empty($pngout_error)) $pngout_error = __("could not move pngout", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				if (!chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755))
					if (empty($pngout_error)) $pngout_error = __("could not set permissions", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				$pngout_version = ewww_image_optimizer_tool_found ( ewww_image_optimizer_escapeshellarg ( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) . 'pngout-static', 'p' );
			}
		}
		if (PHP_OS == 'Darwin') {
			$download_result = ewww_image_optimizer_escapeshellarg ( download_url ( 'http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-darwin.tar.gz' ) );
			if (is_wp_error($download_result)) {
				$pngout_error = $download_result->get_error_message();
			} else {
				exec("$tar xzf $download_result -C " . ewww_image_optimizer_escapeshellarg ( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH ) . ' pngout-' . $latest . '-darwin/pngout');
				if (!rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin/pngout', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static'))
					if (empty($pngout_error)) $pngout_error = __("could not move pngout", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				if (!chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755))
					if (empty($pngout_error)) $pngout_error = __("could not set permissions", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				$pngout_version = ewww_image_optimizer_tool_found( ewww_image_optimizer_escapeshellarg ( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) . 'pngout-static', 'p' );
			}
		}
	}
	if (PHP_OS == 'WINNT') {
		$download_result = download_url('http://advsys.net/ken/util/pngout.exe');
		if (is_wp_error($download_result)) {
			$pngout_error = $download_result->get_error_message();
		} else {
			if (!rename($download_result, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe'))
				if (empty($pngout_error)) $pngout_error = __("could not move pngout", EWWW_IMAGE_OPTIMIZER_DOMAIN);
			$pngout_version = ewww_image_optimizer_tool_found ( '"' . EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe"', 'p' );
		}
	}
	if (!empty($pngout_version)) {
		$sendback = add_query_arg('pngout', 'success', remove_query_arg(array('pngout', 'error'), wp_get_referer()));
	}
	if (!isset($sendback)) {
		$sendback = add_query_arg(array('pngout' => 'failed', 'error' => urlencode($pngout_error)), remove_query_arg(array('pngout', 'error'), wp_get_referer()));
	}
	wp_redirect($sendback);
	exit(0);
}

// displays the EWWW IO options and provides one-click install for the optimizer utilities
function ewww_image_optimizer_options () {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_options()</b><br>";
	if (isset($_REQUEST['pngout'])) {
		if ($_REQUEST['pngout'] == 'success') { ?>
			<div id='ewww-image-optimizer-pngout-success' class='updated fade'>
				<p><?php _e('Pngout was successfully installed, check the Plugin Status area for version information.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			</div>
<?php		}
		if ($_REQUEST['pngout'] == 'failed') { ?>
			<div id='ewww-image-optimizer-pngout-failure' class='error'>
				<p><?php printf(__('Pngout was not installed: %1$s. Make sure this folder is writable: %2$s', EWWW_IMAGE_OPTIMIZER_DOMAIN), $_REQUEST['error'], EWWW_IMAGE_OPTIMIZER_TOOL_PATH); ?></p>
			</div>
<?php		}
	} ?>
	<script type='text/javascript'>
		jQuery(document).ready(function($) {$('.fade').fadeTo(5000,1).fadeOut(3000);});
	</script>
	<div class="wrap" style="clear: both"><div id="ewww-container-left" style="float: left; margin-right: 200px;">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>EWWW <?php _e('Image Optimizer Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h2>
		<p><a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/"><?php _e('Plugin Home Page', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a> |
		<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/"><?php _e('Installation Instructions', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a> | 
		<a href="http://wordpress.org/support/plugin/ewww-image-optimizer"><?php _e('Plugin Support', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a> | 
		<a href="http://stats.pingdom.com/w89y81bhecp4"><?php _e('Cloud Status', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></p>
<?php		if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) {
			$bulk_link = __('Media Library') . ' -> ' . __('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN);
		} else {
			$bulk_link = '<a href="upload.php?page=ewww-image-optimizer-bulk">' . __('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a>';
		} ?>
		<p><?php printf(__('New images uploaded to the Media Library will be optimized automatically. If you have existing images you would like to optimize, you can use the %s tool.', EWWW_IMAGE_OPTIMIZER_DOMAIN), $bulk_link); ?></p>
		<div id="status" style="border: 1px solid #ccc; padding: 0 8px; border-radius: 12px;">
			<h3>Plugin Status</h3>
	<?php 
			echo "<b>" . __('Total Savings:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b> <span id='total_savings'>" . __('Calculating...', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span><br>";
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key')) {
				echo '<p><b>Cloud API Key:</b> ';
				$verify_cloud = ewww_image_optimizer_cloud_verify(false); 
				if (preg_match('/great/', $verify_cloud)) {
					echo '<span style="color: green">' . __('Verified,', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' </span>';
					echo ewww_image_optimizer_cloud_quota();
				} elseif (preg_match('/exceeded/', $verify_cloud)) { 
					echo '<span style="color: orange">' . __('Verified,', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' </span>'; 
					echo ewww_image_optimizer_cloud_quota();
				} else { 
					echo '<span style="color: red">' . __('Not Verified', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>'; 
				}
				echo '</p>';
			}
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle') && !EWWW_IMAGE_OPTIMIZER_CLOUD && !EWWW_IMAGE_OPTIMIZER_NOEXEC) { ?>
				<p><?php _e('If updated versions are available below you may either download the newer versions and install them yourself, or uncheck "Use System Paths" and use the bundled tools.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?><br />
				<i>*<?php _e('Updates are optional, but may contain increased optimization or security patches', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></i></p>
			<?php } elseif (!EWWW_IMAGE_OPTIMIZER_CLOUD && !EWWW_IMAGE_OPTIMIZER_NOEXEC) { ?>
				<p><?php printf(__('If updated versions are available below, you may need to enable write permission on the %s folder to use the automatic installs.', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<i>' . EWWW_IMAGE_OPTIMIZER_TOOL_PATH . '</i>'); ?><br />
				<i>*<?php _e('Updates are optional, but may contain increased optimization or security patches', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></i></p>
			<?php }
			if (!EWWW_IMAGE_OPTIMIZER_CLOUD && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				list ($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst) = ewww_image_optimizer_install_paths();
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_jpegtran') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')  && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>jpegtran: </b>';
				$jpegtran_installed = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'j');
				if (!empty($jpegtran_installed)) {
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $jpegtran_installed . '<br />'; 
				} else { 
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>optipng:</b> '; 
				$optipng_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'o');
				if (!empty($optipng_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $optipng_version . '<br />'; 
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_gifsicle') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>gifsicle:</b> ';
				$gifsicle_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'g');
				if (!empty($gifsicle_version) && preg_match('/LCDF Gifsicle/', $gifsicle_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $gifsicle_version . '<br />'; 
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>pngout:</b> '; 
				$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_PNGOUT, 'p');
				if (!empty($pngout_version) && (preg_match('/PNGOUT/', $pngout_version))) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . preg_replace('/PNGOUT \[.*\)\s*?/', '', $pngout_version) . '<br />'; 
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>' . __('Install', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' <a href="admin.php?action=ewww_image_optimizer_install_pngout">' . __('automatically', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a> | <a href="http://advsys.net/ken/utils.htm">' . __('manually', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a></b> - ' . __('Pngout is free closed-source software that can produce drastically reduced filesizes for PNGs, but can be very time consuming to process images', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '<br />'; 
				}
			}
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_png_lossy') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>pngquant:</b> '; 
				$pngquant_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_PNGQUANT, 'q');
				if (!empty($pngquant_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $pngquant_version . '<br />'; 
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			echo "\n";
			if (!EWWW_IMAGE_OPTIMIZER_CLOUD && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				printf(__("%s only need one, used for conversion, not optimization: ", EWWW_IMAGE_OPTIMIZER_DOMAIN), '<b>' . __('Graphics libraries', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</b> - ');
				if (ewww_image_optimizer_gd_support()) {
					echo 'GD: <span style="color: green; font-weight: bolder">OK';
				} else {
					echo 'GD: <span style="color: red; font-weight: bolder">MISSING';
				} ?></span>&emsp;&emsp;
				Imagemagick 'convert': <?php
				if (ewww_image_optimizer_find_binary('convert', 'i')) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>'; 
				} else { 
					echo '<span style="color: red; font-weight: bolder">MISSING</span>'; 
				}
				echo "<br />\n";
				if (ewww_image_optimizer_safemode_check()) {
					echo 'safe mode: <span style="color: red; font-weight: bolder">On</span>&emsp;&emsp;';
				} else {
					echo 'safe mode: <span style="color: green; font-weight: bolder">Off</span>&emsp;&emsp;';
				}
				if (ewww_image_optimizer_exec_check()) {
					echo 'exec(): <span style="color: red; font-weight: bolder">DISABLED</span>&emsp;&emsp;';
				} else {
					echo 'exec(): <span style="color: green; font-weight: bolder">OK</span>&emsp;&emsp;';
				}
				echo "<br />\n";
			}
			echo '<b>' . __('Only need one of these:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' </b>';
			// initialize this variable to check for the 'file' command if we don't have any php libraries we can use
			$file_command_check = true;
			if (function_exists('finfo_file')) {
				echo 'finfo: <span style="color: green; font-weight: bolder">OK</span>&emsp;&emsp;';
				$file_command_check = false;
			} else {
				echo 'finfo: <span style="color: red; font-weight: bolder">MISSING</span>&emsp;&emsp;';
			}
			if (function_exists('getimagesize')) {
				echo 'getimagesize(): <span style="color: green; font-weight: bolder">OK</span>&emsp;&emsp;';
			} else {
				echo 'getimagesize(): <span style="color: red; font-weight: bolder">MISSING</span>&emsp;&emsp;';
			}
			if (function_exists('mime_content_type')) {
				echo 'mime_content_type(): <span style="color: green; font-weight: bolder">OK</span><br>';
				$file_command_check = false;
			} else {
				echo 'mime_content_type(): <span style="color: red; font-weight: bolder">MISSING</span><br>';
			}
			if (PHP_OS != 'WINNT' && !EWWW_IMAGE_OPTIMIZER_CLOUD && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				if ($file_command_check && !ewww_image_optimizer_find_binary('file', 'f')) {
					echo '<span style="color: red; font-weight: bolder">file ' . __('command not found on your system', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br>';
				}
				if (!ewww_image_optimizer_find_binary('nice', 'n')) {
					echo '<span style="color: orange; font-weight: bolder">nice ' . __('command not found on your system', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' (' . __('not required', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ')</span><br>';
				}
				if (PHP_OS != 'SunOS' && !ewww_image_optimizer_find_binary('tar', 't')) {
					echo '<span style="color: red; font-weight: bolder">tar ' . __('command not found on your system', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' (' . __('required for automatic pngout installer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ')</span><br>';
				}
			}
			?></p>
		</div>
<?php		if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) { ?>
		<form method="post" action="">
<?php		} else { ?>
		<form method="post" action="options.php">
			<?php settings_fields('ewww_image_optimizer_options'); 
		} ?>
			<div id="ewww-accordion">
			<h3><?php _e('Cloud Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<div>
			<p><?php _e('If exec() is disabled for security reasons (and enabling it is not an option), or you would like to offload image optimization to a third-party server, you may purchase an API key for our cloud optimization service. The API key should be entered below, and cloud optimization must be enabled for each image format individually.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> <a href="http://www.exactlywww.com/cloud/"><?php _e('Purchase an API key.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_cloud_key"><?php _e('Cloud optimization API Key', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="text" id="ewww_image_optimizer_cloud_key" name="ewww_image_optimizer_cloud_key" value="<?php echo ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key'); ?>" size="32" /> <?php _e('API Key will be validated when you save your settings.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> <a href="http://www.exactlywww.com/cloud/"><?php _e('Purchase a key.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></td></tr>
<?php			if (!empty( $verify_cloud) && preg_match('/great|exceeded/', $verify_cloud)) { ?>
				<tr><th><label for="ewww_image_optimizer_cloud_jpg">JPG <?php _e('cloud optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_cloud_jpg" name="ewww_image_optimizer_cloud_jpg" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_cloud_png">PNG <?php _e('cloud optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_cloud_png" name="ewww_image_optimizer_cloud_png" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') == TRUE) { ?>checked="true"<?php } ?> />&emsp;&emsp;
					<label for="ewww_image_optimizer_cloud_png_compress"><?php _e('extra compression (slower)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label> <input type="checkbox" id="ewww_image_optimizer_cloud_png_compress" name="ewww_image_optimizer_cloud_png_compress" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png_compress') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_cloud_gif">GIF <?php _e('cloud optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_cloud_gif" name="ewww_image_optimizer_cloud_gif" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
<?php			} ?>
			</table>
			</div>
			<h3><?php _e('General Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<div>
			<p class="nocloud"><?php _e('The plugin performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, pngout, and gifsicle. In some rare cases, these checks may falsely report that you are missing the required utilities even though you have them installed.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_debug"><?php _e('Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_debug" name="ewww_image_optimizer_debug" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_debug') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_auto"><?php _e('Scheduled optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_auto" name="ewww_image_optimizer_auto" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('This will enable scheduled optimization of unoptimized images for your theme, buddypress, and any additional folders you have configured below. Runs hourly: wp_cron only runs when your site is visited, so it may be even longer between optimizations.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_aux_paths"><?php _e('Folders to optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><?php printf(__('One path per line, must be within %s. Use full paths, not relative paths.', EWWW_IMAGE_OPTIMIZER_DOMAIN), ABSPATH); ?><br />
					<textarea id="ewww_image_optimizer_aux_paths" name="ewww_image_optimizer_aux_paths" rows="3" cols="60"><?php if ($aux_paths = ewww_image_optimizer_get_option('ewww_image_optimizer_aux_paths')) { foreach ($aux_paths as $path) echo "$path\n"; } ?></textarea>
					<p class="description">Provide paths containing images to be optimized using scheduled optimization or 'Optimize More' in the Tools menu.<br>
					<b><a href="http://wordpress.org/support/plugin/ewww-image-optimizer"><?php _e('Please submit a support request in the forums to have folders created by a particular plugin auto-included in the future.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></b></p></td></tr>
				<tr><th><label for="ewww_image_optimizer_delay"><?php _e('Bulk Delay', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="text" id="ewww_image_optimizer_delay" name="ewww_image_optimizer_delay" size="5" value="<?php echo ewww_image_optimizer_get_option('ewww_image_optimizer_delay'); ?>"> <?php _e('Choose how long to pause between images (in seconds, 0 = disabled)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
<!--				<tr><th><label for="ewww_image_optimizer_interval"><?php _e('Image Batch Size', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="text" id="ewww_image_optimizer_interval" name="ewww_image_optimizer_interval" size="5" value="<?php echo ewww_image_optimizer_get_option('ewww_image_optimizer_interval'); ?>"> <?php _e('Choose how many images should be processed before each delay', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>-->
				<tr><th><label for="ewww_image_optimizer_skip_size"><?php _e('Skip Images', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="text" id="ewww_image_optimizer_skip_size" name="ewww_image_optimizer_skip_size" size="8" value="<?php echo ewww_image_optimizer_get_option('ewww_image_optimizer_skip_size'); ?>"> <?php _e('Do not optimize images smaller than this (in bytes)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_lossy_skip_full"><?php _e('Exclude full-size images from lossy optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_lossy_skip_full" name="ewww_image_optimizer_lossy_skip_full" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_lossy_skip_full') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_skip_bundle"><?php _e('Use System Paths', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_skip_bundle" name="ewww_image_optimizer_skip_bundle" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle') == TRUE) { ?>checked="true"<?php } ?> /> <?php printf(__('If you have already installed the utilities in a system location, such as %s or %s, use this to force the plugin to use those versions and skip the auto-installers.', EWWW_IMAGE_OPTIMIZER_DOMAIN), '/usr/local/bin', '/usr/bin'); ?></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_jpegtran"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> jpegtran</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_jpegtran" name="ewww_image_optimizer_disable_jpegtran" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_jpegtran') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_optipng"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> optipng</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_optipng" name="ewww_image_optimizer_disable_optipng" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_pngout"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> pngout</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_pngout" name="ewww_image_optimizer_disable_pngout" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') == TRUE) { ?>checked="true"<?php } ?> /></td><tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_gifsicle"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> gifsicle</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_gifsicle" name="ewww_image_optimizer_disable_gifsicle" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_gifsicle') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
<?php	if (class_exists('Cloudinary') && Cloudinary::config_get("api_secret")) { ?>
				<tr><th><label for="ewww_image_optimizer_enable_cloudinary"><?php _e('Automatic Cloudinary upload', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_enable_cloudinary" name="ewww_image_optimizer_enable_cloudinary" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_enable_cloudinary') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('When enabled, uploads to the Media Library will be transferred to Cloudinary after optimization. Cloudinary generates resizes, so only the full-size image is uploaded.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
<?php	} ?>
			</table>
			</div>
			<h3><?php _e('Optimization Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<div>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_jpegtran_copy"><?php _e('Remove metadata', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th>
				<td><input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('This will remove ALL metadata: EXIF and comments.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_optipng_level">optipng <?php _e('optimization level', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th>
				<td><span><select id="ewww_image_optimizer_optipng_level" name="ewww_image_optimizer_optipng_level">
				<option value="1"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 1) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 1) . ': ' . sprintf(__('%d trial', EWWW_IMAGE_OPTIMIZER_DOMAIN), 1); ?></option>
				<option value="2"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 2) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 2) . ': ' . sprintf(__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 8); ?></option>
				<option value="3"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 3) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 3) . ': ' . sprintf(__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 16); ?></option>
				<option value="4"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 4) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 4) . ': ' . sprintf(__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 24); ?></option>
				<option value="5"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 5) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 5) . ': ' . sprintf(__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 48); ?></option>
				<option value="6"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 6) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 6) . ': ' . sprintf(__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 120); ?></option>
				<option value="7"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') == 7) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 7) . ': ' . sprintf(__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 240); ?></option>
				</select> (<?php _e('default', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>=2)</span>
				<p class="description"><?php _e('According to the author of optipng, 10 trials should satisfy most people, 30 trials should satisfy everyone.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_pngout_level">pngout <?php _e('optimization level', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th>
				<td><span><select id="ewww_image_optimizer_pngout_level" name="ewww_image_optimizer_pngout_level">
				<option value="0"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level') == 0) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 0) . ': ' . __('Xtreme! (Slowest)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></option>
				<option value="1"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level') == 1) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 1) . ': ' . __('Intense (Slow)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></option>
				<option value="2"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level') == 2) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 2) . ': ' . __('Longest Match (Fast)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></option>
				<option value="3"<?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level') == 3) { echo ' selected="selected"'; } echo '>' . sprintf(__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 3) . ': ' . __('Huffman Only (Faster)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></option>
				</select> (<?php _e('default', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>=2)</span>
				<p class="description"><?php printf(__('If you have CPU cycles to spare, go with level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 0); ?></p></td></tr>
				<tr><th><label for="ewww_image_optimizer_png_lossy"><?php _e('Lossy PNG optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_png_lossy" name="ewww_image_optimizer_png_lossy" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_png_lossy') == TRUE) { ?>checked="true"<?php } ?> /> <b><?php _e('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></b> <?php _e('While most users will not notice a difference in image quality, lossy means there IS a loss in image quality.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
			</table>
			</div>
			<h3><?php _e('Conversion Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<div>
			<p><?php _e('Conversion is only available for images in the Media Library. By default, all images have a link available in the Media Library for one-time conversion. Turning on individual conversion operations below will enable conversion filters any time an image is uploaded or modified.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?><br />
				<b><?php _e('NOTE:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></b> <?php _e('The plugin will attempt to update image locations for any posts that contain the images. You may still need to manually update locations/urls for converted images.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> 
			</p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_disable_convert_links"><?php _e('Hide Conversion Links', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label</th><td><input type="checkbox" id="ewww_image_optimizer_disable_convert_links" name="ewww_image_optimizer_disable_convert_links" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_convert_links') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('Site or Network admins can use this to prevent other users from using the conversion links in the Media Library which bypass the settings below.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_delete_originals"><?php _e('Delete originals', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_delete_originals" name="ewww_image_optimizer_delete_originals" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('This will remove the original image from the server after a successful conversion.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_jpg_to_png"><?php printf(__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'JPG', 'PNG'); ?></label></th><td><span><input type="checkbox" id="ewww_image_optimizer_jpg_to_png" name="ewww_image_optimizer_jpg_to_png" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <b><?php _e('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></b> <?php _e('Removes metadata and increases cpu usage dramatically.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></span>
				<p class="description"><?php _e('PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p></td></tr>
				<tr><th><label for="ewww_image_optimizer_png_to_jpg"><?php printf(__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'PNG', 'JPG'); ?></label></th><td><span><input type="checkbox" id="ewww_image_optimizer_png_to_jpg" name="ewww_image_optimizer_png_to_jpg" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_png_to_jpg') == TRUE) { ?>checked="true"<?php } ?> /> <b><?php _e('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></b> <?php _e('This is not a lossless conversion.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></span>
				<p class="description"><?php _e('JPG is generally much better than PNG for photographic use because it compresses the image and discards data. PNGs with transparency are not converted by default.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
				<span><label for="ewww_image_optimizer_jpg_background"> <?php _e('JPG background color:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label> #<input type="text" id="ewww_image_optimizer_jpg_background" name="ewww_image_optimizer_jpg_background" size="6" value="<?php echo ewww_image_optimizer_jpg_background(); ?>" /> <span style="padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #<? echo ewww_image_optimizer_jpg_background(); ?>">&nbsp;</span> <?php _e('HEX format (#123def)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>.</span>
				<p class="description"><?php _e('Background color is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
				<span><label for="ewww_image_optimizer_jpg_quality"><?php _e('JPG quality level:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label> <input type="text" id="ewww_image_optimizer_jpg_quality" name="ewww_image_optimizer_jpg_quality" class="small-text" value="<?php echo ewww_image_optimizer_jpg_quality(); ?>" /> <?php _e('Valid values are 1-100.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></span>
				<p class="description"><?php _e('If JPG quality is blank, the plugin will attempt to set the optimal quality level or default to 92. Remember, this is a lossy conversion, so you are losing pixels, and it is not recommended to actually set the level here unless you want noticable loss of image quality.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p></td></tr>
				<tr><th><label for="ewww_image_optimizer_gif_to_png"><?php printf(__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'GIF', 'PNG'); ?></label></th><td><span><input type="checkbox" id="ewww_image_optimizer_gif_to_png" name="ewww_image_optimizer_gif_to_png" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('No warnings here, just do it.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></span>
				<p class="description"> <?php _e('PNG is generally better than GIF, but animated images cannot be converted.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p></td></tr>
			</table>
			</div></div>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>" /></p>
		</form></div><!-- end container left -->
		<div id="ewww-container-right" style="border: 1px solid #ccc; padding: 0 8px; border-radius: 12px; float: right; margin-left: -200px; display: inline-block; width: 174px;">
			<h3>Support EWWW I.O.</h3>
			<p>Would you like to help support development of this plugin? Use any of these referral links to keep me working on this plugin:</p>
			<p><b>Web Hosting:</b><br>
				<a href="http://www.dreamhost.com/r.cgi?132143">Dreamhost</a><br>
				<a href="http://www.bluehost.com/track/nosilver4u">Bluehost</a><br>
				<a href="http://www.liquidweb.com/?RID=nosilver4u">liquidweb</a><br>
				<a href="http://www.stormondemand.com/?RID=nosilver4u">Storm on Demand</a>
			</p>
			<p><b>VPS:</b><br>
				<a href="http://www.bluehost.com/track/nosilver4u?page=/vps">Bluehost</a><br>
				<a href="https://www.digitalocean.com/?refcode=89ef0197ec7e">DigitalOcean</a><br>
				<a href="https://clientarea.ramnode.com/aff.php?aff=1469">RamNode</a>
			</p>
			<p><b>CDN Networks:</b><br>Add the MaxCDN content delivery network to increase website speeds dramatically! <a target="_blank" href="http://tracking.maxcdn.com/c/91625/36539/378">Sign Up Now and Save 25%</a> (100% Money Back Guarantee for 30 days). Integrate it within Wordpress using the W3 Total Cache plugin.</p>
<p>Contribute directly by <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW">donating with Paypal</a>.</p>
		</div>
	</div>
	<?php
}

