<?php
class EWWWIO_GD_Editor extends WP_Image_Editor_GD {
	protected function _save ($image, $filename = null, $mime_type = null) {
		global $ewww_debug;
		require_once(plugin_dir_path(__FILE__) . 'ewww-image-optimizer.php');
		ewww_image_optimizer_admin_init();
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
			ewww_image_optimizer_aux_images_loop($filename, true, 'auto');
			$ewww_debug = "$ewww_debug image editor saved: $filename <br>";
			$ewww_debug = "$ewww_debug image width: " . $this->size['width'] . " <br>";
			$ewww_debug = "$ewww_debug image height: " . $this->size['height'] . " <br>";
			$ewww_debug = "$ewww_debug image mime: $mime_type <br>";
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
		require_once(plugin_dir_path(__FILE__) . 'ewww-image-optimizer.php');
		ewww_image_optimizer_admin_init();
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
			ewww_image_optimizer_aux_images_loop($filename, true, 'auto');
			$ewww_debug = "$ewww_debug image editor saved: $filename <br>";
			$ewww_debug = "$ewww_debug image width: " . $this->size['width'] . " <br>";
			$ewww_debug = "$ewww_debug image height: " . $this->size['height'] . " <br>";
			$ewww_debug = "$ewww_debug image mime: $mime_type <br>";
			ewww_image_optimizer_debug_log();	
	                return array(
	                        'path' => $filename,
	                        'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
	                        'width' => $this->size['width'],
	                        'height' => $this->size['height'],
	                        'mime-type' => $mime_type,
	                );
	        }
}
//abstract class EWWWIO_Editor extends WP_Image_Editor {
/*	abstract public function load();
	abstract public function save( $destfilename = null, $mime_type = null );
	abstract public function resize( $max_w, $max_h, $crop = false );
	abstract public function multi_resize( $sizes );
	abstract public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false );
	abstract public function rotate( $angle );
	abstract public function flip( $horz, $vert );
	abstract public function stream( $mime_type = null );*/
/*	protected function make_image( $filename, $function, $arguments ) {
		if ($stream = wp_is_stream($filename)) {
                        ob_start();
                } else {
                        // The directory containing the original file may no longer exist when using a replication plugin.
                        wp_mkdir_p( dirname( $filename ) ); 
                }

	        $result = call_user_func_array( $function, $arguments );

                if ( $result && $stream ) {
                        $contents = ob_get_contents();

                        $fp = fopen( $filename, 'w' );

                        if ( ! $fp )
                                return false;

                        fwrite( $fp, $contents );
                        fclose( $fp );
                }

                if ( $stream ) {
                        ob_end_clean();
                }

		global $ewww_debug;
		require_once(plugin_dir_path(__FILE__) . 'ewww-image-optimizer.php');
		ewww_image_optimizer_admin_init();
		ewww_image_optimizer_aux_images_loop($filename, true, 'auto');
		$ewww_debug = "$ewww_debug image editor created image: $filename <br>";
		ewww_image_optimizer_debug_log();	
	
                return $result;
	}
}*/
