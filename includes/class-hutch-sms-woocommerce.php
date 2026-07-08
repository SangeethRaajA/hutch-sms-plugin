<?php
/**
 * Hutch SMS – WooCommerce Integration  v1.2.0
 *
 * XStore COMPATIBILITY FIX:
 * XStore's checkout builder + payment gateways (especially cash on delivery,
 * bank transfer, and custom gateways) can route order creation through
 * different hooks depending on payment method:
 *
 *   - Online payments (Stripe, PayHere, etc):
 *       woocommerce_payment_complete  →  status becomes 'processing'
 *
 *   - Offline payments (COD, BACS):
 *       woocommerce_checkout_order_created  →  status is set immediately
 *       then woocommerce_order_status_processing fires
 *
 *   - XStore Checkout Builder (Elementor shortcode):
 *       Still uses [woocommerce_checkout] shortcode submission,
 *       so standard WC hooks DO fire — but only AFTER Elementor JS
 *       processing completes. This can cause timing issues.
 *
 * SOLUTION: Hook into ALL relevant order lifecycle events and use a
 * per-order transient to prevent duplicate SMS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_WooCommerce {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        // ── Order Confirmation hooks ───────────────────────────
        // woocommerce_order_status_processing : standard flow (COD, BACS, manual)
        add_action( 'woocommerce_order_status_processing',           array( __CLASS__, 'on_order_confirmed' ), 10, 1 );
        // woocommerce_payment_complete         : fires after online payment succeeds
        add_action( 'woocommerce_payment_complete',                  array( __CLASS__, 'on_order_confirmed' ), 10, 1 );
        // woocommerce_order_status_on-hold     : some gateways use on-hold as confirmation
        add_action( 'woocommerce_order_status_on-hold',              array( __CLASS__, 'on_order_confirmed' ), 10, 1 );
        // woocommerce_checkout_order_processed : fires right after checkout form submit
        //   — catches cases where status hooks fire too late
        add_action( 'woocommerce_checkout_order_processed',          array( __CLASS__, 'on_checkout_processed' ), 10, 3 );

        // ── Order Completion hook ──────────────────────────────
        add_action( 'woocommerce_order_status_completed',            array( __CLASS__, 'on_order_completed' ), 10, 1 );
    }

    // ──────────────────────────────────────────────────────────
    // Hook handlers
    // ──────────────────────────────────────────────────────────

    /**
     * Triggered by status→processing, payment_complete, or status→on-hold.
     * Uses a dedup transient so only one confirmation SMS is ever sent
     * per order, regardless of which hook fires first.
     */
    public static function on_order_confirmed( $order_id ) {
        if ( ! get_option( 'hutch_sms_enable_order_confirm', false ) ) return;

        // Dedup: skip if we already sent a confirmation for this order
        $dedup_key = 'hutch_sms_confirm_sent_' . (int) $order_id;
        if ( get_transient( $dedup_key ) ) {
            Hutch_SMS_Logger::debug( "[Order Confirm] Order $order_id — already sent, skipping duplicate." );
            return;
        }

        $order = self::load_order( $order_id );
        if ( ! $order ) return;

        // Only send for actual customer orders (not refunds/subscriptions)
        if ( ! in_array( $order->get_type(), array( 'shop_order' ), true ) ) return;

        // Gift Voucher orders get their own SMS — skip confirmation SMS for them
        if ( class_exists( 'Hutch_SMS_Voucher' ) && Hutch_SMS_Voucher::order_has_voucher( $order ) ) {
            Hutch_SMS_Logger::debug( "[Order Confirm] Order $order_id — contains Gift Voucher, skipping (voucher SMS handles this)." );
            return;
        }

        $phone = self::get_phone( $order );
        if ( ! $phone ) {
            Hutch_SMS_Logger::debug( "[Order Confirm] Order $order_id — no valid phone. Raw: '" . $order->get_billing_phone() . "'" );
            return;
        }

        $message = self::build_message(
            get_option( 'hutch_sms_msg_order_confirm',
                'Hi {first_name}, thank you for your order #{order_id} of {total}. We are processing it now!' ),
            $order
        );

        Hutch_SMS_Logger::debug( "[Order Confirm] Sending to $phone for order $order_id via hook." );

        $result = Hutch_SMS_API::send_sms( array(
            'campaignName' => 'Order Confirmation',
            'mask'         => get_option( 'hutch_sms_mask', '' ),
            'numbers'      => $phone,
            'content'      => $message,
        ) );

        if ( ! is_wp_error( $result ) ) {
            // Mark as sent for 7 days to prevent any duplicate from other hooks
            set_transient( $dedup_key, true, 7 * DAY_IN_SECONDS );
        }
    }

    /**
     * Fires immediately after XStore's checkout form submission
     * (woocommerce_checkout_order_processed). This is the earliest
     * point the order exists, BEFORE payment processing.
     *
     * We use this as a FALLBACK for payment gateways that don't
     * trigger woocommerce_order_status_processing (e.g. some COD
     * setups where order goes straight to on-hold or pending).
     *
     * @param int    $order_id
     * @param array  $posted_data  Checkout form POST data (contains billing_phone)
     * @param object $order
     */
    public static function on_checkout_processed( $order_id, $posted_data, $order ) {
        if ( ! get_option( 'hutch_sms_enable_order_confirm', false ) ) return;

        // Gift Voucher orders skip confirmation SMS
        if ( class_exists( 'Hutch_SMS_Voucher' ) && Hutch_SMS_Voucher::order_has_voucher( $order ) ) {
            Hutch_SMS_Logger::debug( "[Checkout] Order $order_id — contains Gift Voucher, skipping confirmation SMS." );
            return;
        }

        // Only fire for payment methods that won't trigger processing/payment_complete
        // (i.e. methods that leave order in pending or on-hold permanently)
        $payment_method = $order->get_payment_method();
        $offline_methods = apply_filters( 'hutch_sms_offline_payment_methods', array( 'bacs', 'cheque', 'cod' ) );

        // For online gateways, we rely on woocommerce_payment_complete instead
        if ( ! in_array( $payment_method, $offline_methods, true ) ) {
            Hutch_SMS_Logger::debug( "[Checkout] Order $order_id — online gateway '$payment_method', deferring to payment_complete hook." );
            return;
        }

        Hutch_SMS_Logger::debug( "[Checkout] Order $order_id — offline gateway '$payment_method', sending confirmation now." );
        self::on_order_confirmed( $order_id );
    }

    /**
     * Order completion — only fires once.
     */
    public static function on_order_completed( $order_id ) {
        if ( ! get_option( 'hutch_sms_enable_order_complete', false ) ) return;

        $order = self::load_order( $order_id );
        if ( ! $order ) return;

        // Gift Voucher orders get their own SMS — skip completion SMS for them
        if ( class_exists( 'Hutch_SMS_Voucher' ) && Hutch_SMS_Voucher::order_has_voucher( $order ) ) {
            Hutch_SMS_Logger::debug( "[Order Complete] Order $order_id — contains Gift Voucher, skipping (voucher SMS handles this)." );
            return;
        }

        $phone = self::get_phone( $order );
        if ( ! $phone ) {
            Hutch_SMS_Logger::debug( "[Order Complete] Order $order_id — no valid phone. Raw: '" . $order->get_billing_phone() . "'" );
            return;
        }

        $message = self::build_message(
            get_option( 'hutch_sms_msg_order_complete',
                'Hi {first_name}, your order #{order_id} has been completed. Thank you for shopping with us!' ),
            $order
        );

        Hutch_SMS_Logger::debug( "[Order Complete] Sending to $phone for order $order_id." );

        Hutch_SMS_API::send_sms( array(
            'campaignName' => 'Order Completion',
            'mask'         => get_option( 'hutch_sms_mask', '' ),
            'numbers'      => $phone,
            'content'      => $message,
        ) );
    }

    // ──────────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Safely load a WC_Order.
     * Compatible with HPOS (wp_wc_orders) and legacy post storage.
     *
     * @return WC_Order|false
     */
    public static function load_order( $order_id ) {
        if ( empty( $order_id ) ) return false;
        $order = wc_get_order( (int) $order_id );
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            Hutch_SMS_Logger::debug( "[load_order] Failed to load order: $order_id" );
            return false;
        }
        return $order;
    }

    /**
     * Get normalised phone from order object.
     */
    public static function get_phone( $order ): string {
        // Try billing phone first (standard WC field saved by XStore checkout)
        $phone = $order->get_billing_phone();

        // Fallback: read directly from order meta (HPOS stores under _billing_phone)
        if ( empty( $phone ) ) {
            $phone = $order->get_meta( '_billing_phone', true );
        }

        // Fallback: read from customer user meta
        if ( empty( $phone ) && $order->get_customer_id() ) {
            $phone = get_user_meta( $order->get_customer_id(), 'billing_phone', true );
        }

        if ( empty( $phone ) ) return '';

        return self::normalise_phone( (string) $phone );
    }

    /**
     * Normalise any Sri Lankan phone format → 94XXXXXXXXX (11 digits).
     *
     * Input formats handled:
     *   07XXXXXXXX    → 10 digits, local     → 947XXXXXXXX
     *   947XXXXXXXX   → 11 digits, correct   → unchanged
     *   +947XXXXXXXX  → + stripped → 947...  → unchanged
     *   00947XXXXXXXX → 13 digits, IDD       → strip 00 prefix
     *   9477XXXXXXX   → 12 digits (rare +94 with extra digit) → unchanged
     */
    public static function normalise_phone( string $raw ): string {
        $phone = preg_replace( '/\D/', '', $raw );
        if ( empty( $phone ) ) return '';

        $len = strlen( $phone );

        // Local format: 07x... (10 digits starting with 0)
        if ( $len === 10 && $phone[0] === '0' ) {
            return '94' . substr( $phone, 1 );
        }

        // IDD prefix: 0094... (13 digits)
        if ( $len === 13 && substr( $phone, 0, 4 ) === '0094' ) {
            return substr( $phone, 2 );
        }

        // Already international: 94XXXXXXXXX (11 digits)
        if ( $len === 11 && substr( $phone, 0, 2 ) === '94' ) {
            return $phone;
        }

        // +94 stripped by preg to 94... + 9 digits = 12 digits total — treat as valid
        if ( $len === 12 && substr( $phone, 0, 2 ) === '94' ) {
            return $phone;
        }

        Hutch_SMS_Logger::debug( "[normalise_phone] Cannot normalise: raw='$raw' stripped='$phone' len=$len" );
        return '';
    }

    /**
     * Build SMS from template. Placeholders:
     * {order_id} {first_name} {last_name} {total} {payment_method} {items_count}
     */
    public static function build_message( string $template, $order ): string {
        $items_count = 0;
        foreach ( $order->get_items() as $item ) {
            $items_count += (int) $item->get_quantity();
        }

        return str_replace(
            array( '{order_id}', '{first_name}', '{last_name}', '{total}', '{payment_method}', '{items_count}' ),
            array(
                $order->get_order_number(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                strip_tags( $order->get_formatted_order_total() ),
                $order->get_payment_method_title(),
                $items_count,
            ),
            $template
        );
    }
}
