<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NGIO_Core {

    private static $instance = null;


    private $converter = null;

    private function __construct() {
    }


    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    public static function activate() {
        if ( ! get_option( 'ngio_settings' ) ) {
            add_option(
                'ngio_settings',
                array(
                    'enable_webp'    => 1,
                    'enable_avif'    => 1,
                    'quality'        => 82,
                    'auto_on_upload' => 1,
                    'enable_picture' => 1,
                )
            );
        }
    }

    public static function deactivate() {

    }

    public function get_converter() {
        if ( null === $this->converter ) {
            require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-converter.php';
            $this->converter = new NGIO_Converter();
        }

        return $this->converter;
    }

    public function run() {

        $converter = $this->get_converter();

        add_filter(
            'wp_generate_attachment_metadata',
            array( $converter, 'handle_attachment_metadata' ),
            10,
            2
        );

        add_action(
            'delete_attachment',
            array( $converter, 'handle_delete_attachment' ),
            10,
            1
        );

        require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-frontend.php';
        new NGIO_Frontend();

        if ( is_admin() ) {
            require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-admin.php';
            new NGIO_Admin();

            require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-bulk.php';
            new NGIO_Bulk();
        }
    }
}
