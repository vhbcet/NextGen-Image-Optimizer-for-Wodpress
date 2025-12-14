<?php
/**
 * Plugin Name:       Hedef Image Optimizer — WebP & AVIF
 * Plugin URI:        https://github.com/vhbcet/Hedef-Image-Optimizer-for-Wodpress
 * Description:       Convert JPEG and PNG images to modern WebP and AVIF formats on upload or in bulk, and optionally serve them using <picture> tags.
 * Version:           0.1.1
 * Author:            Hedef Hosting
 * Author URI:        https://hedefhosting.com.tr/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hedef-image-optimizer-webp-avif
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'NGIO_VERSION' ) ) {
    define( 'NGIO_VERSION', '0.1.1' );
}

if ( ! defined( 'NGIO_PLUGIN_FILE' ) ) {
    define( 'NGIO_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'NGIO_PLUGIN_DIR' ) ) {
    define( 'NGIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'NGIO_PLUGIN_URL' ) ) {
    define( 'NGIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Bootstrap: load required classes and init singletons.
 */
function ngio_bootstrap() {
    // Converter
    if ( file_exists( NGIO_PLUGIN_DIR . 'includes/class-ngio-converter.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-converter.php';
    }

    // Admin settings UI
    if ( file_exists( NGIO_PLUGIN_DIR . 'admin/class-ngio-admin.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'admin/class-ngio-admin.php';
    }

    // Bulk optimizer
    if ( file_exists( NGIO_PLUGIN_DIR . 'admin/class-ngio-bulk.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'admin/class-ngio-bulk.php';
    }

    // Frontend <picture> integration
    if ( file_exists( NGIO_PLUGIN_DIR . 'includes/class-ngio-frontend.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-frontend.php';
    }

    if ( class_exists( 'NGIO_Converter' ) ) {
        $GLOBALS['ngio_converter'] = new NGIO_Converter();
    }

    if ( is_admin() ) {
        if ( class_exists( 'NGIO_Admin' ) ) {
            $GLOBALS['ngio_admin'] = new NGIO_Admin();
        }

        if ( class_exists( 'NGIO_Bulk' ) ) {
            $GLOBALS['ngio_bulk'] = new NGIO_Bulk();
        }
    }

    if ( class_exists( 'NGIO_Frontend' ) ) {
        $GLOBALS['ngio_frontend'] = new NGIO_Frontend();
    }
}
add_action( 'plugins_loaded', 'ngio_bootstrap', 20 );
