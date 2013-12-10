<?php
/**
 * Integrate image optimizers into WordPress.
 * @version 1.7.5
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://www.shanebishop.net/ewww-image-optimizer/
Description: Reduce file sizes for images within WordPress including NextGEN Gallery and GRAND FlAGallery. Uses jpegtran, optipng/pngout, and gifsicle.
Author: Shane Bishop
Text Domain: ewww-image-optimizer
Version: 1.7.5
Author URI: http://www.shanebishop.net/
License: GPLv3
*/
// initialize debug global
$disabled = ini_get('disable_functions');
if (preg_match('/get_current_user/', $disabled)) {
	$ewww_debug = '';
} else {
	$ewww_debug = get_current_user() . '<br>';
}
/**
 * Constants
 */
define('EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww-image-optimizer');
define('EWWW_IMAGE_OPTIMIZER_VERSION', '175');
$ewww_debug .= 'EWWW IO version: ' . EWWW_IMAGE_OPTIMIZER_VERSION . '<br>';
// this is the full system path to the plugin folder
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__) );
// the folder where we install optimization tools
define('EWWW_IMAGE_OPTIMIZER_TOOL_PATH', WP_CONTENT_DIR . '/ewww/');

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 60, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
// variable for plugin settings link
$plugin = plugin_basename (__FILE__);
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
add_action('admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout');
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_media_scripts');
add_action('ewww_image_optimizer_auto', 'ewww_image_optimizer_auto');
//global $wp_version;
$my_version = substr($wp_version, 0, 3);
$ewww_debug .= "WP version: $wp_version<br>";
if ($my_version < 3.5) {
	add_filter('wp_save_image_editor_file', 'ewww_image_optimizer_save_image_editor_file', 60, 5);
} else {
	add_filter('wp_image_editors', 'ewww_image_optimizer_load_editor', 60);
}
register_deactivation_hook(__FILE__, 'ewww_image_optimizer_network_deactivate');

// require the file that does the bulk processing
require(dirname(__FILE__) . '/bulk.php');
require(dirname(__FILE__) . '/aux-optimize.php');

// need to include the plugin library for the is_plugin_active function (even though it isn't supposed to be necessary in the admin)
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

// include the file that loads the nextgen gallery optimization functions
if (is_plugin_active('nextgen-gallery/nggallery.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('nextgen-gallery/nggallery.php'))) {
	$plugin_dir = str_replace('ewww-image-optimizer', '', dirname(__FILE__));
	$nextgen_data = get_plugin_data($plugin_dir . 'nextgen-gallery/nggallery.php', false, false);
		$ewww_debug .= 'Nextgen version: ' . $nextgen_data['Version'] . '<br>';
	if (preg_match('/^2\./', $nextgen_data['Version'])) { // for Nextgen 2
		require(dirname(__FILE__) . '/nextgen2-integration.php');
	} else {
		require(dirname(__FILE__) . '/nextgen-integration.php');
	}
}

// include the file that loads the grand flagallery optimization functions
if (is_plugin_active('flash-album-gallery/flag.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('flash-album-gallery/flag.php')))
	require( dirname(__FILE__) . '/flag-integration.php' );

/**
 * Plugin initialization function
 */
function ewww_image_optimizer_init() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_init()</b><br>";
	if (get_option('ewww_image_optimizer_version') < EWWW_IMAGE_OPTIMIZER_VERSION) {
		ewww_image_optimizer_install_table();
		update_option('ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION);
	}
	ewww_image_optimizer_cloud_verify();
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
			if (empty($_POST['ewww_image_optimizer_cloud_gif'])) $_POST['ewww_image_optimizer_cloud_gif'] = '';
			update_site_option('ewww_image_optimizer_cloud_gif', $_POST['ewww_image_optimizer_cloud_gif']);
			if (empty($_POST['ewww_image_optimizer_auto'])) $_POST['ewww_image_optimizer_auto'] = '';
			update_site_option('ewww_image_optimizer_auto', $_POST['ewww_image_optimizer_auto']);
			if (empty($_POST['ewww_image_optimizer_aux_paths'])) $_POST['ewww_image_optimizer_aux_paths'] = '';
			update_site_option('ewww_image_optimizer_aux_paths', ewww_image_optimizer_aux_paths_sanitize($_POST['ewww_image_optimizer_aux_paths']));
			if (empty($_POST['ewww_image_optimizer_enable_cloudinary'])) $_POST['ewww_image_optimizer_enable_cloudinary'] = '';
			update_site_option('ewww_image_optimizer_enable_cloudinary', $_POST['ewww_image_optimizer_enable_cloudinary']);
			add_action('network_admin_notices', 'ewww_image_optimizer_network_settings_saved');
		}
	}
	// register all the EWWW IO settings
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_check');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_bundle');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_debug');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_copy');
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
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_key');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_jpg');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_png');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_gif');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_auto');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_aux_paths', 'ewww_image_optimizer_aux_paths_sanitize');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_enable_cloudinary');
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
	add_action('admin_enqueue_scripts', 'ewww_image_optimizer_progressbar_style');
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

// tells the user they are on an unsupported operating system
function ewww_image_optimizer_notice_os() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_notice_os()</b><br>";
	echo "<div id='ewww-image-optimizer-warning-os' class='error'><p><strong>" . __('EWWW Image Optimizer is supported on Linux, FreeBSD, Mac OSX, and Windows', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</strong> " . sprintf(__('Unfortunately, the EWWW Image Optimizer plugin does not work with %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities(PHP_OS)) . ".</p></div>";
}   

// adds table to db for storing status of auxiliary images that have been optimized
function ewww_image_optimizer_install_table() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_install_table()</b><br>";
	global $wpdb;
	$table_name = $wpdb->prefix . "ewwwio_images";
	
	// create a table with 4 columns: an id, the file path, the md5sum, and the optimization results
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		path text NOT NULL,
		image_md5 VARCHAR(55),
		results VARCHAR(55) NOT NULL,
		gallery VARCHAR(30),
		image_size mediumint(20),
		UNIQUE KEY id (id)
	);";

	// include the upgrade library to initialize a table
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
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
	// set a few defaults
	add_option('ewww_image_optimizer_disable_pngout', TRUE);
	add_option('ewww_image_optimizer_optipng_level', 2);
	add_option('ewww_image_optimizer_pngout_level', 2);
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
	if (ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE) {
		$ewww_debug .= "running scheduled optimization<br>";
		update_option('ewww_image_optimizer_aux_resume', '');
		update_option('ewww_image_optimizer_aux_attachments', '');
		ewww_image_optimizer_aux_images_script('appearance_page_ewww-image-optimizer-theme-images');
		ewww_image_optimizer_aux_images_initialize(true);
		$attachments = get_option('ewww_image_optimizer_aux_attachments');
		foreach ($attachments as $attachment) {
			if (!get_option('ewww_image_optimizer_aux_resume')) {
				ewww_image_optimizer_debug_log();
				return;
			}
			ewww_image_optimizer_aux_images_loop($attachment, true);
		}	
		ewww_image_optimizer_aux_images_cleanup(true);
		ewww_image_optimizer_debug_log();
	}
	return;
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
		'eabcbabde6c7c568e95afd73b7ed096e'
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
	if ($case == 'b' && preg_match('/executable/', $type)) {
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
			exec($path . ' -v', $optipng_version);
			if (!empty($optipng_version)) $ewww_debug .= "$path: $optipng_version[0]<br>";
			if (!empty($optipng_version) && strpos($optipng_version[0], 'OptiPNG') === 0) {
				return $optipng_version[0];
			}
			break;
		case 'g': // gifsicle
			exec($path . ' --version', $gifsicle_version);
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
		case 'i': // ImageMagick
			exec("$path -version", $convert_version);
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
			exec("$path --version", $tar_version);
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

// If the utitilites are in the content folder, we use that. Otherwise, we check system paths. We also do a basic check to make sure we weren't given a malicious path.
function ewww_image_optimizer_path_check() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_path_check()</b><br>";
	$jpegtran = false;
	$optipng = false;
	$gifsicle = false;
	$pngout = false;
	// for Windows, everything must be in the wp-content/ewww folder, so that is all we check
	if ('WINNT' == PHP_OS) {
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe')) {
			$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe';
			$ewww_debug .= "found $jpt, testing...<br>";
			if (ewww_image_optimizer_tool_found($jpt, 'j') && ewww_image_optimizer_md5check($jpt)) {
				$jpegtran = $jpt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe')) {
			$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe';
			$ewww_debug .= "found $opt, testing...<br>";
			if (ewww_image_optimizer_tool_found($opt, 'o') && ewww_image_optimizer_md5check($opt)) {
				$optipng = $opt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe')) {
			$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe';
			$ewww_debug .= "found $gpt, testing...<br>";
			if (ewww_image_optimizer_tool_found($gpt, 'g') && ewww_image_optimizer_md5check($gpt)) {
				$gifsicle = $gpt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe')) {
			$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe';
			$ewww_debug .= "found $ppt, testing...<br>";
			if (ewww_image_optimizer_tool_found($ppt, 'p') && ewww_image_optimizer_md5check($ppt)) {
				$pngout = $ppt;
			}
		}
	} else {
		// check to see if the user has disabled using bundled binaries
		$use_system = ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle');
		// first check for the jpegtran binary in the ewww tool folder
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran') && !$use_system) {
			$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
			$ewww_debug .= "found $jpt, testing...<br>";
			if (ewww_image_optimizer_md5check($jpt) && ewww_image_optimizer_mimetype($jpt, 'b')) {
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
				if (ewww_image_optimizer_tool_found($jpt, 'j')) {
					$jpegtran = $jpt;
				}
			}
		}
		// if we still haven't found a usable binary, try a system-installed version
		if (!$jpegtran) {
			$jpegtran = ewww_image_optimizer_find_binary('jpegtran', 'j');
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng') && !$use_system) {
			$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
			$ewww_debug .= "found $opt, testing...<br>";
			if (ewww_image_optimizer_md5check($opt) && ewww_image_optimizer_mimetype($opt, 'b')) {
				if (ewww_image_optimizer_tool_found($opt, 'o')) {
					$optipng = $opt;
				}
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') && !$optipng && !$use_system) {
			$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom';
			$ewww_debug .= "found $opt, testing...<br>";
			if (filesize($opt) > 15000 && ewww_image_optimizer_mimetype($opt, 'b')) {
				if (ewww_image_optimizer_tool_found($opt, 'o')) {
					$optipng = $opt;
				}
			}
		}
		if (!$optipng) {
			$optipng = ewww_image_optimizer_find_binary('optipng', 'o');
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle') && !$use_system) {
			$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
			$ewww_debug .= "found $gpt, testing...<br>";
			if (ewww_image_optimizer_md5check($gpt) && ewww_image_optimizer_mimetype($gpt, 'b')) {
				if (ewww_image_optimizer_tool_found($gpt, 'g')) {
					$gifsicle = $gpt;
				}
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') && !$gifsicle && !$use_system) {
			$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom';
			$ewww_debug .= "found $gpt, testing...<br>";
			if (filesize($gpt) > 15000 && ewww_image_optimizer_mimetype($gpt, 'b')) {
				if (ewww_image_optimizer_tool_found($gpt, 'g')) {
					$gifsicle = $gpt;
				}
			}
		}
		if (!$gifsicle) {
			$gifsicle = ewww_image_optimizer_find_binary('gifsicle', 'g');
		}
		// pngout is special and has a dynamic and static binary to check
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static') && !$use_system) {
			$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static';
			$ewww_debug .= "found $ppt, testing...<br>";
			if (ewww_image_optimizer_md5check($ppt) && ewww_image_optimizer_mimetype($ppt, 'b')) {
				if (ewww_image_optimizer_tool_found($ppt, 'p')) {
					$pngout = $ppt;
				}
			}
		}
		if (!$pngout)
			$pngout = ewww_image_optimizer_find_binary('pngout', 'p');
		if (!$pngout)
			$pngout = ewww_image_optimizer_find_binary('pngout-static', 'p');
	}
	if ($jpegtran) $ewww_debug .= "using: $jpegtran<br>";
	if ($optipng) $ewww_debug .= "using: $optipng<br>";
	if ($gifsicle) $ewww_debug .= "using: $gifsicle<br>";
	if ($pngout) $ewww_debug .= "using: $pngout<br>";
	return array(
		'JPEGTRAN' => $jpegtran,
		'OPTIPNG' => $optipng,
		'GIFSICLE' => $gifsicle,
		'PNGOUT' => $pngout
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
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe';
	}
	if (PHP_OS == 'Darwin') {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-mac';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-mac';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-mac';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
	}
	if (PHP_OS == 'SunOS') {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-sol';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-sol';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-sol';
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
	}
	if (PHP_OS == 'FreeBSD') {
		$arch_type = php_uname('m');
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-fbsd';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-fbsd';
		if ($arch_type == 'amd64') {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-fbsd64';
		} else {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-fbsd';
		}
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
	}
	if (PHP_OS == 'Linux') {
		$arch_type = php_uname('m');
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'gifsicle-linux';
		$optipng_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'optipng-linux';
		if ($arch_type == 'x86_64') {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-linux64';
		} else {
			$jpegtran_src = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'jpegtran-linux';
		}
		$gifsicle_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$optipng_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$jpegtran_dst = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
	}
	$ewww_debug .= "generated paths:<br>$jpegtran_src<br>$optipng_src<br>$gifsicle_src<br>$jpegtran_dst<br>$optipng_dst<br>$gifsicle_dst<br>";
	return array($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst);
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
	list ($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst) = ewww_image_optimizer_install_paths();
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
		$ewww_debug .= "gifislce permissions: $gifsicle_perms<br>";
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
		}
	}
	// expand the missing utilities list for use in the error message
	$msg = implode(', ', $missing);
	// if there is a message, display the warning
	if(!empty($msg)){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p>" . sprintf(__('EWWW Image Optimizer requires %1$s, %2$s or %3$s, and %4$s. You are missing: %5$s. Please install via the %6$s or the %7$s.', EWWW_IMAGE_OPTIMIZER_DOMAIN), "<a href='http://jpegclub.org/jpegtran/'>jpegtran</a>", "<a href='http://optipng.sourceforge.net/'>optipng</a>", "<a href='http://advsys.net/ken/utils.htm'>pngout</a>", "<a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>", $msg, "<a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>" . __('Settings Page', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>", "<a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>" . __('Installation Instructions', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>") . "</p></div>";
	}
}

// removes the network settings when the plugin is deactivated
function ewww_image_optimizer_network_deactivate($network_wide) {
	global $wpdb;
	wp_clear_scheduled_hook('ewww_image_optimizer_auto');
	if ($network_wide) {
		$query = "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";
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
	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) {
		// add options page to the settings menu
		$ewww_network_options_page = add_submenu_page(
			'settings.php',				//slug of parent
			'EWWW Image Optimizer',			//Title
			'EWWW Image Optimizer',			//Sub-menu title
			'manage_network_options',		//Security
			__FILE__,				//File to open
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
	$ewww_theme_optimize_page = add_theme_page(__('Optimize Theme Images', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'install_themes', 'ewww-image-optimizer-theme-images', 'ewww_image_optimizer_aux_images');
	add_action('admin_footer-' . $ewww_theme_optimize_page, 'ewww_image_optimizer_debug');
	$ewww_aux_optimize_page = add_management_page(__('Optimize More Images', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('Optimize More', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'install_themes', 'ewww-image-optimizer-aux-images', 'ewww_image_optimizer_aux_images');
	add_action('admin_footer-' . $ewww_aux_optimize_page, 'ewww_image_optimizer_debug');
	// if buddypress is active, add the BuddyPress Optimizer to the menu
	if (is_plugin_active('buddypress/bp-loader.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('buddypress/bp-loader.php'))) {
		$ewww_buddypress_optimize_page = add_media_page(__('Optimize BuddyPress Images', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('BuddyPress Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'install_themes', 'ewww-image-optimizer-buddypress-images', 'ewww_image_optimizer_aux_images');
		add_action('admin_footer-' . $ewww_buddypress_optimize_page, 'ewww_image_optimizer_debug');
	}
	// if WP Symposium is active, add the Symposium Optimizer to the menu
	if (is_plugin_active('wp-symposium/wp-symposium.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('wp-symposium/wp-symposium.php'))) {
		$ewww_symposium_optimize_page = add_media_page(__('Optimize WP Symposium Images', EWWW_IMAGE_OPTIMIZER_DOMAIN), __('WP Symposium Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'install_themes', 'ewww-image-optimizer-symposium-images', 'ewww_image_optimizer_aux_images');
		add_action('admin_footer-' . $ewww_symposium_optimize_page, 'ewww_image_optimizer_debug');
	}
	if (!function_exists('is_plugin_active_for_network') || !is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) { 
		// add options page to the settings menu
		$ewww_options_page = add_options_page(
			'EWWW Image Optimizer',		//Title
			'EWWW Image Optimizer',		//Sub-menu title
			'manage_options',		//Security
			__FILE__,			//File to open
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

// list IMS images and optimization status
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
		// we need to strip the excess data since we only want IDs
		$galleries = ewww_image_optimizer_clean_attachments($galleries);
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
				// we need to strip the excess data from attachments, since we only want the attachment IDs
				$attachments = ewww_image_optimizer_clean_attachments($attachments);
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
			// we need to strip the excess data from attachments, since we only want the attachment IDs
			$attachments = ewww_image_optimizer_clean_attachments($attachments);
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

// enqueue custom jquery stylesheet
function ewww_image_optimizer_media_scripts($hook) {
	if ($hook == 'upload.php') {
		wp_enqueue_script('jquery-ui-tooltip');
		global $wp_version;
		$my_version = $wp_version;
		$my_version = substr($my_version, 0, 3);
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
	$settings_link = '<a href="options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php">' . __('Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a>';
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
		$table_name = $wpdb->prefix . "posts";
		$esql = "SELECT ID, post_content FROM $table_name WHERE post_content LIKE '%$filename%'";
		$es = mysql_query($esql);
		$rows = mysql_fetch_assoc($es);
		// if the original file still exists and no posts contain links to the image
		if (file_exists($file_path) && empty($rows))
			unlink($file_path);
	}
	// resized versions, so we can continue
	if (isset($meta['sizes']) ) {
		// if the full-size didn't have an original image, so $file_path isn't set
		if(empty($file_path)) {
			// get the filepath
			list($file_path, $upload_path) = ewww_image_optimizer_attachment_path($meta, $id);
		}
		// one way or another, $file_path is now set, and we can get the base folder name
		$base_dir = dirname($file_path) . '/';
		// check each resized version
		foreach($meta['sizes'] as $size => $data) {
			// if the original resize is set, and still exists
			if (!empty($data['orig_file']) && file_exists($base_dir . $data['orig_file'])) {
				unset($srows);
				// retrieve the filename from the metadata
				$filename = $data['orig_file'];
				// retrieve any posts that link the image
				$table_name = $wpdb->prefix . "posts";
				$esql = "SELECT ID, post_content FROM $table_name WHERE post_content LIKE '%$filename%'";
				$es = mysql_query($esql);
				$srows = mysql_fetch_assoc($es);
				// if there are no posts containing links to the original, delete it
				if(empty($srows)) {
					unlink($base_dir . $data['orig_file']);
				}
			}
		}
	}
	return;
}

/**
 * Re-optimize image after an edit. The metadata hasn't been updated yet, so we add a filter to be fired when it is.
 */
function ewww_image_optimizer_save_image_editor_file ($nothing, $file, $image, $mime_type, $post_id) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_save_image_editor_file()</b><br>";
	// if we don't already have this update attachment filter
	if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file'))
		// add the update saved file filter
		add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file', 60, 2);
	return;
}

// This is added as a filter on the metadata, only when an image is saved via the image editor
function ewww_image_optimizer_update_saved_file ($meta, $ID) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_update_saved_file()</b><br>";
	$meta = ewww_image_optimizer_resize_from_meta_data($meta, $ID);
	$ewww_debug = '';
	return $meta;
}

// submits the api key for verification
function ewww_image_optimizer_cloud_verify() {
	global $ewww_debug;
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
	$servers = gethostbynamel('optimize.exactlywww.com');
	foreach ($servers as $ip) {
		$url = "http://$ip/";
		$result = wp_remote_post($url, array(
			'timeout' => 20,
			'body' => array('api_key' => $api_key)
		));
		if (is_wp_error($result)) {
			$error_message = $result->get_error_message();
			$ewww_debug .= "verification failed: $error_message <br>";
		} elseif (!empty($result['body']) && preg_match('/(great|exceeded)/', $result['body'])) {
			$verified = $result['body'];
			if (!defined('EWWW_IMAGE_OPTIMIZER_CLOUD_IP')) {
				define('EWWW_IMAGE_OPTIMIZER_CLOUD_IP', $ip);
			}
			$ewww_debug .= "verification success via: $ip <br>";
			break;
		} else {
			$ewww_debug .= "verification failed via: $ip <br>" . print_r($result, true) . "<br>";
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
		$ewww_debug .= "verification body contents: " . $result['body'] . "<br>";
		return $verified;
	}
}

// checks the provided api key for quota information
function ewww_image_optimizer_cloud_quota() {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_cloud_quota()</b><br>";
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	$url = "http://" . EWWW_IMAGE_OPTIMIZER_CLOUD_IP . "/quota.php";
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
function ewww_image_optimizer_cloud_optimizer($file, $type, $convert = false, $newfile = null, $newtype = null, $jpg_params = array('r' => '255', 'g' => '255', 'b' => '255', 'quality' => null)) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_cloud_optimizer()</b><br>";
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
	$ewww_debug .= "file: $file <br>";
	$ewww_debug .= "type: $type <br>";
	$ewww_debug .= "convert: $convert <br>";
	$ewww_debug .= "newfile: $newfile <br>";
	$ewww_debug .= "newtype: $newtype <br>";
	$ewww_debug .= "jpg_params: " . print_r($jpg_params, true) . " <br>";
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	$url = 'http://' . EWWW_IMAGE_OPTIMIZER_CLOUD_IP . '/';
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
		$newsize = filesize($tempfile);
		$orig_size = filesize($file);
		$converted = false;
		if (preg_match('/exceeded/', $response['body'])) {
			$ewww_debug .= "License Exceeded<br>";
			$msg = 'exceeded';
			unlink($tempfile);
		} elseif (ewww_image_optimizer_mimetype($tempfile, 'i') == $type) {
			rename($tempfile, $file);
			// store the result of the conversion
			$msg = "$orig_size vs. $newsize";
			$ewww_debug .= "optimized image is better: $msg<br>";
		} elseif (ewww_image_optimizer_mimetype($tempfile, 'i') == $newtype) {
			$converted = true;
			rename($tempfile, $newfile);
			// store the result of the conversion
			$msg = "$orig_size vs. $newsize";
			$ewww_debug .= "converted image is better: $msg<br>";
			$file = $newfile;
		} else {
			unlink($tempfile);
			$msg = 'unchanged';
		}
		return array($file, $converted, $msg);
	}
}

/**
 * Process an image.
 *
 * Returns an array of the $file, $results, $converted to tell us if an image changes formats, and the $original file if it did.
 *
 * @param   string $file		Full absolute path to the image file
 * @param   int $gallery_type		1=wordpress, 2=nextgen, 3=flagallery, 4=aux_images, 5=image editor, 6=imagestore
 * @param   boolean $converted		tells us if this is a resize and the full image was converted to a new format
 * @returns array
 */
function ewww_image_optimizer($file, $gallery_type, $converted, $resize) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer()</b><br>";
	// if the plugin gets here without initializing, we need to run through some things first
	if (!defined('EWWW_IMAGE_OPTIMIZER_CLOUD')) {
		ewww_image_optimizer_init();
	}
	// initialize the original filename 
	$original = $file;
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
		// get the utility paths
		$tools = ewww_image_optimizer_path_check();
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
		preg_match('/-\d+x\d+$/', $filename, $fileresize);
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
	// initialize $new_size with the original size
	$new_size = $orig_size;
	// set the optimization process to OFF
	$optimize = false;
	// toggle the convert process to ON
	$convert = true;
	// run the appropriate optimization/conversion for the mime-type
	switch($type) {
		case 'image/jpeg':
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
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')) {
				list($file, $converted, $result) = ewww_image_optimizer_cloud_optimizer($file, $type, $convert, $pngfile, 'image/png');
				if ($converted) $converted = $filenum;
				break;
			}
			// if the conversion process is turned ON, or if this is a resize and the full-size was converted
			if ($convert && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg')) {
				$ewww_debug .= "attempting to convert JPG to PNG: $pngfile <br>";
				// retrieve version info for ImageMagick
				$convert_path = ewww_image_optimizer_find_binary('convert', 'i');
				// convert the JPG to PNG
				if (!empty($convert_path)) {
					$ewww_debug .= "converting with ImageMagick<br>";
					exec("$convert_path $file -strip " . escapeshellarg($pngfile));
				} elseif (ewww_image_optimizer_gd_support()) {
					$ewww_debug .= "converting with GD<br>";
					imagepng(imagecreatefromjpeg($file), $pngfile);
				}
				// if pngout isn't disabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the pngout optimization level
					$pngout_level = ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						$ewww_debug .= "optimizing converted PNG with pngout<br>";
						// run pngout on the new PNG
						exec("$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . escapeshellarg($pngfile));
					}
				}
				// if optipng isn't disabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optipng optimization level
					$optipng_level = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						$ewww_debug .= "optimizing converted PNG with optipng<br>";
						// run optipng on the new PNG
						exec("$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet " . escapeshellarg($pngfile));
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
				if ($orig_size > $png_size && $png_size != 0) {
					// successful conversion (for now), and we store the increment
					$converted = $filenum;
				} else {
					// otherwise delete the PNG
					$converted = FALSE;
					if (is_file($pngfile)) {
						unlink ($pngfile);
					}
				}
			// if conversion and optimization are both turned OFF, finish the JPG processing
			} elseif (!$optimize) {
				break;
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
				exec("$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -outfile " . escapeshellarg($tempfile) . " " . escapeshellarg($file));
				// run jpegtran - progressive
				exec("$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -progressive -outfile " . escapeshellarg($progfile) . " " . escapeshellarg($file));
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
			}
			// if we generated a smaller PNG than the optimized JPG
			if ($converted && $new_size > $png_size) {
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
				// store the result of the conversion
				$result = "$orig_size vs. $new_size";
			// if the PNG was smaller than the original JPG, but bigger than the optimized JPG
			} elseif ($converted) {
				$ewww_debug .= "converted PNG is no good<br>";
				// unsuccessful conversion
				$converted = FALSE;
				// remove the converted PNG
				unlink($pngfile);
			}
			break;
		case 'image/png':
			// png2jpg conversion is turned on, and the image is in the wordpress media library
			if (((ewww_image_optimizer_get_option('ewww_image_optimizer_png_to_jpg') && $gallery_type == 1) || !empty($_GET['convert'])) && (!ewww_image_optimizer_png_alpha($file) || ewww_image_optimizer_jpg_background())) {
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
					$r = '255';
					$g = '255';
					$b = '255';
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
				// turn the conversion process OFF
				$convert = false;
				$jpgfile = '';
				$r = null;
				$g = null;
				$b = null;
				$gquality = null;
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
			// retrieve the filesize of the original image
			$orig_size = filesize($file);
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png')) {
				list($file, $converted, $result) = ewww_image_optimizer_cloud_optimizer($file, $type, $convert, $jpgfile, 'image/jpeg', array('r' => $r, 'g' => $g, 'b' => $b, 'quality' => $gquality));
				if ($converted) $converted = $filenum;
				break;
			}
			// if conversion is on and the PNG doesn't have transparency or the user set a background color to replace transparency, or this is a resize and the full-size image was converted
			if ($convert) {
				// retrieve version info for ImageMagick
				$convert_path = ewww_image_optimizer_find_binary('convert', 'i');
				// convert the PNG to a JPG with all the proper options
				if (!empty($convert_path)) {
					$ewww_debug .= "converting with ImageMagick<br>";
					exec ("$convert_path $background -flatten $cquality " . escapeshellarg($file) . " " . escapeshellarg($jpgfile));
				} elseif (ewww_image_optimizer_gd_support()) {
					$ewww_debug .= "converting with GD<br>";
					// retrieve the data from the PNG
					$input = imagecreatefrompng($file);
					// retrieve the dimensions of the PNG
					list($width, $height) = getimagesize($file);
					// create a new image with those dimensions
					$output = imagecreatetruecolor($width, $height);
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
				} else {
					$jpg_size = 0;
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
					exec("$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -outfile " . escapeshellarg($tempfile) . " " . escapeshellarg($jpgfile));
					// run jpegtran - progressive
					exec("$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize -progressive -outfile " . escapeshellarg($progfile) . " " . escapeshellarg($jpgfile));
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
					// if the progressive file is bigger
					if ($prog_size > $non_size) {
						// store the size of the non-progessive JPG
						$opt_jpg_size = $non_size;
						if (is_file($progfile)) {
							// delete the progressive file
							unlink($progfile);
						}
					// if the progressive file is smaller or the same
					} else {
						// store the size of the progressive JPG
						$opt_jpg_size = $prog_size;
						// replace the non-progressive with the progressive file
						rename($progfile, $tempfile);
					}
					// if the best-optimized is smaller than the original JPG, and we didn't create an empty JPG
					if ($jpg_size > $opt_jpg_size && $opt_jpg_size != 0) {
						// replace the original with the optimized file
						rename($tempfile, $jpgfile);
						// store the size of the optimized JPG
						$jpg_size = $opt_jpg_size;
					// if the optimization didn't produce a smaller JPG
					} elseif (is_file($tempfile)) {
						// delete the optimized file
						unlink($tempfile);
					}
				} 
				$ewww_debug .= "converted JPG size: $jpg_size<br>";
				// if the new JPG is smaller than the original PNG
				if ($orig_size > $jpg_size && $jpg_size != 0) {
					// successful conversion (for now), so we store the increment
					$converted = $filenum;
				} else {
					$converted = FALSE;
					if (is_file($jpgfile)) {
						// otherwise delete the new JPG
						unlink ($jpgfile);
					}
				}
			// if conversion and optimization are both disabled we are done here
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned on
			if ($optimize) {
				// if pngout is enabled
				if(!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the optimization level for pngout
					$pngout_level = ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the PNG file
					exec("$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . escapeshellarg($file));
				}
				// if optipng is enabled
				if(!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optimization level for optipng
					$optipng_level = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level');
					// run optipng on the PNG file
					exec("$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet " . escapeshellarg($file));
				}
				//}
			}
			// flush the cache for filesize
			clearstatcache();
			// retrieve the new filesize of the PNG
			$new_size = filesize($file);
			// if the converted JPG was smaller than the original, and smaller than the optimized PNG, and the JPG isn't an empty file
			if ($converted && $new_size > $jpg_size && $jpg_size != 0) {
				// store the size of the JPG as the new filesize
				$new_size = $jpg_size;
				// if the user wants originals delted after a conversion
				if (ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE) {
					// delete the original PNG
					unlink($file);
				}
				// update the $file location to the new JPG
				$file = $jpgfile;
			// if the converted JPG was smaller than the original, but larger than the optimized PNG
			} elseif ($converted) {
				// unsuccessful conversion
				$converted = FALSE;
				if (is_file($jpgfile)) {
					// delete the resulting JPG
					unlink($jpgfile);
				}
			}
			// if the new file (converted OR optimized) is smaller than the original
			if ($orig_size > $new_size) {
				// return a message comparing the two
				$result = "$orig_size vs. $new_size";    
			} else {
				// otherwise nothing has changed
				$result = "unchanged";
			}
			break;
		case 'image/gif':
			// if gif2png is turned on, and the image is in the wordpress media library
			if (((ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png') && $gallery_type == 1) || !empty($_GET['convert'])) && !ewww_image_optimizer_is_animated($file)) {
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
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif')) {
				list($file, $converted, $result) = ewww_image_optimizer_cloud_optimizer($file, $type, $convert, $pngfile, 'image/png');
				if ($converted) $converted = $filenum;
				break;
			}
			// if conversion is ON, the GIF isn't animated, or this is a resize and the full-size image was converted
			if ($convert) {
				// if pngout is enabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') && $tools['PNGOUT']) {
					// retrieve the pngout optimization level
					$pngout_level = ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the file
					exec("$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . escapeshellarg($file) . " " . escapeshellarg($pngfile));
				}
				// if optipng is enabled
				if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') && $tools['OPTIPNG']) {
					// retrieve the optipng optimization level
					$optipng_level = ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level');
					// if $pngfile exists (which means pngout was run already)
					if (file_exists($pngfile)) {
						// run optipng on the PNG file
						exec("$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet " . escapeshellarg($pngfile));
					// otherwise, if pngout was not used
					} else {
						// run optipng on the GIF file
						exec("$nice " . $tools['OPTIPNG'] . " -out " . escapeshellarg($pngfile) . " -o$optipng_level -quiet " . escapeshellarg($file));
					}
				}
				// if a PNG file was created
				if (file_exists($pngfile)) {
					// retrieve the filesize of the PNG
					$png_size = filesize($pngfile);
					// if the new PNG is smaller than the original GIF
					if ($orig_size > $png_size && $png_size != 0) {
						// successful conversion (for now), so we store the increment
						$converted = $filenum;
					} else {
						$converted = FALSE;
						if (is_file($pngfile)) {
							unlink ($pngfile);
						}
					}
				}
			// if conversion and optimization are both turned OFF, we are done here
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned ON
			if ($optimize) {
				// run gifsicle on the GIF
				exec("$nice " . $tools['GIFSICLE'] . " -b -O3 --careful " . escapeshellarg($file));
			}
			// flush the cache for filesize
			clearstatcache();
			// get the new filesize for the GIF
			$new_size = filesize($file);
			// if the PNG was smaller than the original GIF, and smaller than the optimized GIF
			if ($converted && $new_size > $png_size && $png_size != 0) {
				// store the PNG size as the new filesize
				$new_size = $png_size;
				// if the user wants original GIFs deleted after successful conversion
				if (ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE) {
					// delete the original GIF
					unlink($file);
				}
				// update the $file location with the new PNG
				$file = $pngfile;
			// if the PNG was smaller than the original GIF, but bigger than the optimized GIF
			} elseif ($converted) {
				// unsuccessful conversion
				$converted = FALSE;
				if (is_file($pngfile)) {
					// delete the resulting PNG
					unlink($pngfile);
				}
			}
			// if the new file (converted or optimized) is smaller than the original
			if ($orig_size > $new_size) {
				// send back a message with the results
				$result = "$orig_size vs. $new_size";
			} else {
				// otherwise, nothing has changed
				$result = "unchanged";
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
	// if the image is unchanged
	if ($result == 'unchanged') {
		// tell the user we couldn't save them anything
		return array($file, __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted, $original);
	}
	// if the image changed
	if (strpos($result, ' vs. ') !== false) {
		// strip and split $result where it says ' vs. '
		$s = explode(' vs. ', $result);
		// calculate how much space was saved
		$savings = intval($s[0]) - intval($s[1]);
		// convert it to human readable format
		$savings_str = size_format($savings, 1);
		// replace spaces with proper html entity encoding
		$savings_str = str_replace('B ', 'B', $savings_str);
		$savings_str = str_replace(' ', '&nbsp;', $savings_str);
		// determine the percentage savings
		$percent = 100 - (100 * ($s[1] / $s[0]));
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf(__("Reduced by %01.1f%% (%s)", EWWW_IMAGE_OPTIMIZER_DOMAIN),
			$percent,
			$savings_str);
		// send back the filename, the results, and the $converted flag
		return array($file, $results_msg, $converted, $original);
	}
	// otherwise, send back the filename, the results (some sort of error message), the $converted flag, and the name of the original image
	return array($file, $result, $converted, $original);
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
function ewww_image_optimizer_resize_from_meta_data($meta, $ID = null) {
	global $ewww_debug;
	global $wpdb;
	$ewww_debug .= "<b>ewww_image_optimizer_resize_from_meta_data()</b><br>";
	if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file')) {
		$gallery_type = 1;
	} else {
		$gallery_type = 5;
	}
	$ewww_debug .= "attachment id: $ID<br>";
	if (!wp_get_attachment_metadata($ID))
		$new_image = true;
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
		$query = "SELECT id,results FROM " . $wpdb->prefix . 'ewwwio_images' . " WHERE path = '$imsanity_path' AND image_size = '$image_size'";
		$already_optimized = $wpdb->get_results($query, ARRAY_A);
	}
	// if converting, we don't care that it was already optimized
	if (!empty($_GET['convert']) || ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_to_png') || ewww_image_optimizer_get_option('ewww_image_optimizer_png_to_jpg') || ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png'))
		$already_optimized = '';
	// if the image wasn't optimized already OR this isn't a newly uploaded image
	if (empty($already_optimized) || empty($new_image)) {
		// run the image optimizer on the file, and store the results
		list($file, $msg, $conv, $original) = ewww_image_optimizer($file_path, $gallery_type, false, false);
	// So... only if this is a newly uploaded image that was resized by Imsanity, we skip optimization and just copy the results from the database
	} else {
		$file = $file_path;
		$msg = $already_optimized[0]['results'];
		$conv = false;
	}
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
				// check the database to make sure it wasn't already optimized via wp_image_editor and friends
				$image_size = filesize($resize_path);
				$query = "SELECT id,results FROM " . $wpdb->prefix . 'ewwwio_images' . " WHERE path = '$resize_path' AND image_size = '$image_size'";
				$already_optimized = $wpdb->get_results($query, ARRAY_A);
				if (!empty($_GET['convert']) || ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_to_png') || ewww_image_optimizer_get_option('ewww_image_optimizer_png_to_jpg') || ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png'))
					$already_optimized = '';
				if (empty($already_optimized)) {
					// run the optimization and store the results
					list($optimized_file, $results, $resize_conv, $original) = ewww_image_optimizer($resize_path, $gallery_type, $conv, true);
				} else {
					$optimized_file = $resize_path;
					$results = $already_optimized[0]['results'] . " - " . __('Previously Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$resize_conv = false;
				}
				// if the resize was converted, store the result and the original filename in the metadata for later recovery
				if ($resize_conv) {
					// if we don't already have the update attachment filter
					if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment'))
						// add the update attachment filter
						add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
					$meta['sizes'][$size]['converted'] = 1;
					$meta['sizes'][$size]['orig_file'] = str_replace($base_dir, '', $original);
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

	ewww_image_optimizer_debug_log();
	// send back the updated metadata
	return $meta;
}

/**
 * Update the attachment's meta data after being converted 
 */
function ewww_image_optimizer_update_attachment($meta, $ID) {
	global $ewww_debug;
	$ewww_debug .= "<b>ewww_image_optimizer_update_attachment()</b><br>";
	// update the file location in the post metadata based on the new path stored in the attachment metadata
	update_attached_file($ID, $meta['file']);
	// retrieve the post information based on the $ID
	$post = get_post($ID);
	// save the previous attachment address
	$old_guid = $post->guid;
	// construct the new guid based on the filename from the attachment metadata
	$guid = dirname($post->guid) . "/" . basename($meta['file']);
	// retrieve any posts that link the image
	global $wpdb;
	$table_name = $wpdb->prefix . "posts";
	$esql = "SELECT ID, post_content FROM $table_name WHERE post_content LIKE '%$old_guid%'";
	$es = mysql_query($esql);
	// while there are posts to process
	while($rows = mysql_fetch_assoc($es)) {
		// replace all occurences of the old guid with the new guid
		$post_content = addslashes(str_replace($old_guid, $guid, $rows['post_content']));
		// send the updated content back to the database
		mysql_query("UPDATE $table_name SET post_content = '$post_content' WHERE ID = {$rows["ID"]}");
	}
	if (isset($meta['sizes']) ) {
		// for each resized version
		foreach($meta['sizes'] as $size => $data) {
			// if the resize was converted
			if (isset($data['converted'])) {
				// generate the url for the old image
				$old_sguid = dirname($post->guid) . "/" . basename($data['orig_file']);
				// generate the url for the new image
				$sguid = dirname($post->guid) . "/" . basename($data['file']);
				// retrieve any posts that link the resize
				$ersql = "SELECT ID, post_content FROM $table_name WHERE post_content LIKE '%$old_sguid%'";
				$ers = mysql_query($ersql);
				// while there are posts to process
				while($rows = mysql_fetch_assoc($ers)) {
					// replace all occurences of the old guid with the new guid
					$post_content = addslashes(str_replace($old_sguid, $sguid, $rows['post_content']));
					// send the updated content back to the database
					mysql_query("UPDATE $table_name SET post_content = '$post_content' WHERE ID = {$rows["ID"]}");
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
	} else {
		return '';
	}
}

// takes an array of attachment info and strips out extra fields
function ewww_image_optimizer_clean_attachments ($attachments) {
	// the 'fields' option was added in 3.1, so (in older versions) we need to strip 
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
	return $attachments;
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
		return true;
	} else {
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
	if(!($fh = @fopen($filename, 'rb')))
		return false;
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
//			$print_meta = preg_replace('/\n/', '<br />', $print_meta);
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
		$file_size = str_replace('B ', 'B', $file_size);
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
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_convert_links') && 'ims_image' != get_post_type($id))
				echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;attachment_ID=$id&amp;convert=1'>$convert_link</a>";
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
				echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;attachment_ID=$id&amp;convert=1'>$convert_link</a>";
		}
	}
}

// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
// adds a bulk optimize action to the drop-down on the media library page
function ewww_image_optimizer_add_bulk_actions_via_javascript() {
	// TODO: there may be a more elegant way of doing this, see the cloudinary plugin
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
			$download_result = download_url('http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-' . $os_string . '-static.tar.gz');
			if (is_wp_error($download_result)) {
				$pngout_error = $download_result->get_error_message();
			} else {
				$arch_type = php_uname('m');
				exec("$tar xzf $download_result -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static');
				if (!rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static'))
					if (empty($pngout_error)) $pngout_error = __("could not move pngout", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				if (!chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755))
					if (empty($pngout_error)) $pngout_error = __("could not set permissions", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 'p');
			}
		}
		if (PHP_OS == 'Darwin') {
			$download_result = download_url('http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-darwin.tar.gz');
			if (is_wp_error($download_result)) {
				$pngout_error = $download_result->get_error_message();
			} else {
				exec("$tar xzf $download_result -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-' . $latest . '-darwin/pngout');
				if (!rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin/pngout', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static'))
					if (empty($pngout_error)) $pngout_error = __("could not move pngout", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				if (!chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755))
					if (empty($pngout_error)) $pngout_error = __("could not set permissions", EWWW_IMAGE_OPTIMIZER_DOMAIN);
				$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 'p');
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
			$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe', 'p');
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

// retrieve an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function ewww_image_optimizer_get_option ($option_name) {
	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) {
		$option_value = get_site_option($option_name);
	} else {
		$option_value = get_option($option_name);
	}
	return $option_value;
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
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>EWWW <?php _e('Image Optimizer Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h2>
		<p><a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/"><?php _e('Plugin Home Page', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a> |
		<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/"><?php _e('Installation Instructions', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a> | 
		<a href="http://wordpress.org/support/plugin/ewww-image-optimizer"><?php _e('Plugin Support', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a> | 
		<a href="http://stats.pingdom.com/w89y81bhecp4"><?php _e('Cloud Status', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></p>
		<p>I recommend hosting your Wordpress site with <a href=http://www.dreamhost.com/r.cgi?132143">Dreamhost.com</a> or <a href="http://www.bluehost.com/track/nosilver4u">Bluehost.com</a>. Using these referral links will allow you to support future development of this plugin: <a href=http://www.dreamhost.com/r.cgi?132143">Dreamhost</a> | <a href="http://www.bluehost.com/track/nosilver4u">Bluehost</a>. Alternatively, you can contribute directly by <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW">donating with Paypal</a>.</p>
		<div id="status" style="border: 1px solid #ccc; padding: 0 8px; border-radius: 12px;">
			<h3>Plugin Status</h3>
			<?php
			if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key')) {
				echo '<p><b>Cloud API Key:</b> ';
				$verify_cloud = ewww_image_optimizer_cloud_verify(); 
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
				//if (!empty($jpegtran_installed) && preg_match('/version 8|9/', $jpegtran_installed)) {
				if (!empty($jpegtran_installed)) {
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $jpegtran_installed . '<br />'; 
//				} elseif (!empty($jpegtran_installed)) {
//					echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> executable from ' . $jpegtran_src . ' to ' . $jpegtran_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://www.ijg.org/"><b>Download</b> jpegtran source</a>&emsp;<b>version:</b> ' . $jpegtran_installed . '<br />';
				} else { 
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>optipng:</b> '; 
				$optipng_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'o');
				if (!empty($optipng_version)) { 
				//if (!empty($optipng_version) && preg_match('/0.7.4/', $optipng_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $optipng_version . '<br />'; 
//				} elseif (!empty($optipng_version)) {
//					echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> binary from ' . $optipng_src . ' to ' . $optipng_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://prdownloads.sourceforge.net/optipng/optipng-0.7.4.tar.gz?download"><b>Download</b> optipng source</a>&emsp;<b>version:</b> ' . $optipng_version . '<br />';
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_gifsicle') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>gifsicle:</b> ';
				$gifsicle_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'g');
				//if (!empty($gifsicle_version) && preg_match('/1.7(0|1)/', $gifsicle_version)) { 
				if (!empty($gifsicle_version) && preg_match('/LCDF Gifsicle/', $gifsicle_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $gifsicle_version . '<br />'; 
//				} elseif (!empty($gifsicle_version) && preg_match('/LCDF Gifsicle/', $gifsicle_version)) {
//						echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> binary from ' . $gifsicle_src . ' to ' . $gifsicle_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://www.lcdf.org/gifsicle/gifsicle-1.70.tar.gz"><b>Download</b> gifsicle source</a>&emsp;<b>version:</b> ' . $gifsicle_version . '<br />';
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span><br />';
				}
			}
			if (!ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') && !ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') && !EWWW_IMAGE_OPTIMIZER_NOEXEC) {
				echo "\n", '<b>pngout:</b> '; 
				$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_PNGOUT, 'p');
				if (!empty($pngout_version) && (preg_match('/PNGOUT/', $pngout_version))) { 
				//if (!empty($pngout_version) && (preg_match('/Feb 2(0|1) 2013/', $pngout_version))) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . preg_replace('/PNGOUT \[.*\)\s*?/', '', $pngout_version) . '<br />'; 
//				} elseif (!empty($pngout_version) && preg_match('/PNGOUT/', $pngout_version)) {
//					echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Install</b> <a href="admin.php?action=ewww_image_optimizer_install_pngout">automatically</a> | <a href="http://advsys.net/ken/utils.htm">manually</a>&emsp;<b>version:</b> ' . preg_replace('/PNGOUT \[.*\)\s*?/', '', $pngout_version) . '<br />'; 
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>' . __('Install', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' <a href="admin.php?action=ewww_image_optimizer_install_pngout">' . __('automatically', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a> | <a href="http://advsys.net/ken/utils.htm">' . __('manually', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a></b> - ' . __('Pngout is free closed-source software that can produce drastically reduced filesizes for PNGs, but can be very time consuming to process images', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '<br />'; 
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
			<h3><?php _e('Cloud Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<p><?php _e('If exec() is disabled for security reasons (and enabling it is not an option), or you would like to offload image optimization to a third-party server, you may purchase an API key for our cloud optimization service. The API key should be entered below, and cloud optimization must be enabled for each image format individually.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> <a href="http://www.exactlywww.com/cloud/"><?php _e('Purchase an API key.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_cloud_key"><?php _e('Cloud optimization API Key', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="text" id="ewww_image_optimizer_cloud_key" name="ewww_image_optimizer_cloud_key" value="<?php echo ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key'); ?>" size="32" /> <?php _e('API Key will be validated when you save your settings.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> <a href="http://www.exactlywww.com/cloud/"><?php _e('Purchase a key.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></td></tr>
				<tr><th><label for="ewww_image_optimizer_cloud_jpg">JPG <?php _e('cloud optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_cloud_jpg" name="ewww_image_optimizer_cloud_jpg" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_jpg') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_cloud_png">PNG <?php _e('cloud optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_cloud_png" name="ewww_image_optimizer_cloud_png" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_png') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_cloud_gif">GIF <?php _e('cloud optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_cloud_gif" name="ewww_image_optimizer_cloud_gif" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_gif') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
			</table>
			<h3><?php _e('General Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<p class="nocloud"><?php _e('The plugin performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, pngout, and gifsicle. In some rare cases, these checks may falsely report that you are missing the required utilities even though you have them installed.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_debug"><?php _e('Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_debug" name="ewww_image_optimizer_debug" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_debug') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_auto"><?php _e('Scheduled optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_auto" name="ewww_image_optimizer_auto" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('This will enable scheduled optimization of images for your theme, buddypress, and any additional folders you have configured below. Runs hourly: wp_cron only runs when your site is visited, so it may be even longer between optimizations.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
				<tr><th><label for="ewww_image_optimizer_aux_paths"><?php _e('Folders to optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><?php printf(__('One path per line, must be within %s. Use full paths, not relative paths.', EWWW_IMAGE_OPTIMIZER_DOMAIN), ABSPATH); ?><br />
					<textarea id="ewww_image_optimizer_aux_paths" name="ewww_image_optimizer_aux_paths" rows="3" cols="60"><?php if ($aux_paths = ewww_image_optimizer_get_option('ewww_image_optimizer_aux_paths')) { foreach ($aux_paths as $path) echo "$path\n"; } ?></textarea>
					<p class="description">Provide paths containing images to be optimized using scheduled optimization or 'Optimize More' in the Tools menu.<br>
					<b><a href="http://wordpress.org/support/plugin/ewww-image-optimizer"><?php _e('Please submit a support request in the forums to have folders created by a particular plugin auto-included in the future.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></a></b></p></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_skip_bundle"><?php _e('Use System Paths', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_skip_bundle" name="ewww_image_optimizer_skip_bundle" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle') == TRUE) { ?>checked="true"<?php } ?> /> <?php printf(__('If you have already installed the utilities in a system location, such as %s or %s, use this to force the plugin to use those versions and skip the auto-installers.', EWWW_IMAGE_OPTIMIZER_DOMAIN), '/usr/local/bin', '/usr/bin'); ?></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_jpegtran"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> jpegtran</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_jpegtran" name="ewww_image_optimizer_disable_jpegtran" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_jpegtran') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_optipng"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> optipng</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_optipng" name="ewww_image_optimizer_disable_optipng" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_optipng') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_pngout"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> pngout</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_pngout" name="ewww_image_optimizer_disable_pngout" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') == TRUE) { ?>checked="true"<?php } ?> /></td><tr>
				<tr class="nocloud"><th><label for="ewww_image_optimizer_disable_gifsicle"><?php _e('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?> gifsicle</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_gifsicle" name="ewww_image_optimizer_disable_gifsicle" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_disable_gifsicle') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
<?php	if (class_exists('Cloudinary') && Cloudinary::config_get("api_secret")) { ?>
				<tr><th><label for="ewww_image_optimizer_enable_cloudinary"><?php _e('Automatic Cloudinary upload', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th><td><input type="checkbox" id="ewww_image_optimizer_enable_cloudinary" name="ewww_image_optimizer_enable_cloudinary" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_enable_cloudinary') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('When enabled, uploads to the Media Library will be transferred to Cloudinary after optimization. Cloudinary generates resizes, so only the full-size image is uploaded.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
<?php	} ?>
			</table>
			<h3><?php _e('Optimization Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_jpegtran_copy"><?php _e('Remove JPG metadata', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label></th>
				<td><input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('This wil remove ALL metadata: EXIF and comments.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></td></tr>
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
			</table>
			<h3><?php _e('Conversion Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></h3>
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
				<span><label for="ewww_image_optimizer_jpg_background"> <?php _e('JPG background color:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label> #<input type="text" id="ewww_image_optimizer_jpg_background" name="ewww_image_optimizer_jpg_background" class="small-text" value="<?php echo ewww_image_optimizer_jpg_background(); ?>" /> <span style="padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #<? echo ewww_image_optimizer_jpg_background(); ?>">&nbsp;</span> <?php _e('HEX format (#123def)', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>.</span>
				<p class="description"><?php _e('Background color is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p>
				<span><label for="ewww_image_optimizer_jpg_quality"><?php _e('JPG quality level:', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></label> <input type="text" id="ewww_image_optimizer_jpg_quality" name="ewww_image_optimizer_jpg_quality" class="small-text" value="<?php echo ewww_image_optimizer_jpg_quality(); ?>" /> <?php _e('Valid values are 1-100.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></span>
				<p class="description"><?php _e('If JPG quality is blank, the plugin will attempt to set the optimal quality level or default to 92. Remember, this is a lossy conversion, so you are losing pixels, and it is not recommended to actually set the level here unless you want noticable loss of image quality.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p></td></tr>
				<tr><th><label for="ewww_image_optimizer_gif_to_png"><?php printf(__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'GIF', 'PNG'); ?></label></th><td><span><input type="checkbox" id="ewww_image_optimizer_gif_to_png" name="ewww_image_optimizer_gif_to_png" <?php if (ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <?php _e('No warnings here, just do it.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></span>
				<p class="description"> <?php _e('PNG is generally better than GIF, but animated images cannot be converted.', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></p></td></tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?>" /></p>
		</form>
	</div>
	<?php
}

