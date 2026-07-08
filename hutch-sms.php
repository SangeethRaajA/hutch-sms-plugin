<?php
/**
 * Plugin Name: Hutch Bulk SMS
 * Plugin URI:  https://bsms.hutch.lk
 * Description: Send individual and bulk SMS messages via Hutch Bulk SMS API. Supports WooCommerce order notifications, gift voucher serial delivery, and promotional campaigns.
 * Version:     1.2.0
 * Author:      Sangeeth
 * Author URI:  #
 * License:     GPL-2.0+
 * Text Domain: hutch-sms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HUTCH_SMS_VERSION', '1.2.0' );
define( 'HUTCH_SMS_FILE',    __FILE__ );
define( 'HUTCH_SMS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'HUTCH_SMS_URL',     plugin_dir_url( __FILE__ ) );

// ──────────────────────────────────────────────────────────────
// Load classes immediately (no WC dependency at load time)
// ──────────────────────────────────────────────────────────────
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-api.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-logger.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-woocommerce.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-voucher.php';
require_once HUTCH_SMS_DIR . 'includes/class-hutch-sms-promotional.php';
require_once HUTCH_SMS_DIR . 'admin/class-hutch-sms-admin.php';

// ──────────────────────────────────────────────────────────────
// Bootstrap on plugins_loaded — WooCommerce is available here
// ──────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'hutch_sms_init', 20 ); // priority 20 ensures WC is fully loaded

function hutch_sms_init() {
    Hutch_SMS_Admin::init();

    // Only register WC hooks if WooCommerce is active
    if ( class_exists( 'WooCommerce' ) ) {
        Hutch_SMS_WooCommerce::init();
        Hutch_SMS_Voucher::init();
        Hutch_SMS_Voucher::register_cron_hook();
    }
}

// ──────────────────────────────────────────────────────────────
// Activation
// ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'hutch_sms_activate' );
function hutch_sms_activate() {
    Hutch_SMS_Logger::create_table();

    $stored_version = get_option( 'hutch_sms_version', '' );
    $history        = get_option( 'hutch_sms_version_history', array() );

    if ( empty( $stored_version ) ) {
        // Fresh install
        add_option( 'hutch_sms_version', HUTCH_SMS_VERSION );
        add_option( 'hutch_sms_version_history', array(
            array( 'version' => '1.0.0', 'date' => current_time( 'mysql' ), 'note' => 'Initial release.' ),
            array( 'version' => '1.1.0', 'date' => current_time( 'mysql' ), 'note' => 'Gift voucher SMS; promotional campaigns from order table.' ),
            array( 'version' => '1.1.1', 'date' => current_time( 'mysql' ), 'note' => 'XStore theme compatibility: multi-hook order confirmation, dedup transient, offline vs online gateway detection, fallback phone lookup from order meta and user meta.' ),
        ) );
    } elseif ( version_compare( $stored_version, HUTCH_SMS_VERSION, '<' ) ) {
        $history[] = array(
            'version' => HUTCH_SMS_VERSION,
            'date'    => current_time( 'mysql' ),
            'note'    => 'XStore theme compatibility: multi-hook order confirmation, dedup transient, offline vs online gateway detection, fallback phone lookup from order meta and user meta.',
        );
        update_option( 'hutch_sms_version', HUTCH_SMS_VERSION );
        update_option( 'hutch_sms_version_history', $history );
    }
}

// ──────────────────────────────────────────────────────────────
// Deactivation — clear scheduled cron events
// ──────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'hutch_sms_deactivate' );
function hutch_sms_deactivate() {
    wp_clear_scheduled_hook( 'hutch_sms_voucher_retry' );
}
