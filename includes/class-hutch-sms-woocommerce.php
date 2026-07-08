<?php
/**
 * Hutch SMS – WooCommerce Integration
 * Sends SMS on order confirmation and order completion.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_WooCommerce {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_order_confirmed' ), 10, 2 );
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'on_order_completed' ),  10, 2 );
    }

    // ──────────────────────────────────────────────────────────
    // Hooks
    // ──────────────────────────────────────────────────────────

    public static function on_order_confirmed( int $order_id, $order ) {
        if ( ! get_option( 'hutch_sms_enable_order_confirm', false ) ) return;

        $phone   = self::get_phone( $order );
        $message = self::build_message(
            get_option( 'hutch_sms_msg_order_confirm', 'Thank you for your order #{order_id}! We are processing it now.' ),
            $order
        );

        if ( $phone ) {
            Hutch_SMS_API::send_sms( array(
                'campaignName' => 'Order Confirmation',
                'mask'         => get_option( 'hutch_sms_mask', '' ),
                'numbers'      => $phone,
                'content'      => $message,
            ) );
        }
    }

    public static function on_order_completed( int $order_id, $order ) {
        if ( ! get_option( 'hutch_sms_enable_order_complete', false ) ) return;

        $phone   = self::get_phone( $order );
        $message = self::build_message(
            get_option( 'hutch_sms_msg_order_complete', 'Your order #{order_id} has been delivered. Thank you for shopping with us!' ),
            $order
        );

        if ( $phone ) {
            Hutch_SMS_API::send_sms( array(
                'campaignName' => 'Order Completion',
                'mask'         => get_option( 'hutch_sms_mask', '' ),
                'numbers'      => $phone,
                'content'      => $message,
            ) );
        }
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Get a normalised phone number (94XXXXXXXXX format).
     */
    private static function get_phone( $order ): string {
        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return '';

        // Strip all non-numeric
        $phone = preg_replace( '/\D/', '', $phone );

        // Normalise Sri Lankan numbers: 07x → 947x, +947x → 947x
        if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '0' ) {
            $phone = '94' . substr( $phone, 1 );
        } elseif ( strlen( $phone ) === 12 && substr( $phone, 0, 2 ) === '94' ) {
            // already correct
        }

        return $phone;
    }

    /**
     * Replace placeholders in message template.
     * Supported: {order_id}, {first_name}, {last_name}, {total}
     */
    private static function build_message( string $template, $order ): string {
        return str_replace(
            array( '{order_id}', '{first_name}', '{last_name}', '{total}' ),
            array(
                $order->get_order_number(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                $order->get_formatted_order_total(),
            ),
            $template
        );
    }
}
