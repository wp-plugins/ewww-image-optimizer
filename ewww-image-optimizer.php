<?php
/**
 * Integrate Linux image optimizers into WordPress.
 * @version 1.0.2
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://www.shanebishop.net/ewww-image-optimizer/
Description: Reduce image file sizes and improve performance using Linux image optimizers within WordPress. Uses jpegtran, optipng, and gifsicle.
Author: Shane Bishop
Version: 1.0.2
Author URI: http://www.shanebishop.net/
License: GPLv3
*/

/**
 * Constants
 */
define('EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww_image_optimizer');
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_DIR', dirname(plugin_basename(__FILE__)));

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
add_action('manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2);
add_action('admin_init', 'ewww_image_optimizer_admin_init');
add_action('admin_action_ewww_image_optimizer_manual', 'ewww_image_optimizer_manual');

/**
 * Check if system requirements are met
 */
if('Linux' != PHP_OS && 'Darwin' != PHP_OS) {
    add_action('admin_notices', 'ewww_image_optimizer_notice_os');
    define('EWWW_IMAGE_OPTIMIZER_PNG', false);
    define('EWWW_IMAGE_OPTIMIZER_GIF', false);
    define('EWWW_IMAGE_OPTIMIZER_JPG', false);
}else{
    add_action('admin_notices', 'ewww_image_optimizer_notice_utils');
}   

function ewww_image_optimizer_notice_os() {
    echo "<div id='ewww-image-optimizer-warning-os' class='updated fade'><p><strong>EWWW Image Optimizer isn't supported on your server.</strong> Unfortunately, the EWWW Image Optimizer plugin doesn't work with " . htmlentities(PHP_OS) . ".</p></div>";
}   

function ewww_image_optimizer_notice_utils() {
    $jpegtran_path = get_option('ewww_image_optimizer_jpegtran_path');
    if(!$jpegtran_path) {
        $jpegtran_path = 'jpegtran';
    }
    $optipng_path = get_option('ewww_image_optimizer_optipng_path');
    if(!$optipng_path) {
        $optipng_path = 'optipng';
    }
    $gifsicle_path = get_option('ewww_image_optimizer_gifsicle_path');
    if(!$gifsicle_path) {
        $gifsicle_path = 'gifsicle';
    }

    $required = array(
        'optipng' => $optipng_path,
        'jpegtran' => $jpegtran_path,
        'gifsicle' => $gifsicle_path,
    );
   
    // To skip binary checking, you can visit the EWWW Image Optimizer options page
    if(get_option('ewww_image_optimizer_skip_check') == TRUE){
        $skip = true;
    } else {
        $skip = false;
    }
 
    $missing = array();

    foreach($required as $key => $req){
        $result = trim(exec('which ' . $req));
        if(!$skip && empty($result)){
            $missing[] = $key;
            define('EWWW_IMAGE_OPTIMIZER_' . $key, false);
        }else{
            define('EWWW_IMAGE_OPTIMIZER_' . $key, true);
        }
    }
    
    $msg = implode(', ', $missing);

    if(!empty($msg)){
        echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>EWWW Image Optimizer requires <a href='http://jpegclub.org/jpegtran/'>jpegtran</a>, <a href='http://optipng.sourceforge.net/'>optipng</a>, and <a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>.</strong> You are missing: $msg. Please install via the <a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>Installation Instructions</a> and update paths (if necessary) on the <a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>Settings Page</a>.</p></div>";
    }

    // Check if exec is disabled
    $disabled = explode(', ', ini_get('disable_functions'));
    if(in_array('exec', $disabled)){
        echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>EWWW Image Optimizer requires exec().</strong> Your system administrator has disabled this function.</p></div>";
    }

}

/**
 * Plugin admin functions
 */
function ewww_image_optimizer_admin_init() {
	load_plugin_textdomain(EWWW_IMAGE_OPTIMIZER_DOMAIN);
	wp_enqueue_script('common');
        register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_check');
        register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_gifs');
        register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_copy');
        register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_path');
        register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_optipng_path');
        register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_gifsicle_path');
}

function ewww_image_optimizer_admin_menu() {
  add_media_page( 'Bulk Optimize', 'Bulk Optimize', 'edit_others_posts', 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview');

  add_options_page(
    'EWWW Image Optimizer',           //Title
    'EWWW Image Optimizer',           //Sub-menu title
    'manage_options',               //Security
    __FILE__,                       //File to open
    'ewww_image_optimizer_options'    //Function to call
  );

}
add_action( 'admin_menu', 'ewww_image_optimizer_admin_menu' );

function ewww_image_optimizer_settings_link($links) {
  $settings_link = '<a href="options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php">Settings</a>';
  array_unshift ( $links, $settings_link );
  return $links;
}
$plugin = plugin_basename ( __FILE__ );
add_filter ( "plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link' );

function ewww_image_optimizer_bulk_preview() {
  if ( function_exists( 'apache_setenv' ) ) {
    @apache_setenv('no-gzip', 1);
  }
  @ini_set('output_buffering','on');
  @ini_set('zlib.output_compression', 0);
  @ini_set('implicit_flush', 1);
  $attachments = get_posts( array(
    'numberposts' => -1,
    'post_type' => 'attachment',
    'post_mime_type' => 'image'
  ));
  require( dirname(__FILE__) . '/bulk.php' );
}

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	if ( FALSE === current_user_can('upload_files') ) {
		wp_die(__('You don\'t have permission to work with uploaded files.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}

	if ( FALSE === isset($_GET['attachment_ID'])) {
		wp_die(__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}

	$attachment_ID = intval($_GET['attachment_ID']);

	$original_meta = wp_get_attachment_metadata( $attachment_ID );
	$new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_ID );
	wp_update_attachment_metadata( $attachment_ID, $new_meta );

	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

/**
 * Process an image.
 *
 * Returns an array of the $file $results.
 *
 * @param   string $file            Full absolute path to the image file
 * @returns array
 */
function ewww_image_optimizer($file, $attach_id) {
	// don't run on localhost, IPv4 and IPv6 checks
	// if( in_array($_SERVER['SERVER_ADDR'], array('127.0.0.1', '::1')) )
	//	return array($file, __('Not processed (local file)', EWWW_IMAGE_OPTIMIZER_DOMAIN));

	// canonicalize path - disabled 2011-02-1 troubleshooting 'Could not find...' errors.
	// From the PHP docs: "The running script must have executable permissions on 
	// all directories in the hierarchy, otherwise realpath() will return FALSE."
	// $file_path = realpath($file);
	
	$file_path = $file;

	// check that the file exists
	if ( FALSE === file_exists($file_path) || FALSE === is_file($file_path) ) {
		$msg = sprintf(__("Could not find <span class='code'>%s</span>", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_path);
		return array($file, $msg);
	}

	// check that the file is writable
	if ( FALSE === is_writable($file_path) ) {
		$msg = sprintf(__("<span class='code'>%s</span> is not writable", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_path);
		return array($file, $msg);
	}

	// check that the file is within the WP_CONTENT_DIR
	$upload_dir = wp_upload_dir();
	$wp_upload_dir = $upload_dir['basedir'];
	$wp_upload_url = $upload_dir['baseurl'];
	if ( 0 !== stripos(realpath($file_path), realpath($wp_upload_dir)) ) {
		$msg = sprintf(__("<span class='code'>%s</span> must be within the content directory (<span class='code'>%s</span>)", EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities($file_path), $wp_upload_dir);

		return array($file, $msg);
	}

    if(function_exists('getimagesize')){
        $type = getimagesize($file_path);
        if(false !== $type){
            $type = $type['mime'];
        }
    }elseif(function_exists('mime_content_type')){
        $type = mime_content_type($file_path);
    }else{
        $type = 'Missing getimagesize() and mime_content_type() PHP functions';
    }

    $jpegtran_path = get_option('ewww_image_optimizer_jpegtran_path');
    if(!$jpegtran_path) {
        $jpegtran_path = 'jpegtran';
    }
    $optipng_path = get_option('ewww_image_optimizer_optipng_path');
    if(!$optipng_path) {
        $optipng_path = 'optipng';
    }
    $gifsicle_path = get_option('ewww_image_optimizer_gifsicle_path');
    if(!$gifsicle_path) {
        $gifsicle_path = 'gifsicle';
    }

    switch($type){
        case 'image/jpeg':
            $orig_size = filesize($file);
            $tempfile = $file . ".tmp";
            if(get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
                $copy_opt = 'none';
            } else {
                $copy_opt = 'all';
            }
            exec("$jpegtran_path -copy $copy_opt -optimize $file > $tempfile");
            $new_size = filesize($tempfile);
            if ($orig_size > $new_size) {
                exec("mv $tempfile $file");
                $result = "$file: $orig_size vs. $new_size";
            } else {
                exec("rm $tempfile");
                $result = "$file: unchanged";
            }
            break;
        case 'image/png':
            $orig_size = filesize($file);
            exec("$optipng_path -o3 -quiet $file");
            clearstatcache();
            $new_size = filesize($file);
            if ($orig_size > $new_size) {
                $result = "$file: $orig_size vs. $new_size";    
            } else {
                $result = "$file: unchanged";
            }
            break;
        case 'image/gif':
            // if someone was paying attention, and disabled gif processing
            //$skip_gifs = get_option('ewww_image_optimizer_skip_gifs');
            //if ($skip_gifs || $frames > 1) {
            //    $result = "animated";
            //} else {
            $orig_size = filesize($file);
            //    $newfile = str_replace('.gif', '.png', $file);
            exec("$gifsicle_path -b -O3 --careful $file");
            clearstatcache();
            $new_size = filesize($file);
            if ($orig_size > $new_size) {
                $result = "$file: $orig_size vs. $new_size";
            //        update_attached_file($attach_id, $newfile );
            //        $file = $newfile;
            } else {
                $result = "$file: unchanged";
            }
            //}
            break;
        default:
            return array($file, __('Unknown type: ' . $type, EWWW_IMAGE_OPTIMIZER_DOMAIN));
    }

    //$result = exec($command . ' ' . escapeshellarg($file));

    $result = str_replace($file . ': ', '', $result);

    if($result == 'unchanged') {
        return array($file, __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN));
    }
    if($result == 'animated') {
        return array($file, __('GIFs not supported', EWWW_IMAGE_OPTIMIZER_DOMAIN));
    }
    if(strpos($result, ' vs. ') !== false) {
        $s = explode(' vs. ', $result);
        
        $savings = intval($s[0]) - intval($s[1]);
        $savings_str = ewww_image_optimizer_format_bytes($savings, 1);
        $savings_str = str_replace(' ', '&nbsp;', $savings_str);

        $percent = 100 - (100 * ($s[1] / $s[0]));

        $results_msg = sprintf(__("Reduced by %01.1f%% (%s)", EWWW_IMAGE_OPTIMIZER_DOMAIN),
                     $percent,
                     $savings_str);

        return array($file, $results_msg);
    }

    return array($file, __('Bad response from optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN));
}


/**
 * Read the image paths from an attachment's meta data and process each image
 * with ewww_image_optimizer().
 *
 * This method also adds a `ewww_image_optimizer` meta key for use in the media library.
 *
 * Called after `wp_generate_attachment_metadata` is completed.
 */
function ewww_image_optimizer_resize_from_meta_data($meta, $ID = null) {
	$file_path = $meta['file'];
	$store_absolute_path = true;
	$upload_dir = wp_upload_dir();
	$upload_path = trailingslashit( $upload_dir['basedir'] );

	// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
	if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
		$store_absolute_path = false;
		$file_path =  $upload_path . $file_path;
	}

	list($file, $msg) = ewww_image_optimizer($file_path, $ID);

	$meta['file'] = $file;
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


	foreach($meta['sizes'] as $size => $data) {
		list($optimized_file, $results) = ewww_image_optimizer($base_dir . $data['file'], $ID);

		$meta['sizes'][$size]['file'] = str_replace($base_dir, '', $optimized_file);
		$meta['sizes'][$size]['ewww_image_optimizer'] = $results;
	}

	return $meta;
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
    if( $column_name == 'ewww-image-optimizer' ) {
        $data = wp_get_attachment_metadata($id);

        if(!isset($data['file'])){
            $msg = 'Metadata is missing file path.';
            print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
            return;
        }

        $file_path = $data['file'];
        $upload_dir = wp_upload_dir();
        $upload_path = trailingslashit( $upload_dir['basedir'] );

        // WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
        if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
            $file_path =  $upload_path . $file_path;
        }

        $msg = '';

        if(function_exists('getimagesize')){
            $type = getimagesize($file_path);
            if(false !== $type){
                $type = $type['mime'];
            }
        }elseif(function_exists('mime_content_type')){
            $type = mime_content_type($file_path);
        }else{
            $type = false;
            $msg = 'getimagesize() and mime_content_type() PHP functions are missing';
        }

        $valid = true;
        switch($type){
            case 'image/jpeg':
                if(EWWW_IMAGE_OPTIMIZER_JPG == false) {
                    $valid = false;
                    $msg = '<br>' . __('<em>jpegtran</em> is missing');
                }
                break; 
            case 'image/png':
                if(EWWW_IMAGE_OPTIMIZER_PNG == false) {
                    $valid = false;
                    $msg = '<br>' . __('<em>optipng</em> is missing');
                }
                break;
            case 'image/gif':
                if(EWWW_IMAGE_OPTIMIZER_GIF == false) {
                    $valid = false;
                    $msg = '<br>' . __('<em>gifsicle</em> is missing');
                }
                break;
            default:
                $valid = false;
        }

        if($valid == false) {
            print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
            return;
        }

        if ( isset($data['ewww_image_optimizer']) && !empty($data['ewww_image_optimizer']) ) {
            print $data['ewww_image_optimizer'];
            printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
                     $id,
                     __('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
        } else {
            print __('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
            printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
                     $id,
                     __('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
        }
    }
}

function ewww_image_optimizer_options () {
?>
    <div class="wrap">
        <div id="icon-options-general" class="icon32"><br /></div>
        <h2>EWWW Image Optimizer Settings</h2>
	<p><a href="http://shanebishop.net/ewww-image-optimizer/">Plugin Home Page</a> |
	<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/">Installation Instructions</a> | 
	<a href="http://wordpress.org/support/plugin/ewww-image-optimizer">Plugin Support</a></p>
        <p>EWWW Image Optimizer performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, and gifsicle.</p>
        <p>In some cases, these checks may erroneously report that you are missing the required utilities even though you have them installed.</p>
        <p>If you are on shared hosting, and have compiled the utilities in your home folder, you can provide the paths below.</p>
        <form method="post" action="options.php">
            <?php settings_fields('ewww_image_optimizer_options'); ?>
            <p>Do you want to skip the utils check?</p>
            <input type="checkbox" id="ewww_image_optimizer_skip_check" name="ewww_image_optimizer_skip_check" value="true" <?php if (get_option('ewww_image_optimizer_skip_check') == TRUE) { ?>checked="true"<?php } ?> /> <label for="ewww_image_optimizer_skip_check" />Skip utils check</label><br />
            <!--<input type="checkbox" id="ewww_image_optimizer_skip_gifs" name="ewww_image_optimizer_skip_gifs" value="true" <?php if (get_option('ewww_image_optimizer_skip_gifs') == TRUE) { ?>checked="true"<?php } ?> /> <label for="ewww_image_optimizer_skip_gifs" />Skip GIFs</label><br />-->
            <label>jpegtran path: <input type="text" id="ewww_image_optimizer_jpegtran_path" name="ewww_image_optimizer_jpegtran_path" value="<?php echo get_option('ewww_image_optimizer_jpegtran_path'); ?>" /></label><br />
            <input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (get_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> <label for="ewww_image_optimizer_jpegtran_copy" />Check this box to remove all metadata (EXIF & comments) from jpegs</label><br />
            <label>optipng path: <input type="text" id="ewww_image_optimizer_optipng_path" name="ewww_image_optimizer_optipng_path" value="<?php echo get_option('ewww_image_optimizer_optipng_path'); ?>" /></label><br />
            <label>gifsicle path: <input type="text" id="ewww_image_optimizer_gifsicle_path" name="ewww_image_optimizer_gifsicle_path" value="<?php echo get_option('ewww_image_optimizer_gifsicle_path'); ?>" /></label><br />
            <p class="submit">
                <input type="submit" class="button-primary" value="Save Changes" />
            </p>
        </form>
    </div>
<?php
}

