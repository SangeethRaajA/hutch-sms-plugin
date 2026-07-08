<?php
/**
 * Hutch SMS – WooCommerce Integration
 * Sends SMS on order confirmation and order completion.
 * Gift voucher serial SMS is handled by Hutch_SMS_Voucher.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_WooCommerce {

    public static function init() {
        if ( ! class_exists( 'WooCommerce' ) ) return;

        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_order_confirmed' ), 10, 2 );
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'on_order_completed' ),  10, 2 );
    }

    public static function on_order_confirmed( int $order_id, $order ) {
        if ( ! get_option( 'hutch_sms_enable_order_confirm', false ) ) return;

        $phone = self::get_phone( $order );
        if ( ! $phone ) return;

        $message = self::build_message(
            get_option( 'hutch_sms_msg_order_confirm', 'Hi {first_name}, thank you for your order #{order_id} of {total}. We are processing it now!' ),
            $order
        );

        Hutch_SMS_API::send_sms( array(
            'campaignName' => 'Order Confirmation',
            'mask'         => get_option( 'hutch_sms_mask', '' ),
            'numbers'      => $phone,
            'content'      => $message,
        ) );
    }

    public static function on_order_completed( int $order_id, $order ) {
        if ( ! get_option( 'hutch_sms_enable_order_complete', false ) ) return;

        $phone = self::get_phone( $order );
        if ( ! $phone ) return;

        $message = self::build_message(
            get_option( 'hutch_sms_msg_order_complete', 'Hi {first_name}, your order #{order_id} has been completed. Thank you for shopping with us!' ),
            $order
        );

        Hutch_SMS_API::send_sms( array(
            'campaignName' => 'Order Completion',
            'mask'         => get_option( 'hutch_sms_mask', '' ),
            'numbers'      => $phone,
            'content'      => $message,
        ) );
    }

    public static function get_phone( $order ): string {
        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return '';
        $phone = preg_replace( '/\D/', '', $phone );
        if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '0' ) {
            $phone = '94' . substr( $phone, 1 );
        } elseif ( strlen( $phone ) === 12 && substr( $phone, 0, 2 ) === '94' ) {
            // already correct
        } else {
            return '';
        }
        return $phone;
    }

    public static function build_message( string $template, $order ): string {
        $items_count = 0;
        foreach ( $order->get_items() as $item ) {
            $items_count += $item->get_quantity();
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
