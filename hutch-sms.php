<?php
/**
 * Plugin Name: Hutch Bulk SMS
 * Plugin URI:  https://bsms.hutch.lk
 * Description: Send individual and bulk SMS messages via Hutch Bulk SMS API. Supports WooCommerce order notifications, gift voucher serial delivery, and promotional campaigns.
 * Version:     1.1.0
 * Author:      Sangeeth
 * Author URI:  #
 * License:     GPL-2.0+
 * Text Domain: hutch-sms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HUTCH_SMS_VERSION', '1.1.0' );
define( 'HUTCH_SMS_FILE',    __FILE__ );
define( 'HUTCH_SMS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HUTCH_SMS_URL',     plugin_dir_url( __FILE__ ) );

// ──────────────────────────────────────────────────────────────
// Autoload classes
// ──────────────────────────────────────────────────────────────
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-api.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-logger.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-woocommerce.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-voucher.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-promotional.php';
require_once HUTCH_SMS_DIR . 'admin/class-hutch-sms-admin.php';

// ──────────────────────────────────────────────────────────────
// Bootstrap
// ──────────────────────────────────────────────────────────────
function hutch_sms_init() {
    Hutch_SMS_Admin::init();
    Hutch_SMS_WooCommerce::init();
    Hutch_SMS_Voucher::init();
}
add_action( 'plugins_loaded', 'hutch_sms_init' );

// ──────────────────────────────────────────────────────────────
// Activation / Deactivation
// ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'hutch_sms_activate' );
function hutch_sms_activate() {
    Hutch_SMS_Logger::create_table();

    $stored_version = get_option( 'hutch_sms_version', '' );
    $history        = get_option( 'hutch_sms_version_history', array() );

    // First install
    if ( empty( $stored_version ) ) {
        add_option( 'hutch_sms_version', HUTCH_SMS_VERSION );
        add_option( 'hutch_sms_version_history', array(
            array( 'version' => '1.0.0', 'date' => current_time( 'mysql' ), 'note' => 'Initial release.' ),
            array( 'version' => '1.1.0', 'date' => current_time( 'mysql' ), 'note' => 'Gift voucher serial number SMS; promotional bulk campaigns sourced from wp_wc_orders; customer contact list preview.' ),
        ) );
    } elseif ( version_compare( $stored_version, HUTCH_SMS_VERSION, '<' ) ) {
        // Upgrade: append new version entry
        $history[] = array(
            'version' => HUTCH_SMS_VERSION,
            'date'    => current_time( 'mysql' ),
            'note'    => 'Gift voucher serial number SMS; promotional bulk campaigns sourced from wp_wc_orders; customer contact list preview.',
        );
        update_option( 'hutch_sms_version', HUTCH_SMS_VERSION );
        update_option( 'hutch_sms_version_history', $history );
    }
}

register_deactivation_hook( __FILE__, 'hutch_sms_deactivate' );
function hutch_sms_deactivate() {
    // Nothing destructive on deactivation
}
