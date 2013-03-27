<?php
/**
 * Integrate image optimizers into WordPress.
 * @version 1.4.0
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://www.shanebishop.net/ewww-image-optimizer/
Description: Reduce file sizes for images within WordPress including NextGEN Gallery and GRAND FlAGallery. Uses jpegtran, optipng/pngout, and gifsicle.
Author: Shane Bishop
Version: 1.4.0
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
// the folder where we install optimization tools
define('EWWW_IMAGE_OPTIMIZER_TOOL_PATH', WP_CONTENT_DIR . '/ewww/');

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
// variable for plugin settings link
$plugin = plugin_basename ( __FILE__ );
add_filter("plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link');
// TODO: eventually implement a new function in the wp_image_editor class to handle this (and maybe more)
add_filter('wp_save_image_editor_file', 'ewww_image_optimizer_save_image_editor_file', 10, 5);
add_action('manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2);
add_action('admin_init', 'ewww_image_optimizer_admin_init');
add_action('admin_action_ewww_image_optimizer_manual', 'ewww_image_optimizer_manual');
add_action('admin_action_ewww_image_optimizer_restore', 'ewww_image_optimizer_restore');
add_action('delete_attachment', 'ewww_image_optimizer_delete');
add_action('admin_menu', 'ewww_image_optimizer_admin_menu' );
add_action('admin_head-upload.php', 'ewww_image_optimizer_add_bulk_actions_via_javascript' ); 
add_action('admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action('admin_action_-1', 'ewww_image_optimizer_bulk_action_handler' ); 
//add_action('admin_action_ewww_image_optimizer_install_jpegtran', 'ewww_image_optimizer_install_jpegtran');
add_action('admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout');
//add_action('admin_action_ewww_image_optimizer_install_optipng', 'ewww_image_optimizer_install_optipng');
//add_action('admin_action_ewww_image_optimizer_install_gifsicle', 'ewww_image_optimizer_install_gifsicle');

/**
 * Check if this is an unsupported OS (not Linux or Mac OSX or FreeBSD or Windows)
 */
if('Linux' != PHP_OS && 'Darwin' != PHP_OS && 'FreeBSD' != PHP_OS && 'WINNT' != PHP_OS) {
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
// need to include the plugin library for the is_plugin_active function (even though it isn't supposed to be necessary in the admin)
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
// include the file that loads the nextgen gallery optimization functions
if (is_plugin_active('nextgen-gallery/nggallery.php') || is_plugin_active_for_network('nextgen-gallery/nggallery.php'))
require( dirname(__FILE__) . '/nextgen-integration.php' );
// include the file that loads the grand flagallery optimization functions
if (is_plugin_active('flash-album-gallery/flag.php') || is_plugin_active_for_network('flash-album-gallery/flag.php'))
require( dirname(__FILE__) . '/flag-integration.php' );

// tells the user they are on an unsupported operating system
function ewww_image_optimizer_notice_os() {
	echo "<div id='ewww-image-optimizer-warning-os' class='error'><p><strong>EWWW Image Optimizer is supported on Linux, FreeBSD, Mac OSX, and Windows.</strong> Unfortunately, the EWWW Image Optimizer plugin doesn't work with " . htmlentities(PHP_OS) . ". Feel free to file a support request if you would like support for your operating system of choice.</p></div>";
}   

// checks the binary at $path against a list of valid md5sums
function ewww_image_optimizer_md5check($path) {
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

// test the given path ($path) to see if it returns a valid version string
// returns: version string if found, FALSE if not
function ewww_image_optimizer_tool_found($path, $tool) {
	//echo "<br> $path - $tool <br>";
	//if (empty($path)) { return FALSE; }
	switch($tool) {
		case 'j': // jpegtran
			//exec("$nice $jpegtran_path -copy $copy_opt -optimize -outfile $tempfile $file");
			//-switch should be something that doesn't exist
			//exec($path . ' -blah', $jpegtran_version); 
			//if (empty($jpegtran_version)) {
			//echo 'ahhahahahahahah<br>';
				exec($path . ' -v ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'sample.jpg 2>&1', $jpegtran_version);
			//}
			foreach ($jpegtran_version as $jout) { 
				if (preg_match('/Independent JPEG Group/', $jout)) {
					return $jout;
				}
			}
			break;
		case 'o': // optipng
			exec($path . ' -v', $optipng_version);
			if (!empty($optipng_version) && strpos($optipng_version[0], 'OptiPNG') === 0) {
				return $optipng_version[0];
			}
			break;
		case 'g': // gifsicle
			exec($path . ' --version', $gifsicle_version);
			if (!empty($gifsicle_version) && strpos($gifsicle_version[0], 'LCDF Gifsicle') === 0) {
				return $gifsicle_version[0];
			}
			break;
		case 'p': // pngout
			exec("$path 2>&1", $pngout_version);
			if (!empty($pngout_version) && strpos($pngout_version[0], 'PNGOUT') === 0) {
				return $pngout_version[0];
			}
			break;
		case 'i': // ImageMagick
			exec("$path -version", $convert_version);
			if (!empty($convert_version) && strpos($convert_version[0], 'ImageMagick')) {
				return $convert_version[0];
			}
			break;
		case 'f': // file
			exec("$path -v 2>&1", $file_version);
			if (!empty($file_version[1]) && preg_match('/magic/', $file_version[1])) {
				return $file_version[0];
			}
			break;
		case 'n': // nice
			exec("$path 2>&1", $nice_output);
			if (isset($nice_output) && preg_match('/usage/', $nice_output[0])) {
				return TRUE;
			} elseif (isset($nice_output) && preg_match('/^\d+$/', $nice_output[0])) {
				return TRUE;
			}
			break;
		case 't': // tar
			exec("$path --version", $tar_version);
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
	// TODO: separate the file checks into a separate function, and add php mime-type checking if feasible
	if (ewww_image_optimizer_tool_found('/usr/bin/file', 'f')) {
		$file = '/usr/bin/file';
	} elseif (ewww_image_optimizer_tool_found('file', 'f')) {
		$file = 'file';
	}
	$jpegtran = false;
	$optipng = false;
	$gifsicle = false;
	$pngout = false;
	// for Windows, everything must be in the wp-content/ewww folder, so that is all we check (unless some bright spark figures out how to put them in their system path on Windows...)
	if ('WINNT' == PHP_OS) {
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe')) {
			$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran.exe';
			if (ewww_image_optimizer_tool_found($jpt, 'j') && ewww_image_optimizer_md5check($jpt)) {
				$jpegtran = $jpt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe')) {
			$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng.exe';
			if (ewww_image_optimizer_tool_found($opt, 'o') && ewww_image_optimizer_md5check($opt)) {
				$optipng = $opt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe')) {
			$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle.exe';
			if (ewww_image_optimizer_tool_found($gpt, 'g') && ewww_image_optimizer_md5check($gpt)) {
				$gifsicle = $gpt;
			}
		}
		if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe')) {
			$ppt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe';
			if (ewww_image_optimizer_tool_found($ppt, 'p') && ewww_image_optimizer_md5check($ppt)) {
				$pngout = $ppt;
			}
		}
	} else {
	// first check for the jpegtran binary in the ewww tool folder
	$use_system = get_option('ewww_image_optimizer_skip_bundle');
	if (empty($file)) {
		$use_system = TRUE;
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran') && !$use_system) {
		$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran';
		exec("$file $jpt", $jpt_filetype);
		if (ewww_image_optimizer_md5check($jpt) && ((strpos($jpt_filetype[0], 'ELF') && strpos($jpt_filetype[0], 'executable')) || strpos($jpt_filetype[0], 'Mach-O universal binary'))) {
			if (ewww_image_optimizer_tool_found($jpt, 'j')) {
				$jpegtran = $jpt;
			}
		}
			
	}
	// if the standard jpegtran binary didn't work, see if the user custom compiled one and check that
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom') && !$jpegtran && !$use_system) {
		$jpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom';
		exec("$file $jpt", $jpt_filetype);
		if (filesize($jpt) > 15000 && ((strpos($jpt_filetype[0], 'ELF') && strpos($jpt_filetype[0], 'executable')) || strpos($jpt_filetype[0], 'Mach-O universal binary'))) {
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
		exec("$file $opt", $opt_filetype);
		if (ewww_image_optimizer_md5check($opt) && ((strpos($opt_filetype[0], 'ELF') && strpos($opt_filetype[0], 'executable')) || strpos($opt_filetype[0], 'Mach-O universal binary'))) {
			if (ewww_image_optimizer_tool_found($opt, 'o')) {
				$optipng = $opt;
			}
		}
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') && !$optipng && !$use_system) {
		$opt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom';
		exec("$file $opt", $opt_filetype);
		if (filesize($opt) > 15000 && ((strpos($opt_filetype[0], 'ELF') && strpos($opt_filetype[0], 'executable')) || strpos($opt_filetype[0], 'Mach-O universal binary'))) {
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
		exec("$file $gpt", $gpt_filetype);
		if (ewww_image_optimizer_md5check($gpt) && ((strpos($gpt_filetype[0], 'ELF') && strpos($gpt_filetype[0], 'executable')) || strpos($gpt_filetype[0], 'Mach-O universal binary'))) {
			if (ewww_image_optimizer_tool_found($gpt, 'g')) {
				$gifsicle = $gpt;
			}
		}
	}
	if (file_exists(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') && !$gifsicle && !$use_system) {
		$gpt = EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom';
		exec("$file $gpt", $gpt_filetype);
		if (filesize($gpt) > 15000 && ((strpos($gpt_filetype[0], 'ELF') && strpos($gpt_filetype[0], 'executable')) || strpos($gpt_filetype[0], 'Mach-O universal binary'))) {
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
		exec("$file $ppt", $ppt_filetype);
		if (ewww_image_optimizer_md5check($ppt) && ((strpos($ppt_filetype[0], 'ELF') && strpos($ppt_filetype[0], 'executable')) || strpos($ppt_filetype[0], 'Mach-O universal binary'))) {
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
	return array($jpegtran, $optipng, $gifsicle, $pngout);
}

// generates the source and destination paths for the executables that we bundle with the plugin based on the operating system
function ewww_image_optimizer_install_paths () {
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
	return array($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst);
}

// installs the executables that are bundled with the plugin
function ewww_image_optimizer_install_tools () {
	$toolfail = false;
	if (!is_dir(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) {
		if (!mkdir(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)) {
			echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>EWWW Image Optimizer couldn't create the tool folder: " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . ".</strong> Please adjust permissions or create the folder.</p></div>";
		}
	}
	list ($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst) = ewww_image_optimizer_install_paths();
	if (!file_exists($jpegtran_dst)) {
		if (!copy($jpegtran_src, $jpegtran_dst)) {
			$toolfail = true;
		}
	} else if (filesize($jpegtran_dst) != filesize($jpegtran_src)) {
		if (!copy($jpegtran_src, $jpegtran_dst)) {
			$toolfail = true;
		}
	}
	if (!file_exists($gifsicle_dst)) {
		if (!copy($gifsicle_src, $gifsicle_dst)) {
			$toolfail = true;
		}
	} else if (filesize($gifsicle_dst) != filesize($gifsicle_src)) {
		if (!copy($gifsicle_src, $gifsicle_dst)) {
			$toolfail = true;
		}
	}
	if (!file_exists($optipng_dst)) {
		if (!copy($optipng_src, $optipng_dst)) {
			$toolfail = true;
		}
	} else if (filesize($optipng_dst) != filesize($optipng_src)) {
		if (!copy($optipng_src, $optipng_dst)) {
			$toolfail = true;
		}
	}
	if (PHP_OS != 'WINNT') {
		$jpegtran_perms = substr(sprintf('%o', fileperms($jpegtran_dst)), -4);
		if ($jpegtran_perms != '0755') {
			if (!chmod($jpegtran_dst, 0755)) {
				$toolfail = true;
			}
		}
		$gifsicle_perms = substr(sprintf('%o', fileperms($gifsicle_dst)), -4);
		if ($gifsicle_perms != '0755') {
			if (!chmod($gifsicle_dst, 0755)) {
				$toolfail = true;
			}
		}
		$optipng_perms = substr(sprintf('%o', fileperms($optipng_dst)), -4);
		if ($optipng_perms != '0755') {
			if (!chmod($optipng_dst, 0755)) {
				$toolfail = true;
			}
		}
	}
	if ($toolfail) {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>EWWW Image Optimizer couldn't install tools in " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . ".</strong> Please adjust permissions or create the folder. If you have installed the tools elsewhere on your system, check the option to 'Use system paths'. For more details, visit the <a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>Settings Page</a> or the <a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>Installation Instructions</a>.</p></div>";
	}
	$migrate_fail = false;
	if ($jpegtran_path = get_option('ewww_image_optimizer_jpegtran_path')) {
		if (file_exists($jpegtran_path)) {
			if (!copy($jpegtran_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran-custom', 0755)) {
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_jpegtran_path');
			}
		}
	}
	if ($optipng_path = get_option('ewww_image_optimizer_optipng_path')) {
		if (file_exists($optipng_path)) {
			if (!copy($optipng_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng-custom', 0755)) {
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_optipng_path');
			}
		}
	}
	if ($gifsicle_path = get_option('ewww_image_optimizer_gifsicle_path')) {
		if (file_exists($gifsicle_path)) {
			if (!copy($gifsicle_path, EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom') || !chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle-custom', 0755)) {
				$migrate_fail = true;
			} else {
				delete_option('ewww_image_optimizer_gifsicle_path');
			}
		}
	}
	if ($migrate_fail) {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='error'><p><strong>EWWW Image Optimizer attempted to move your custom-built binaries to " . htmlentities(EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . " but the operation was unsuccessful.</strong> Please adjust permissions or create the folder.</p></div>";
	}
}
		
// we check for safe mode and exec, then also direct the user where to go if they don't have the tools installed
function ewww_image_optimizer_notice_utils() {
	//echo "checking tools<br>";
	// query the php settings for safe mode
	if( ini_get('safe_mode') ){
		// display a warning to the user
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='error'><p><strong>PHP's Safe Mode is turned on. This plugin cannot operate in safe mode.</strong></p></div>";
	}
	// make sure the bundled tools are installed
	if(!get_option('ewww_image_optimizer_skip_bundle')) {
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
	wp_enqueue_script('common');
	// register all the EWWW IO settings
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_check');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_bundle');
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
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_resume');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_bulk_attachments');
	// set a few defaults
	add_option('ewww_image_optimizer_disable_pngout', TRUE);
	add_option('ewww_image_optimizer_optipng_level', 2);
	add_option('ewww_image_optimizer_pngout_level', 2);
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

// Retrieves jpg background fill setting, or returns null for png2jpg conversions
function ewww_image_optimizer_jpg_background () {
	// retrieve the user-supplied value for jpg background color
	$background = get_option('ewww_image_optimizer_jpg_background');
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

// require the file that does the bulk processing
require( dirname(__FILE__) . '/bulk.php' );

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
 * Manually restore a converted image
 */
function ewww_image_optimizer_restore() {
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
	// TODO: do a file_exists check instead, which should tell us that we have a full path, instead of this hackiness...
	// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
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
			// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
			// if the path given is not the absolute path
			if (FALSE === file_exists($file_path)) {
			//if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
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
	// if we don't already have this update attachment filter
	if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file'))
		// add the update saved file filter
		add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file', 10, 2);
	return;
}

// This is added as a filter on the metadata, only when an image is saved via the image editor
function ewww_image_optimizer_update_saved_file ($meta, $ID) {
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
	// check that the file exists
	if (FALSE === file_exists($file) || FALSE === is_file($file)) {
		// tell the user we couldn't find the file
		$msg = sprintf(__("Could not find <span class='code'>%s</span>", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		// send back the above message
		return array($file, $msg, $converted, $original);
	}

	// check that the file is writable
	if ( FALSE === is_writable($file) ) {
		// tell the user we can't write to the file
		$msg = sprintf(__("<span class='code'>%s</span> is not writable", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file);
		// send back the above message
		return array($file, $msg, $converted, $original);
	}
	// retrieve the wordpress upload directory location
	/*$upload_dir = wp_upload_dir();
	// do some cleanup on the upload location we retrieved
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	// see if the file path matches the upload directory
	$path_in_upload = stripos(realpath($file), realpath($upload_path));
	// see if the file path matches the location where wordpress is installed (for NextGEN and Grand FlAGallery)
	$path_in_wp = stripos(realpath($file), realpath(ABSPATH));
	// check that the file is within the WP uploads folder or the wordpress folder
	if (0 !== $path_in_upload && 0 !== $path_in_wp) {
		// tell the user they can only process images in the upload directory or the wordpress folder
		$msg = sprintf(__("<span class='code'>%s</span> must be within the wordpress or upload directory (<span class='code'>%s or %s</span>)", EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities($file), $upload_path, ABSPATH);
		// send back the above message
		return array($file, $msg, $converted, $original);
	}*/
	// use finfo functions when available
	if (function_exists('finfo_file') && defined('FILEINFO_MIME')) {
		// create a finfo resource
		$finfo = finfo_open(FILEINFO_MIME);
		// retrieve the mimetype
		$type = explode(';', finfo_file($finfo, $file));
		$type = $type[0];
		finfo_close($finfo);
	// see if we can use the getimagesize function
	} elseif (function_exists('getimagesize')) {
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
		$type = 'Missing finfo_file(), getimagesize() and mime_content_type() PHP functions';
	}
	// get the utility paths
	list ($jpegtran_path, $optipng_path, $gifsicle_path, $pngout_path) = ewww_image_optimizer_path_check();
	//$jpegtran_path = EWWW_IMAGE_OPTIMIZER_JPEGTRAN;
	//$optipng_path = EWWW_IMAGE_OPTIMIZER_OPTIPNG;
	//$gifsicle_path = EWWW_IMAGE_OPTIMIZER_GIFSICLE;
	//$pngout_path = EWWW_IMAGE_OPTIMIZER_PNGOUT;
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
	// if the full-size image was converted
	if ($converted) {
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
		// and set $file to the new filename
		$file = $refile;
		$original = $file;
	}
	// run the appropriate optimization/conversion for the mime-type
	switch($type) {
		case 'image/jpeg':
			// if jpg2png conversion is enabled, and this image is in the wordpress media library
			if (get_option('ewww_image_optimizer_jpg_to_png') && $gallery_type == 1) {
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
			if (get_option('ewww_image_optimizer_disable_jpegtran')) {
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
			//echo "filename: $file<br>";
			//echo "original size: $orig_size <br>";
			// if the conversion process is turned ON, or if this is a resize and the full-size was converted
			if ($convert || $converted) {
				// retrieve version info for ImageMagick
				//exec('convert -version', $convert_version);
				if (ewww_image_optimizer_tool_found('convert', 'i')) {
					$convert_path = 'convert';
				} elseif (ewww_image_optimizer_tool_found('/usr/bin/convert', 'i')) {
					$convert_path = '/usr/bin/convert';
				} elseif (ewww_image_optimizer_tool_found('/usr/local/bin/convert', 'i')) {
					$convert_path = '/usr/local/bin/convert';
				}
				// convert the JPG to PNG (try with GD if possible, 'convert' if not)
				if (ewww_image_optimizer_gd_support()) {
					imagepng(imagecreatefromjpeg($file), $pngfile);
				} elseif (!empty($convert_path)) {
				//} elseif (!empty($convert_version) && strpos($convert_version[0], 'ImageMagick')) {
					exec("$convert_path $file -strip $pngfile");
				}
				// if pngout isn't disabled
				if (!get_option('ewww_image_optimizer_disable_pngout')) {
					// retrieve the pngout optimization level
					$pngout_level = get_option('ewww_image_optimizer_pngout_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						// run pngout on the new PNG
						exec("$nice $pngout_path -s$pngout_level -q $pngfile");
					}
				}
				// if optipng isn't disabled
				if (!get_option('ewww_image_optimizer_disable_optipng')) {
					// retrieve the optipng optimization level
					$optipng_level = get_option('ewww_image_optimizer_optipng_level');
					// if the PNG file was created
					if (file_exists($pngfile)) {
						// run optipng on the new PNG
						exec("$nice $optipng_path -o$optipng_level -quiet $pngfile");
					}
				}
				// find out the size of the new PNG file
				$png_size = filesize($pngfile);
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
				// Check if shell_exec() is disabled
				//$disabled = ini_get('disable_functions');
				//if(strpos($disabled, 'shell_exec') !== FALSE){
					// run jpegtran - non-progressive
					exec("$nice $jpegtran_path -copy $copy_opt -optimize -outfile $tempfile $file");
					// run jpegtran - progressive
					exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive -outfile $progfile $file");
					// check the filesize of the non-progressive JPG
					$non_size = filesize($tempfile);
					// check the filesize of the progressive JPG
					$prog_size = filesize($progfile);
				/*} else {
					// run jpegtran - non-progressive
					$tempdata = shell_exec("$nice $jpegtran_path -copy $copy_opt -optimize $file");
					$non_size = file_put_contents($tempfile, $tempdata);
					// run jpegtran - progressive
					$progdata = shell_exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive $file");
					$prog_size = file_put_contents($progfile, $progdata);
				}*/
			//echo "temp filename: $tempfile<br>";
			//echo "temp size: $non_size <br>";
			//echo "progressive filename: $progfile<br>";
			//echo "prog size: $prog_size <br>";
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
			//echo "$result <br>";
			break;
		case 'image/png':
			// png2jpg conversion is turned on, and the image is in the wordpress media library
			if (get_option('ewww_image_optimizer_png_to_jpg') && $gallery_type == 1) {
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
			if (get_option('ewww_image_optimizer_disable_optipng') && get_option('ewww_image_optimizer_disable_pngout')) {
				// tell the user all PNG tools are disabled
				$result = 'png tools are disabled';
				// turn off optimization
				$optimize = false;
			// if the utility checking is on, optipng is enabled, but optipng cannot be found
			} elseif (!$skip_optipng_check && !$optipng_path && !get_option('ewww_image_optimizer_disable_optipng')) {
				// tell the user optipng is missing
				$result = '<em>optipng</em> is missing';
				// turn off optimization
				$optimize = false;
			// if the utility checking is on, pngout is enabled, but pngout cannot be found
			} elseif (!$skip_pngout_check && !$pngout_path && !get_option('ewww_image_optimizer_disable_pngout')) {
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
					// Check if shell_exec() is disabled
					//$disabled = ini_get('disable_functions');
					//if(strpos($disabled, 'shell_exec') !== FALSE){
						// run jpegtran - non-progressive
						exec("$nice $jpegtran_path -copy $copy_opt -optimize -outfile $tempfile $jpgfile");
						// run jpegtran - progressive
						exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive -outfile $progfile $jpgfile");
					/*} else {
						// run jpegtran - non-progressive
						$tempdata = shell_exec("$nice $jpegtran_path -copy $copy_opt -optimize $jpgfile");
						file_put_contents($tempfile, $tempdata);
						// run jpegtran - progressive
						$progdata = shell_exec("$nice $jpegtran_path -copy $copy_opt -optimize -progressive $jpgfile");
						file_put_contents($progfile, $progdata);
					}*/
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
			if (get_option('ewww_image_optimizer_disable_gifsicle')) {
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
				if (!get_option('ewww_image_optimizer_disable_pngout') && $pngout_path) {
					// retrieve the pngout optimization level
					$pngout_level = get_option('ewww_image_optimizer_pngout_level');
					// run pngout on the file
					exec("$nice $pngout_path -s$pngout_level -q $file $pngfile");
				}
				// if optipng is enabled
				if (!get_option('ewww_image_optimizer_disable_optipng') && $optipng_path) {
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
	// don't do anything else if the attachment has no metadata
	if (!isset($meta['file'])) {
		return $meta;
	}
	if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_saved_file')) {
		$gallery_type = 1;
	} else {
		$gallery_type = 5;
	}
	// get the filepath from the metadata
	$file_path = $meta['file'];
	// store absolute paths for older wordpress versions
	$store_absolute_path = true;
	// retrieve the location of the wordpress upload folder
	$upload_dir = wp_upload_dir();
	// retrieve the path of the upload folder
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	// TODO: do a file_exists check instead, which should tell us that we have a full path, instead of this hackiness...
	// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
	// if the path given is not the absolute path
	if (FALSE === file_exists($file_path)) {
		// don't store absolute paths
		$store_absolute_path = false;
		// generate the absolute path
		$file_path =  $upload_path . $file_path;
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
		$meta = wp_get_attachment_metadata($id);
		//	echo "<!-- \n";
		//	print_r($meta);
		//	echo "\n -->";
		// if the filepath isn't set in the metadata
		if(empty($meta['file'])){
			if (isset($meta['file'])) {
				unset($meta['file']);
				if (strpos($meta['ewww_image_optimizer'], 'Could not find') === 0) {
					unset($meta['ewww_image_optimizer']);
				}
				wp_update_attachment_metadata($id, $meta);
			}
			$msg = '<br>Metadata is missing file path.';
			print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
			return;
		}
		// retrieve the filepath from the metadata
		$file_path = $meta['file'];
		// retrieve the wordpress upload folder
		$upload_dir = wp_upload_dir();
		// retrieve the wordpress upload folder path
		$upload_path = trailingslashit( $upload_dir['basedir'] );
	// TODO: do a file_exists check instead, which should tell us that we have a full path, instead of this hackiness...
		// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
		// if the path given is not the absolute path
		if (FALSE === file_exists($file_path)) {
		//if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
			// find the absolute path
			$file_path = $upload_path . $file_path;
		}
		$msg = '';
		// use finfo functions when available
		if (function_exists('finfo_file')) {
			// create a finfo resource
			$finfo = finfo_open(FILEINFO_MIME);
			// retrieve the mimetype
			$type = explode(';', finfo_file($finfo, $file_path));
			$type = $type[0];
			finfo_close($finfo);
		} elseif(function_exists('getimagesize')){
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
			$msg = '<br>finfo_file(), getimagesize() and mime_content_type() PHP functions are missing';
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
		if (isset($meta['ewww_image_optimizer']) && !empty($meta['ewww_image_optimizer']) ) {
			// output the optimizer results
			print $meta['ewww_image_optimizer'];
			// output the filesize
			print "<br>Image Size: $file_size";
			// output a link to re-optimize manually
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
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

// retrieves the pngout linux package with wget, unpacks it with tar, 
// copies the appropriate version to the plugin folder, and sends the user back where they came from
function ewww_image_optimizer_install_pngout () {
	// TODO: see if we can finetune our error messages a bit
	if ( FALSE === current_user_can('install_plugins') ) {
		wp_die(__('You don\'t have permission to install image optimizer utilities.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	if (PHP_OS != 'WINNT' && ewww_image_optimizer_tool_found('tar', 't')) {
		$tar = 'tar';
	} elseif (PHP_OS != 'WINNT' && ewww_image_optimizer_tool_found('/usr/bin/tar', 't')) {
		$tar = '/usr/bin/tar';
	}
	if (PHP_OS == 'Linux') {
		$os_string = 'linux';
	}
	if (PHP_OS == 'FreeBSD') {
		$os_string = 'bsd';
	}
	$latest = '20130221';
	if (PHP_OS == 'Linux' || PHP_OS == 'FreeBSD') {
		if (!file_exists(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static.tar.gz')) {
			ewww_image_optimizer_download_file('http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-' . $os_string . '-static.tar.gz', EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static.tar.gz');
		}
		$arch_type = php_uname('m');//$_REQUEST['arch'];
		/*if (PHP_OS == 'FreeBSD' && $arch_type == 'x86_64') {
			$arch_type = 'amd64';
		}*/
		exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static');
		rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
		/*switch ($arch_type) {
			case 'i386':
				exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-20120530-' . $os_string . '-static/i386/pngout-static');
				rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static/i386/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
				break;
			case 'i686':
				exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-20120530-' . $os_string . '-static/i686/pngout-static');
				rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static/i686/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
				break;
			case 'athlon':
				exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-20120530-' . $os_string . '-static/athlon/pngout-static');
				rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static/athlon/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
				break;
			case 'pentium4':
				exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-20120530-' . $os_string . '-static/pentium4/pngout-static');
				rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static/pentium4/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
				break;
			case 'x86_64':
				exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-20120530-' . $os_string . '-static/x86_64/pngout-static');
				rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static/x86_64/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
				break;
			case 'amd64':
				exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-20120530-' . $os_string . '-static/amd64/pngout-static');
				rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-20120530-' . $os_string . '-static/amd64/pngout-static', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
				break;
		}*/
		chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755);
		//exec(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static 2>&1', $pngout_version);
		$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 'p');
	}
	if (PHP_OS == 'Darwin') {
	// from http://static.jonof.id.au/dl/kenutils/pngout-20120530-darwin.tar.gz
		if (!file_exists(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin.tar.gz')) {
			ewww_image_optimizer_download_file('http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-darwin.tar.gz', EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin.tar.gz');
		}
		exec("$tar xzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin.tar.gz -C ' . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . ' pngout-' . $latest . '-darwin/pngout');
		rename(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'pngout-' . $latest . '-darwin/pngout', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static');
		chmod(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 0755);
		$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static', 'p');
		//exec(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static 2>&1', $pngout_version);
	}
	if (PHP_OS == 'WINNT') {
		ewww_image_optimizer_download_file('http://advsys.net/ken/util/pngout.exe', EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe');
		$pngout_version = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe', 'p');
		//exec(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout.exe 2>&1', $pngout_version);
	}
	if (!empty($pngout_version)) {
		$sendback = wp_get_referer();
		$sendback = preg_replace('/\&pngout\=\w+/', '', $sendback) . "&pngout=success";
	}
	if (!isset($sendback)) {
		$sendback = wp_get_referer();
		$sendback = preg_replace('/\&pngout\=\w+/', '', $sendback) . "&pngout=failed";
	}
	wp_redirect($sendback);
	exit(0);
}
// TODO: add jpegtran permissions to debug
// displays the EWWW IO options and provides one-click install for the optimizer utilities
function ewww_image_optimizer_options () {
	if (isset($_REQUEST['pngout'])) {
		if ($_REQUEST['pngout'] == 'success') { ?>
			<div id='ewww-image-optimizer-pngout-success' class='updated fade'>
				<p>pngout was successfully installed, check the Plugin Status area for version information.</p>
			</div>
<?php		}
		if ($_REQUEST['pngout'] == 'failed') { ?>
			<div id='ewww-image-optimizer-pngout-failure' class='error'>
				<p>pngout was not installed, check permissions on the wp-content/ewww folder.</p>
			</div>
<?php		} ?>
		<script type='text/javascript'>
			jQuery(document).ready(function($) {$('.fade').fadeTo(5000,1).fadeOut(3000);});
		</script>
<?php	} ?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>EWWW Image Optimizer Settings</h2>
		<p><a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/">Plugin Home Page</a> |
		<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/">Installation Instructions</a> | 
		<a href="http://wordpress.org/support/plugin/ewww-image-optimizer">Plugin Support</a> | 
		<a id="debug" href="#">Debug (see below)</a></p>
		<div id="status" style="border: 1px solid #ccc; padding: 0 8px; border-radius: 12px;">
			<h3>Plugin Status</h3>
			<?php
			if (get_option('ewww_image_optimizer_skip_bundle')) { ?>
				<p>If updated versions are available below you may either download the newer versions and install them yourself, or uncheck "Use system paths" and install them automatically.<br />
			<?php } else { ?>
				<p>If updated versions are available below, you may need to enable write permission on the <i>wp-content/ewww</i> folder to use the automatic installs.<br />
			<?php } ?>
			<i>*Updates are optional, but may contain increased optimization or security patches</i></p>
			<?php
			list ($jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst) = ewww_image_optimizer_install_paths();
			if (!get_option('ewww_image_optimizer_disable_jpegtran')) {
				echo "\n";
				echo '<b>jpegtran: </b>';
				$jpegtran_installed = ewww_image_optimizer_tool_found(EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'j');
				if (!empty($jpegtran_installed) && preg_match('/version 9/', $jpegtran_installed)) {
					echo '<span style="color: green; font-weight: bolder">OK</span>&emsp;version: ' . $jpegtran_installed . '<br />'; 
				} elseif (!empty($jpegtran_installed)) {
					echo '<span style="color: orange; font-weight: bolder">UPDATE AVAILABLE</span>*&emsp;<b>Copy</b> executable from ' . $jpegtran_src . ' to ' . $jpegtran_dst . ' or to a system path (like /usr/local/bin), OR <a href="http://www.ijg.org/"><b>Download</b> jpegtran source</a>&emsp;<b>version:</b> ' . $jpegtran_installed . '<br />';
				} else { 
					echo '<span style="color: red; font-weight: bolder">MISSING</span>&emsp;<b>Copy</b> executable from ' . $jpegtran_src . ' to ' . $jpegtran_dst . ' or a system path (like /usr/local/bin), OR <a href="http://www.ijg.org/"><b>Download</b> jpegtran source</a><br />';
				}
			}
			echo "\n";
			if (!get_option('ewww_image_optimizer_disable_optipng')) {
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
			if (!get_option('ewww_image_optimizer_disable_gifsicle')) {
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
			if (!get_option('ewww_image_optimizer_disable_pngout')) {
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
			//echo 'Operating System: ' . PHP_OS;
			?></p>
			<div id="debuginfo" style="display:none; word-wrap: break-word;"><h3>Debug Info</h3><p><?php
				echo '<b>jpegtran path:</b> ' . EWWW_IMAGE_OPTIMIZER_JPEGTRAN . '<br />';
				echo '<b>optipng path:</b> ' . EWWW_IMAGE_OPTIMIZER_OPTIPNG . '<br />';
				echo '<b>gifsicle path:</b> ' . EWWW_IMAGE_OPTIMIZER_GIFSICLE . '<br />';
				echo '<b>pngout path:</b> ' . EWWW_IMAGE_OPTIMIZER_PNGOUT . '<br />';
				echo '<b>disabled functions:</b> ' . $disabled . '<br />';
				if (PHP_OS != 'WINNT') {
					$jpegtran_perms = substr(sprintf('%o', fileperms(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran')), -4);
					$gifsicle_perms = substr(sprintf('%o', fileperms(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle')), -4);
					$optipng_perms = substr(sprintf('%o', fileperms(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng')), -4);
					$ewww_perms = substr(sprintf('%o', fileperms(EWWW_IMAGE_OPTIMIZER_TOOL_PATH)), -4);
					echo '<b>bundled jpegtran permissions:</b> ' . $jpegtran_perms . '<br />';
					echo '<b>bundled gifsicle permissions:</b> ' . $gifsicle_perms . '<br />';
					echo '<b>bundled optipng permissions:</b> ' . $optipng_perms . '<br />';
					echo '<b>wp-content/ewww permissions:</b> ' . $ewww_perms . '<br />';
				}
				echo '<b>jpegtran checksum:</b> ' . md5_file(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'jpegtran') . '<br />';
				echo '<b>gifsicle checksum:</b> ' . md5_file(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'gifsicle') . '<br />';
				echo '<b>optipng checksum:</b> ' . md5_file(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'optipng') . '<br />';
				echo '<b>pngout checksum:</b> ' . md5_file(EWWW_IMAGE_OPTIMIZER_TOOL_PATH . 'pngout-static') . '<br />';
				echo '<b>user:</b> ' . exec('/usr/bin/whoami') . '<br />';
				echo '<b>Operating environment:</b> ' . php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('v') . ' ' . php_uname('m');
			?></p></div>
<script>
jQuery("#debug").click(function () {
  jQuery("#debuginfo").toggle();
});
</script>
		</div>
		<form method="post" action="options.php">
			<?php settings_fields('ewww_image_optimizer_options'); ?>
			<h3>General Settings</h3>
			<p>The plugin performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, pngout, and gifsicle. In some rare cases, these checks may erroneously report that you are missing the required utilities even though you have them installed.</p>
			<table class="form-table">
				<tr><td><label for="ewww_image_optimizer_skip_bundle">Use system paths</label></td><td><input type="checkbox" id="ewww_image_optimizer_skip_bundle" name="ewww_image_optimizer_skip_bundle" value="true" <?php if (get_option('ewww_image_optimizer_skip_bundle') == TRUE) { ?>checked="true"<?php } ?> /> If you have already installed the utilities in a system location, such as /usr/local/bin or /usr/bin, use this to force the plugin to use those versions and skip the auto-installers.</td></tr>
				<tr><td><label for="ewww_image_optimizer_skip_check">Skip utils check</label></td><td><input type="checkbox" id="ewww_image_optimizer_skip_check" name="ewww_image_optimizer_skip_check" value="true" <?php if (get_option('ewww_image_optimizer_skip_check') == TRUE) { ?>checked="true"<?php } ?> /> <i>*DEPRECATED - please uncheck this and report any errors in the support forum.</i></td></tr>
				<tr><td><label for="ewww_image_optimizer_disable_jpegtran">disable jpegtran</label></td><td><input type="checkbox" id="ewww_image_optimizer_disable_jpegtran" name="ewww_image_optimizer_disable_jpegtran" <?php if (get_option('ewww_image_optimizer_disable_jpegtran') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><td><label for="ewww_image_optimizer_disable_optipng">disable optipng</label></td><td><input type="checkbox" id="ewww_image_optimizer_disable_optipng" name="ewww_image_optimizer_disable_optipng" <?php if (get_option('ewww_image_optimizer_disable_optipng') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><td><label for="ewww_image_optimizer_disable_pngout">disable pngout</label></td><td><input type="checkbox" id="ewww_image_optimizer_disable_pngout" name="ewww_image_optimizer_disable_pngout" <?php if (get_option('ewww_image_optimizer_disable_pngout') == TRUE) { ?>checked="true"<?php } ?> /></td><tr>
				<tr><td><label for="ewww_image_optimizer_disable_gifsicle">disable gifsicle</label></td><td><input type="checkbox" id="ewww_image_optimizer_disable_gifsicle" name="ewww_image_optimizer_disable_gifsicle" <?php if (get_option('ewww_image_optimizer_disable_gifsicle') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
			</table>
			<h3>Optimization settings</h3>
			<table class="form-table">
				<tr><td><label for="ewww_image_optimizer_jpegtran_copy">Remove JPG metadata</label></td><td><input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (get_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> This wil remove ALL metadata (EXIF and comments)</td></tr>
				<tr><td><label for="ewww_image_optimizer_optipng_level">optipng optimization level</label></td>
				<td><select id="ewww_image_optimizer_optipng_level" name="ewww_image_optimizer_optipng_level">
				<option value="1"<?php if (get_option('ewww_image_optimizer_optipng_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: 1 trial</option>
				<option value="2"<?php if (get_option('ewww_image_optimizer_optipng_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: 8 trials</option>
				<option value="3"<?php if (get_option('ewww_image_optimizer_optipng_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: 16 trials</option>
				<option value="4"<?php if (get_option('ewww_image_optimizer_optipng_level') == 4) { echo ' selected="selected"'; } ?>>Level 4: 24 trials</option>
				<option value="5"<?php if (get_option('ewww_image_optimizer_optipng_level') == 5) { echo ' selected="selected"'; } ?>>Level 5: 48 trials</option>
				<option value="6"<?php if (get_option('ewww_image_optimizer_optipng_level') == 6) { echo ' selected="selected"'; } ?>>Level 6: 120 trials</option>
				<option value="7"<?php if (get_option('ewww_image_optimizer_optipng_level') == 7) { echo ' selected="selected"'; } ?>>Level 7: 240 trials</option>
				</select> (default=2) - <i>According to the author of optipng, 10 trials should satisfy most people, 30 trials should satisfy everyone.</i></td></tr>
				<tr><td><label for="ewww_image_optimizer_pngout_level">pngout optimization level</label></td>
				<td><select id="ewww_image_optimizer_pngout_level" name="ewww_image_optimizer_pngout_level">
				<option value="0"<?php if (get_option('ewww_image_optimizer_pngout_level') == 0) { echo ' selected="selected"'; } ?>>Level 0: Xtreme! (Slowest)</option>
				<option value="1"<?php if (get_option('ewww_image_optimizer_pngout_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: Intense (Slow)</option>
				<option value="2"<?php if (get_option('ewww_image_optimizer_pngout_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: Longest Match (Fast)</option>
				<option value="3"<?php if (get_option('ewww_image_optimizer_pngout_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: Huffman Only (Faster)</option>
			</select> (default=2) - <i>If you have CPU cycles to spare, go with level 0</i></td></tr>
			</table>
			<h3>Conversion Settings</h3>
			<p><i>Conversion settings do not apply to NextGEN or GRAND FlAGallery.</i><br />
				<b>NOTE:</b> Converting images does not update any posts that contain those images. You will need to manually update your image urls after you convert any images.</p>
			<table class="form-table">
				<tr><td><label for="ewww_image_optimizer_delete_originals">Delete originals</label></td><td><input type="checkbox" id="ewww_image_optimizer_delete_originals" name="ewww_image_optimizer_delete_originals" <?php if (get_option('ewww_image_optimizer_delete_originals') == TRUE) { ?>checked="true"<?php } ?> /> This will remove the original image from the server after a successful conversion.</td></tr>
				<tr><td><label for="ewww_image_optimizer_jpg_to_png">enable <b>JPG</b> to <b>PNG</b> conversion</label></td><td><input type="checkbox" id="ewww_image_optimizer_jpg_to_png" name="ewww_image_optimizer_jpg_to_png" <?php if (get_option('ewww_image_optimizer_jpg_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <b>WARNING:</b> Removes metadata! Requires GD support in PHP or 'convert' from ImageMagick and should be used sparingly. PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.</td></tr>
				<tr><td><label for="ewww_image_optimizer_png_to_jpg">enable <b>PNG</b> to <b>JPG</b> conversion</label></td><td><input type="checkbox" id="ewww_image_optimizer_png_to_jpg" name="ewww_image_optimizer_png_to_jpg" <?php if (get_option('ewww_image_optimizer_png_to_jpg') == TRUE) { ?>checked="true"<?php } ?> /> <b>WARNING:</b> This is not a lossless conversion and requires GD support in PHP or the 'convert' utility provided by ImageMagick. JPG is generally much better than PNG for photographic use because it compresses the image and discards data. JPG does not support transparency, so we don't convert PNGs with transparency.</td></tr>
				<tr><td><label for="ewww_image_optimizer_jpg_background">JPG background color</label></td><td>#<input type="text" id="ewww_image_optimizer_jpg_background" name="ewww_image_optimizer_jpg_background" style="width: 60px" value="<?php echo ewww_image_optimizer_jpg_background(); ?>" /> <span style="padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #<? echo ewww_image_optimizer_jpg_background(); ?>">&nbsp;</span> HEX format (#123def). This is used only if the PNG has transparency or leave it blank to skip PNGs with transparency.</td></tr>
				<tr><td><label for="ewww_image_optimizer_jpg_quality">JPG quality level</label></td><td><input type="text" id="ewww_image_optimizer_jpg_quality" name="ewww_image_optimizer_jpg_quality" style="width: 40px" value="<?php echo ewww_image_optimizer_jpg_quality(); ?>" /> Valid values are 1-100. If left blank, the conversion process will attempt to set the optimal quality level or default to 92. Remember, this is a lossy conversion, so you are losing pixels, and it is not recommended to actually set the level here unless you want noticable loss of image quality.</td></tr>
				<tr><td><label for="ewww_image_optimizer_gif_to_png">enable <b>GIF</b> to <b>PNG</b> conversion</label></td><td><input type="checkbox" id="ewww_image_optimizer_gif_to_png" name="ewww_image_optimizer_gif_to_png" <?php if (get_option('ewww_image_optimizer_gif_to_png') == TRUE) { ?>checked="true"<?php } ?> /> PNG is generally much better than GIF, but doesn't support animated images, so we don't convert those.</td></tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>
		</form>
	</div>
	<?php
}

