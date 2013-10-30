<?php
class EWWWIO_GD_Editor extends WP_Image_Editor_GD {
	protected function _save ($image, $filename = null, $mime_type = null) {
		global $ewww_debug;
		if (!defined('EWWW_IMAGE_OPTIMIZER_DOMAIN'))
			require_once(plugin_dir_path(__FILE__) . 'ewww-image-optimizer.php');
		if (!defined('EWWW_IMAGE_OPTIMIZER_JPEGTRAN'))
			ewww_image_optimizer_init();
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
	
                if ( ! $filename )
                        $filename = $this->generate_filename( null, null, $extension );
	
                if ( 'image/gif' == $mime_type ) {
                        if ( ! $this->make_image( $filename, 'imagegif', array( $image, $filename ) ) )
                                return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
                }
                elseif ( 'image/png' == $mime_type ) {
                        // convert from full colors to index colors, like original PNG.
                        if ( function_exists('imageistruecolor') && ! imageistruecolor( $image ) )
                                imagetruecolortopalette( $image, false, imagecolorstotal( $image ) );

                        if ( ! $this->make_image( $filename, 'imagepng', array( $image, $filename ) ) )
                                return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
                }
                elseif ( 'image/jpeg' == $mime_type ) {
                        if ( ! $this->make_image( $filename, 'imagejpeg', array( $image, $filename, apply_filters( 'jpeg_quality', $this->quality, 'image_resize' ) ) ) )
                                return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
                }
                else {
                        return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
                }

                // Set correct file permissions
                $stat = stat( dirname( $filename ) );
                $perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
                @ chmod( $filename, $perms );
		ewww_image_optimizer_aux_images_loop($filename, true);
		$ewww_debug = "$ewww_debug image editor (gd) saved: $filename <br>";
		$image_size = filesize($filename);
		$ewww_debug = "$ewww_debug image editor size: $image_size <br>";
		ewww_image_optimizer_debug_log();
                return array(
                        'path' => $filename,
                        'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
                        'width' => $this->size['width'],
                        'height' => $this->size['height'],
                        'mime-type'=> $mime_type,
                );
	}
}

class EWWWIO_Imagick_Editor extends WP_Image_Editor_Imagick {
	protected function _save( $image, $filename = null, $mime_type = null ) {
		global $ewww_debug;
		if (!defined('EWWW_IMAGE_OPTIMIZER_DOMAIN'))
			require_once(plugin_dir_path(__FILE__) . 'ewww-image-optimizer.php');
		if (!defined('EWWW_IMAGE_OPTIMIZER_JPEGTRAN'))
			ewww_image_optimizer_init();
	        list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
	
                if ( ! $filename )
                        $filename = $this->generate_filename( null, null, $extension );
	
                try {
                        // Store initial Format
                        $orig_format = $this->image->getImageFormat();

                        $this->image->setImageFormat( strtoupper( $this->get_extension( $mime_type ) ) );
                        $this->make_image( $filename, array( $image, 'writeImage' ), array( $filename ) );

                        // Reset original Format
                        $this->image->setImageFormat( $orig_format );
                }
                catch ( Exception $e ) {
                        return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
                }

                // Set correct file permissions
                $stat = stat( dirname( $filename ) );
                $perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
                @ chmod( $filename, $perms );
		ewww_image_optimizer_aux_images_loop($filename, true);
		$ewww_debug = "$ewww_debug image editor (imagick) saved: $filename <br>";
		$image_size = filesize($filename);
		$ewww_debug = "$ewww_debug image editor size: $image_size <br>";
		ewww_image_optimizer_debug_log();
		$ewww_debug = '';
                return array(
                        'path' => $filename,
                        'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
                        'width' => $this->size['width'],
                        'height' => $this->size['height'],
                        'mime-type' => $mime_type,
                );
        }
}
if (class_exists('WP_Image_Editor_Gmagick')) {
	class EWWWIO_Gmagick_Editor extends WP_Image_Editor_Gmagick {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_debug;
			if (!defined('EWWW_IMAGE_OPTIMIZER_DOMAIN'))
				require_once(plugin_dir_path(__FILE__) . 'ewww-image-optimizer.php');
			if (!defined('EWWW_IMAGE_OPTIMIZER_JPEGTRAN'))
				ewww_image_optimizer_init();
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
	
			if ( ! $filename )
				$filename = $this->generate_filename( null, null, $extension );
	
			try {
				// Store initial Format
				$orig_format = $this->image->getimageformat();
	
				$this->image->setimageformat( strtoupper( $this->get_extension( $mime_type ) ) );
				$this->make_image( $filename, array( $image, 'writeImage' ), array( $filename ) );
	
				// Reset original Format
				$this->image->setimageformat( $orig_format );
			}
			catch ( Exception $e ) {
				return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
			}

			// Set correct file permissions
			$stat = stat( dirname( $filename ) );
			$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
			@ chmod( $filename, $perms );
			ewww_image_optimizer_aux_images_loop($filename, true);
			$ewww_debug = "$ewww_debug image editor (gmagick) saved : $filename <br>";
			$image_size = filesize($filename);
			$ewww_debug = "$ewww_debug image editor size: $image_size <br>";
			ewww_image_optimizer_debug_log();
			$ewww_debug = '';

			return array(
				'path' => $filename,
				'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
				'width' => $this->size['width'],
				'height' => $this->size['height'],
				'mime-type' => $mime_type,
			);
		}
	}
}
