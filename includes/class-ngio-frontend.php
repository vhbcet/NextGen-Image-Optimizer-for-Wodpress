<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NGIO_Frontend {

    protected $settings = array();

    public function __construct() {
        $this->settings = $this->get_settings();

        if ( empty( $this->settings['enable_picture'] ) ) {
            return;
        }

        add_filter( 'wp_get_attachment_image', array( $this, 'filter_attachment_image_html' ), 20, 5 );
        add_filter( 'post_thumbnail_html', array( $this, 'filter_post_thumbnail_html' ), 20, 5 );

        add_filter( 'the_content', array( $this, 'filter_content_images' ), 20 );
    }

    protected function get_settings() {
        $defaults = array(
            'enable_picture' => 1,
            'enable_webp'    => 1,
            'enable_avif'    => 1,
        );

        $saved = get_option( 'ngio_settings', array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $settings = wp_parse_args( $saved, $defaults );

        $settings['enable_picture'] = (int) ! empty( $settings['enable_picture'] );
        $settings['enable_webp']    = (int) ! empty( $settings['enable_webp'] );
        $settings['enable_avif']    = (int) ! empty( $settings['enable_avif'] );

        return $settings;
    }

    public function filter_attachment_image_html( $html, $attachment_id, $size, $icon, $attr ) {
        return $this->build_picture_markup( $html, $attachment_id );
    }

    public function filter_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        if ( ! $post_thumbnail_id ) {
            return $html;
        }

        return $this->build_picture_markup( $html, $post_thumbnail_id );
    }

    public function filter_content_images( $content ) {
        if ( false === strpos( $content, '<img' ) ) {
            return $content;
        }

        $self = $this;

        $content = preg_replace_callback(
            '/<img[^>]*>/i',
            function ( $matches ) use ( $self ) {
                $img_html = $matches[0];

                if ( false !== stripos( $img_html, 'ngio-picture' ) ) {
                    return $img_html;
                }

                $attachment_id = 0;

                if ( preg_match( '/wp-image-(\d+)/i', $img_html, $id_match ) ) {
                    $attachment_id = (int) $id_match[1];

                } elseif ( preg_match( '/src=["\']([^"\']+)["\']/i', $img_html, $src_match ) ) {
                    $src = $src_match[1];

                    if ( 0 === strpos( $src, '//' ) ) {
                        $src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
                    } elseif ( 0 === strpos( $src, '/' ) && 0 !== strpos( $src, 'http' ) ) {
                        $src = home_url( $src );
                    }

                    $attachment_id = attachment_url_to_postid( $src );
                }

                if ( $attachment_id <= 0 ) {
                    return $img_html;
                }

                return $self->build_picture_markup( $img_html, $attachment_id );
            },
            $content
        );

        return $content;
    }

    protected function build_picture_markup( $html, $attachment_id ) {
        if ( empty( $this->settings['enable_picture'] ) ) {
            return $html;
        }

        if ( false !== stripos( $html, '<picture' ) ) {
            return $html;
        }

        $attachment_id = (int) $attachment_id;
        if ( $attachment_id <= 0 ) {
            return $html;
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/jpg', 'image/png' ), true ) ) {
            return $html;
        }

        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $meta ) || ! is_array( $meta ) || empty( $meta['ngio'] ) || empty( $meta['ngio']['formats'] ) ) {
            return $html;
        }

        $formats = (array) $meta['ngio']['formats'];

        $has_webp = in_array( 'webp', $formats, true ) && ! empty( $this->settings['enable_webp'] );

        $has_avif = in_array( 'avif', $formats, true )
            && ! empty( $this->settings['enable_avif'] )
            && empty( $this->settings['enable_webp'] );

        $final_formats = array();
        if ( $has_avif ) {
            $final_formats[] = 'avif';
        } elseif ( $has_webp ) {
            $final_formats[] = 'webp';
        }

        if ( empty( $final_formats ) ) {
            return $html;
        }

        if ( ! preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m ) ) {
            return $html;
        }

        $src = $m[1];

        $sources = '';

        if ( in_array( 'avif', $final_formats, true ) ) {
            $sources .= '<source type="image/avif" srcset="' . esc_url( $src . '.avif' ) . '">';
        }

        if ( in_array( 'webp', $final_formats, true ) ) {
            $sources .= '<source type="image/webp" srcset="' . esc_url( $src . '.webp' ) . '">';
        }

        if ( ! $sources ) {
            return $html;
        }

        return '<picture class="ngio-picture">' . $sources . $html . '</picture>';
    }
}
