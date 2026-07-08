<?php
/**
 * Plugin Name: Hutch Bulk SMS
 * Plugin URI:  https://bsms.hutch.lk
 * Description: Send individual and bulk SMS messages via Hutch Bulk SMS API. Supports WooCommerce order notifications and promotional campaigns.
 * Version:     1.0.0
 * Author:      Sangeeth
 * Author URI:  #
 * License:     GPL-2.0+
 * Text Domain: hutch-sms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HUTCH_SMS_VERSION', '1.0.0' );
define( 'HUTCH_SMS_FILE',    __FILE__ );
define( 'HUTCH_SMS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HUTCH_SMS_URL',     plugin_dir_url( __FILE__ ) );

// ──────────────────────────────────────────────────────────────
// Autoload classes
// ──────────────────────────────────────────────────────────────
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-api.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-logger.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-woocommerce.php';
require_once HUTCH_SMS_DIR . 'admin/class-hutch-sms-admin.php';

// ──────────────────────────────────────────────────────────────
// Bootstrap
// ──────────────────────────────────────────────────────────────
function hutch_sms_init() {
    Hutch_SMS_Admin::init();
    Hutch_SMS_WooCommerce::init();
}
add_action( 'plugins_loaded', 'hutch_sms_init' );

// ──────────────────────────────────────────────────────────────
// Activation / Deactivation
// ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'hutch_sms_activate' );
function hutch_sms_activate() {
    Hutch_SMS_Logger::create_table();
    // Store initial version
    add_option( 'hutch_sms_version', HUTCH_SMS_VERSION );
    add_option( 'hutch_sms_version_history', array(
        array( 'version' => HUTCH_SMS_VERSION, 'date' => current_time( 'mysql' ), 'note' => 'Initial release' )
    ) );
}

register_deactivation_hook( __FILE__, 'hutch_sms_deactivate' );
function hutch_sms_deactivate() {
    // Nothing destructive on deactivation
}
