<?php
/**
 * Plugin Name: TelXL Competitions
 * Plugin URI: https://theeasypc.co.uk
 * Description: UK prize competition platform powered by WooCommerce. Run legally compliant competitions with qualifying questions, automated draws, and instant wins.
 * Version: 1.1.3
 * Author: TelXL
 * Author URI: https://theeasypc.co.uk
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: telxl-competitions
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load local configuration (contains GitHub token, gitignored).
$txc_config_file = plugin_dir_path( __FILE__ ) . 'txc-config.php';
if ( file_exists( $txc_config_file ) ) {
    require_once $txc_config_file;
}

define( 'TXC_VERSION', '1.1.3' );
define( 'TXC_PLUGIN_FILE', __FILE__ );
define( 'TXC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TXC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TXC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TXC_LICENSE_SERVER', 'https://license.theeasypc.co.uk' );
define( 'TXC_DB_VERSION', '1.0.0' );

/**
 * Check if WooCommerce is active before loading the plugin.
 */
function txc_check_dependencies() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'txc_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

function txc_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><strong>TelXL Competitions</strong> requires WooCommerce to be installed and active.</p>
    </div>
    <?php
}

/**
 * Plugin activation.
 */
function txc_activate() {
    require_once TXC_PLUGIN_DIR . 'includes/class-txc-activator.php';
    TXC_Activator::activate();
}
register_activation_hook( __FILE__, 'txc_activate' );

/**
 * Plugin deactivation.
 */
function txc_deactivate() {
    require_once TXC_PLUGIN_DIR . 'includes/class-txc-deactivator.php';
    TXC_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'txc_deactivate' );

/**
 * Begin plugin execution.
 */
function txc_init() {
    if ( ! txc_check_dependencies() ) {
        return;
    }

    // Flush rewrite rules once after a version upgrade so new CPT
    // archive slugs and permalink structures take effect immediately.
    $stored = get_option( 'txc_plugin_version', '' );
    if ( $stored !== TXC_VERSION ) {
        add_action( 'init', function () {
            flush_rewrite_rules();
        }, 999 );
        update_option( 'txc_plugin_version', TXC_VERSION );
    }

    require_once TXC_PLUGIN_DIR . 'includes/class-txc-loader.php';

    $loader = new TXC_Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'txc_init', 20 );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
