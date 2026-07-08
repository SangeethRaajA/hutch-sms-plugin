<?php
/**
 * Hutch SMS – Gift Voucher Serial Number SMS  v1.3.0
 *
 * Serial Number Source: WooCommerce Serial Numbers by PluginEver
 *   Plugin slug: wc-serial-numbers
 *   Table: wp_wc_serial_numbers
 *   API class: WC_Serial_Numbers_Query (if available) or direct DB
 *
 * Gift Voucher Detection: By product category slug 'gift-vouchers'
 *   Matches nadiyas.com/product-category/gift-vouchers/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Voucher {

    /** PluginEver table name (without prefix) */
    const SERIAL_TABLE = 'wc_serial_numbers';

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        // Priority 30 — after standard order SMS (priority 10) and after
        // PluginEver assigns serials (usually priority 10–20)
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
        add_action( 'woocommerce_payment_complete',        array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
    }

    public static function register_cron_hook() {
        add_action( 'hutch_sms_voucher_retry', array( __CLASS__, 'cron_retry_send' ) );
    }

    // ─────────────────────────────────────────────────────────────────
    // Main handler
    // ─────────────────────────────────────────────────────────────────

    public static function send_voucher_serials( $order_id ) {
        if ( ! get_option( 'hutch_sms_enable_voucher_sms', false ) ) return;

        // Dedup — only send voucher SMS once per order
        $dedup = 'hutch_voucher_sent_' . (int) $order_id;
        if ( get_transient( $dedup ) ) {
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — already sent, skipping." );
            return;
        }

        $order = Hutch_SMS_WooCommerce::load_order( $order_id );
        if ( ! $order ) return;

        $phone = Hutch_SMS_WooCommerce::get_phone( $order );
        if ( ! $phone ) {
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — no phone." );
            return;
        }

        $voucher_cat = get_option( 'hutch_sms_voucher_category', 'gift-vouchers' );
        $sent_any    = false;

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = (int) $item->get_product_id();

            // Check if this product is in the gift voucher category
            if ( ! self::product_is_voucher( $product_id, $voucher_cat ) ) continue;

            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — product $product_id is a gift voucher." );

            $serials = self::get_serials( $order_id, $item_id, $product_id );

            if ( empty( $serials ) ) {
                Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — no serials found yet, scheduling retry." );
                self::schedule_retry( (int) $order_id );
                continue;
            }

            foreach ( $serials as $serial ) {
                $message = self::build_message( $serial, $order, $item );
                Hutch_SMS_Logger::debug( "[Voucher] Sending serial '{$serial['serial_key']}' to $phone." );

                Hutch_SMS_API::send_sms( array(
                    'campaignName' => 'Gift Voucher',
                    'mask'         => get_option( 'hutch_sms_mask', '' ),
                    'numbers'      => $phone,
                    'content'      => $message,
                ) );

                $sent_any = true;
            }
        }

        if ( $sent_any ) {
            set_transient( $dedup, true, 7 * DAY_IN_SECONDS );
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Category check
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns true if $product_id belongs to $category_slug (or any child of it).
     */
    public static function product_is_voucher( int $product_id, string $category_slug ): bool {
        if ( empty( $category_slug ) ) return false;
        return has_term( $category_slug, 'product_cat', $product_id );
    }

    // ─────────────────────────────────────────────────────────────────
    // PluginEver Serial Numbers query
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch serial records from wp_wc_serial_numbers (PluginEver plugin).
     *
     * PluginEver schema (wp_wc_serial_numbers):
     *   id, serial_key, product_id, activation_limit, activation_count,
     *   order_id, order_item_id (may not exist in older versions), vendor_id,
     *   status, validity, expire_date, order_date, uuid, source, created_date
     *
     * Uses the plugin's own query class if available, falls back to direct DB.
     */
    private static function get_serials( int $order_id, int $item_id, int $product_id ): array {
        // Prefer PluginEver's own API if loaded
        if ( class_exists( 'WC_Serial_Numbers_Query' ) ) {
            return self::get_via_plugin_api( $order_id, $product_id );
        }

        // Direct DB fallback
        return self::get_via_db( $order_id, $item_id, $product_id );
    }

    private static function get_via_plugin_api( int $order_id, int $product_id ): array {
        try {
            $query = new WC_Serial_Numbers_Query( array(
                'order_id'   => $order_id,
                'product_id' => $product_id,
                'status'     => 'sold',
                'return'     => 'all',
            ) );
            $results = $query->get_serial_numbers();
            Hutch_SMS_Logger::debug( "[Voucher] PluginEver API: order=$order_id product=$product_id → " . count( $results ) . " serials." );
            // Normalise to array-of-arrays
            return array_map( function( $r ) {
                return is_object( $r ) ? (array) $r : $r;
            }, $results );
        } catch ( \Exception $e ) {
            Hutch_SMS_Logger::debug( "[Voucher] PluginEver API error: " . $e->getMessage() );
            return array();
        }
    }

    private static function get_via_db( int $order_id, int $item_id, int $product_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::SERIAL_TABLE;

        // Check table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            Hutch_SMS_Logger::debug( "[Voucher] Table $table does not exist." );
            return array();
        }

        // Check if order_item_id column exists (added in newer PluginEver versions)
        $has_item_col = (bool) $wpdb->get_var( "SHOW COLUMNS FROM `$table` LIKE 'order_item_id'" );

        if ( $has_item_col ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE order_id=%d AND order_item_id=%d AND product_id=%d ORDER BY id ASC",
                $order_id, $item_id, $product_id
            ), ARRAY_A );
        } else {
            $rows = array();
        }

        // Fallback: order_id + product_id only
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE order_id=%d AND product_id=%d ORDER BY id ASC",
                $order_id, $product_id
            ), ARRAY_A );
        }

        Hutch_SMS_Logger::debug( "[Voucher] DB query: order=$order_id product=$product_id → " . count( $rows ) . " rows. Error: " . ( $wpdb->last_error ?: 'none' ) );
        return $rows ?: array();
    }

    // ─────────────────────────────────────────────────────────────────
    // Message builder
    // ─────────────────────────────────────────────────────────────────

    private static function build_message( array $serial, $order, $item ): string {
        $template = get_option(
            'hutch_sms_msg_voucher',
            'Hi {first_name}, your gift voucher for {product_name} is: {serial}. Valid until: {expire_date}. Order #{order_id}. - Nadiyas'
        );

        $expire = $serial['expire_date'] ?? '';
        if ( $expire && $expire !== '0000-00-00 00:00:00' ) {
            $expire = date( 'd M Y', strtotime( $expire ) );
        } else {
            $expire = ! empty( $serial['validity'] ) ? $serial['validity'] : 'No expiry';
        }

        return str_replace(
            array( '{serial}', '{product_name}', '{order_id}', '{expire_date}', '{validity}', '{first_name}' ),
            array(
                $serial['serial_key'],
                $item->get_name(),
                $order->get_order_number(),
                $expire,
                ! empty( $serial['validity'] ) ? $serial['validity'] : 'No expiry',
                $order->get_billing_first_name(),
            ),
            $template
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Cron retry
    // ─────────────────────────────────────────────────────────────────

    private static function schedule_retry( int $order_id ) {
        if ( ! wp_next_scheduled( 'hutch_sms_voucher_retry', array( $order_id ) ) ) {
            wp_schedule_single_event( time() + 90, 'hutch_sms_voucher_retry', array( $order_id ) );
            Hutch_SMS_Logger::debug( "[Voucher] Retry scheduled in 90s for order $order_id." );
        }
    }

    public static function cron_retry_send( int $order_id ) {
        Hutch_SMS_Logger::debug( "[Voucher] Cron retry for order $order_id." );
        // Clear dedup so retry can proceed
        delete_transient( 'hutch_voucher_sent_' . $order_id );
        self::send_voucher_serials( $order_id );
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper: get all products in voucher category (for Settings UI)
    // ─────────────────────────────────────────────────────────────────

    public static function get_voucher_category_products( string $slug = '' ): array {
        if ( empty( $slug ) ) $slug = get_option( 'hutch_sms_voucher_category', 'gift-vouchers' );
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( ! $term ) return array();

        $products = wc_get_products( array(
            'category' => array( $slug ),
            'limit'    => 50,
            'status'   => 'publish',
            'return'   => 'objects',
        ) );

        return $products ?: array();
    }

    /**
     * Check if PluginEver Serial Numbers plugin is active.
     */
    public static function plugin_ever_active(): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::SERIAL_TABLE;
        return (bool) $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
    }
}
