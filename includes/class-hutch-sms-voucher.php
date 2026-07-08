<?php
/**
 * Hutch SMS – Gift Voucher Serial Number SMS  v1.1.1
 *
 * FIX: Uses same 1-arg hook pattern + wc_get_order() to load order safely.
 * FIX: Retries serial lookup with a short delay to allow Serial Numbers
 *      plugin to write its records before we query.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Voucher {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        // Priority 30 — runs AFTER order completion SMS (priority 10)
        // and after Serial Numbers plugin writes its records (usually priority 10-20)
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
    }

    // ─────────────────────────────────────────────────────────────────
    // Main handler — receives only $order_id
    // ─────────────────────────────────────────────────────────────────

    public static function send_voucher_serials( $order_id ) {
        if ( ! get_option( 'hutch_sms_enable_voucher_sms', false ) ) return;

        $order = Hutch_SMS_WooCommerce::load_order( $order_id );
        if ( ! $order ) return;

        $phone = Hutch_SMS_WooCommerce::get_phone( $order );
        if ( ! $phone ) {
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — no billing phone, skipping." );
            return;
        }

        $voucher_product_ids = self::get_voucher_product_ids();
        if ( empty( $voucher_product_ids ) ) {
            Hutch_SMS_Logger::debug( "[Voucher] No voucher product IDs configured." );
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = (int) $item->get_product_id();
            if ( ! in_array( $product_id, $voucher_product_ids, true ) ) continue;

            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — found voucher product $product_id, item $item_id." );

            $serials = self::get_serials_for_order_item( $order_id, $item_id, $product_id );

            if ( empty( $serials ) ) {
                Hutch_SMS_Logger::debug( "[Voucher] Order $order_id, product $product_id — no serials in wp_serial_numbers yet." );
                // Schedule a delayed retry via WP Cron (60s) in case Serial Numbers plugin writes later
                self::schedule_retry( $order_id );
                continue;
            }

            foreach ( $serials as $serial ) {
                $message = self::build_voucher_message( $serial, $order, $item );
                Hutch_SMS_Logger::debug( "[Voucher] Sending serial to $phone for order $order_id." );

                Hutch_SMS_API::send_sms( array(
                    'campaignName' => 'Gift Voucher',
                    'mask'         => get_option( 'hutch_sms_mask', '' ),
                    'numbers'      => $phone,
                    'content'      => $message,
                ) );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Cron retry (in case Serial Numbers plugin writes serials late)
    // ─────────────────────────────────────────────────────────────────

    public static function register_cron_hook() {
        add_action( 'hutch_sms_voucher_retry', array( __CLASS__, 'cron_retry_send' ) );
    }

    private static function schedule_retry( int $order_id ) {
        if ( ! wp_next_scheduled( 'hutch_sms_voucher_retry', array( $order_id ) ) ) {
            wp_schedule_single_event( time() + 90, 'hutch_sms_voucher_retry', array( $order_id ) );
            Hutch_SMS_Logger::debug( "[Voucher] Scheduled retry in 90s for order $order_id." );
        }
    }

    public static function cron_retry_send( int $order_id ) {
        Hutch_SMS_Logger::debug( "[Voucher] Cron retry firing for order $order_id." );
        self::send_voucher_serials( $order_id );
    }

    // ─────────────────────────────────────────────────────────────────
    // DB helpers
    // ─────────────────────────────────────────────────────────────────

    private static function get_serials_for_order_item( int $order_id, int $item_id, int $product_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'serial_numbers';

        // Most precise: order_id + order_item_id + product_id
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE order_id = %d AND order_item_id = %d AND product_id = %d ORDER BY id ASC",
            $order_id, $item_id, $product_id
        ), ARRAY_A );

        Hutch_SMS_Logger::debug( "[Voucher] Serial lookup (precise): order=$order_id item=$item_id product=$product_id → " . count( $rows ) . " rows. DB error: " . ( $wpdb->last_error ?: 'none' ) );

        // Fallback: order_id + product_id
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE order_id = %d AND product_id = %d ORDER BY id ASC",
                $order_id, $product_id
            ), ARRAY_A );
            Hutch_SMS_Logger::debug( "[Voucher] Serial lookup (fallback order+product): → " . count( $rows ) . " rows." );
        }

        return $rows ?: array();
    }

    // ─────────────────────────────────────────────────────────────────
    // Message builder
    // ─────────────────────────────────────────────────────────────────

    private static function build_voucher_message( array $serial, $order, $item ): string {
        $template = get_option(
            'hutch_sms_msg_voucher',
            'Hi {first_name}, your gift voucher for {product_name} is: {serial}. Valid until: {expire_date}. Order #{order_id}.'
        );

        $expire = $serial['expire_date'] ?? '';
        if ( $expire && $expire !== '0000-00-00 00:00:00' && $expire !== null ) {
            $expire = date( 'd M Y', strtotime( $expire ) );
        } else {
            $expire = ! empty( $serial['validity'] ) ? $serial['validity'] : 'N/A';
        }

        return str_replace(
            array( '{serial}', '{product_name}', '{order_id}', '{expire_date}', '{validity}', '{first_name}' ),
            array(
                $serial['serial_key'],
                $item->get_name(),
                $order->get_order_number(),
                $expire,
                ! empty( $serial['validity'] ) ? $serial['validity'] : 'N/A',
                $order->get_billing_first_name(),
            ),
            $template
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Config
    // ─────────────────────────────────────────────────────────────────

    public static function get_voucher_product_ids(): array {
        $raw = get_option( 'hutch_sms_voucher_product_ids', '' );
        if ( empty( $raw ) ) return array();
        return array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $raw ) ) );
    }
}
