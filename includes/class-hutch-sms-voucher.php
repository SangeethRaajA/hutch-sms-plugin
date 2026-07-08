<?php
/**
 * Hutch SMS – Gift Voucher Serial Number SMS
 *
 * When an order containing a "gift voucher" product is completed,
 * look up the matching serial key(s) from wp_serial_numbers and
 * send them to the customer's billing phone.
 *
 * wp_serial_numbers schema (from DESCRIBE):
 *   id, serial_key, product_id, activation_limit, activation_count,
 *   order_id, order_item_id, vendor_id, status, validity,
 *   expire_date, order_date, uuid, source, created_date
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Voucher {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        // Fires when order moves to "completed" — same hook as order completion
        // but we run at priority 20 (after the standard completion SMS at 10)
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'send_voucher_serials' ), 20, 2 );

        // Also support "processing" for digital-only orders that deliver immediately
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'send_voucher_serials' ), 20, 2 );
    }

    // ─────────────────────────────────────────────────────────────────
    // Main handler
    // ─────────────────────────────────────────────────────────────────

    /**
     * For each item in the order that matches a gift-voucher product,
     * fetch serial key(s) from wp_serial_numbers and SMS them.
     */
    public static function send_voucher_serials( int $order_id, $order ) {
        if ( ! get_option( 'hutch_sms_enable_voucher_sms', false ) ) return;

        $phone = self::get_phone( $order );
        if ( ! $phone ) {
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — no billing phone, skipping." );
            return;
        }

        $voucher_product_ids = self::get_voucher_product_ids();
        if ( empty( $voucher_product_ids ) ) {
            Hutch_SMS_Logger::debug( "[Voucher] No voucher product IDs configured, skipping." );
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = (int) $item->get_product_id();
            if ( ! in_array( $product_id, $voucher_product_ids, true ) ) continue;

            // Fetch serial(s) from wp_serial_numbers for this order + product
            $serials = self::get_serials_for_order_item( $order_id, $item_id, $product_id );

            if ( empty( $serials ) ) {
                Hutch_SMS_Logger::debug( "[Voucher] Order $order_id, item $item_id — no serials found in wp_serial_numbers." );
                continue;
            }

            foreach ( $serials as $serial ) {
                $message = self::build_voucher_message( $serial, $order, $item );

                Hutch_SMS_API::send_sms( array(
                    'campaignName' => 'Gift Voucher',
                    'mask'         => get_option( 'hutch_sms_mask', '' ),
                    'numbers'      => $phone,
                    'content'      => $message,
                ) );

                Hutch_SMS_Logger::debug( "[Voucher] Sent serial for order $order_id, product $product_id to $phone." );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DB helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch serial records from wp_serial_numbers for a given order + item + product.
     */
    private static function get_serials_for_order_item( int $order_id, int $item_id, int $product_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'serial_numbers';

        // Try order_id + order_item_id first (most precise)
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `$table`
             WHERE order_id = %d AND order_item_id = %d AND product_id = %d
             ORDER BY id ASC",
            $order_id, $item_id, $product_id
        ), ARRAY_A );

        // Fallback: match by order_id + product_id only
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `$table`
                 WHERE order_id = %d AND product_id = %d
                 ORDER BY id ASC",
                $order_id, $product_id
            ), ARRAY_A );
        }

        return $rows ?: array();
    }

    // ─────────────────────────────────────────────────────────────────
    // Template helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the voucher SMS message.
     * Placeholders: {serial}, {product_name}, {order_id},
     *               {expire_date}, {validity}, {first_name}
     */
    private static function build_voucher_message( array $serial, $order, $item ): string {
        $template = get_option(
            'hutch_sms_msg_voucher',
            'Hi {first_name}, your gift voucher for {product_name} is: {serial}. Valid until: {expire_date}. Order #{order_id}.'
        );

        $expire = $serial['expire_date'] ?? '';
        if ( $expire && $expire !== '0000-00-00 00:00:00' ) {
            $expire = date( 'd M Y', strtotime( $expire ) );
        } else {
            $expire = $serial['validity'] ?: 'N/A';
        }

        return str_replace(
            array( '{serial}', '{product_name}', '{order_id}', '{expire_date}', '{validity}', '{first_name}' ),
            array(
                $serial['serial_key'],
                $item->get_name(),
                $order->get_order_number(),
                $expire,
                $serial['validity'] ?: 'N/A',
                $order->get_billing_first_name(),
            ),
            $template
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Config helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return array of product IDs configured as gift vouchers.
     */
    public static function get_voucher_product_ids(): array {
        $raw = get_option( 'hutch_sms_voucher_product_ids', '' );
        if ( empty( $raw ) ) return array();
        return array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $raw ) ) );
    }

    /**
     * Normalise phone to 94XXXXXXXXX.
     */
    private static function get_phone( $order ): string {
        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return '';
        $phone = preg_replace( '/\D/', '', $phone );
        if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '0' ) {
            $phone = '94' . substr( $phone, 1 );
        }
        return $phone;
    }
}
