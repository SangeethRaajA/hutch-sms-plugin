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
        // Try PluginEver v2+ Serial Object API first (returns decrypted keys)
        $rows = self::get_via_serial_object_api( $order_id, $product_id );
        if ( ! empty( $rows ) ) return $rows;

        // Try legacy WC_Serial_Numbers_Query API
        if ( class_exists( 'WC_Serial_Numbers_Query' ) ) {
            $rows = self::get_via_plugin_api( $order_id, $product_id );
            if ( ! empty( $rows ) ) return $rows;
        }

        // Fallback: direct DB query + decrypt
        return self::get_via_db( $order_id, $item_id, $product_id );
    }

    /**
     * PluginEver v2+ uses namespaced Serial model objects which return decrypted keys.
     */
    private static function get_via_serial_object_api( int $order_id, int $product_id ): array {
        $classes = array(
            'WooCommerce_Serial_Numbers\Models\Serial',
            'WC_Serial_Numbers\Models\Serial',
        );
        $class = null;
        foreach ( $classes as $c ) {
            if ( class_exists( $c ) ) { $class = $c; break; }
        }
        if ( ! $class ) return array();

        try {
            $results = $class::get(
                array(
                    'order_id'   => $order_id,
                    'product_id' => $product_id,
                    'status'     => 'sold',
                    'limit'      => -1,
                ),
                'objects'
            );
            if ( empty( $results ) ) return array();
            Hutch_SMS_Logger::debug( "[Voucher] Serial Object API: order=$order_id product=$product_id => " . count( $results ) . " rows." );
            return array_map( function( $obj ) {
                return array(
                    'serial_key'  => method_exists( $obj, 'get_serial_key' ) ? $obj->get_serial_key() : ( $obj->serial_key ?? '' ),
                    'product_id'  => $obj->product_id ?? 0,
                    'order_id'    => $obj->order_id ?? 0,
                    'validity'    => $obj->validity ?? '',
                    'expire_date' => $obj->expire_date ?? '',
                );
            }, $results );
        } catch ( \Exception $e ) {
            Hutch_SMS_Logger::debug( "[Voucher] Serial Object API error: " . $e->getMessage() );
            return array();
        }
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
            Hutch_SMS_Logger::debug( "[Voucher] PluginEver Legacy API: order=$order_id product=$product_id => " . count( $results ) . " rows." );
            $rows = array_map( fn( $r ) => is_object( $r ) ? (array) $r : $r, $results );
            foreach ( $rows as &$row ) {
                $row['serial_key'] = self::decrypt_serial( $row['serial_key'] ?? '' );
            }
            return $rows;
        } catch ( \Exception $e ) {
            Hutch_SMS_Logger::debug( "[Voucher] PluginEver Legacy API error: " . $e->getMessage() );
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

        $sample = isset( $rows[0]['serial_key'] ) ? substr( $rows[0]['serial_key'], 0, 20 ) . '...' : 'none';
        Hutch_SMS_Logger::debug( "[Voucher] DB query: order=$order_id => " . count( $rows ) . " rows. Raw sample: $sample. Error: " . ( $wpdb->last_error ?: 'none' ) );

        // PluginEver stores serial_key encrypted — decrypt before use
        foreach ( $rows as &$row ) {
            $row['serial_key'] = self::decrypt_serial( $row['serial_key'] ?? '' );
        }

        return $rows ?: array();
    }

    /**
     * Decrypt a PluginEver-encrypted serial key.
     *
     * PluginEver encrypts serials using AES-256-CBC before storing in the DB.
     * We try their own decryption classes first, then fall back to manual decryption.
     */
    private static function decrypt_serial( string $raw ): string {
        if ( empty( $raw ) ) return $raw;

        // Method 1: PluginEver v1 encryption class
        if ( class_exists( 'WC_Serial_Numbers_Encryption' ) && method_exists( 'WC_Serial_Numbers_Encryption', 'decrypt' ) ) {
            $dec = WC_Serial_Numbers_Encryption::decrypt( $raw );
            if ( $dec && $dec !== $raw ) {
                Hutch_SMS_Logger::debug( "[Voucher] Decrypted via WC_Serial_Numbers_Encryption." );
                return $dec;
            }
        }

        // Method 2: PluginEver v2 encryption class
        if ( class_exists( 'WooCommerce_Serial_Numbers\Encryption' ) && method_exists( 'WooCommerce_Serial_Numbers\Encryption', 'decrypt' ) ) {
            $dec = \WooCommerce_Serial_Numbers\Encryption::decrypt( $raw );
            if ( $dec && $dec !== $raw ) {
                Hutch_SMS_Logger::debug( "[Voucher] Decrypted via WooCommerce_Serial_Numbers\\Encryption." );
                return $dec;
            }
        }

        // Method 3: helper function
        if ( function_exists( 'wc_serial_numbers_decrypt' ) ) {
            $dec = wc_serial_numbers_decrypt( $raw );
            if ( $dec && $dec !== $raw ) return $dec;
        }

        // Method 4: manual AES-256-CBC matching PluginEver's scheme
        $dec = self::aes_decrypt( $raw );
        if ( $dec !== false ) {
            Hutch_SMS_Logger::debug( "[Voucher] Decrypted via manual AES-256-CBC." );
            return $dec;
        }

        Hutch_SMS_Logger::debug( "[Voucher] Warning: could not decrypt serial key — sending raw value." );
        return $raw;
    }

    /**
     * Manual AES-256-CBC decrypt using WordPress AUTH_KEY as the encryption key base.
     * PluginEver stores: base64( IV[16 bytes] . ciphertext )
     */
    private static function aes_decrypt( string $encoded ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) return false;

        $wp_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );
        if ( empty( $wp_key ) ) return false;

        $key     = substr( hash( 'sha256', $wp_key, true ), 0, 32 );
        $decoded = base64_decode( $encoded, true );
        if ( $decoded === false || strlen( $decoded ) <= 16 ) return false;

        $iv         = substr( $decoded, 0, 16 );
        $ciphertext = substr( $decoded, 16 );
        $result     = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return ( $result !== false && $result !== '' ) ? $result : false;
    }


    // ─────────────────────────────────────────────────────────────────
    // Message builder
    // ─────────────────────────────────────────────────────────────────

    private static function build_message( array $serial, $order, $item ): string {
        $template = get_option(
            'hutch_sms_msg_voucher',
            'Hi {first_name}, your gift voucher is: {serial}. Valid until: {expire_date}. Order #{order_id}. - Nadiyas'
        );

        // ── Resolve validity / expire_date ────────────────────────────
        // Priority:
        //   1. expire_date is a real datetime   → format as "d M Y"
        //   2. validity column has a number     → use as-is (e.g. "180")
        //   3. PluginEver product meta          → _serial_numbers_validity
        //   4. Nothing found                    → "N/A"

        $expire_raw = trim( (string) ( $serial['expire_date'] ?? '' ) );
        $validity   = trim( (string) ( $serial['validity']    ?? '' ) );

        $has_expire   = $expire_raw && ! in_array( $expire_raw, array( '', '0000-00-00 00:00:00', 'NULL' ), true );
        $has_validity = $validity   && ! in_array( $validity,   array( '', 'NULL' ), true ) && is_numeric( $validity );

        if ( $has_expire ) {
            $expire_display   = date( 'd M Y', strtotime( $expire_raw ) );
            $validity_display = $expire_display;
        } elseif ( $has_validity ) {
            $expire_display   = $validity;          // just the number — template already says "days"
            $validity_display = $validity;
        } else {
            // Final fallback: read validity from the WooCommerce product meta set by PluginEver
            $product_id      = (int) $item->get_product_id();
            $meta_validity   = get_post_meta( $product_id, '_serial_numbers_validity', true );
            if ( $meta_validity && is_numeric( $meta_validity ) ) {
                $expire_display   = $meta_validity;
                $validity_display = $meta_validity;
            } else {
                $expire_display   = 'N/A';
                $validity_display = 'N/A';
            }
        }

        return str_replace(
            array( '{serial}', '{serial_key}', '{product_name}', '{order_id}', '{expire_date}', '{validity}', '{first_name}' ),
            array(
                $serial['serial_key'],
                $serial['serial_key'],
                $item->get_name(),
                $order->get_order_number(),
                $expire_display,
                $validity_display,
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
