<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'NGIO_Converter' ) ) {

class NGIO_Converter {

    protected $settings = array();

    protected $capabilities = array();

    protected $force_convert = false;

    public function __construct() {
        $this->settings     = $this->get_settings();
        $this->capabilities = self::get_server_capabilities();

        if ( ! empty( $this->settings['auto_on_upload'] ) ) {
            add_filter(
                'wp_generate_attachment_metadata',
                array( $this, 'handle_attachment_metadata' ),
                10,
                2
            );
        }

        add_action(
            'delete_attachment',
            array( $this, 'handle_delete_attachment' ),
            10,
            1
        );
    }

    public function handle_delete_attachment( $attachment_id ) {
        self::remove_for_attachment( $attachment_id );
    }

    public static function remove_for_attachment( $attachment_id ) {
        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            return;
        }

        $source_file = get_attached_file( $attachment_id );
        if ( ! $source_file ) {
            return;
        }

        $paths = array();

        if ( $source_file ) {
            $paths[] = $source_file;
        }

        $meta = wp_get_attachment_metadata( $attachment_id );

        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            $base_dir = dirname( $source_file );

            foreach ( $meta['sizes'] as $size_data ) {
                if ( empty( $size_data['file'] ) ) {
                    continue;
                }

                $size_path = path_join( $base_dir, $size_data['file'] );

                if ( $size_path ) {
                    $paths[] = $size_path;
                }
            }
        }

        $formats = array( 'webp', 'avif' );

        if ( ! empty( $meta['ngio']['formats'] ) && is_array( $meta['ngio']['formats'] ) ) {
            $formats = array_values( array_unique( array_map( 'sanitize_key', $meta['ngio']['formats'] ) ) );
        }

        foreach ( array_unique( $paths ) as $path ) {
            foreach ( $formats as $format ) {
                $candidate = $path . '.' . $format;

                if ( is_file( $candidate ) ) {
                    wp_delete_file( $candidate );
                }
            }
        }
    }

    public function set_force_convert( $force ) {
        $this->force_convert = (bool) $force;
    }

    protected function get_settings() {
        $defaults = array(
            'enable_webp'              => 1,
            'enable_avif'              => 1,
            'quality'                  => 82,
            'auto_on_upload'           => 1,
            'enable_picture'           => 1,
            'resize_enabled'           => 0,
            'resize_max_width'         => 2048,
            'strip_metadata'           => 1,
            'exclude_patterns_enabled' => 0,
            'exclude_patterns'         => '',
        );

        $saved = get_option( 'ngio_settings', array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $settings = wp_parse_args( $saved, $defaults );

        $settings['quality']          = max( 0, min( 100, (int) $settings['quality'] ) );
        $settings['resize_max_width'] = max( 320, min( 8000, (int) $settings['resize_max_width'] ) );

        $settings['enable_webp']    = (int) ! empty( $settings['enable_webp'] );
        $settings['enable_avif']    = (int) ! empty( $settings['enable_avif'] );
        $settings['auto_on_upload'] = (int) ! empty( $settings['auto_on_upload'] );
        $settings['enable_picture'] = (int) ! empty( $settings['enable_picture'] );
        $settings['resize_enabled'] = (int) ! empty( $settings['resize_enabled'] );
        $settings['strip_metadata'] = (int) ! empty( $settings['strip_metadata'] );

        $settings['exclude_patterns_enabled'] = (int) ! empty( $settings['exclude_patterns_enabled'] );
        $settings['exclude_patterns']         = is_string( $settings['exclude_patterns'] ) ? $settings['exclude_patterns'] : '';

        return $settings;
    }

    public static function get_server_capabilities() {
        $caps = array(
            'webp_gd'      => false,
            'webp_imagick' => false,
            'avif_gd'      => false,
            'avif_imagick' => false,
        );

        if ( function_exists( 'gd_info' ) ) {
            $gd = gd_info();

            if ( isset( $gd['WebP Support'] ) && $gd['WebP Support'] ) {
                $caps['webp_gd'] = true;
            }
            if ( isset( $gd['AVIF Support'] ) && $gd['AVIF Support'] ) {
                $caps['avif_gd'] = true;
            }
        }

        if ( class_exists( 'Imagick' ) ) {
            try {
                $imagick  = new Imagick();
                $formats  = $imagick->queryFormats();
                $upper    = array_map( 'strtoupper', $formats );
                $caps['webp_imagick'] = in_array( 'WEBP', $upper, true );
                $caps['avif_imagick'] = in_array( 'AVIF', $upper, true );
            } catch ( Exception $e ) {
            }
        }

        $caps['webp_any'] = $caps['webp_gd'] || $caps['webp_imagick'];
        $caps['avif_any'] = $caps['avif_gd'] || $caps['avif_imagick'];

        return $caps;
    }

    public function handle_attachment_metadata( $metadata, $attachment_id ) {
        if ( empty( $metadata ) || ! is_array( $metadata ) ) {
            return $metadata;
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            return $metadata;
        }

        $formats = array();

        $webp_requested = ! empty( $this->settings['enable_webp'] ) && ! empty( $this->capabilities['webp_any'] );
        $avif_requested = ! empty( $this->settings['enable_avif'] ) && ! empty( $this->capabilities['avif_any'] );

        $want_webp = $webp_requested;
        $want_avif = $avif_requested && ! $webp_requested;

        if ( $want_avif ) {
            $formats[] = 'avif';
        } elseif ( $want_webp ) {
            $formats[] = 'webp';
        }

        if ( empty( $formats ) ) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return $metadata;
        }

        $base_file = isset( $metadata['file'] ) ? $metadata['file'] : '';
        if ( ! $base_file ) {
            return $metadata;
        }

        $original_path = path_join( $upload_dir['basedir'], $base_file );
        $base_dir      = dirname( $original_path );

        $stats = array(
            'original_bytes' => 0,
            'nextgen_bytes'  => 0,
        );

        if ( file_exists( $original_path ) ) {
            $this->convert_file_for_formats(
                $original_path,
                $mime,
                'full',
                $formats,
                $stats
            );
        }

        if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size_key => $size_data ) {
                if ( empty( $size_data['file'] ) ) {
                    continue;
                }

                $size_path = path_join( $base_dir, $size_data['file'] );

                if ( file_exists( $size_path ) ) {
                    $this->convert_file_for_formats(
                        $size_path,
                        $mime,
                        $size_key,
                        $formats,
                        $stats
                    );
                }
            }
        }

        if ( $stats['original_bytes'] > 0 && $stats['nextgen_bytes'] > 0 ) {
            $metadata['ngio'] = array(
                'generated_at'   => time(),
                'formats'        => $formats,
                'original_bytes' => $stats['original_bytes'],
                'nextgen_bytes'  => $stats['nextgen_bytes'],
                'bytes_saved'    => max( 0, $stats['original_bytes'] - $stats['nextgen_bytes'] ),
            );
        }

        return $metadata;
    }

    protected function should_exclude_file( $source_path ) {
        if ( empty( $this->settings['exclude_patterns_enabled'] ) ) {
            return false;
        }

        if ( empty( $this->settings['exclude_patterns'] ) || ! is_string( $this->settings['exclude_patterns'] ) ) {
            return false;
        }

        $patterns_raw = $this->settings['exclude_patterns'];
        if ( '' === trim( $patterns_raw ) ) {
            return false;
        }

        $basename = function_exists( 'wp_basename' ) ? wp_basename( $source_path ) : basename( $source_path );

        $lines = preg_split( '/\r\n|\r|\n/', $patterns_raw );
        if ( ! is_array( $lines ) ) {
            return false;
        }

        foreach ( $lines as $pattern ) {
            $pattern = trim( $pattern );
            if ( '' === $pattern ) {
                continue;
            }

            if ( false !== stripos( $source_path, $pattern ) || false !== stripos( $basename, $pattern ) ) {
                return true;
            }
        }

        return false;
    }

    protected function convert_file_for_formats( $source_path, $mime, $size_key, $formats, &$stats ) {
    if ( $this->should_exclude_file( $source_path ) ) {
        return;
    }

    $original_bytes = @filesize( $source_path );
    if ( $original_bytes ) {
        $stats['original_bytes'] += (int) $original_bytes;
    }

    $best_nextgen = 0;

    foreach ( $formats as $format ) {
        $dest_path = $source_path . '.' . $format;

        if ( ! $this->force_convert && file_exists( $dest_path ) ) {
            $bytes = @filesize( $dest_path );
            if ( $bytes ) {
                $bytes = (int) $bytes;
                if ( ! $best_nextgen || $bytes < $best_nextgen ) {
                    $best_nextgen = $bytes;
                }
            }
            continue;
        }

        $created = $this->create_nextgen_file( $source_path, $dest_path, $mime, $format );

        if ( $created && file_exists( $dest_path ) ) {
            $bytes = @filesize( $dest_path );
            if ( $bytes ) {
                $bytes = (int) $bytes;
                if ( ! $best_nextgen || $bytes < $best_nextgen ) {
                    $best_nextgen = $bytes;
                }
            }
        }
    }

    if ( $best_nextgen ) {
        $stats['nextgen_bytes'] += $best_nextgen;
    } elseif ( $original_bytes ) {
        $stats['nextgen_bytes'] += (int) $original_bytes;
    }
}

    protected function create_nextgen_file( $source_path, $dest_path, $mime, $format ) {
        $use_imagick = false;

        if ( 'webp' === $format && ! empty( $this->capabilities['webp_imagick'] ) ) {
            $use_imagick = true;
        } elseif ( 'avif' === $format && ! empty( $this->capabilities['avif_imagick'] ) ) {
            $use_imagick = true;
        }

        if ( $use_imagick && class_exists( 'Imagick' ) ) {
            return $this->create_with_imagick( $source_path, $dest_path, $format );
        }

        return $this->create_with_gd( $source_path, $dest_path, $mime, $format );
    }

    protected function create_with_imagick( $source_path, $dest_path, $format ) {
        try {
            $image = new Imagick( $source_path );
        } catch ( Exception $e ) {
            return false;
        }

        try {
            $geometry = $image->getImageGeometry();
            $width    = isset( $geometry['width'] ) ? (int) $geometry['width'] : 0;
            $height   = isset( $geometry['height'] ) ? (int) $geometry['height'] : 0;

            if ( $this->settings['resize_enabled'] && $this->settings['resize_max_width'] > 0 && $width > $this->settings['resize_max_width'] ) {
                $target_width  = $this->settings['resize_max_width'];
                $ratio         = $target_width / $width;
                $target_height = (int) round( $height * $ratio );

                $image->resizeImage( $target_width, $target_height, Imagick::FILTER_LANCZOS, 1 );
            }

            if ( $this->settings['strip_metadata'] ) {
                $image->stripImage();
            }

            $image->setImageFormat( $format );
            $image->setImageCompressionQuality( $this->settings['quality'] );

            $dir = dirname( $dest_path );
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }

            $image->writeImage( $dest_path );
            $image->clear();
            $image->destroy();

            return true;
        } catch ( Exception $e ) {
            if ( isset( $image ) ) {
                $image->clear();
                $image->destroy();
            }
            return false;
        }
    }

    protected function create_with_gd( $source_path, $dest_path, $mime, $format ) {
        if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
            return false;
        }

        $image = null;

        if ( 'image/jpeg' === $mime || 'image/jpg' === $mime ) {
            $image = @imagecreatefromjpeg( $source_path );
        } elseif ( 'image/png' === $mime ) {
            $image = @imagecreatefrompng( $source_path );
        }

        if ( ! $image ) {
            return false;
        }

        $width  = imagesx( $image );
        $height = imagesy( $image );

        $target   = $image;
        $resized  = null;

        if ( $this->settings['resize_enabled'] && $this->settings['resize_max_width'] > 0 && $width > $this->settings['resize_max_width'] ) {
            $target_width  = $this->settings['resize_max_width'];
            $ratio         = $target_width / $width;
            $target_height = (int) round( $height * $ratio );

            $resized = imagecreatetruecolor( $target_width, $target_height );

            if ( 'image/png' === $mime ) {
                imagealphablending( $resized, false );
                imagesavealpha( $resized, true );
                $transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
                imagefilledrectangle( $resized, 0, 0, $target_width, $target_height, $transparent );
            }

            imagecopyresampled(
                $resized,
                $image,
                0,
                0,
                0,
                0,
                $target_width,
                $target_height,
                $width,
                $height
            );

            $target = $resized;
        }

        $dir = dirname( $dest_path );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        $quality = $this->settings['quality'];

        $result = false;

        if ( 'webp' === $format && function_exists( 'imagewebp' ) ) {
            $result = imagewebp( $target, $dest_path, $quality );
        } elseif ( 'avif' === $format && function_exists( 'imageavif' ) ) {
            $result = imageavif( $target, $dest_path, $quality );
        }

        if ( $resized ) {
            imagedestroy( $resized );
        }

        imagedestroy( $image );

        return (bool) $result;
    }
}

}
