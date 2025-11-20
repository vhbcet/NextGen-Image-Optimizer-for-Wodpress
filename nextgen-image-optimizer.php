<?php
/**
 * Plugin Name:       NextGen Image Optimizer
 * Plugin URI:        https://github.com/vhbcet/NextGen-Image-Optimizer-for-Wodpress
 * Description:       Convert JPEG and PNG images to modern WebP and AVIF formats on upload or in bulk, and optionally serve them using <picture> tags.
 * Version:           0.1.0
 * Author:            Hedef Hosting
 * Author URI:        https://hedefhosting.com.tr/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nextgen-image-optimizer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'NGIO_VERSION' ) ) {
    define( 'NGIO_VERSION', '0.1.0' );
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

function ngio_load_textdomain() {
    load_plugin_textdomain(
        'nextgen-image-optimizer',
        false,
        dirname( plugin_basename( NGIO_PLUGIN_FILE ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'ngio_load_textdomain' );

function ngio_bootstrap() {
    if ( file_exists( NGIO_PLUGIN_DIR . 'includes/class-ngio-converter.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-converter.php';
    }

    if ( file_exists( NGIO_PLUGIN_DIR . 'admin/class-ngio-admin.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'admin/class-ngio-admin.php';
    }

    if ( file_exists( NGIO_PLUGIN_DIR . 'admin/class-ngio-bulk.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'admin/class-ngio-bulk.php';
    }

    if ( file_exists( NGIO_PLUGIN_DIR . 'includes/class-ngio-frontend.php' ) ) {
        require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-frontend.php';
    }

    if ( class_exists( 'NGIO_Converter' ) ) {
        $GLOBALS['ngio_converter'] = new NGIO_Converter();
    }

    if ( class_exists( 'NGIO_Admin' ) ) {
        $GLOBALS['ngio_admin'] = new NGIO_Admin();
    }

    if ( class_exists( 'NGIO_Bulk' ) ) {
        $GLOBALS['ngio_bulk'] = new NGIO_Bulk();
    }

    if ( class_exists( 'NGIO_Frontend' ) ) {
        $GLOBALS['ngio_frontend'] = new NGIO_Frontend();
    }
}
add_action( 'plugins_loaded', 'ngio_bootstrap', 20 );
