<?php
/**
 * Plugin Name:       NextGen Image Optimizer
 * Plugin URI:        https://example.com/nextgen-image-optimizer
 * Description:       Convert JPEG/PNG images to WebP and AVIF on upload or in bulk, with fine-grained control.
 * Version:           0.1.0
 * Author:            Hedef Hosting
 * Author URI:        https://hedefhosting.com.tr/
 * Text Domain:       nextgen-image-optimizer
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NGIO_VERSION', '0.1.0' );
define( 'NGIO_PLUGIN_FILE', __FILE__ );
define( 'NGIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NGIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Çekirdek sınıfı yükle.
require_once NGIO_PLUGIN_DIR . 'includes/class-ngio-core.php';

/**
 * Eklentiyi başlat.
 */
function ngio_run() {
    $plugin = NGIO_Core::instance();
    $plugin->run();
}
ngio_run();
/**
 * Load plugin textdomain for translations.
 */
function ngio_load_textdomain() {
    load_plugin_textdomain(
        'nextgen-image-optimizer',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'ngio_load_textdomain' );

// Aktivasyon / de-aktivasyon hook'ları.
register_activation_hook( __FILE__, array( 'NGIO_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NGIO_Core', 'deactivate' ) );
