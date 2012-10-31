<?php
/**
 * Integrate Linux image optimizers into WordPress.
 * @version 1.2.0
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://www.shanebishop.net/ewww-image-optimizer/
Description: Reduce file sizes and improve performance for images within WordPress including NextGEN Gallery. Uses jpegtran, optipng/pngout, and gifsicle.
Author: Shane Bishop
Version: 1.2.0
Author URI: http://www.shanebishop.net/
License: GPLv3
*/

/**
 * Constants
 */
define('EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww_image_optimizer');
// this is just the name of the plugin folder
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_DIR', dirname(plugin_basename(__FILE__)));
// this is the full system path to the plugin folder
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__) );

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
// variable for plugin settings link
$plugin = plugin_basename ( __FILE__ );
add_filter ("plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link' );
add_action('manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2);
add_action('admin_init', 'ewww_image_optimizer_admin_init');
add_action('admin_action_ewww_image_optimizer_manual', 'ewww_image_optimizer_manual');
add_action('admin_menu', 'ewww_image_optimizer_admin_menu' );
add_action('admin_head-upload.php', 'ewww_image_optimizer_add_bulk_actions_via_javascript' ); 
add_action('admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action('admin_action_-1', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action('admin_print_scripts-media_page_ewww-image-optimizer-bulk', 'ewww_image_optimizer_scripts' );
add_action('admin_action_ewww_image_optimizer_install_jpegtran', 'ewww_image_optimizer_install_jpegtran');
add_action('admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout');
add_action('admin_action_ewww_image_optimizer_install_optipng', 'ewww_image_optimizer_install_optipng');
add_action('admin_action_ewww_image_optimizer_install_gifsicle', 'ewww_image_optimizer_install_gifsicle');

/**
 * Check if this is an unsupported OS (not Linux or Mac OSX)
 */
if('Linux' != PHP_OS && 'Darwin' != PHP_OS) {
	// call the function to display a notice
	add_action('admin_notices', 'ewww_image_optimizer_notice_os');
	// turn off all the tools
	define('EWWW_IMAGE_OPTIMIZER_PNGOUT', false);
	define('EWWW_IMAGE_OPTIMIZER_GIFSICLE', false);
	define('EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false);
	define('EWWW_IMAGE_OPTIMIZER_OPTIPNG', false);
} else {
	//Otherwise, we run the function to check for optimization utilities
	add_action('admin_notices', 'ewww_image_optimizer_notice_utils');
} 

// include the file that loads the nextgen gallery optimization functions
require( dirname(__FILE__) . '/nextgen-integration.php' );

// tells the user they are on an unsupported operating system
function ewww_image_optimizer_notice_os() {
	echo "<div id='ewww-image-optimizer-warning-os' class='updated fade'><p><strong>EWWW Image Optimizer isn't supported on your server.</strong> Unfortunately, the EWWW Image Optimizer plugin doesn't work with " . htmlentities(PHP_OS) . ".</p></div>";
}   

// If the utitilites are in the plugin folder, we use that. Otherwise, we retrieve user specified paths or set defaults if all else fails. We also do a basic check to make sure we weren't given a malicious path.
function ewww_image_optimizer_path_check() {
	$jpegtran = get_option('ewww_image_optimizer_jpegtran_path');
	if(exec("which " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "jpegtran")) {
		$jpegtran = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "jpegtran";
	} elseif (!preg_match('/^\/[\w\.-\d\/_]+\/jpegtran$/', $jpegtran)) {
		$jpegtran = 'jpegtran';
	}
	$optipng = get_option('ewww_image_optimizer_optipng_path');
	if(exec("which " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "optipng")) {
		$optipng = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "optipng";
	} elseif (!preg_match('/^\/[\w\.-\d\/_]+\/optipng$/', $optipng)) {
		$optipng = 'optipng';
	}
	$gifsicle = get_option('ewww_image_optimizer_gifsicle_path');
	if(exec("which " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "gifsicle")) {
		$gifsicle = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "gifsicle";
	} elseif (!preg_match('/^\/[\w\.-\d\/_]+\/gifsicle$/', $gifsicle)) {
		$gifsicle = 'gifsicle';
	}
	// pngout is special, we only support it being in the plugin folder
	$pngout = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static";
	return array($jpegtran, $optipng, $gifsicle, $pngout);
}

// Retrieves jpg background fill setting, or returns null for png2jpg conversions
function ewww_image_optimizer_jpg_background () {
	// retrieve the user-supplied value for jpg background color
	$background = get_option('ewww_image_optimizer_jpg_background');
	//verify that the supplied value is in hex notation
	if (preg_match('/^\#*(\d|[a-f]){6}$/',$background)) {
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
	// retrieve the user-supplied value for jpg quality
	$quality = get_option('ewww_image_optimizer_jpg_quality');
	// verify that the quality level is an integer, 1-100
	if (preg_match('/^(100|[1-9][0-9]?)$/',$quality)) {
		// send back the valid quality level
		return $quality;
	} else {
		// send back nothing
		return NULL;
	}
}

// we check for safe mode and exec, then also direct the user where to go if they don't have the tools installed
function ewww_image_optimizer_notice_utils() {
	// query the php settings for safe mode
	if( ini_get('safe_mode') ){
		// display a warning to the user
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>PHP's Safe Mode is turned on. This plugin cannot operate in safe mode.</strong></p></div>";
	}
	// attempt to retrieve values for utility paths, and store them in the appropriate variables
	list ($jpegtran_path, $optipng_path, $gifsicle_path, $pngout_path) = ewww_image_optimizer_path_check();
	// store those values back into an array, probably a more efficient way of doing this
	$required = array(
		'JPEGTRAN' => $jpegtran_path,
		'OPTIPNG' => $optipng_path,
		'GIFSICLE' => $gifsicle_path,
		'PNGOUT' => $pngout_path
	);
	// if the user has disabled the utility checks
	if(get_option('ewww_image_optimizer_skip_check') == TRUE){
		// set a variable for each tool
		$skip_jpegtran_check = true;
		$skip_optipng_check = true;
		$skip_gifsicle_check = true;
		$skip_pngout_check = true;
	} else {
		// set the variables false otherwise
		$skip_jpegtran_check = false;
		$skip_optipng_check = false;
		$skip_gifsicle_check = false;
		$skip_pngout_check = false;
	}
	// if the user has disabled a variable, we aren't going to bother checking to see if it is there
	if (get_option('ewww_image_optimizer_disable_jpegtran')) {
		$skip_jpegtran_check = true;
	}
	if (get_option('ewww_image_optimizer_disable_optipng')) {
		$skip_optipng_check = true;
	}
	if (get_option('ewww_image_optimizer_disable_gifsicle')) {
		$skip_gifsicle_check = true;
	}
	if (get_option('ewww_image_optimizer_disable_pngout')) {
		$skip_pngout_check = true;
	}
	// we are going to store our validation results in $missing
	$missing = array();
	// go through each of the required tools
	foreach($required as $key => $req){
		// check the paths with the unix 'which' command
		$result = trim(exec('which ' . $req));
		// if the tool wasn't found, add it to the $missing array if we are supposed to check the tool in question
		if(empty($result)){
			switch($key) {
				case 'JPEGTRAN':
					if (!$skip_jpegtran_check) {
						$missing[] = 'jpegtran';
						// also set the appropriate constant to false
						define('EWWW_IMAGE_OPTIMIZER_' . $key, false);
					}
					break; 
				case 'OPTIPNG':
					if (!$skip_optipng_check) {
						$missing[] = 'optipng';
						// also set the appropriate constant to false
						define('EWWW_IMAGE_OPTIMIZER_' . $key, false);
					}
					break;
				case 'GIFSICLE':
					if (!$skip_gifsicle_check) {
						$missing[] = 'gifsicle';
						// also set the appropriate constant to false
						define('EWWW_IMAGE_OPTIMIZER_' . $key, false);
					}
					break;
				case 'PNGOUT':
					if (!$skip_pngout_check) {
						$missing[] = 'pngout';
						// also set the appropriate constant to false
						define('EWWW_IMAGE_OPTIMIZER_' . $key, false);
					}
					break;
			}
		} else {
			// otherwise we set the constant to true
			define('EWWW_IMAGE_OPTIMIZER_' . $key, true);
		}
	}
	// expand the missing utilities list for use in the error message
	$msg = implode(', ', $missing);
	// if there is a message, display the warning
	if(!empty($msg)){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>EWWW Image Optimizer requires <a href='http://jpegclub.org/jpegtran/'>jpegtran</a>, <a href='http://optipng.sourceforge.net/'>optipng</a> or <a href='http://advsys.net/ken/utils.htm'>pngout</a>, and <a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>.</strong> You are missing: $msg. Please install via the <a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>Settings Page</a>. If the one-click install links don't work for you, try the <a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>Installation Instructions</a>.</p></div>";
	}

	// Check if exec is disabled
	$disabled = explode(', ', ini_get('disable_functions'));
	if(in_array('exec', $disabled)){
		//display a warning if exec() is disabled, can't run much of anything without it
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>EWWW Image Optimizer requires exec().</strong> Your system administrator has disabled this function.</p></div>";
	}
}

/**
 * Plugin admin initialization function
 */
function ewww_image_optimizer_admin_init() {
	load_plugin_textdomain(EWWW_IMAGE_OPTIMIZER_DOMAIN);
	wp_enqueue_script('common');
	// register all the EWWW IO settings
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_check');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_gifs');
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
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_background');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality');
	// set a few defaults
	add_option('ewww_image_optimizer_disable_pngout', TRUE);
	add_option('ewww_image_optimizer_optipng_level', 2);
	add_option('ewww_image_optimizer_pngout_level', 2);
}

// load javascript for EWWW IO
function ewww_image_optimizer_scripts () {
	// creates a timer on the bulk optimize page
	wp_enqueue_script ('ewwwloadscript', plugins_url('/pageload.js', __FILE__));
}	

// adds the bulk optimize and settings page to the admin menu
function ewww_image_optimizer_admin_menu() {
	// adds bulk optimize to the media library menu
	add_media_page( 'Bulk Optimize', 'Bulk Optimize', 'edit_others_posts', 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview');
	// add options page to the settings menu
	add_options_page(
		'EWWW Image Optimizer',		//Title
		'EWWW Image Optimizer',		//Sub-menu title
		'manage_options',		//Security
		__FILE__,			//File to open
		'ewww_image_optimizer_options'	//Function to call
	);
}

// adds a link on the Plugins page for the EWWW IO settings
function ewww_image_optimizer_settings_link($links) {
	// load the html for the settings link
	$settings_link = '<a href="options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php">Settings</a>';
	// load the settings link into the plugin links array
	array_unshift ( $links, $settings_link );
	// send back the plugin links array
	return $links;
}

// check for GD support of both PNG and JPG
function ewww_image_optimizer_gd_support() {
	if (function_exists('gd_info')) {
		$gd_support = gd_info();
		if (($gd_support["JPEG Support"] || $gd_support["JPG Support"]) && $gd_support["PNG Support"]) {
			return TRUE;
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}

// presents the bulk optimize function with the number of images, and runs it once they submit the button (most of the html is in bulk.php)
function ewww_image_optimizer_bulk_preview() {
	// initialize a few variables for the bulk operation
	$attachments = null;
	$auto_start = false;
	$skip_attachments = false;
	// get the value of the wordpress upload directory
	$upload_dir = wp_upload_dir();
	// set the location of our temporary status file
	$progress_file = $upload_dir['basedir'] . "/ewww.tmp";
	// check if the bulk operation was given any attachment IDs to work with
	if (isset($_REQUEST['ids'])) {
		// retrieve post information correlating to the IDs selected
		$attachments = get_posts( array(
			'numberposts' => -1,
			'include' => explode(',', $_REQUEST['ids']),
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
		));
		// tell the bulk optimizer to proceed without confirmation
		$auto_start = true;
	// check if the user asked us to resume a previous bulk operation
	} else if (isset($_REQUEST['resume'])) {
		// get the contents of the temp file
		$progress_contents = file($progress_file);
		// find out the last attachment that was optimized from the temp file
		$last_attachment = $progress_contents[0];
		// load the post info from the temp file into $attachments
		$attachments = unserialize($progress_contents[1]);
		// tell the bulk optimizer to proceed without confirmation
		$auto_start = true;
		// tell the optimizer to skip each attachment (until we tell it otherwise)
		$skip_attachments = true;
	} else {
		// load up all the attachments we can find
		$attachments = get_posts( array(
			'numberposts' => -1,
			'post_type' => 'attachment',
			'post_mime_type' => 'image'
		));
	}
	// prep $attachments for storing in a file
	$attach_ser = serialize($attachments);
	// require the file that does most of the work and the html
	require( dirname(__FILE__) . '/bulk.php' );
}

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
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
	// call the optimize from metadata function and store the resulting new metadata
	$new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_ID );
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
 * Process an image.
 *
 * Returns an array of the $file $results and $converted to tell us if an image changes formats.
 *
 * @param   string $file		Full absolute path to the image file
 * @param   int $gallery_type		1=wordpress, 2=nextgen, 3=flagallery
 * @param   boolean $converted		tells us if this is a resize and the full image was converted to a new format
 * @returns array
 */
function ewww_image_optimizer($file, $gallery_type, $converted, $resize) {
	// TODO: properly rename resizes the same as the full version, and avoid conflicts somehow
	// if 'nice' doesn't exist, set $nice to NULL, otherwise, set $nice to 'nice'
	if (exec('nice') === NULL) {
		$nice = NULL;
	} else {
		$nice = 'nice';
	}
	// check that the file exists
	if (FALSE === file_exists($file) || FALSE === is_file($file)) {
		// if the full-size image was converted, we are likely running into a duplicate resizes issue, so we just rename the resize
		// for a JPG, rename it to a PNG
	//	if (preg_match('/.jpe*g*$/i', $file)) {
	//		$newfile = preg_replace('/.jpe*g*$/i', '.png', $file);
		// for a PNG, rename to a JPG
	//	} elseif (preg_match('/.png$/i', $file)) {
	//		$newfile = preg_replace('/.png$/i', '.jpg', $file);
		// for a GIF, rename to a PNG
	//	} elseif (preg_match('/.gif$/i', $file)) {
	//		$newfile = preg_replace('/.gif$/i', '.png', $file);
	//	}
		// if we find a match with the $newfile
	//	if (TRUE === file_exists($newfile) && TRUE === is_file($newfile)) {
			// return the updated filename, and the appropriate message
	//		return array($newfile, __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted);
	//	} else {
			// tell the user we couldn't find the file
			$msg = sprintf(__("Could not find <span class='code'>%s</span>", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
			// send back the above message
			return array($file, $msg, $converted);
	//	}
	}

	// check that the file is writable
	if ( FALSE === is_writable($file) ) {
		// tell the user we can't write to the file
		$msg = sprintf(__("<span class='code'>%s</span> is not writable", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		// send back the above message
		return array($file, $msg, $converted);
	}
	// retrieve the wordpress upload directory location
	$upload_dir = wp_upload_dir();
	// do some cleanup on the upload location we retrieved
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	// see if the file path matches the upload directory
	$path_in_upload = stripos(realpath($file), realpath($upload_path));
	// see if the file path matches the location where wordpress is installed (for NextGEN and Grand FlaGallery)
	$path_in_wp = stripos(realpath($file), realpath(ABSPATH));
	// check that the file is within the WP uploads folder or the wordpress folder
	if (0 !== $path_in_upload && 0 !== $path_in_wp) {
		// tell the user they can only process images in the upload directory or the wordpress folder
		$msg = sprintf(__("<span class='code'>%s</span> must be within the wordpress or upload directory (<span class='code'>%s or %s</span>)", EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities($file), $upload_path, ABSPATH);
		// send back the above message
		return array($file, $msg, $converted);
	}
	// see if we can use the getimagesize function
	if (function_exists('getimagesize')) {
		// run getimagesize on the file
		$type = getimagesize($file);
		// make sure we have results
		if(false !== $type){
			// store the mime-type
			$type = $type['mime'];
		}
	// see if we can use mime_content_type if getimagesize isn't available
	} elseif (function_exists('mime_content_type')) {
		// retrieve and store the mime-type
		$type = mime_content_type($file);
	} else {
		//otherwise we store an error message since we couldn't get the mime-type
		$type = 'Missing getimagesize() and mime_content_type() PHP functions';
	}
	// get the utility paths
	list ($jpegtran_path, $optipng_path, $gifsicle_path, $pngout_path) = ewww_image_optimizer_path_check();
	// To skip binary checking, you can visit the EWWW Image Optimizer options page
	// if the user has disabled the utility checks
	if(get_option('ewww_image_optimizer_skip_check') == TRUE){
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
	// run the appropriate optimization/conversion for the mime-type
	switch($type) {
		case 'image/jpeg':
			// if jpg2png conversion is enabled, and this image is in the wordpress media library
			if (get_option('ewww_image_optimizer_jpg_to_png') && $gallery_type == 1) {
				// toggle the convert process to ON
				$convert = true;
				// generate the filename for a PNG
				$filename = preg_replace('/.jpe*g*$/i', '', $file);
				if (preg_match('/-\d+x\d+$/', $file, $fileresize)) {
					$filename = preg_replace('/-\d+x\d+$/', '', $filename);
					print_r ($fileresize);
					echo "<br>-------------------------------<br>";
				} else {
					$fileresize[0] = NULL;
				}
				$filenum = NULL;
				$fileext = '.png';
				while (file_exists($filename . $filenum . $fileresize[0] . $fileext)) {
					$filenum++;
				}
				$pngfile = $filename . $filenum . $fileresize[0] . $fileext;
			} else {
				// otherwise, set it to OFF
				$convert = false;
			}
			// use 'which' to make sure jpegtran is available
			$jpegtran_exists = trim(exec('which ' . $jpegtran_path));
			// if jpegtran optimization is disabled
			if (get_option('ewww_image_optimizer_disable_jpegtran')) {
				// store an appropriate message in $result
				$result = 'jpegtran is disabled';
				// set the optimization process to OFF
				$optimize = false;
			// otherwise, if we aren't skipping the utility verification and jpegtran doesn't exist
			} elseif (!$skip_jpegtran_check && empty($jpegtran_exists)){
				// store an appropriate message in $result
				$result = '<em>jpegtran</em> is missing';
				// set the optimization process to OFF
				$optimize = false;
			// otherwise, things should be good, so...
			} else {
				// set the optimization process to ON
				$optimize = true;
			}
			// get the original image size
			$orig_size = filesize($file);
			// initialize $new_size with the original size
			$new_size = $orig_size;
			// if the conversion process is turned ON, or if this is a resize and the full-size was converted
			if ($convert || $converted) {
				// if pngout isn't disabled
				if (!get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the pngout optimization level
					$pngout_level = get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the JPG to produce the PNG
					exec("$nice $pngout_path -s$pngout_level -q $file $pngfile");
				}
				// if optipng isn't disabled
				if (!get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optipng optimization level
					$optipng_level = get_option('ewww_image_optimizer_optipng_level');
					// if $pngfile exists (which means pngout was already run)
					if (file_exists($pngfile)) {
						// run optipng on the PNG file
						exec("$nice $optipng_path -o$optipng_level -quiet $pngfile");
					// otherwise, we need to use convert, since optipng doesn't do JPG conversion
					} else {
						// convert the JPG to  PNG (try with GD if possible, 'convert' if not)
						if (ewww_image_optimizer_gd_support()) {
							imagepng(imagecreatefromjpeg($file), $pngfile);
						} elseif (trim(exec('which convert'))) {
							exec("convert $file -strip $pngfile");
						}
						// then optimize the PNG with optipng
						exec("$nice $optipng_path -o$optipng_level -quiet $pngfile");
					}
				}
				// find out the size of the new PNG file
				$png_size = filesize($pngfile);
				// if the PNG is smaller than the original JPG, and we didn't end up with an empty file
				if ($orig_size > $png_size && $png_size != 0) {
					// successful conversion (for now)
					$converted = TRUE;
				} else {
					unlink ($pngfile);
				}
			// if conversion and optimization are both turned OFF, finish the JPG processing
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned ON
			if ($optimize) {
				// generate temporary file-names:
				$tempfile = $file . ".tmp"; //non-progressive jpeg
				$progfile = $file . ".prog"; // progressive jpeg
				// check to see if we are supposed to strip metadata (badly named)
				if(get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
					// don't copy metadata
					$copy_opt = 'none';
				} else {
					// copy all the metadata
					$copy_opt = 'all';
				}
				// run jpegtran - non-progressive
				// TODO: push the output into php, to avoid weird hacky stuff
				exec("$nice $jpegtran_path -copy $copy_opt -optimize $file > $tempfile");
				// run jpegtran - progressive
				exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive $file > $progfile");
				// check the filesize of the non-progressive JPG
				$non_size = filesize($tempfile);
				// check the filesize of the progressive JPG
				$prog_size = filesize($progfile);
				// if the progressive file is bigger
				if ($prog_size > $non_size) {
					// store the size of the non-progessive JPG
					$new_size = $non_size;
					// delete the progressive file
					unlink($progfile);
				// if the progressive file is smaller or the same
				} else {
					// store the size of the progressive JPG
					$new_size = $prog_size;
					// replace the non-progressive with the progressive file
					rename($progfile, $tempfile);
				}
				// if the best-optimized is smaller than the original JPG, and we didn't create an empty JPG
				if ($orig_size > $new_size && $new_size != 0) {
					// replace the original with the optimized file
					rename($tempfile, $file);
					// store the results of the optimization
					$result = "$orig_size vs. $new_size";
				// if the optimization didn't produce a smaller JPG
				} else {
					// delete the optimized file
					unlink($tempfile);
					// store the results
					$result = "unchanged";
				}
			}
			// if we generated a smaller PNG than the optimized JPG
			if ($converted && $new_size > $png_size) {
				// store the size of the converted PNG
				$new_size = $png_size;
				// check to see if the user wants the originals deleted
				if (get_option('ewww_image_optimizer_delete_originals') == TRUE) {
					// delete the original JPG
					unlink($file);
				}
				// store the location of the PNG file
				$file = $pngfile;
				// store the result of the conversion
				$result = "$orig_size vs. $new_size";
			// if the PNG was smaller than the original JPG, but bigger than the optimized JPG
			} elseif ($converted) {
				// unsuccessful conversion
				$converted = FALSE;
				// remove the converted PNG
				unlink($pngfile);
			}
			break;
		case 'image/png':
			// png2jpg conversion is turned on, and the image is in the wordpress media library
			if (get_option('ewww_image_optimizer_png_to_jpg') && $gallery_type == 1) {
				// turn the conversion process ON
				$convert = true;
				// generate the filename for a JPG
				$filename = preg_replace('/.png$/i', '', $file);
				$filenum = NULL;
				$fileext = '.jpg';
				while (file_exists($filename . $filenum . $fileext)) {
					$filenum++;
				}
				$jpgfile = $filename . $filenum . $fileext;
			} else {
				// turn the conversion process OFF
				$convert = false;
			}
			// use which to see if optipng is installed
			$optipng_exists = trim(exec('which ' . $optipng_path));
			// use pngout to see if pngout is installed
			$pngout_exists = trim(exec('which ' . $pngout_path));
			// if pngout and optipng are disabled
			if (get_option('ewww_image_optimizer_disable_optipng') && get_option('ewww_image_optimizer_disable_pngout')) {
				// tell the user all PNG tools are disabled
				$result = 'png tools are disabled';
				// turn off optimization
				$optimize = false;
			// if the utility checking is on, optipng is enabled, but optipng cannot be found
			} elseif (!$skip_optipng_check && empty($optipng_exists) && !get_option('ewww_image_optimizer_disable_optipng')) {
				// tell the user optipng is missing
				$result = '<em>optipng</em> is missing';
				// turn off optimization
				$optimize = false;
			// if the utility checking is on, pngout is enabled, but pngout cannot be found
			} elseif (!$skip_pngout_check && empty($pngout_exists) && !get_option('ewww_image_optimizer_disable_pngout')) {
				// tell the user pngout is missing
				$result = '<em>pngout</em> is missing';
				// turn off optimization
				$optimize = false;
			} else {
				// turn optimization on if we made it through all the checks
				$optimize = true;
			}
			// retrieve the filesize of the original image
			$orig_size = filesize($file);
			// if conversion is on and the PNG doesn't have transparency or the user set a background color to replace transparency, or this is a resize and the full-size image was converted
			if (($convert && (!ewww_image_optimizer_png_alpha($file) || ewww_image_optimizer_jpg_background())) || $converted) {
				// generate the name of the JPG
				//$jpgfile = substr_replace($file, 'jpg', -3);
				// if the user set a fill background for transparency
				if ($background = ewww_image_optimizer_jpg_background()) {
					// set background color for GD
					$r = '0x' . substr($background, 0, 2);
                                        $g = '0x' . substr($background, 2, 2);
					$b = '0x' . substr($background, 4, 2);
					// set the background flag for 'convert'
					$background = "-background " . '"' . "#$background" . '"';
				}
				// if the user manually set the JPG quality
				if ($quality = ewww_image_optimizer_jpg_quality()) {
					// set the quality for GD
					$gquality = $quality;
					// set the quality flag for 'convert'
					$cquality = "-quality $quality";
				}
				// convert the PNG to a JPG with all the proper options (try GD first, then 'convert')
				if (ewww_image_optimizer_gd_support()) {
					// retrieve the data from the PNG
					$input = imagecreatefrompng($file);
					// retrieve the dimensions of the PNG
					list($width, $height) = getimagesize($file);
					// create a new image with those dimensions
					$output = imagecreatetruecolor($width, $height);
					// if the red color is set
					if (isset($r)) {
						// allocate the background color
						$color = imagecolorallocate($output,  $r, $g, $b);
						// fill the new image with the background color 
						imagefilledrectangle($output, 0, 0, $width, $height, $color);
					}
					// copy the original image to the new image
					imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
					// if the JPG quality is set
					if (isset($gquality)) {
						// output the JPG with the quality setting
						imagejpeg($output, $jpgfile, $gquality);
					} else {
						// otherwise, output the JPG at quality 92
						imagejpeg($output, $jpgfile, 92);
					}
				} elseif (exec('which convert')) {
					exec ("convert $background -flatten $cquality $file $jpgfile");
				} 
				// retrieve the filesize of the new JPG
				$jpg_size = filesize($jpgfile);
				// next we need to optimize that JPG if jpegtran is enabled
				if (!get_option('ewww_image_optimizer_disable_jpegtran') && file_exists($jpgfile)) {
					// generate temporary file-names:
					$tempfile = $jpgfile . ".tmp"; //non-progressive jpeg
					$progfile = $jpgfile . ".prog"; // progressive jpeg
					// check to see if we are supposed to strip metadata (badly named)
					if(get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
						// don't copy metadata
						$copy_opt = 'none';
					} else {
						// copy all the metadata
						$copy_opt = 'all';
					}
					// run jpegtran - non-progressive
					exec("$nice $jpegtran_path -copy $copy_opt -optimize $jpgfile > $tempfile");
					// run jpegtran - progressive
					exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive $jpgfile > $progfile");
					// check the filesize of the non-progressive JPG
					$non_size = filesize($tempfile);
					// check the filesize of the progressive JPG
					$prog_size = filesize($progfile);
					// if the progressive file is bigger
					if ($prog_size > $non_size) {
						// store the size of the non-progessive JPG
						$opt_jpg_size = $non_size;
						// delete the progressive file
						unlink($progfile);
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
					} else {
						// delete the optimized file
						unlink($tempfile);
					}
				}
				// if the new JPG is smaller than the original PNG
				if ($orig_size > $jpg_size && $jpg_size != 0) {
					// successful conversion (for now)
					$converted = TRUE;
				} else {
					unlink ($jpgfile);
				}
			// if conversion and optimization are both disabled we are done here
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned on
			if ($optimize) {
				// if pngout is enabled
				if(!get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the optimization level for pngout
					$pngout_level = get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the PNG file
					exec("$nice $pngout_path -s$pngout_level -q $file");
				}
				// if optipng is enabled
				if(!get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optimization level for optipng
					$optipng_level = get_option('ewww_image_optimizer_optipng_level');
					// run optipng on the PNG file
					exec("$nice $optipng_path -o$optipng_level -quiet $file");
				}
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
				if (get_option('ewww_image_optimizer_delete_originals') == TRUE) {
					// delete the original PNG
					unlink($file);
				}
				// update the $file location to the new JPG
				$file = $jpgfile;
			// if the converted JPG was smaller than the original, but larger than the optimized PNG
			} elseif ($converted) {
				// unsuccessful conversion
				$converted = FALSE;
				// delete the resulting JPG
				unlink($jpgfile);
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
			if (get_option('ewww_image_optimizer_gif_to_png') && $gallery_type == 1) {
				// turn conversion ON
				$convert = true;
				// generate the filename for a PNG
				$filename = preg_replace('/.gif$/i', '', $file);
				$filenum = NULL;
				$fileext = '.png';
				while (file_exists($filename . $filenum . $fileext)) {
					$filenum++;
				}
				$pngfile = $filename . $filenum . $fileext;
			} else {
				// turn conversion OFF
				$convert = false;
			}
			// use which to see if gifsicle is installed
			$gifsicle_exists = trim(exec('which ' . $gifsicle_path));
			// if gifsicle is disabled
			if (get_option('ewww_image_optimizer_disable_gifsicle')) {
				// return an appropriate message
				$result = 'gifsicle is disabled';
				// turn optimization off
				$optimize = false;
			// if utility checking is on, and gifsicle is not installed
			} elseif (!$skip_gifsicle_check && empty($gifsicle_exists)) {
				// return an appropriate message
				$result = '<em>gifsicle</em> is missing';
				// turn optimization off
				$optimize = false;
			} else {
				// otherwise, turn optimization ON
				$optimize = true;
			}
			// retrieve the filesize of the original file
			$orig_size = filesize($file);
			// if conversion is ON, the GIF isn't animated, or this is a resize and the full-size image was converted
			if (($convert && !ewww_image_optimizer_is_animated($file)) || $converted) {
				// if pngout is enabled
				if (!get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the pngout optimization level
					$pngout_level = get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the file
					exec("$nice $pngout_path -s$pngout_level -q $file $pngfile");
					// generate the filename of the new PNG
					//$pngfile = substr_replace($file, 'png', -3);
				}
				// if optipng is enabled
				if (!get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optipng optimization level
					$optipng_level = get_option('ewww_image_optimizer_optipng_level');
					// if $pngfile exists (which means pngout was run already)
					if (file_exists($pngfile)) {
						// run optipng on the PNG file
						exec("$nice $optipng_path -o$optipng_level -quiet $pngfile");
					// otherwise, if pngout was not used
					} else {
						// run optipng on the GIF file
						exec("$nice $optipng_path -out $pngfile -o$optipng_level -quiet $file");
						// generate the filename of the new PNG
						//$pngfile = substr_replace($file, 'png', -3);
					}
				}
				// if a PNG file was created
				if (file_exists($pngfile)) {
					// retrieve the filesize of the PNG
					$png_size = filesize($pngfile);
					// if the new PNG is smaller than the original GIF
					if ($orig_size > $png_size && $png_size != 0) {
						// successful conversion (for now)
						$converted = TRUE;
					} else {
						unlink ($pngfile);
					}
				}
			// if conversion and optimization are both turned OFF, we are done here
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned ON
			if ($optimize) {
				// run gifsicle on the GIF
				exec("$nice $gifsicle_path -b -O3 --careful $file");
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
				if (get_option('ewww_image_optimizer_delete_originals') == TRUE) {
					// delete the original GIF
					unlink($file);
				}
				// update the $file location with the new PNG
				$file = $pngfile;
			// if the PNG was smaller than the original GIF, but bigger than the optimized GIF
			} elseif ($converted) {
				// unsuccessful conversion
				$converted = FALSE;
				// delete the resulting PNG
				unlink($pngfile);
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
			return array($file, __('Unknown type: ' . $type, EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted);
	}
	// this is unnecessary
	//$result = str_replace($file . ': ', '', $result);
	// if the image is unchanged
	if($result == 'unchanged') {
		// tell the user we couldn't save them anything
		return array($file, __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted);
	}
	// if the image changed
	if(strpos($result, ' vs. ') !== false) {
		// strip and split $result where it says ' vs. '
		$s = explode(' vs. ', $result);
		// calculate how much space was saved
		$savings = intval($s[0]) - intval($s[1]);
		// convert it to human readable format
		$savings_str = ewww_image_optimizer_format_bytes($savings, 1);
		// replace spaces with proper html entity encoding
		$savings_str = str_replace(' ', '&nbsp;', $savings_str);
		// determine the percentage savings
		$percent = 100 - (100 * ($s[1] / $s[0]));
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf(__("Reduced by %01.1f%% (%s)", EWWW_IMAGE_OPTIMIZER_DOMAIN),
			$percent,
			$savings_str);
		// send back the filename, the results, and the $converted flag
		return array($file, $results_msg, $converted);
	}
	// otherwise, send back the filename, the results (some sort of error message), and the $converted flag
	return array($file, $result, $converted);
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
	// get the filepath from the metadata
	$file_path = $meta['file'];
	// store absolute paths for older wordpress versions
	$store_absolute_path = true;
	// retrieve the location of the wordpress upload folder
	$upload_dir = wp_upload_dir();
	// retrieve the path of the upload folder
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	// TODO: determine if this is really necessary, since we only claim to support 2.9 or better
	// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
	// if the wp content folder is not contained in the file path
	if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
		// don't store absolute paths
		$store_absolute_path = false;
		// generate the absolute path
		$file_path =  $upload_path . $file_path;
	}
	// run the image optimizer on the file, and store the results
	list($file, $msg, $conv) = ewww_image_optimizer($file_path, 1, false, false);
	// if the file was converted
	if ($conv) {
		// if we don't already have the update attachment filter
		if ( FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment') )
			// add the update attachment filter
			add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
		// adding some metadata to allow us to revert conversions down the road
		// store the conversion status in the metadata
		$meta['converted'] = 1;
		// store the old filename in the database
		$meta['orig_file'] = $file;
	}
	// update the filename in the metadata
	$meta['file'] = $file;
	// update the optimization results in the metadata
	$meta['ewww_image_optimizer'] = $msg;
	// strip absolute path for Wordpress >= 2.6.2
	if ( FALSE === $store_absolute_path ) {
		$meta['file'] = str_replace($upload_path, '', $meta['file']);
	}
	// no resized versions, so we can exit
	if ( !isset($meta['sizes']) )
		return $meta;

	// meta sizes don't contain a path, so we calculate one
	$base_dir = dirname($file_path) . '/';
	// process each resized version
	foreach($meta['sizes'] as $size => $data) {
		//print_r ($data);
		//echo "<br>----------------------------------------------------------------------<br>";
		$dup_size = false;
		// don't try to re-convert an identical resize, just re-use the metadata
		foreach($processed as $proc => $scan) {
			// if a previous resize had identical dimensions
			if ($scan['height'] == $data['height'] && $scan['width'] == $data['width']) {
		//		echo "dup found: " . $data['file'] . "<br>";
				// found a duplicate resize
				$dup_size = true;
				// point this resize at the same image as the previous one
				$meta['sizes'][$size]['file'] = $meta['sizes'][$proc]['file'];
				// and tell the user we didn't do any further optimization
				$meta['sizes'][$size]['ewww_image_optimizer'] = 'No savings';
			}
		}
		if (!$dup_size) {
			// run the optimization and store the results
			list($optimized_file, $results, $resize_conv) = ewww_image_optimizer($base_dir . $data['file'], 1, $conv, true);
			// if the resize was converted, store the result and the original filename in the metadata for later recovery
			if ($resize_conv) {
				$meta['sizes'][$size]['converted'] = 1;
				$meta['sizes'][$size]['orig_file'] = $data['file'];
			}
			// update the filename
			$meta['sizes'][$size]['file'] = str_replace($base_dir, '', $optimized_file);
			// update the optimization results
			$meta['sizes'][$size]['ewww_image_optimizer'] = $results;
		}
		// store info on the sizes we've processed, so we can check the list for duplicate sizes
		$processed[$size]['width'] = $data['width'];
		$processed[$size]['height'] = $data['height'];
		//print_r ($processed);
		//echo "<br>----------------------------------------------------------------------<br>";
	}
	//	print_r ($meta);
	//	echo "<br>----------------------------------------------------------------------<br>";
	// send back the updated metadata
	return $meta;
}

/**
 * Update the attachment's meta data after being converted 
 */
function ewww_image_optimizer_update_attachment($data, $ID) {
	// retrieve the original filename based on the $ID
	$orig_file = get_attached_file($ID);
	$new_file = basename($data['file']);
//	echo "$new_file";
	// update the file location in the post metadata based on the new path stored in the attachment metadata
	update_attached_file($ID, $data['file']);
	// retrieve the post information based on the $ID
//	echo "<br>----------------------------------------------------------------------<br>";
	$post = get_post($ID);
//	print_r ($post);
	// if the original image was a JPG
	$guid = dirname($post->guid) . "/" . basename($data['file']);
	if (preg_match('/.jpg$/i', basename($data['file']))) {
		// update the guid to reference the new PNG
		//$guid = preg_replace('/.jpe*g*$/i', '.png', $post->guid);
		// set the mimetype to PNG
		$mime = 'image/jpg';
	}
	// if the original image was a PNG
	if (preg_match('/.png$/i', basename($data['file']))) {
		// update the guid to reference the new JPG
		//$guid = preg_replace('/.png$/i', '.jpg', $post->guid);
		// set the mimetype to JPG
		$mime = 'image/png';
	}
	// if the original image was a GIF
//	if (preg_match('/.gif$/i', $post->guid)) {
		// update the guid to reference the new PNG
//		$guid = preg_replace('/.gif$/i', '.png', $post->guid);
		// set the mimetype to PNG
//		$mime = 'image/png';
//	}
	// update the attachment post with the new mimetype and guid
	wp_update_post( array('ID' => $ID,
			      'post_mime_type' => $mime,
			      'guid' => $guid) );
	// temp code
//	$post = get_post($ID);
//	print_r ($post);
//	echo "<br>----------------------------------------------------------------------<br>";
	return $data;
}

/**
 * Check the submitted PNG to see if it has transparency
 */
function ewww_image_optimizer_png_alpha ($filename){
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
	$defaults['ewww-image-optimizer'] = 'Image Optimizer';
	return $defaults;
}

/**
 * Return the filesize in a humanly readable format.
 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
 */
function ewww_image_optimizer_format_bytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Print column data for optimizer results in the media library using
 * the `manage_media_custom_column` hook.
 */
function ewww_image_optimizer_custom_column($column_name, $id) {
	// once we get to the EWWW IO custom column
	if( $column_name == 'ewww-image-optimizer' ) {
		// retrieve the metadata
		$data = wp_get_attachment_metadata($id);
		// if the filepath isn't set in the metadata (which happens sometimes, oddly)
		if(!isset($data['file'])){
			$msg = '<br>Metadata is missing file path.';
			print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
			return;
		}
		// retrieve the filepath from the metadata
		$file_path = $data['file'];
		// retrieve the wordpress upload folder
		$upload_dir = wp_upload_dir();
		// retrieve the wordpress upload folder path
		$upload_path = trailingslashit( $upload_dir['basedir'] );
		// TODO: again, is this really necessary?
		// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
		// if $file_path isn't an absolute path
		if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
			// find the absolute path
			$file_path =  $upload_path . $file_path;
		}
		// initialize $msg
		$msg = '';
		
		if(function_exists('getimagesize')){
			// run getimagesize on the file
			$type = getimagesize($file_path);
			// if we were successful
			if(false !== $type){
				// store the mimetype
				$type = $type['mime'];
			}
		} elseif(function_exists('mime_content_type')) {
			// use mime_content_type to get the mimetype
			$type = mime_content_type($file_path);
		} else {
			$type = false;
			$msg = '<br>getimagesize() and mime_content_type() PHP functions are missing';
		}
		// get a human readable filesize
		$file_size = ewww_image_optimizer_format_bytes(filesize($file_path));
		// initialize $valid
		$valid = true;
		// run the appropriate code based on the mimetype
		switch($type) {
			case 'image/jpeg':
				// if jpegtran is missing, tell them that
				if(EWWW_IMAGE_OPTIMIZER_JPEGTRAN == false) {
					$valid = false;
					$msg = '<br>' . __('<em>jpegtran</em> is missing');
				}
				break; 
			case 'image/png':
				// if pngout and optipng are missing, tell the user
				if(EWWW_IMAGE_OPTIMIZER_PNGOUT == false && EWWW_IMAGE_OPTIMIZER_OPTIPNG == false) {
					$valid = false;
					$msg = '<br>' . __('<em>optipng/pngout</em> is missing');
				}
				break;
			case 'image/gif':
				// if gifsicle is missing, tell the user
				if(EWWW_IMAGE_OPTIMIZER_GIFSICLE == false) {
					$valid = false;
					$msg = '<br>' . __('<em>gifsicle</em> is missing');
				}
				break;
			default:
				// not a supported mimetype
				$valid = false;
		}
		// if this is an unsupported filetype, tell the user we don't work with strangers
		if($valid == false) {
			print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
			return;
		}
		// if the optimizer metadata exists
		if ( isset($data['ewww_image_optimizer']) && !empty($data['ewww_image_optimizer']) ) {
			// output the optimizer results
			print $data['ewww_image_optimizer'];
			// output the filesize
			print "<br>Image Size: $file_size";
			// output a link to re-optimize manually
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		} else {
			// otherwise, this must be an image we haven't processed
			print __('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			// tell them the filesize
			print "<br>Image Size: $file_size";
			// and give the user the option to optimize the image right now
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		}
	}
}

// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
// adds a bulk optimize action to the drop-down on the media library page
function ewww_image_optimizer_add_bulk_actions_via_javascript() { ?> 
	<script type="text/javascript"> 
		jQuery(document).ready(function($){ 
			$('select[name^="action"] option:last-child').before('<option value="bulk_optimize">Bulk Optimize</option>'); 
		}); 
	</script>
<?php } 

// Handles the bulk actions POST 
// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ 
function ewww_image_optimizer_bulk_action_handler() { 
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
	// Can't use wp_nonce_url() as it escapes HTML entities, call the optimizer with the $ids selected
	wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'ewww-image-optimizer-bulk' ), admin_url( 'upload.php?page=ewww-image-optimizer-bulk&goback=1&ids=' . $ids ) ) ); 
	exit(); 
}

// retrieves the file located at $url and stores it at $path, used for our one-click installs
// buffered so as not to use so much memory
// from http://stackoverflow.com/questions/3938534/download-file-to-server-from-url
function ewww_image_optimizer_download_file ($url, $path) {
	$newfname = $path;
	$file = fopen($url, "rb");
	if ($file) {
		$newf = fopen ($newfname, "wb");
		if ($newf) {
			while(!feof($file)) {
				fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
			}
		}
	}
	if ($file) {
		fclose($file);
	}
	if ($newf) {
		fclose($newf);
	}
}

// retrieves the jpegtran linux package with wget, unpacks it with tar, and sends the user back where they came from
function ewww_image_optimizer_install_jpegtran() {
	if ( FALSE === current_user_can('install_plugins') ) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	ewww_image_optimizer_download_file("http://jpegclub.org/droppatch.v09.tar.gz", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "jpegtran.tar.gz");
	exec ("tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "jpegtran.tar.gz -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

// retrieves the pngout linux package with wget, unpacks it with tar, 
// copies the appropriate version to the plugin folder, and sends the user back where they came from
function ewww_image_optimizer_install_pngout() {
	if ( FALSE === current_user_can('install_plugins') ) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	ewww_image_optimizer_download_file("http://static.jonof.id.au/dl/kenutils/pngout-20120530-linux-static.tar.gz", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static.tar.gz");
	exec ("tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static.tar.gz -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
	$arch_type = $_REQUEST['arch'];
	switch ($arch_type) {
		case 'i386':
			copy(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/i386/pngout-static", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static");
			break;
		case 'i686':
			copy(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/i686/pngout-static", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static");
			break;
		case 'athlon':
			copy(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/athlon/pngout-static", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static");
			break;
		case 'pentium4':
			copy(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/pentium4/pngout-static", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static");
			break;
		case 'x64':
			copy(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/x86_64/pngout-static", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static");
			break;
	}
	chmod(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static", 0755);
	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

// retrieves the optipng linux package with wget, unpacks it with tar, and sends the user back where they came from
function ewww_image_optimizer_install_optipng() {
	if ( FALSE === current_user_can('install_plugins') ) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	ewww_image_optimizer_download_file("http://shanebishop.net/uploads/optipng.tar.gz", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "optipng.tar.gz");
	exec ("tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "optipng.tar.gz -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

// retrieves the gifsicle linux package with wget, unpacks it with tar, and sends the user back where they came from
function ewww_image_optimizer_install_gifsicle() {
	if ( FALSE === current_user_can('install_plugins') ) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	ewww_image_optimizer_download_file("http://shanebishop.net/uploads/gifsicle.tar.gz", EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "gifsicle.tar.gz");
	exec ("tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "gifsicle.tar.gz -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

// displays the EWWW IO options and provides one-click install for the optimizer utilities
function ewww_image_optimizer_options () {
	list ($jpegtran_path, $optipng_path, $gifsicle_path) = ewww_image_optimizer_path_check();
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>EWWW Image Optimizer Settings</h2>
		<p><a href="http://shanebishop.net/ewww-image-optimizer/">Plugin Home Page</a> |
		<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/">Installation Instructions</a> | 
		<a href="http://wordpress.org/support/plugin/ewww-image-optimizer">Plugin Support</a></p>
		<div id="right_panel" style="float: right">
		<div id="poll" style="margin: 8px"><script type="text/javascript" charset="utf-8" src="http://static.polldaddy.com/p/6602406.js"></script>
		<noscript><a href="http://polldaddy.com/poll/6602406/">EWWW IO Feedback</a></noscript></div>
		<div id="debug" style="border: 1px solid #ccc; padding: 0 8px; margin: 8px; width: 284px; border-radius: 12px;">
			<h3>Debug information</h3>
			<div style="border-top: 1px solid #e8e8e8; padding: 10px 0"><p><!--computed jpegtran path: <?php echo $jpegtran_path; ?><br />
			jpegtran location (using 'which'): <?php echo trim(exec('which ' . $jpegtran_path)); ?><br />-->
			jpegtran version: <?php exec($jpegtran_path . ' -v ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'sample.jpg 2>&1', $jpegtran_version); foreach ($jpegtran_version as $jout) { if (preg_match('/Independent JPEG Group/', $jout)) { echo $jout; } } ?><br />
			<!--computed optipng path: <?php echo $optipng_path; ?><br />
			optipng location (using 'which'): <?php echo trim(exec('which ' . $optipng_path)); ?><br />-->
			optipng version: <?php exec($optipng_path . ' -v', $optipng_version); echo $optipng_version[0]; ?><br />
			<!--computed gifsicle path: <?php echo $gifsicle_path; ?><br />
			gifsicle location (using 'which'): <?php echo trim(exec('which ' . $gifsicle_path)); ?><br />-->
			gifsicle version: <?php exec($gifsicle_path . ' --version', $gifsicle_version); echo $gifsicle_version[0]; ?><br />
			<?php if (ewww_image_optimizer_gd_support()) {
				echo "GD: OK<br>";
			} else {
				echo "GD: no good<br>";
			} ?>
			Imagemagick convert installed: <?php if (trim(exec('which convert'))) { echo "YES"; } else { echo "NO"; } ?><br />
			<?php if( ini_get('safe_mode') ){
				echo "safe mode: On<br />";
			} else {
				echo "safe mode: Off<br />";
			}
			echo "Operating System: " . PHP_OS . "<br>";
			$disabled = explode(', ', ini_get('disable_functions'));
			if(in_array('exec', $disabled)){
				echo "exec(): disabled<br>";
			} else {
				echo "exec(): enabled<br>";
			}
			if(function_exists('getimagesize')){
				echo "getimagesize(): OK<br>";
			} else {
				echo "getimagesize(): missing<br>";
			}
			if(function_exists('mime_content_type')){
				echo "mime_content_type(): OK<br>";
			} else {
				echo "mime_content_type(): missing<br>";
			}
			?></p></div>
		</div>
		</div>
		<h3>Installation</h3>
		<p><b>Install jpegtran</b> - <a href="admin.php?action=ewww_image_optimizer_install_jpegtran">automatically</a> | <a href="http://jpegclub.org/droppatch.v09.tar.gz">manually</a><br />
		<a href="http://www.lcdf.org/gifsicle/gifsicle-1.67.tar.gz"><b>Download gifsicle source</b></a><br />
		<a href="http://prdownloads.sourceforge.net/optipng/optipng-0.7.4.tar.gz?download"><b>Download optipng source</b></a><br>
		<b>Install pngout</b> - Click the link below that corresponds to the architecture of your server to automatically install pngout. If in doubt, try the i386 or ask your webhost. Pngout is free closed-source software that can produce drastically reduced filesizes for PNGs, but can be very time consuming to process images<br />
<a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=i386">i386</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=athlon">athlon</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=pentium4">pentium4</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=i686">i686</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=x64">64-bit</a></p>
		<form method="post" action="options.php">
			<?php settings_fields('ewww_image_optimizer_options'); ?>
			<h3>General Settings</h3>
			<p>The plugin performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, and gifsicle. In some rare cases, these checks may erroneously report that you are missing the required utilities even though you have them installed.</p>
			<p><b>Do you want to skip the utils check?</b> <i>*Only do this if you are SURE that you have the utilities installed, or you don't care about the missing ones. Checking this option also bypasses our basic security checks on the paths entered below.</i><br />
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_skip_check">Skip utils check</label></th><td><input type="checkbox" id="ewww_image_optimizer_skip_check" name="ewww_image_optimizer_skip_check" value="true" <?php if (get_option('ewww_image_optimizer_skip_check') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_jpegtran">disable jpegtran</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_jpegtran" name="ewww_image_optimizer_disable_jpegtran" <?php if (get_option('ewww_image_optimizer_disable_jpegtran') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_optipng">disable optipng</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_optipng" name="ewww_image_optimizer_disable_optipng" <?php if (get_option('ewww_image_optimizer_disable_optipng') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_pngout">disable pngout</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_pngout" name="ewww_image_optimizer_disable_pngout" <?php if (get_option('ewww_image_optimizer_disable_pngout') == TRUE) { ?>checked="true"<?php } ?> /></td><tr>
				<tr><th><label for="ewww_image_optimizer_disable_gifsicle">disable gifsicle</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_gifsicle" name="ewww_image_optimizer_disable_gifsicle" <?php if (get_option('ewww_image_optimizer_disable_gifsicle') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
			</table>
			<h3>Path Settings</h3>
			<p><b>*Deprecated</b>: just drop the binaries in the ewww-image-optimizer plugin folder, and off you go:<br />
			<i><?php echo EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH; ?></i><br />
			If you are on shared hosting, and have installed the utilities in your home folder, you can provide the paths below.</p>
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_jpegtran_path">jpegtran path</label></th><td><input type="text" style="width: 400px" id="ewww_image_optimizer_jpegtran_path" name="ewww_image_optimizer_jpegtran_path" value="<?php echo get_option('ewww_image_optimizer_jpegtran_path'); ?>" /></td></tr>
				<tr><th><label for="ewww_image_optimizer_optipng_path">optipng path</label></th><td><input type="text" style="width: 400px" id="ewww_image_optimizer_optipng_path" name="ewww_image_optimizer_optipng_path" value="<?php echo get_option('ewww_image_optimizer_optipng_path'); ?>" /></td></tr>
				<tr><th><label for="ewww_image_optimizer_gifsicle_path">gifsicle path</label></th><td><input type="text" style="width: 400px" id="ewww_image_optimizer_gifsicle_path" name="ewww_image_optimizer_gifsicle_path" value="<?php echo get_option('ewww_image_optimizer_gifsicle_path'); ?>" /></td></tr>
			</table>
			<h3>Conversion Settings</h3>
			<p>Conversion settings do not apply to NextGEN gallery.</p>
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_delete_originals">Delete originals</label></th><td><input type="checkbox" id="ewww_image_optimizer_delete_originals" name="ewww_image_optimizer_delete_originals" <?php if (get_option('ewww_image_optimizer_delete_originals') == TRUE) { ?>checked="true"<?php } ?> /> This will remove the original image from the server after a successful conversion.</td></tr>
				<tr><th><label for="ewww_image_optimizer_jpg_to_png">enable <b>JPG</b> to <b>PNG</b> conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_jpg_to_png" name="ewww_image_optimizer_jpg_to_png" <?php if (get_option('ewww_image_optimizer_jpg_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <b>WARNING:</b> Removes metadata! Requires GD support in PHP, 'convert' from ImageMagick, or 'pngout' and should be used sparingly. PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.</td></tr>
				<tr><th><label for="ewww_image_optimizer_png_to_jpg">enable <b>PNG</b> to <b>JPG</b> conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_png_to_jpg" name="ewww_image_optimizer_png_to_jpg" <?php if (get_option('ewww_image_optimizer_png_to_jpg') == TRUE) { ?>checked="true"<?php } ?> /> <b>WARNING:</b> This is not a lossless conversion and requires the 'convert' utility provided by ImageMagick. JPG is generally much better than PNG for photographic use because it compresses the image and discards data. JPG does not support transparency, so we don't convert PNGs with transparency.</td></tr>
				<tr><th><label for="ewww_image_optimizer_jpg_background">JPG background color</label></th><td>#<input type="text" id="ewww_image_optimizer_jpg_background" name="ewww_image_optimizer_jpg_background" style="width: 60px" value="<?php echo ewww_image_optimizer_jpg_background(); ?>" /> <span style="padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #<? echo ewww_image_optimizer_jpg_background(); ?>">&nbsp;</span> HEX format (#123def). This is used only if the PNG has transparency or leave it blank to skip PNGs with transparency.</td></tr>
				<tr><th><label for="ewww_image_optimizer_jpg_quality">JPG quality level</label></th><td><input type="text" id="ewww_image_optimizer_jpg_quality" name="ewww_image_optimizer_jpg_quality" style="width: 40px" value="<?php echo ewww_image_optimizer_jpg_quality(); ?>" /> Valid values are 1-100. If left blank, the conversion process will attempt to set the optimal quality level or default to 92. Remember, this is a lossy conversion, so you are losing pixels, and it is not recommended to actually set the level here unless you want noticable loss of image quality.</td></tr>
				<tr><th><label for="ewww_image_optimizer_gif_to_png">enable <b>GIF</b> to <b>PNG</b> conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_gif_to_png" name="ewww_image_optimizer_gif_to_png" <?php if (get_option('ewww_image_optimizer_gif_to_png') == TRUE) { ?>checked="true"<?php } ?> /> PNG is generally much better than GIF, but doesn't support animated images, so we don't convert those.</td></tr>
			</table>
			<h3>Advanced options</h3>
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_jpegtran_copy">Remove JPG metadata</label></th><td><input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (get_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> This wil remove ALL metadata (EXIF and comments)</td></tr>
				<tr><th><label for="ewww_image_optimizer_optipng_level">optipng optimization level</label></th>
				<td><select id="ewww_image_optimizer_optipng_level" name="ewww_image_optimizer_optipng_level">
				<option value="1"<?php if (get_option('ewww_image_optimizer_optipng_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: 1 trial</option>
				<option value="2"<?php if (get_option('ewww_image_optimizer_optipng_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: 8 trials</option>
				<option value="3"<?php if (get_option('ewww_image_optimizer_optipng_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: 16 trials</option>
				<option value="4"<?php if (get_option('ewww_image_optimizer_optipng_level') == 4) { echo ' selected="selected"'; } ?>>Level 4: 24 trials</option>
				<option value="5"<?php if (get_option('ewww_image_optimizer_optipng_level') == 5) { echo ' selected="selected"'; } ?>>Level 5: 48 trials</option>
				<option value="6"<?php if (get_option('ewww_image_optimizer_optipng_level') == 6) { echo ' selected="selected"'; } ?>>Level 6: 120 trials</option>
				<option value="7"<?php if (get_option('ewww_image_optimizer_optipng_level') == 7) { echo ' selected="selected"'; } ?>>Level 7: 240 trials</option>
				</select> (default=2) - <i>According to the author of optipng, 10 trials should satisfy most people, 30 trials should satisfy everyone.</i></td></tr>
				<tr><th><label for="ewww_image_optimizer_pngout_level">pngout optimization level</label></th>
				<td><select id="ewww_image_optimizer_pngout_level" name="ewww_image_optimizer_pngout_level">
				<option value="0"<?php if (get_option('ewww_image_optimizer_pngout_level') == 0) { echo ' selected="selected"'; } ?>>Level 0: Xtreme! (Slowest)</option>
				<option value="1"<?php if (get_option('ewww_image_optimizer_pngout_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: Intense (Slow)</option>
				<option value="2"<?php if (get_option('ewww_image_optimizer_pngout_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: Longest Match (Fast)</option>
				<option value="3"<?php if (get_option('ewww_image_optimizer_pngout_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: Huffman Only (Faster)</option>
			</select> (default=2) - <i>If you have CPU cycles to spare, go with level 0</i></td></tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>
		</form>
	</div>
	<?php
}

