<?php
/**
 * Hutch SMS – Gift Voucher Serial Number SMS  v1.4.0
 *
 * Serial Number Source: WooCommerce Serial Numbers by PluginEver
 *   Table: wp_serial_numbers  (confirmed from DESCRIBE output)
 *
 * Gift Voucher Detection: Product name contains "Gift Voucher" (case-insensitive)
 *
 * Important: Orders containing a Gift Voucher product are EXCLUDED from
 * Order Confirmation and Order Completion SMS — only the Voucher Serial SMS fires.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Voucher {

    /** PluginEver actual table (confirmed: wp_serial_numbers) */
    const SERIAL_TABLE = 'serial_numbers';

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        // Priority 30 — after PluginEver assigns serials (usually priority 10–20)
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
        add_action( 'woocommerce_payment_complete',        array( __CLASS__, 'send_voucher_serials' ), 30, 1 );
    }

    public static function register_cron_hook() {
        add_action( 'hutch_sms_voucher_retry', array( __CLASS__, 'cron_retry_send' ) );
    }

    // ─────────────────────────────────────────────────────────────────
    // Public: check if an order contains any gift voucher product
    // Used by WooCommerce class to skip confirmation/completion SMS
    // ─────────────────────────────────────────────────────────────────

    public static function order_has_voucher( $order ): bool {
        foreach ( $order->get_items() as $item ) {
            if ( self::product_is_voucher( $item->get_name() ) ) return true;
        }
        return false;
    }

    /**
     * A product is a gift voucher if its name contains "Gift Voucher" (case-insensitive).
     */
    public static function product_is_voucher( string $product_name ): bool {
        return stripos( $product_name, 'Gift Voucher' ) !== false;
    }

    // ─────────────────────────────────────────────────────────────────
    // Main handler
    // ─────────────────────────────────────────────────────────────────

    public static function send_voucher_serials( $order_id ) {
        if ( ! get_option( 'hutch_sms_enable_voucher_sms', false ) ) return;

        // Dedup — only send once per order regardless of which hook fires first
        $dedup = 'hutch_voucher_sent_' . (int) $order_id;
        if ( get_transient( $dedup ) ) {
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — already sent, skipping duplicate." );
            return;
        }

        $order = Hutch_SMS_WooCommerce::load_order( $order_id );
        if ( ! $order ) return;

        $phone = Hutch_SMS_WooCommerce::get_phone( $order );
        if ( ! $phone ) {
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — no phone, skipping." );
            return;
        }

        $sent_any = false;

        foreach ( $order->get_items() as $item_id => $item ) {
            if ( ! self::product_is_voucher( $item->get_name() ) ) continue;

            $product_id = (int) $item->get_product_id();
            Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — '{$item->get_name()}' identified as gift voucher." );

            $serials = self::get_serials( $order_id, $item_id, $product_id );

            if ( empty( $serials ) ) {
                Hutch_SMS_Logger::debug( "[Voucher] Order $order_id — no serials yet, scheduling retry." );
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
    // PluginEver Serial Numbers query — table: wp_serial_numbers
    // ─────────────────────────────────────────────────────────────────

    private static function get_serials( int $order_id, int $item_id, int $product_id ): array {
        // Try PluginEver's own query API first
        if ( class_exists( 'WC_Serial_Numbers_Query' ) ) {
            return self::get_via_plugin_api( $order_id, $product_id );
        }
        return self::get_via_db( $order_id, $item_id, $product_id );
    }

    private static function get_via_plugin_api( int $order_id, int $product_id ): array {
        try {
            $query   = new WC_Serial_Numbers_Query( array(
                'order_id'   => $order_id,
                'product_id' => $product_id,
                'status'     => 'sold',
                'return'     => 'all',
            ) );
            $results = $query->get_serial_numbers();
            Hutch_SMS_Logger::debug( "[Voucher] PluginEver API: order=$order_id product=$product_id → " . count( $results ) . " rows." );
            return array_map( fn( $r ) => is_object( $r ) ? (array) $r : $r, $results );
        } catch ( \Exception $e ) {
            Hutch_SMS_Logger::debug( "[Voucher] PluginEver API error: " . $e->getMessage() );
            return array();
        }
    }

    private static function get_via_db( int $order_id, int $item_id, int $product_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::SERIAL_TABLE;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            Hutch_SMS_Logger::debug( "[Voucher] Table $table not found." );
            return array();
        }

        // Precise: order_id + order_item_id + product_id
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE order_id=%d AND order_item_id=%d AND product_id=%d ORDER BY id ASC",
            $order_id, $item_id, $product_id
        ), ARRAY_A );

        // Fallback: order_id + product_id
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE order_id=%d AND product_id=%d ORDER BY id ASC",
                $order_id, $product_id
            ), ARRAY_A );
        }

        Hutch_SMS_Logger::debug( "[Voucher] DB query wp_serial_numbers: order=$order_id → " . count( $rows ) . " rows. Error: " . ( $wpdb->last_error ?: 'none' ) );
        return $rows ?: array();
    }

    // ─────────────────────────────────────────────────────────────────
    // Message builder
    // ─────────────────────────────────────────────────────────────────

    private static function build_message( array $serial, $order, $item ): string {
        $template = get_option(
            'hutch_sms_msg_voucher',
            'Hi {first_name}, your gift voucher is: {serial}. Valid until: {expire_date}. Order #{order_id}. - Nadiyas'
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
            Hutch_SMS_Logger::debug( "[Voucher] Retry in 90s for order $order_id." );
        }
    }

    public static function cron_retry_send( int $order_id ) {
        delete_transient( 'hutch_voucher_sent_' . $order_id );
        self::send_voucher_serials( $order_id );
    }

    // ─────────────────────────────────────────────────────────────────
    // Status check for Settings UI
    // ─────────────────────────────────────────────────────────────────

    public static function plugin_ever_active(): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::SERIAL_TABLE;
        return $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
    }
}
