<?php
/**
 * Integrate image optimizers into WordPress.
 * @version 1.4.4
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://www.shanebishop.net/ewww-image-optimizer/
Description: Reduce file sizes for images within WordPress including NextGEN Gallery and GRAND FlAGallery. Uses jpegtran, optipng/pngout, and gifsicle.
Author: Shane Bishop
Version: 1.4.4
Author URI: http://www.shanebishop.net/
License: GPLv3
*/
// TODO: internationalize plugin - if we get enough interest
/**
 * Constants
 */
define('EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww_image_optimizer');
// this is just the name of the plugin folder
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_DIR', dirname(plugin_basename(__FILE__)));
if (function_exists('plugin_dir_path')) {
	// this is the full system path to the plugin folder
	define('EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__) );
} else {
	define('EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', trailingslashit(dirname(__FILE__)));
}
// the folder where we install optimization tools
define('EWWW_IMAGE_OPTIMIZER_TOOL_PATH', WP_CONTENT_DIR . '/ewww/');
// initialize debug global
$ewww_debug = get_current_user() . '<br>';

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
// variable for plugin settings link
$plugin = plugin_basename ( __FILE__ );
add_filter("plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link');
add_filter('wp_save_image_editor_file', 'ewww_image_optimizer_save_image_editor_file', 10, 5);
add_action('manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2);
add_action('admin_init', 'ewww_image_optimizer_admin_init');
add_action('admin_action_ewww_image_optimizer_manual', 'ewww_image_optimizer_manual');
add_action('admin_action_ewww_image_optimizer_restore', 'ewww_image_optimizer_restore');
add_action('delete_attachment', 'ewww_image_optimizer_delete');
add_action('admin_menu', 'ewww_image_optimizer_admin_menu');
add_action('network_admin_menu', 'ewww_image_optimizer_network_admin_menu');
add_action('admin_head-upload.php', 'ewww_image_optimizer_add_bulk_actions_via_javascript'); 
add_action('admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler'); 
add_action('admin_action_-1', 'ewww_image_optimizer_bulk_action_handler'); 
add_action('admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout');
add_action('admin_enqueue_scripts', 'ewww_image_optimizer_media_scripts');
register_deactivation_hook(__FILE__, 'ewww_image_optimizer_network_deactivate');

/**
 * Check if this is an unsupported OS (not Linux or Mac OSX or FreeBSD or Windows or SunOS)
 */
if('Linux' != PHP_OS && 'Darwin' != PHP_OS && 'FreeBSD' != PHP_OS && 'WINNT' != PHP_OS && 'SunOS' != PHP_OS) {
	// call the function to display a notice
	add_action('network_admin_notices', 'ewww_image_optimizer_notice_os');
	add_action('admin_notices', 'ewww_image_optimizer_notice_os');
	// turn off all the tools
	define('EWWW_IMAGE_OPTIMIZER_PNGOUT', false);
	define('EWWW_IMAGE_OPTIMIZER_GIFSICLE', false);
	define('EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false);
	define('EWWW_IMAGE_OPTIMIZER_OPTIPNG', false);
} else {
	//Otherwise, we run the function to check for optimization utilities
	add_action('network_admin_notices', 'ewww_image_optimizer_notice_utils');
	add_action('admin_notices', 'ewww_image_optimizer_notice_utils');
} 
// need to include the plugin library for the is_plugin_active function (even though it isn't supposed to be necessary in the admin)
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
// include the file that loads the nextgen gallery optimization functions
if (is_plugin_active('nextgen-gallery/nggallery.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('nextgen-gallery/nggallery.php')))
require( dirname(__FILE__) . '/nextgen-integration.php' );
// include the file that loads the grand flagallery optimization functions
if (is_plugin_active('flash-album-gallery/flag.php') || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('flash-album-gallery/flag.php')))
require( dirname(__FILE__) . '/flag-integration.php' );

// tells the user they are on an unsupported operating system
function ewww_image_optimizer_notice_os() {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_notice_os()</b><br>";
	echo "<div id='ewww-image-optimizer-warning-os' class='error'><p><strong>EWWW Image Optimizer is supported on Linux, FreeBSD, Mac OSX, and Windows.</strong> Unfortunately, the EWWW Image Optimizer plugin doesn't work with " . htmlentities(PHP_OS) . ". Feel free to file a support request if you would like support for your operating system of choice.</p></div>";
}   

// lets the user know their network settings have been saved
function ewww_image_optimizer_network_settings_saved() {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_network_settings_saved()</b><br>";
	echo "<div id='ewww-image-optimizer-settings-saved' class='updated fade'><p><strong>Settings saved.</strong></p></div>";
}   

// checks the binary at $path against a list of valid md5sums
function ewww_image_optimizer_md5check($path) {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_md5check()</b><br>";
	$ewww_debug = "$ewww_debug $path: " . md5_file($path) . "<br>";
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
		//optipng
		'4eb91937291ce5038d0c68f5f2edbcfd',
		'899e3c569080a55bcc5de06a01c8e23a',
		'0467bd0c73473221d21afbc5275503e4',
		'293e26924a274c6185a06226619d8e02',
		//gifsicle
		'2384f770d307c42b9c1e53cdc8dd662d',
		'24fc5f33b33c0d11fb2e88f5a93949d0',
		'e4a14bce92755261fe21798c295d06db',
		'9ddef564fed446700a3a7303c39610a3',
		'aad47bafdb2bc8a9f0755f57f94d6eaf',
		'46360c01622ccb514e9e7ef1ac5398f0',
		'44273fad7b3fd1145bfcf35189648f66',
		'4568ef450ec9cd73bab55d661fb167ec',
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_mimetype()</b><br>";
	$ewww_debug = "$ewww_debug testing mimetype: $path <br>";
	if (function_exists('finfo_file') && defined('FILEINFO_MIME')) {
		// create a finfo resource
		$finfo = finfo_open(FILEINFO_MIME);
		// retrieve the mimetype
		$type = explode(';', finfo_file($finfo, $path));
		$type = $type[0];
		finfo_close($finfo);
		$ewww_debug = "$ewww_debug finfo_file: $type <br>";
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
		$ewww_debug = "$ewww_debug getimagesize: $type <br>";
	}
	// see if we can use mime_content_type
	if (empty($type) && function_exists('mime_content_type')) {
		// retrieve and store the mime-type
		$type = mime_content_type($path);
		$ewww_debug = "$ewww_debug mime_content_type: $type <br>";
	}
	// if nothing else has worked, try the 'file' command
	if ((empty($type) || $type != 'application/x-executable') && $case == 'b') {
		// find the 'file command'
		if (ewww_image_optimizer_tool_found('/usr/bin/file', 'f')) {
			$file = '/usr/bin/file';
		} elseif (ewww_image_optimizer_tool_found('file', 'f')) {
			$file = 'file';
		}
		// run 'file' on the file in question
		exec("$file $path", $filetype);
		$ewww_debug = "$ewww_debug file command: $filetype[0] <br>";
		// if we've found a proper binary
		if ((strpos($filetype[0], 'ELF') && strpos($filetype[0], 'executable')) || strpos($filetype[0], 'Mach-O universal binary')) {
			$type = 'application/x-executable';
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_tool_found()</b><br>";
	switch($tool) {
		case 'j': // jpegtran
			exec($path . ' -v ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'sample.jpg 2>&1', $jpegtran_version);
			if (!empty($jpegtran_version)) $ewww_debug = "$ewww_debug $path: $jpegtran_version[0]<br>";
			foreach ($jpegtran_version as $jout) { 
				if (preg_match('/Independent JPEG Group/', $jout)) {
					return $jout;
				}
			}
			break;
		case 'o': // optipng
			exec($path . ' -v', $optipng_version);
			if (!empty($optipng_version)) $ewww_debug = "$ewww_debug $path: $optipng_version[0]<br>";
			if (!empty($optipng_version) && strpos($optipng_version[0], 'OptiPNG') === 0) {
				return $optipng_version[0];
			}
			break;
		case 'g': // gifsicle
			exec($path . ' --version', $gifsicle_version);
			if (!empty($gifsicle_version)) $ewww_debug = "$ewww_debug $path: $gifsicle_version[0]<br>";
			if (!empty($gifsicle_version) && strpos($gifsicle_version[0], 'LCDF Gifsicle') === 0) {
				return $gifsicle_version[0];
			}
			break;
		case 'p': // pngout
			exec("$path 2>&1", $pngout_version);
			if (!empty($pngout_version)) $ewww_debug = "$ewww_debug $path: $pngout_version[0]<br>";
			if (!empty($pngout_version) && strpos($pngout_version[0], 'PNGOUT') === 0) {
				return $pngout_version[0];
			}
			break;
		case 'i': // ImageMagick
			exec("$path -version", $convert_version);
			if (!empty($convert_version)) $ewww_debug = "$ewww_debug $path: $convert_version[0]<br>";
			if (!empty($convert_version) && strpos($convert_version[0], 'ImageMagick')) {
				return $convert_version[0];
			}
			break;
		case 'f': // file
			exec("$path -v 2>&1", $file_version);
			if (!empty($file_version[1])) $ewww_debug = "$ewww_debug $path: $file_version[1]<br>";
			if (!empty($file_version[1]) && preg_match('/magic/', $file_version[1])) {
				return $file_version[0];
			}
			break;
		case 'n': // nice
			exec("$path 2>&1", $nice_output);
			if (isset($nice_output)) $ewww_debug = "$ewww_debug $path: $nice_output[0]<br>";
			if (isset($nice_output) && preg_match('/usage/', $nice_output[0])) {
				return TRUE;
			} elseif (isset($nice_output) && preg_match('/^\d+$/', $nice_output[0])) {
				return TRUE;
			}
			break;
		case 't': // tar
			exec("$path --version", $tar_version);
			if (!empty($tar_version[0])) $ewww_debug = "$ewww_debug $path: $tar_version[0]<br>";
			if (!empty($tar_version[0]) && preg_match('/bsdtar/', $tar_version[0])) {
				return $tar_version[0];
			} elseif (!empty($tar_version[0]) && preg_match('/GNU tar/i', $tar_version[0])) {
				return $tar_version[0];
			}
			break;
	}
	return FALSE;
}

// If the utitilites are in the content folder, we use that. Otherwise, we retrieve user specified paths or set defaults if all else fails. We also do a basic check to make sure we weren't given a malicious path.
function ewww_image_optimizer_path_check() {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_path_check()</b><br>";
	$jpegtran = false;
	$optipng = false;
	$gifsicle = false;
	$pngout = false;
	// for Windows, everything must be in the wp-content/ewww folder, so that is all we check (unless some bright spark figures out how to put them in their system path on Windows...)
	if ('WINNT' == PHP_OS) {
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe')) {
			$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe';
			$ewww_debug = "$ewww_debug found $jpt, testing...<br>";
			if (ewww_image_optimizer_tool_found($jpt, 'j') && ewww_image_optimizer_md5check($jpt)) {
				$jpegtran = $jpt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe')) {
			$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe';
			$ewww_debug = "$ewww_debug found $opt, testing...<br>";
			if (ewww_image_optimizer_tool_found($opt, 'o') && ewww_image_optimizer_md5check($opt)) {
				$optipng = $opt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe')) {
			$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe';
			$ewww_debug = "$ewww_debug found $gpt, testing...<br>";
			if (ewww_image_optimizer_tool_found($gpt, 'g') && ewww_image_optimizer_md5check($gpt)) {
				$gifsicle = $gpt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe')) {
			$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe';
			$ewww_debug = "$ewww_debug found $ppt, testing...<br>";
			if (ewww_image_optimizer_tool_found($ppt, 'p') && ewww_image_optimizer_md5check($ppt)) {
				$pngout = $ppt;
			}
		}
	} else {
	// check to see if the user has disabled using bundled binaries
	$use_system = get_site_option('ewww_image_optimizer_skip_bundle');
	// first check for the jpegtran binary in the ewww tool folder
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran') && !$use_system) {
		$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
		$ewww_debug = "$ewww_debug found $jpt, testing...<br>";
		if (ewww_image_optimizer_md5check($jpt) && ewww_image_optimizer_mimetype($jpt, 'b')) {
			if (ewww_image_optimizer_tool_found($jpt, 'j')) {
				$jpegtran = $jpt;
			}
		}
			
	}
	// if the standard jpegtran binary didn't work, see if the user custom compiled one and check that
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom') && !$jpegtran && !$use_system) {
		$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom';
		$ewww_debug = "$ewww_debug found $jpt, testing...<br>";
		if (filesize($jpt) > 15000 && ewww_image_optimizer_mimetype($jpt, 'b')) {
			if (ewww_image_optimizer_tool_found($jpt, 'j')) {
				$jpegtran = $jpt;
			}
		}
	}
	// if we still haven't found a usable binary, try a system-installed version
	if (!$jpegtran) {
		if (ewww_image_optimizer_tool_found('jpegtran', 'j')) {
			$jpegtran = 'jpegtran';
		} elseif (ewww_image_optimizer_tool_found('/usr/bin/jpegtran', 'j')) {
			$jpegtran = '/usr/bin/jpegtran';
		} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/jpegtran', 'j')) {
			$jpegtran = '/usr/local/bin/jpegtran';
		}
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng') && !$use_system) {
		$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng';
		$ewww_debug = "$ewww_debug found $opt, testing...<br>";
		if (ewww_image_optimizer_md5check($opt) && ewww_image_optimizer_mimetype($opt, 'b')) {
			if (ewww_image_optimizer_tool_found($opt, 'o')) {
				$optipng = $opt;
			}
		}
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') && !$optipng && !$use_system) {
		$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom';
		$ewww_debug = "$ewww_debug found $opt, testing...<br>";
		if (filesize($opt) > 15000 && ewww_image_optimizer_mimetype($opt, 'b')) {
			if (ewww_image_optimizer_tool_found($opt, 'o')) {
				$optipng = $opt;
			}
		}
	}
	if (!$optipng) {
		if (ewww_image_optimizer_tool_found('optipng', 'o')) {
			$optipng = 'optipng';
		} elseif (ewww_image_optimizer_tool_found('/usr/bin/optipng', 'o')) {
			$optipng = '/usr/bin/optipng';
		} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/optipng', 'o')) {
			$optipng = '/usr/local/bin/optipng';
		}
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle') && !$use_system) {
		$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle';
		$ewww_debug = "$ewww_debug found $gpt, testing...<br>";
		if (ewww_image_optimizer_md5check($gpt) && ewww_image_optimizer_mimetype($gpt, 'b')) {
			if (ewww_image_optimizer_tool_found($gpt, 'g')) {
				$gifsicle = $gpt;
			}
		}
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') && !$gifsicle && !$use_system) {
		$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom';
		$ewww_debug = "$ewww_debug found $gpt, testing...<br>";
		if (filesize($gpt) > 15000 && ewww_image_optimizer_mimetype($gpt, 'b')) {
			if (ewww_image_optimizer_tool_found($gpt, 'g')) {
				$gifsicle = $gpt;
			}
		}
	}
	if (!$gifsicle) {
		if (ewww_image_optimizer_tool_found('gifsicle', 'g')) {
			$gifsicle = 'gifsicle';
		} elseif (ewww_image_optimizer_tool_found('/usr/bin/gifsicle', 'g')) {
			$gifsicle = '/usr/bin/gifsicle';
		} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/gifsicle', 'g')) {
			$gifsicle = '/usr/local/bin/gifsicle';
		}
	}
	// pngout is special and has a dynamic and static binary to check
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static') && !$use_system) {
		$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static';
		$ewww_debug = "$ewww_debug found $ppt, testing...<br>";
		if (ewww_image_optimizer_md5check($ppt) && ewww_image_optimizer_mimetype($ppt, 'b')) {
			if (ewww_image_optimizer_tool_found($ppt, 'p')) {
				$pngout = $ppt;
			}
		}
	}
	if (!$pngout) {
		if (ewww_image_optimizer_tool_found('pngout-static', 'p')) {
			$pngout = 'pngout-static';
		} elseif (ewww_image_optimizer_tool_found('pngout', 'p')) {
			$pngout = 'pngout';
		} elseif (ewww_image_optimizer_tool_found('/usr/bin/pngout-static', 'p')) {
			$pngout = '/usr/bin/pngout-static';
		} elseif (ewww_image_optimizer_tool_found('/usr/bin/pngout', 'p')) {
			$pngout = '/usr/bin/pngout';
		} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/pngout-static', 'p')) {
			$pngout = '/usr/local/bin/pngout-static';
		} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/pngout', 'p')) {
			$pngout = '/usr/local/bin/pngout';
		}
	}
	}
	if ($jpegtran) $ewww_debug = "$ewww_debug using: $jpegtran<br>";
	if ($optipng) $ewww_debug = "$ewww_debug using: $optipng<br>";
	if ($gifsicle) $ewww_debug = "$ewww_debug using: $gifsicle<br>";
	if ($pngout) $ewww_debug = "$ewww_debug using: $pngout<br>";
	return array($jpegtran, $optipng, $gifsicle, $pngout);
}

// generates the source and destination paths for the executables that we bundle with the plugin based on the operating system
function ewww_image_optimizer_install_paths () {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_install_paths()</b><br>";
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
	$ewww_debug = "$ewww_debug generated paths:<br>$jpegtran_src<br>$optipng_src<br>$gifsicle_src<br>$jpegtran_dst<br>$optipng_dst<br>$gifsicle_dst<br>";
	return array($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst);
}

// installs the executables that are bundled with the plugin
function ewww_image_optimizer_install_tools () {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_install_tools()</b><br>";
	$ewww_debug = "$ewww_debug Checking/Installing tools in " . EWWW_IMAGE_OPTIMIZER_TOOL_PATH . "<br>";
	$toolfail = false;
	if (!is_dir(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) {
		$ewww_debug = "$ewww_debug Folder doesn't exist, creating...<br>";
		if (!mkdir(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) {
			echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>EWWW Image Optimizer couldn't create the tool folder: " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . ".</strong> Please adjust permissions or create the folder.</p></div>";
			$ewww_debug = "$ewww_debug Couldn't create folder<br>";
		}
	} else {
		$ewww_perms = substr(sprintf('%o', fileperms(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)), -4);
		$ewww_debug = "$ewww_debug wp-content/ewww permissions: $ewww_perms <br />";
	}
	list ($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst) = ewww_image_optimizer_install_paths();
	if (!file_exists($jpegtran_dst)) {
		$ewww_debug = "$ewww_debug jpegtran not found, installing<br>";
		if (!copy($jpegtran_src, $jpegtran_dst)) {
			$toolfail = true;
			$ewww_debug = "$ewww_debug Couldn't copy jpegtran<br>";
		}
	} else if (filesize($jpegtran_dst) != filesize($jpegtran_src)) {
		$ewww_debug = "$ewww_debug jpegtran found, different size, attempting to replace<br>";
		if (!copy($jpegtran_src, $jpegtran_dst)) {
			$toolfail = true;
			$ewww_debug = "$ewww_debug Couldn't copy jpegtran<br>";
		}
	}
	// install 32-bit jpegtran at jpegtran-custom for some weird 64-bit hosts
	$arch_type = php_uname('m');
	if (PHP_OS == 'Linux' && $arch_type == 'x86_64') {
		$ewww_debug = "$ewww_debug 64-bit linux detected while installing tools<br>";
		$jpegtran32_src = substr($jpegtran_src, 0, -2);
		$jpegtran32_dst = $jpegtran_dst . '-custom';
		if (!file_exists($jpegtran32_dst) || (ewww_image_optimizer_md5check($jpegtran32_dst) && filesize($jpegtran32_dst) != filesize($jpegtran32_src))) {
			$ewww_debug = "$ewww_debug copying $jpegtran32_src to $jpegtran32_dst<br>";
			if (!copy($jpegtran32_src, $jpegtran32_dst)) {
				// this isn't a fatal error, besides we'll see it in the debug if needed
				$ewww_debug = "$ewww_debug Couldn't copy 32-bit jpegtran to jpegtran-custom<br>";
			}
			$jpegtran32_perms = substr(sprintf('%o', fileperms($jpegtran32_dst)), -4);
			$ewww_debug = "$ewww_debug jpegtran-custom (32-bit) permissions: $jpegtran32_perms<br>";
			if ($jpegtran32_perms != '0755') {
				if (!chmod($jpegtran32_dst, 0755)) {
					//$toolfail = true;
					$ewww_debug = "$ewww_debug couldn't set jpegtran-custom permissions<br>";
				}
			}
		}
	}
	if (!file_exists($gifsicle_dst)) {
		$ewww_debug = "$ewww_debug gifsicle not found, installing<br>";
		if (!copy($gifsicle_src, $gifsicle_dst)) {
			$toolfail = true;
			$ewww_debug = "$ewww_debug Couldn't copy gifsicle<br>";
		}
	} else if (filesize($gifsicle_dst) != filesize($gifsicle_src)) {
		$ewww_debug = "$ewww_debug gifsicle found, different size, attempting to replace<br>";
		if (!copy($gifsicle_src, $gifsicle_dst)) {
			$toolfail = true;
			$ewww_debug = "$ewww_debug Couldn't copy gifsicle<br>";
		}
	}
	if (!file_exists($optipng_dst)) {
		$ewww_debug = "$ewww_debug optipng not found, installing<br>";
		if (!copy($optipng_src, $optipng_dst)) {
			$toolfail = true;
			$ewww_debug = "$ewww_debug Couldn't copy optipng<br>";
		}
	} else if (filesize($optipng_dst) != filesize($optipng_src)) {
		$ewww_debug = "$ewww_debug optipng found, different size, attempting to replace<br>";
		if (!copy($optipng_src, $optipng_dst)) {
			$toolfail = true;
			$ewww_debug = "$ewww_debug Couldn't copy optipng<br>";
		}
	}
	if (PHP_OS != 'WINNT') {
		$ewww_debug = "$ewww_debug Linux/UNIX style OS, checking permissions<br>";
		$jpegtran_perms = substr(sprintf('%o', fileperms($jpegtran_dst)), -4);
		$ewww_debug = "$ewww_debug jpegtran permissions: $jpegtran_perms<br>";
		if ($jpegtran_perms != '0755') {
			if (!chmod($jpegtran_dst, 0755)) {
				$toolfail = true;
				$ewww_debug = "$ewww_debug couldn't set jpegtran permissions<br>";
			}
		}
		$gifsicle_perms = substr(sprintf('%o', fileperms($gifsicle_dst)), -4);
		$ewww_debug = "$ewww_debug gifislce permissions: $gifsicle_perms<br>";
		if ($gifsicle_perms != '0755') {
			if (!chmod($gifsicle_dst, 0755)) {
				$toolfail = true;
				$ewww_debug = "$ewww_debug couldn't set gifsicle permissions<br>";
			}
		}
		$optipng_perms = substr(sprintf('%o', fileperms($optipng_dst)), -4);
		$ewww_debug = "$ewww_debug optipng permissions: $optipng_perms<br>";
		if ($optipng_perms != '0755') {
			if (!chmod($optipng_dst, 0755)) {
				$toolfail = true;
				$ewww_debug = "$ewww_debug couldn't set optipng permissions<br>";
			}
		}
	}
	if ($toolfail) {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>EWWW Image Optimizer couldn't install tools in " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . ".</strong> Please adjust permissions or create the folder. If you have installed the tools elsewhere on your system, check the option to 'Use system paths'. For more details, visit the <a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>Settings Page</a> or the <a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>Installation Instructions</a>.</p></div>";
	}
	$migrate_fail = false;
	if ($jpegtran_path = get_site_option('ewww_image_optimizer_jpegtran_path')) {
		$ewww_debug = "$ewww_debug found path setting for jpegtran, migrating<br>";
		if (file_exists($jpegtran_path)) {
			$ewww_debug = "$ewww_debug found custom jpegtran binary<br>";
			if (!copy($jpegtran_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom', 0755)) {
				$ewww_debug = "$ewww_debug unable to copy custom jpegtran binary or set permissions<br>";
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_jpegtran_path');
				$ewww_debug = "$ewww_debug migration successful, deleting path setting<br>";
			}
		}
	}
	if ($optipng_path = get_site_option('ewww_image_optimizer_optipng_path')) {
		$ewww_debug = "$ewww_debug found path setting for optipng, migrating<br>";
		if (file_exists($optipng_path)) {
			$ewww_debug = "$ewww_debug found custom optipng binary<br>";
			if (!copy($optipng_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom', 0755)) {
				$ewww_debug = "$ewww_debug unable to copy custom optipng binary or set permissions<br>";
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_optipng_path');
				$ewww_debug = "$ewww_debug migration successful, deleting path setting<br>";
			}
		}
	}
	if ($gifsicle_path = get_site_option('ewww_image_optimizer_gifsicle_path')) {
		$ewww_debug = "$ewww_debug found path setting for gifsicle, migrating<br>";
		if (file_exists($gifsicle_path)) {
			$ewww_debug = "$ewww_debug found custom gifsicle binary<br>";
			if (!copy($gifsicle_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom', 0755)) {
				$ewww_debug = "$ewww_debug unable to copy custom gifislce binary or set permissions<br>";
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_gifsicle_path');
				$ewww_debug = "$ewww_debug migration successful, deleting path setting<br>";
			}
		}
	}
	if ($migrate_fail) {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>EWWW Image Optimizer attempted to move your custom-built binaries to " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . " but the operation was unsuccessful.</strong> Please adjust permissions or create the folder.</p></div>";
	}
}
		
// we check for safe mode and exec, then also direct the user where to go if they don't have the tools installed
function ewww_image_optimizer_notice_utils() {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_notice_utils()</b><br>";
	// query the php settings for safe mode
	if( ini_get('safe_mode') ){
		// display a warning to the user
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p><strong>PHP's Safe Mode is turned on. This plugin cannot operate in safe mode.</strong></p></div>";
	}
	// make sure the bundled tools are installed
	if(!get_site_option('ewww_image_optimizer_skip_bundle')) {
		ewww_image_optimizer_install_tools ();
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
	if(get_site_option('ewww_image_optimizer_skip_check') == TRUE){
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
	if (get_site_option('ewww_image_optimizer_disable_jpegtran')) {
		$skip_jpegtran_check = true;
	}
	if (get_site_option('ewww_image_optimizer_disable_optipng')) {
		$skip_optipng_check = true;
	}
	if (get_site_option('ewww_image_optimizer_disable_gifsicle')) {
		$skip_gifsicle_check = true;
	}
	if (get_site_option('ewww_image_optimizer_disable_pngout')) {
		$skip_pngout_check = true;
	}
	// we are going to store our validation results in $missing
	$missing = array();
	// go through each of the required tools
	foreach($required as $key => $req){
		// if the tool wasn't found, add it to the $missing array if we are supposed to check the tool in question
		switch($key) {
			case 'JPEGTRAN':
				if (!$skip_jpegtran_check && $req === false) {
					$missing[] = 'jpegtran';
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $jpegtran_path);
				break; 
			case 'OPTIPNG':
				if (!$skip_optipng_check && $req === false) {
					$missing[] = 'optipng';
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $optipng_path);
				break;
			case 'GIFSICLE':
				if (!$skip_gifsicle_check && $req === false) {
					$missing[] = 'gifsicle';
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $gifsicle_path);
				break;
			case 'PNGOUT':
				if (!$skip_pngout_check && $req === false) {
					$missing[] = 'pngout';
				}
				define('EWWW_IMAGE_OPTIMIZER_' . $key, $pngout_path);
				break;
		}
	}
	// expand the missing utilities list for use in the error message
	$msg = implode(', ', $missing);
	// if there is a message, display the warning
	if(!empty($msg)){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated'><p><strong>EWWW Image Optimizer requires <a href='http://jpegclub.org/jpegtran/'>jpegtran</a>, <a href='http://optipng.sourceforge.net/'>optipng</a> or <a href='http://advsys.net/ken/utils.htm'>pngout</a>, and <a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>.</strong> You are missing: $msg. Please install via the <a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>Settings Page</a>. If the one-click install links don't work for you, try the <a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>Installation Instructions</a>.</p></div>";
	}

	// Check if exec is disabled
	$disabled = ini_get('disable_functions');
	if(preg_match('/[^_]exec/', $disabled)){
		//display a warning if exec() is disabled, can't run much of anything without it
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p><strong>EWWW Image Optimizer requires exec().</strong> Your system administrator has disabled this function.</p></div>";
	}
}

/**
 * Plugin admin initialization function
 */
function ewww_image_optimizer_admin_init() {
	load_plugin_textdomain(EWWW_IMAGE_OPTIMIZER_DOMAIN);
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
			//print_r($_POST);
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
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_background');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_convert_links');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_resume');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_attachments');
	// set a few defaults
	add_option('ewww_image_optimizer_disable_pngout', TRUE);
	add_option('ewww_image_optimizer_optipng_level', 2);
	add_option('ewww_image_optimizer_pngout_level', 2);
}

// removes the network settings when the plugin is deactivated
function ewww_image_optimizer_network_deactivate($network_wide) {
	if ($network_wide) {
		delete_site_option('ewww_image_optimizer_skip_check');
		delete_site_option('ewww_image_optimizer_skip_bundle');
		delete_site_option('ewww_image_optimizer_debug');
		delete_site_option('ewww_image_optimizer_jpegtran_copy');
		delete_site_option('ewww_image_optimizer_optipng_level');
		delete_site_option('ewww_image_optimizer_pngout_level');
		delete_site_option('ewww_image_optimizer_disable_jpegtran');
		delete_site_option('ewww_image_optimizer_disable_optipng');
		delete_site_option('ewww_image_optimizer_disable_gifsicle');
		delete_site_option('ewww_image_optimizer_disable_pngout');
		delete_site_option('ewww_image_optimizer_delete_originals');
		delete_site_option('ewww_image_optimizer_jpg_to_png');
		delete_site_option('ewww_image_optimizer_png_to_jpg');
		delete_site_option('ewww_image_optimizer_gif_to_png');
		delete_site_option('ewww_image_optimizer_jpg_background');
		delete_site_option('ewww_image_optimizer_jpg_quality');
		delete_site_option('ewww_image_optimizer_network_version');
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
	$ewww_bulk_page = add_media_page( 'Bulk Optimize', 'Bulk Optimize', 'edit_others_posts', 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview');
	add_action('admin_footer-' . $ewww_bulk_page, 'ewww_image_optimizer_debug');
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
}

function ewww_image_optimizer_media_scripts($hook) {
	if ($hook == 'upload.php') {
		wp_enqueue_script('jquery-ui-tooltip');
		global $wp_version;
		$my_version = $wp_version;
		$my_version = substr($my_version, 0, 3);
		if ($my_version < 3.6) {
			wp_enqueue_style('jquery-ui-tooltip', plugins_url('jquery-ui-1.10.1.custom.css', __FILE__));
		}
	}
}

// used to output any debug messages available
function ewww_image_optimizer_debug() {
	global $ewww_debug;
	if (get_site_option('ewww_image_optimizer_debug')) echo '<div style="background-color:#ffff99;position:relative;bottom:60px;padding:5px 20px 10px;margin:0 0 15px 146px"><h3>Debug Log</h3>' . $ewww_debug . '</div>';
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
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_gd_support()</b><br>";
	if (function_exists('gd_info')) {
		$gd_support = gd_info();
		$ewww_debug = "$ewww_debug GD found, supports: <br>"; 
		foreach ($gd_support as $supports => $supported) {
			 $ewww_debug = "$ewww_debug $supports: $supported<br>";
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

// Retrieves jpg background fill setting, or returns null for png2jpg conversions
function ewww_image_optimizer_jpg_background () {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_jpeg_background()</b><br>";
	// retrieve the user-supplied value for jpg background color
	$background = get_site_option('ewww_image_optimizer_jpg_background');
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_jpg_quality()</b><br>";
	// retrieve the user-supplied value for jpg quality
	$quality = get_site_option('ewww_image_optimizer_jpg_quality');
	// verify that the quality level is an integer, 1-100
	if (preg_match('/^(100|[1-9][0-9]?)$/',$quality)) {
		// send back the valid quality level
		return $quality;
	} else {
		// send back nothing
		return NULL;
	}
}

// require the file that does the bulk processing
require( dirname(__FILE__) . '/bulk.php' );

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_manual()</b><br>";
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
 * Manually restore a converted image
 */
function ewww_image_optimizer_restore() {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_restore()</b><br>";
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
	$meta = wp_get_attachment_metadata($attachment_ID);
	// get the filepath from the metadata
	$file_path = $meta['file'];
	// store absolute paths for older wordpress versions
	$store_absolute_path = true;
	// if the path given is not the absolute path
	if (FALSE === file_exists($file_path)) {
	//if (FALSE === strpos($file_path, WP_CONTENT_DIR)) {
		// don't store absolute paths
		$store_absolute_path = false;
		// retrieve the location of the wordpress upload folder
		$upload_dir = wp_upload_dir();
		// retrieve the path of the upload folder
		$upload_path = trailingslashit( $upload_dir['basedir'] );
		// generate the absolute path
		$file_path =  $upload_path . $file_path;
	}
	if (!empty($meta['converted'])) {
		if (file_exists($meta['orig_file'])) {
			// update the filename in the metadata
			$meta['file'] = $meta['orig_file'];
			// update the optimization results in the metadata
			$meta['ewww_image_optimizer'] = 'Original Restored';
			$meta['orig_file'] = $file_path;
			$meta['converted'] = 0;
			unlink($meta['orig_file']);
			// strip absolute path for Wordpress >= 2.6.2
			if ( FALSE === $store_absolute_path ) {
				$meta['file'] = str_replace($upload_path, '', $meta['file']);
			}
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
					$meta['sizes'][$size]['ewww_image_optimizer'] = 'Original Restored';
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
	// update the attachment metadata in the database
	wp_update_attachment_metadata($attachment_ID, $meta );
	// store the referring webpage location
	$sendback = wp_get_referer();
	// sanitize the referring webpage location
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	// send the user back where they came from
	wp_redirect($sendback);
	// we are done, nothing to see here
	exit(0);
}

// deletes 'orig_file' when an attachment is being deleted
function ewww_image_optimizer_delete ($id) {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_delete()</b><br>";
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
			// get the filepath from the metadata
			$file_path = $meta['file'];
			// retrieve the location of the wordpress upload folder
			$upload_dir = wp_upload_dir();
			// retrieve the path of the upload folder
			$upload_path = trailingslashit( $upload_dir['basedir'] );
			// if the path given is not the absolute path
			if (FALSE === file_exists($file_path)) {
				// don't store absolute paths
				$store_absolute_path = false;
				// generate the absolute path
				$file_path =  $upload_path . $file_path;
			}
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_save_image_editor_file()</b><br>";
	// if we don't already have this update attachment filter
	if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file'))
		// add the update saved file filter
		add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file', 10, 2);
	return;
}

// This is added as a filter on the metadata, only when an image is saved via the image editor
function ewww_image_optimizer_update_saved_file ($meta, $ID) {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_update_saved_file()</b><br>";
	$meta = ewww_image_optimizer_resize_from_meta_data($meta, $ID);
	return $meta;
}

/**
 * Process an image.
 *
 * Returns an array of the $file, $results, $converted to tell us if an image changes formats, and the $original file if it did.
 *
 * @param   string $file		Full absolute path to the image file
 * @param   int $gallery_type		1=wordpress, 2=nextgen, 3=flagallery
 * @param   boolean $converted		tells us if this is a resize and the full image was converted to a new format
 * @returns array
 */
function ewww_image_optimizer($file, $gallery_type, $converted, $resize) {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer()</b><br>";
	// initialize the original filename 
	$original = $file;
	// check to see if 'nice' exists
	if (ewww_image_optimizer_tool_found('/usr/bin/nice', 'n')) {
		$nice = '/usr/bin/nice';
	} elseif (ewww_image_optimizer_tool_found('nice', 'n')) {
		$nice = 'nice';
	} else {
		$nice = '';
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
	$ewww_debug = "$ewww_debug permissions: $file_perms, owner: $file_owner, group: $file_group <br>";
	// check that the file exists
	if (FALSE === file_exists($file)) {
		// tell the user we couldn't find the file
		$msg = sprintf(__("Could not find <span class='code'>%s</span>", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		$ewww_debug = "$ewww_debug file doesn't appear to exist<br>";
		// send back the above message
		return array($file, $msg, $converted, $original);
	}

	// check that the file is writable
	if ( FALSE === is_writable($file) ) {
		// tell the user we can't write to the file
		$msg = sprintf(__("<span class='code'>%s</span> is not writable", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		$ewww_debug = "$ewww_debug couldn't write to the file<br>";
		// send back the above message
		return array($file, $msg, $converted, $original);
	}
	$type = ewww_image_optimizer_mimetype($file, 'i');
	if (!$type) {
		//otherwise we store an error message since we couldn't get the mime-type
		$type = 'Missing finfo_file(), getimagesize() and mime_content_type() PHP functions';
	}
	// get the utility paths
	list ($jpegtran_path, $optipng_path, $gifsicle_path, $pngout_path) = ewww_image_optimizer_path_check();
	// if the user has disabled the utility checks
	if(get_site_option('ewww_image_optimizer_skip_check') == TRUE){
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
	// if the full-size image was converted
	if ($converted) {
		$ewww_debug = "$ewww_debug full-size image was converted, need to rebuild filename for meta<br>";
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
		$ewww_debug = "$ewww_debug moved $file to $refile<br>";
		// and set $file to the new filename
		$file = $refile;
		$original = $file;
	}
	// run the appropriate optimization/conversion for the mime-type
	switch($type) {
		case 'image/jpeg':
			// if jpg2png conversion is enabled, and this image is in the wordpress media library
			if ((get_site_option('ewww_image_optimizer_jpg_to_png') && $gallery_type == 1) || !empty($_GET['convert'])) {
				// toggle the convert process to ON
				$convert = true;
				// generate the filename for a PNG
				// if this is a resize version
				if ($converted) {
					// just change the file extension
					$pngfile = preg_replace('/\.\w+$/', '.png', $file);
				// if this is a full size image
				} else {
					// strip the file extension
					$filename = preg_replace('/\.\w+$/', '', $file);
					// set the increment to 1 (we always rename converted files with an increment)
					$filenum = 1;
					// set the new file extension
					$fileext = '.png';
					// while a file exists with the current increment
					while (file_exists($filename . '-' . $filenum . $fileext)) {
						// increment the increment...
						$filenum++;
					}
					// all done, let's reconstruct the filename
					$pngfile = $filename . '-' . $filenum . $fileext;
				}
			} else {
				// otherwise, set it to OFF
				$convert = false;
			}
			// if jpegtran optimization is disabled
			if (get_site_option('ewww_image_optimizer_disable_jpegtran')) {
				// store an appropriate message in $result
				$result = 'jpegtran is disabled';
				// set the optimization process to OFF
				$optimize = false;
			// otherwise, if we aren't skipping the utility verification and jpegtran doesn't exist
			} elseif (!$skip_jpegtran_check && !$jpegtran_path) {
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
				$ewww_debug = "$ewww_debug attempting to convert JPG to PNG: $pngfile <br>";
				// retrieve version info for ImageMagick
				if (ewww_image_optimizer_tool_found('convert', 'i')) {
					$convert_path = 'convert';
				} elseif (ewww_image_optimizer_tool_found('/usr/bin/convert', 'i')) {
					$convert_path = '/usr/bin/convert';
				} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/convert', 'i')) {
					$convert_path = '/usr/local/bin/convert';
				}
				// convert the JPG to PNG (try with GD if possible, 'convert' if not)
				if (!empty($convert_path)) {
					$ewww_debug = "$ewww_debug converting with ImageMagick<br>";
					exec("$convert_path $file -strip $pngfile");
				} elseif (ewww_image_optimizer_gd_support()) {
					$ewww_debug = "$ewww_debug converting with GD<br>";
					imagepng(imagecreatefromjpeg($file), $pngfile);
				}
				// if pngout isn't disabled
				if (!get_site_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the pngout optimization level
					$pngout_level = get_site_option('ewww_image_optimizer_pngout_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						$ewww_debug = "$ewww_debug optimizing converted PNG with pngout<br>";
						// run pngout on the new PNG
						exec("$nice $pngout_path -s$pngout_level -q $pngfile");
					}
				}
				// if optipng isn't disabled
				if (!get_site_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optipng optimization level
					$optipng_level = get_site_option('ewww_image_optimizer_optipng_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						$ewww_debug = "$ewww_debug optimizing converted PNG with optipng<br>";
						// run optipng on the new PNG
						exec("$nice $optipng_path -o$optipng_level -quiet $pngfile");
					}
				}
				// find out the size of the new PNG file
				$png_size = filesize($pngfile);
				$ewww_debug = "$ewww_debug converted PNG size: $png_size<br>";
				// if the PNG is smaller than the original JPG, and we didn't end up with an empty file
				if ($orig_size > $png_size && $png_size != 0) {
					// successful conversion (for now), and we store the increment
					$converted = $filenum;
				} else {
					// otherwise delete the PNG
					$converted = FALSE;
					unlink ($pngfile);
				}
			// if conversion and optimization are both turned OFF, finish the JPG processing
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned ON
			if ($optimize) {
				$ewww_debug = "$ewww_debug attempting to optimize JPG...<br>";
				// generate temporary file-names:
				$tempfile = $file . ".tmp"; //non-progressive jpeg
				$progfile = $file . ".prog"; // progressive jpeg
				// check to see if we are supposed to strip metadata (badly named)
				if(get_site_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
					// don't copy metadata
					$copy_opt = 'none';
				} else {
					// copy all the metadata
					$copy_opt = 'all';
				}
				// run jpegtran - non-progressive
				exec("$nice $jpegtran_path -copy $copy_opt -optimize -outfile $tempfile $file");
				// run jpegtran - progressive
				exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive -outfile $progfile $file");
				// check the filesize of the non-progressive JPG
				$non_size = filesize($tempfile);
				// check the filesize of the progressive JPG
				$prog_size = filesize($progfile);
				$ewww_debug = "$ewww_debug optimized JPG (non-progresive) size: $non_size<br>";
				$ewww_debug = "$ewww_debug optimized JPG (progresive) size: $prog_size<br>";
				if ($non_size === false || $prog_size === false) {
					$result = 'Unable to write file';
				} elseif (!$non_size || !$prog_size) {
					$result = 'Optimization failed';
				}
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
				if (get_site_option('ewww_image_optimizer_delete_originals') == TRUE) {
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
			if ((get_site_option('ewww_image_optimizer_png_to_jpg') && $gallery_type == 1) || !empty($_GET['convert'])) {
				// turn the conversion process ON
				$convert = true;
				// if this is a resize version
				if ($converted) {
					// just replace the file extension with a .jpg
					$jpgfile = preg_replace('/\.\w+$/', '.jpg', $file);
				// if this is a full version
				} else {
					// strip the file extension
					$filename = preg_replace('/\.\w+$/', '', $file);
					// set the increment
					$filenum = 1;
					// set the new extension
					$fileext = '.jpg';
					// if a file exists with the current increment
					while (file_exists($filename . '-' . $filenum . $fileext)) {
						// increase the increment
						$filenum++;
					}
					// construct the filename for the new JPG
					$jpgfile = $filename . '-' . $filenum . $fileext;
				}
			} else {
				// turn the conversion process OFF
				$convert = false;
			}
			// if pngout and optipng are disabled
			if (get_site_option('ewww_image_optimizer_disable_optipng') && get_site_option('ewww_image_optimizer_disable_pngout')) {
				// tell the user all PNG tools are disabled
				$result = 'png tools are disabled';
				// turn off optimization
				$optimize = false;
			// if the utility checking is on, optipng is enabled, but optipng cannot be found
			} elseif (!$skip_optipng_check && !$optipng_path && !get_site_option('ewww_image_optimizer_disable_optipng')) {
				// tell the user optipng is missing
				$result = '<em>optipng</em> is missing';
				// turn off optimization
				$optimize = false;
			// if the utility checking is on, pngout is enabled, but pngout cannot be found
			} elseif (!$skip_pngout_check && !$pngout_path && !get_site_option('ewww_image_optimizer_disable_pngout')) {
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
				// if the user set a fill background for transparency
				if ($background = ewww_image_optimizer_jpg_background()) {
					// set background color for GD
					$r = hexdec('0x' . strtoupper(substr($background, 0, 2)));
                                        $g = hexdec('0x' . strtoupper(substr($background, 2, 2)));
					$b = hexdec('0x' . strtoupper(substr($background, 4, 2)));
					// set the background flag for 'convert'
					$background = "-background " . '"' . "#$background" . '"';
				}
				// if the user manually set the JPG quality
				if ($quality = ewww_image_optimizer_jpg_quality()) {
					// set the quality for GD
					$gquality = $quality;
					// set the quality flag for 'convert'
					$cquality = "-quality $quality";
				} else {
					$cquality = '';
				}
				// retrieve version info for ImageMagick
				//exec('convert -version', $convert_version);
				if (ewww_image_optimizer_tool_found('convert', 'i')) {
					$convert_path = 'convert';
				} elseif (ewww_image_optimizer_tool_found('/usr/bin/convert', 'i')) {
					$convert_path = '/usr/bin/convert';
				} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/convert', 'i')) {
					$convert_path = '/usr/local/bin/convert';
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
						$rgb = imagecolorallocate($output, $r, $g, $b);
						// fill the new image with the background color 
						imagefilledrectangle($output, 0, 0, $width, $height, $rgb);
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
				} elseif (!empty($convert_path)) {
				//} elseif (!empty($convert_version) && strpos($convert_version[0], 'ImageMagick')) {
					exec ("$convert_path $background -flatten $cquality $file $jpgfile");
				} 
				// retrieve the filesize of the new JPG
				$jpg_size = filesize($jpgfile);
				// next we need to optimize that JPG if jpegtran is enabled
				if (!get_site_option('ewww_image_optimizer_disable_jpegtran') && file_exists($jpgfile)) {
					// generate temporary file-names:
					$tempfile = $jpgfile . ".tmp"; //non-progressive jpeg
					$progfile = $jpgfile . ".prog"; // progressive jpeg
					// check to see if we are supposed to strip metadata (badly named)
					if(get_site_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
						// don't copy metadata
						$copy_opt = 'none';
					} else {
						// copy all the metadata
						$copy_opt = 'all';
					}
					// run jpegtran - non-progressive
					exec("$nice $jpegtran_path -copy $copy_opt -optimize -outfile $tempfile $jpgfile");
					// run jpegtran - progressive
					exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive -outfile $progfile $jpgfile");
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
					// successful conversion (for now), so we store the increment
					$converted = $filenum;
				} else {
					$converted = FALSE;
					// otherwise delete the new JPG
					unlink ($jpgfile);
				}
			// if conversion and optimization are both disabled we are done here
			} elseif (!$optimize) {
				break;
			}
			// if optimization is turned on
			if ($optimize) {
				// if pngout is enabled
				if(!get_site_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the optimization level for pngout
					$pngout_level = get_site_option('ewww_image_optimizer_pngout_level');
					// run pngout on the PNG file
					exec("$nice $pngout_path -s$pngout_level -q $file");
				}
				// if optipng is enabled
				if(!get_site_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optimization level for optipng
					$optipng_level = get_site_option('ewww_image_optimizer_optipng_level');
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
				if (get_site_option('ewww_image_optimizer_delete_originals') == TRUE) {
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
			if ((get_site_option('ewww_image_optimizer_gif_to_png') && $gallery_type == 1) || !empty($_GET['convert'])) {
				// turn conversion ON
				$convert = true;
				// generate the filename for a PNG
				// if this is a resize version
				if ($converted) {
					// just change the file extension
					$pngfile = preg_replace('/\.\w+$/', '.png', $file);
				// if this is the full version
				} else {
					// strip the file extension
					$filename = preg_replace('/\.\w+$/', '', $file);
					// set the increment
					$filenum = 1;
					// set the new extension
					$fileext = '.png';
					// if a file exists with the current increment
					while (file_exists($filename . '-' . $filenum . $fileext)) {
						// increase the increment
						$filenum++;
					}
					// construct the filename for the new PNG
					$pngfile = $filename . '-' . $filenum . $fileext;
				}
			} else {
				// turn conversion OFF
				$convert = false;
			}
			// if gifsicle is disabled
			if (get_site_option('ewww_image_optimizer_disable_gifsicle')) {
				// return an appropriate message
				$result = 'gifsicle is disabled';
				// turn optimization off
				$optimize = false;
			// if utility checking is on, and gifsicle is not installed
			} elseif (!$skip_gifsicle_check && !$gifsicle_path) {
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
				if (!get_site_option('ewww_image_optimizer_disable_pngout') && $pngout_path) {
					// retrieve the pngout optimization level
					$pngout_level = get_site_option('ewww_image_optimizer_pngout_level');
					// run pngout on the file
					exec("$nice $pngout_path -s$pngout_level -q $file $pngfile");
				}
				// if optipng is enabled
				if (!get_site_option('ewww_image_optimizer_disable_optipng') && $optipng_path) {
					// retrieve the optipng optimization level
					$optipng_level = get_site_option('ewww_image_optimizer_optipng_level');
					// if $pngfile exists (which means pngout was run already)
					if (file_exists($pngfile)) {
						// run optipng on the PNG file
						exec("$nice $optipng_path -o$optipng_level -quiet $pngfile");
					// otherwise, if pngout was not used
					} else {
						// run optipng on the GIF file
						exec("$nice $optipng_path -out $pngfile -o$optipng_level -quiet $file");
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
				if (get_site_option('ewww_image_optimizer_delete_originals') == TRUE) {
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
			return array($file, __('Unknown type: ' . $type, EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted, $original);
	}
	// if the image is unchanged
	if($result == 'unchanged') {
		// tell the user we couldn't save them anything
		return array($file, __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN), $converted, $original);
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_resize_from_meta_data()</b><br>";
	// don't do anything else if the attachment has no metadata
	if (!isset($meta['file'])) {
	$ewww_debug = "$ewww_debug file has no meta<br>";
		return $meta;
	}
	if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file')) {
		$gallery_type = 1;
	} else {
		$gallery_type = 5;
	}
	// get the filepath from the metadata
	$file_path = $meta['file'];
	$ewww_debug = "$ewww_debug meta file path: $file_path<br>";
	// store absolute paths for older wordpress versions
	$store_absolute_path = true;
	// retrieve the location of the wordpress upload folder
	$upload_dir = wp_upload_dir();
	// retrieve the path of the upload folder
	$upload_path = trailingslashit($upload_dir['basedir']);
	// if the path given is not the absolute path
	if (FALSE == file_exists($file_path)) {
		// don't store absolute paths
		$store_absolute_path = false;
		// generate the absolute path
		$file_path =  $upload_path . $file_path;
		$ewww_debug = "$ewww_debug generated absolute path: $file_path<br>";
	}
	// run the image optimizer on the file, and store the results
	list($file, $msg, $conv, $original) = ewww_image_optimizer($file_path, $gallery_type, false, false);
	// update the filename in the metadata
	$meta['file'] = $file;
	// update the optimization results in the metadata
	$meta['ewww_image_optimizer'] = $msg;
	// strip absolute path for Wordpress >= 2.6.2
	if ( FALSE === $store_absolute_path ) {
		$meta['file'] = str_replace($upload_path, '', $meta['file']);
	}
	// if the file was converted
	if ($conv) {
		$ewww_debug = "$ewww_debug image was converted<br>";
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
		$ewww_debug = "$ewww_debug processing resizes<br>";
		// meta sizes don't contain a path, so we calculate one
		$base_dir = dirname($file_path) . '/';
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
					$meta['sizes'][$size]['ewww_image_optimizer'] = 'No savings';
				}
			}
			// if this is a unique size
			if (!$dup_size) {
				// run the optimization and store the results
				list($optimized_file, $results, $resize_conv, $original) = ewww_image_optimizer($base_dir . $data['file'], $gallery_type, $conv, true);
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
	// send back the updated metadata
	return $meta;
}

/**
 * Update the attachment's meta data after being converted 
 */
function ewww_image_optimizer_update_attachment($meta, $ID) {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_update_attachment()</b><br>";
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

/**
 * Check the submitted PNG to see if it has transparency
 */
function ewww_image_optimizer_png_alpha ($filename){
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_png_alpha()</b><br>";
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_is_animated()</b><br>";
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
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_optimizer_columns()</b><br>";
	$defaults['ewww-image-optimizer'] = 'Image Optimizer';
	return $defaults;
}

/**
 * Return the filesize in a humanly readable format.
 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
 */
function ewww_image_optimizer_format_bytes($bytes, $precision = 2) {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_format_bytes()</b><br>";
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
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_custom_column()</b><br>";
	// once we get to the EWWW IO custom column
	if ($column_name == 'ewww-image-optimizer') {
		// retrieve the metadata
		$meta = wp_get_attachment_metadata($id);
		// if the filepath isn't set in the metadata
		if(empty($meta['file'])){
			if (isset($meta['file'])) {
				unset($meta['file']);
				if (strpos($meta['ewww_image_optimizer'], 'Could not find') === 0) {
					unset($meta['ewww_image_optimizer']);
				}
				wp_update_attachment_metadata($id, $meta);
			}
			echo 'Metadata is missing file path.';
			//print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
			return;
		}
		// retrieve the filepath from the metadata
		$file_path = $meta['file'];
		// retrieve the wordpress upload folder
		$upload_dir = wp_upload_dir();
		// retrieve the wordpress upload folder path
		$upload_path = trailingslashit( $upload_dir['basedir'] );
		// if the path given is not the absolute path
		if (FALSE === file_exists($file_path)) {
			// find the absolute path
			$file_path = $upload_path . $file_path;
		}
		$msg = '';
		$type = ewww_image_optimizer_mimetype($file_path, 'i');
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
				} else {
					$convert_link = 'JPG to PNG';
					$class_type = 'jpg';
					$convert_desc = "WARNING: Removes metadata. Requires GD or ImageMagick. PNG is generally much better than JPG for logos and other images with a limited range of colors.";
				}
				break; 
			case 'image/png':
				// if pngout and optipng are missing, tell the user
				if(EWWW_IMAGE_OPTIMIZER_PNGOUT == false && EWWW_IMAGE_OPTIMIZER_OPTIPNG == false) {
					$valid = false;
					$msg = '<br>' . __('<em>optipng/pngout</em> is missing');
				} else {
					$convert_link = 'PNG to JPG';
					$class_type = 'png';
					$convert_desc = "WARNING: This is not a lossless conversion and requires GD or ImageMagick. JPG is much better than PNG for photographic use because it compresses the image and discards data. Transparent images will only be converted if a background color has been set.";
				}
				break;
			case 'image/gif':
				// if gifsicle is missing, tell the user
				if(EWWW_IMAGE_OPTIMIZER_GIFSICLE == false) {
					$valid = false;
					$msg = '<br>' . __('<em>gifsicle</em> is missing');
				} else {
					$convert_link = 'GIF to PNG';
					$class_type = 'gif';
					$convert_desc = "PNG is generally better than GIF, but does not support animation. Animated images will not be converted.";
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
		if (isset($meta['ewww_image_optimizer']) && !empty($meta['ewww_image_optimizer']) ) {
			// output the optimizer results
			print $meta['ewww_image_optimizer'];
			// output the filesize
			print "<br>Image Size: $file_size";
			// output a link to re-optimize manually
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			if (!get_site_option('ewww_image_optimizer_disable_convert_links'))
				echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=$id&amp;convert=1'>$convert_link</a>";
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
				printf("<br><a href=\"admin.php?action=ewww_image_optimizer_restore&amp;attachment_ID=%d\">%s</a>",
					$id,
					__('Restore original', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			}
		} else {
			// otherwise, this must be an image we haven't processed
			print __('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			// tell them the filesize
			print "<br>Image Size: $file_size";
			// and give the user the option to optimize the image right now
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
			if (!get_site_option('ewww_image_optimizer_disable_convert_links'))
				echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=$id&amp;convert=1'>$convert_link</a>";
		}
	}
}

// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
// adds a bulk optimize action to the drop-down on the media library page
function ewww_image_optimizer_add_bulk_actions_via_javascript() { 
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_add_bulk_actions_via_javascript()</b><br>";
?>
	<script type="text/javascript"> 
		jQuery(document).ready(function($){ 
			$('select[name^="action"] option:last-child').before('<option value="bulk_optimize">Bulk Optimize</option>');
			$('.ewww-convert').tooltip();
		}); 
	</script>
<?php } 

// Handles the bulk actions POST 
// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ 
function ewww_image_optimizer_bulk_action_handler() { 
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_bulk_action_handler()</b><br>";
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
	if (!$file = fopen($url, 'rb')) {
		exit;
	}
	if ($file) {
		if (!$newf = fopen ($path, 'wb')) {
			exit;
		}
		if ($newf) {
			while(!feof($file)) {
				if (fwrite($newf, fread($file, 1024 * 8), 1024 * 8) === false) {
					exit;
				}
			}
		}
	}
	if ($file) {
		fclose($file);
	}
	if ($newf) {
		fclose($newf);
	}
	if (file_exists($path)) {
		return true;
	} else {
		return false;
	}
}

// retrieves the pngout linux package with wget, unpacks it with tar, 
// copies the appropriate version to the plugin folder, and sends the user back where they came from
function ewww_image_optimizer_install_pngout() {
	if (FALSE === current_user_can('install_plugins')) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	if (PHP_OS != 'WINNT' && ewww_image_optimizer_tool_found('tar', 't')) {
		$tar = 'tar';
	} elseif (PHP_OS != 'WINNT' && ewww_image_optimizer_tool_found('/usr/bin/tar', 't')) {
		$tar = '/usr/bin/tar';
	}
	if (empty($tar) && PHP_OS != 'WINNT') $pngout_error = "tar command not found";
	if (PHP_OS == 'Linux') {
		$os_string = 'linux';
	}
	if (PHP_OS == 'FreeBSD') {
		$os_string = 'bsd';
	}
	$latest = '20130221';
	if (empty($pngout_error)) {
		if (PHP_OS == 'Linux' || PHP_OS == 'FreeBSD') {
			if (!file_exists(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static.tar.gz')) {
				$download_result = ewww_image_optimizer_download_file('http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-' . $os_string . '-static.tar.gz', EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static.tar.gz');
				if (!$download_result) $pngout_error = "file not downloaded";
			}
			$arch_type = php_uname('m');
			exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static');
			if (!rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static'))
				if (empty($pngout_error)) $pngout_error = "could not move pngout";
			if (!chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755))
				if (empty($pngout_error)) $pngout_error = "could not set permissions";
			$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 'p');
		}
		if (PHP_OS == 'Darwin') {
			if (!file_exists(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin.tar.gz')) {
				$download_result = ewww_image_optimizer_download_file('http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-darwin.tar.gz', EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin.tar.gz');
				if (!$download_result) $pngout_error = "file not downloaded";
			}
			exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-' . $latest . '-darwin/pngout');
			if (!rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin/pngout', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static'))
				if (empty($pngout_error)) $pngout_error = "could not move pngout";
			if (!chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755))
				if (empty($pngout_error)) $pngout_error = "could not set permissions";
			$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 'p');
		}
	}
	if (PHP_OS == 'WINNT') {
		$download_result = ewww_image_optimizer_download_file('http://advsys.net/ken/util/pngout.exe', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe');
			if (!$download_result) $pngout_error = "file not downloaded";
		$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe', 'p');
	}
	if (!empty($pngout_version)) {
		$sendback = wp_get_referer();
		$sendback = preg_replace('/\&pngout\=\w+/', '', $sendback) . "&pngout=success";
	}
	if (!isset($sendback)) {
		$sendback = wp_get_referer();
		$sendback = preg_replace('/\&pngout\=\w+/', '', $sendback) . '&pngout=failed&error=' . urlencode($pngout_error);
	}
	wp_redirect($sendback);
	exit(0);
}

// displays the EWWW IO options and provides one-click install for the optimizer utilities
function ewww_image_optimizer_options () {
	global $ewww_debug;
	$ewww_debug = "$ewww_debug <b>ewww_image_optimizer_options()</b><br>";
	if (isset($_REQUEST['pngout'])) {
		if ($_REQUEST['pngout'] == 'success') { ?>
			<div id='ewww-image-optimizer-pngout-success' class='updated fade'>
				<p>pngout was successfully installed, check the Plugin Status area for version information.</p>
			</div>
<?php		}
		if ($_REQUEST['pngout'] == 'failed') { ?>
			<div id='ewww-image-optimizer-pngout-failure' class='error'>
				<p>pngout was not installed, <?php echo $_REQUEST['error']; ?>. Make sure the wp-content/ewww folder is writable.</p>
			</div>
<?php		}
	} ?>
	<script type='text/javascript'>
		jQuery(document).ready(function($) {$('.fade').fadeTo(5000,1).fadeOut(3000);});
	</script>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>EWWW Image Optimizer Settings</h2>
		<p><a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/">Plugin Home Page</a> |
		<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/">Installation Instructions</a> | 
		<a href="http://wordpress.org/support/plugin/ewww-image-optimizer">Plugin Support</a> | 
		Debug - see the new Debugging option below</p>
		<p>I recommend hosting your Wordpress site with <a href=http://www.dreamhost.com/r.cgi?132143">Dreamhost.com</a> or <a href="http://www.bluehost.com/track/nosilver4u">Bluehost.com</a>. Using these referral links will allow you to support future development of this plugin: <a href=http://www.dreamhost.com/r.cgi?132143">Dreamhost</a> | <a href="http://www.bluehost.com/track/nosilver4u">Bluehost</a>. Alternatively, you can contribute directly by <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=QFXCW38HE24NY">donating with Paypal</a>.</p>
		<div id="status" style="border: 1px solid #ccc; padding: 0 8px; border-radius: 12px;">
			<h3>Plugin Status</h3>
			<?php
			if (get_site_option('ewww_image_optimizer_skip_bundle')) { ?>
				<p>If updated versions are available below you may either download the newer versions and install them yourself, or uncheck "Use system paths" and install them automatically.<br />
			<?php } else { ?>
				<p>If updated versions are available below, you may need to enable write permission on the <i>wp-content/ewww</i> folder to use the automatic installs.<br />
			<?php } ?>
			<i>*Updates are optional, but may contain increased optimization or security patches</i></p>
			<?php
			list ($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst) = ewww_image_optimizer_install_paths();
			if (!get_site_option('ewww_image_optimizer_disable_jpegtran')) {
				echo "\n";
				echo '<b>jpegtran: </b>';
				$jpegtran_installed = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'j');
				if (!empty($jpegtran_installed) && preg_match('/version 8|9/', $jpegtran_installed)) {
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $jpegtran_installed . '<br />'; 
				} elseif (!empty($jpegtran_installed)) {
					echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> executable from ' . $jpegtran_src . ' to ' . $jpegtran_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://www.ijg.org/"><b>Download</b> jpegtran source</a>&emsp;<b>version:</b> ' . $jpegtran_installed . '<br />';
				} else { 
					echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>Copy</b> executable from ' . $jpegtran_src . ' to ' . $jpegtran_dst . ' or a system path (like /usr/local/bin), OR <a href="http://www.ijg.org/"><b>Download</b> jpegtran source</a><br />';
				}
			}
			echo "\n";
			if (!get_site_option('ewww_image_optimizer_disable_optipng')) {
				echo "\n";
				echo '<b>optipng:</b> '; 
				$optipng_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'o');
				if (!empty($optipng_version) && preg_match('/0.7.4/', $optipng_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $optipng_version . '<br />'; 
				} elseif (!empty($optipng_version)) {
						echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> binary from ' . $optipng_src . ' to ' . $optipng_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://prdownloads.sourceforge.net/optipng/optipng-0.7.4.tar.gz?download"><b>Download</b> optipng source</a>&emsp;<b>version:</b> ' . $optipng_version . '<br />';
				} else {
						echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>Copy</b> binary from ' . $optipng_src . ' to ' . $optipng_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://prdownloads.sourceforge.net/optipng/optipng-0.7.4.tar.gz?download"><b>Download</b> optipng source</a><br />';
				}
			}
			echo "\n";
			if (!get_site_option('ewww_image_optimizer_disable_gifsicle')) {
				echo "\n";
				echo '<b>gifsicle:</b> ';
				$gifsicle_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'g');
				if (!empty($gifsicle_version) && preg_match('/1.70/', $gifsicle_version)) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $gifsicle_version . '<br />'; 
				} elseif (!empty($gifsicle_version) && preg_match('/LCDF Gifsicle/', $gifsicle_version)) {
						echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> binary from ' . $gifsicle_src . ' to ' . $gifsicle_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://www.lcdf.org/gifsicle/gifsicle-1.70.tar.gz"><b>Download</b> gifsicle source</a>&emsp;<b>version:</b> ' . $gifsicle_version . '<br />';
				} else {
						echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>Copy</b> binary from ' . $gifsicle_src . ' to ' . $gifsicle_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://www.lcdf.org/gifsicle/gifsicle-1.70.tar.gz"><b>Download</b> gifsicle source</a><br />';
				}
			}
			echo "\n";
			if (!get_site_option('ewww_image_optimizer_disable_pngout')) {
				echo "\n";
				echo '<b>pngout:</b> '; 
				$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_PNGOUT, 'p');
				if (!empty($pngout_version) && (preg_match('/Feb 2(0|1) 2013/', $pngout_version))) { 
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . preg_replace('/PNGOUT \[.*\)\s*?/', '', $pngout_version) . '<br />'; 
				} elseif (!empty($pngout_version) && preg_match('/PNGOUT/', $pngout_version)) {
					echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Install</b> <a href="admin.php?action=ewww_image_optimizer_install_pngout">automatically</a> | <a href="http://advsys.net/ken/utils.htm">manually</a>&emsp;<b>version:</b> ' . preg_replace('/PNGOUT \[.*\)\s*?/', '', $pngout_version) . '<br />'; 
				} else {
					echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>Install <a href="admin.php?action=ewww_image_optimizer_install_pngout">automatically</a> | <a href="http://advsys.net/ken/utils.htm">manually</a></b> - Pngout is free closed-source software that can produce drastically reduced filesizes for PNGs, but can be very time consuming to process images<br />'; 
				}
			}
			echo "\n";
			echo "<b>Graphics libraries</b> - only need one, used for conversion, not optimization: ";
			if (ewww_image_optimizer_gd_support()) {
				echo 'GD: <span style="color: green; font-weight: bolder">OK';
			} else {
				echo 'GD: <span style="color: red; font-weight: bolder">MISSING';
			} ?></span>&emsp;&emsp;
			Imagemagick 'convert': <?php
			if (ewww_image_optimizer_tool_found('convert', 'i') || ewww_image_optimizer_tool_found('/usr/bin/convert', 'i') || ewww_image_optimizer_tool_found('/usr/local/bin/convert', 'i')) { 
				echo '<span style="color: green; font-weight: bolder">OK</span>'; 
			} else { 
				echo '<span style="color: red; font-weight: bolder">MISSING</span>'; 
			}
			echo "<br />\n";
			if (ini_get('safe_mode')) {
				echo 'safe mode: <span style="color: red; font-weight: bolder">On</span>&emsp;&emsp;';
			} else {
				echo 'safe mode: <span style="color: green; font-weight: bolder">Off</span>&emsp;&emsp;';
			}
			$disabled = ini_get('disable_functions');
			$ewww_debug = "$ewww_debug disabled functions: $disabled<br />";
			if (preg_match('/[^_]exec/', $disabled)) {
				echo 'exec(): <span style="color: red; font-weight: bolder">DISABLED</span>&emsp;&emsp;';
			} else {
				echo 'exec(): <span style="color: green; font-weight: bolder">OK</span>&emsp;&emsp;';
			}
			if (PHP_OS != 'WINNT') {
				if (!ewww_image_optimizer_tool_found('/usr/bin/file', 'f') && !ewww_image_optimizer_tool_found('file', 'f')) {
					echo '<span style="color: red; font-weight: bolder">file command not found on your system</span>';
				}
				if (!ewww_image_optimizer_tool_found('/usr/bin/nice', 'n') && !ewww_image_optimizer_tool_found('nice', 'n')) {
					echo '<span style="color: orange; font-weight: bolder">nice command not found on your system (not required)</span>';
				}
				if (!ewww_image_optimizer_tool_found('tar', 't') && !ewww_image_optimizer_tool_found('/usr/bin/tar', 't')) {
					echo '<span style="color: red; font-weight: bolder">tar command not found on your system (required for automatic pngout installer)</span>';
				}
			}
			echo "<br />\n<b>Only need one of these: </b>";
			if (function_exists('finfo_file')) {
				echo 'finfo: <span style="color: green; font-weight: bolder">OK</span>&emsp;&emsp;';
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
			} else {
				echo 'mime_content_type(): <span style="color: red; font-weight: bolder">MISSING</span><br>';
			}
			?></p>
		</div>
<?php		if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('ewww-image-optimizer/ewww-image-optimizer.php')) { ?>
		<form method="post" action="">
<?php		} else { ?>
		<form method="post" action="options.php">
			<?php settings_fields('ewww_image_optimizer_options'); 
		} ?>
			<h3>General Settings</h3>
			<p>The plugin performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, pngout, and gifsicle. In some rare cases, these checks may erroneously report that you are missing the required utilities even though you have them installed.</p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_skip_bundle">Use system paths</label></th><td><input type="checkbox" id="ewww_image_optimizer_skip_bundle" name="ewww_image_optimizer_skip_bundle" value="true" <?php if (get_site_option('ewww_image_optimizer_skip_bundle') == TRUE) { ?>checked="true"<?php } ?> /> If you have already installed the utilities in a system location, such as /usr/local/bin or /usr/bin, use this to force the plugin to use those versions and skip the auto-installers.</td></tr>
				<tr><th><label for="ewww_image_optimizer_debug">Debugging</label></th><td><input type="checkbox" id="ewww_image_optimizer_debug" name="ewww_image_optimizer_debug" value="true" <?php if (get_site_option('ewww_image_optimizer_debug') == TRUE) { ?>checked="true"<?php } ?> /> Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.</td></tr>
				<tr><th><label for="ewww_image_optimizer_skip_check">Skip utils check</label></th><td><input type="checkbox" id="ewww_image_optimizer_skip_check" name="ewww_image_optimizer_skip_check" value="true" <?php if (get_site_option('ewww_image_optimizer_skip_check') == TRUE) { ?>checked="true"<?php } ?> /> <b>DEPRECATED</b> - please uncheck this and report any errors in the support forum.</td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_jpegtran">disable jpegtran</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_jpegtran" name="ewww_image_optimizer_disable_jpegtran" <?php if (get_site_option('ewww_image_optimizer_disable_jpegtran') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_optipng">disable optipng</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_optipng" name="ewww_image_optimizer_disable_optipng" <?php if (get_site_option('ewww_image_optimizer_disable_optipng') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_pngout">disable pngout</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_pngout" name="ewww_image_optimizer_disable_pngout" <?php if (get_site_option('ewww_image_optimizer_disable_pngout') == TRUE) { ?>checked="true"<?php } ?> /></td><tr>
				<tr><th><label for="ewww_image_optimizer_disable_gifsicle">disable gifsicle</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_gifsicle" name="ewww_image_optimizer_disable_gifsicle" <?php if (get_site_option('ewww_image_optimizer_disable_gifsicle') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
			</table>
			<h3>Optimization settings</h3>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_jpegtran_copy">Remove JPG metadata</label></th>
				<td><input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (get_site_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> This wil remove ALL metadata (EXIF and comments)</td></tr>
				<tr><th><label for="ewww_image_optimizer_optipng_level">optipng optimization level</label></th>
				<td><span><select id="ewww_image_optimizer_optipng_level" name="ewww_image_optimizer_optipng_level">
				<option value="1"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: 1 trial</option>
				<option value="2"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: 8 trials</option>
				<option value="3"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: 16 trials</option>
				<option value="4"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 4) { echo ' selected="selected"'; } ?>>Level 4: 24 trials</option>
				<option value="5"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 5) { echo ' selected="selected"'; } ?>>Level 5: 48 trials</option>
				<option value="6"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 6) { echo ' selected="selected"'; } ?>>Level 6: 120 trials</option>
				<option value="7"<?php if (get_site_option('ewww_image_optimizer_optipng_level') == 7) { echo ' selected="selected"'; } ?>>Level 7: 240 trials</option>
				</select> (default=2)</span>
				<p class="description">According to the author of optipng, 10 trials should satisfy most people, 30 trials should satisfy everyone.</p></td></tr>
				<tr><th><label for="ewww_image_optimizer_pngout_level">pngout optimization level</label></th>
				<td><span><select id="ewww_image_optimizer_pngout_level" name="ewww_image_optimizer_pngout_level">
				<option value="0"<?php if (get_site_option('ewww_image_optimizer_pngout_level') == 0) { echo ' selected="selected"'; } ?>>Level 0: Xtreme! (Slowest)</option>
				<option value="1"<?php if (get_site_option('ewww_image_optimizer_pngout_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: Intense (Slow)</option>
				<option value="2"<?php if (get_site_option('ewww_image_optimizer_pngout_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: Longest Match (Fast)</option>
				<option value="3"<?php if (get_site_option('ewww_image_optimizer_pngout_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: Huffman Only (Faster)</option>
			</select> (default=2)</span>
			<p class="description">If you have CPU cycles to spare, go with level 0</p></td></tr>
			</table>
			<h3>Conversion Settings</h3>
			<p>
				Conversion is not available in NextGEN or GRAND FlAGallery.
				By default, all images have a link available in the media library for one-time conversion. Turning on individual conversion operations below will enable conversion filters any time an image is uploaded or modified.<br />
				<b>NOTE:</b> The plugin will attempt to update image locations for any posts that contain the images. You may need to manually update locations/urls for converted images. 
			</p>
			<table class="form-table">
				<tr><th><label for="ewww_image_optimizer_disable_convert_links">Hide Conversion Links</label</th><td><input type="checkbox" id="ewww_image_optimizer_disable_convert_links" name="ewww_image_optimizer_disable_convert_links" <?php if (get_site_option('ewww_image_optimizer_disable_convert_links') == TRUE) { ?>checked="true"<?php } ?> /> Site or Network admins can use this to prevent other users from using the conversion links in the Media Library which bypass the settings below.</td></tr>
				<tr><th><label for="ewww_image_optimizer_delete_originals">Delete originals</label></th><td><input type="checkbox" id="ewww_image_optimizer_delete_originals" name="ewww_image_optimizer_delete_originals" <?php if (get_site_option('ewww_image_optimizer_delete_originals') == TRUE) { ?>checked="true"<?php } ?> /> This will remove the original image from the server after a successful conversion.</td></tr>
				<tr><th><label for="ewww_image_optimizer_jpg_to_png">enable <b>JPG</b> to <b>PNG</b> conversion</label></th><td><span><input type="checkbox" id="ewww_image_optimizer_jpg_to_png" name="ewww_image_optimizer_jpg_to_png" <?php if (get_site_option('ewww_image_optimizer_jpg_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <b>WARNING:</b> Removes metadata! Requires GD or ImageMagick and should be used sparingly.</span>
				<p class="description">PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.</p></td></tr>
				<tr><th><label for="ewww_image_optimizer_png_to_jpg">enable <b>PNG</b> to <b>JPG</b> conversion</label></th><td><span><input type="checkbox" id="ewww_image_optimizer_png_to_jpg" name="ewww_image_optimizer_png_to_jpg" <?php if (get_site_option('ewww_image_optimizer_png_to_jpg') == TRUE) { ?>checked="true"<?php } ?> /> <b>WARNING:</b> This is not a lossless conversion and requires GD or ImageMagick.</span>
				<p class="description">JPG is generally much better than PNG for photographic use because it compresses the image and discards data. PNGs with transparency are not converted by default.</p>
				<span><label for="ewww_image_optimizer_jpg_background">JPG background color:</label> #<input type="text" id="ewww_image_optimizer_jpg_background" name="ewww_image_optimizer_jpg_background" class="small-text" value="<?php echo ewww_image_optimizer_jpg_background(); ?>" /> <span style="padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #<? echo ewww_image_optimizer_jpg_background(); ?>">&nbsp;</span> HEX format (#123def).</span>
				<p class="description">This is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.</p>
				<span><label for="ewww_image_optimizer_jpg_quality">JPG quality level:</label> <input type="text" id="ewww_image_optimizer_jpg_quality" name="ewww_image_optimizer_jpg_quality" class="small-text" value="<?php echo ewww_image_optimizer_jpg_quality(); ?>" /> Valid values are 1-100.</span>
				<p class="description">If left blank, the plugin will attempt to set the optimal quality level or default to 92. Remember, this is a lossy conversion, so you are losing pixels, and it is not recommended to actually set the level here unless you want noticable loss of image quality.</p></td></tr>
				<tr><th><label for="ewww_image_optimizer_gif_to_png">enable <b>GIF</b> to <b>PNG</b> conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_gif_to_png" name="ewww_image_optimizer_gif_to_png" <?php if (get_site_option('ewww_image_optimizer_gif_to_png') == TRUE) { ?>checked="true"<?php } ?> />
				<p class="description"> PNG is generally much better than GIF, but animated images cannot be converted.</p></td></tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>
		</form>
	</div>
	<?php
}

