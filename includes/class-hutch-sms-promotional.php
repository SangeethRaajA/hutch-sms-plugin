<?php
/**
 * Hutch SMS – Promotional Campaign Helper
 *
 * Pulls a unique customer contact list directly from wp_wc_orders
 * (the HPOS / WooCommerce custom orders table) so admins can
 * launch targeted bulk promotions to existing customers.
 *
 * wp_wc_orders schema used:
 *   billing_email, billing_phone (via order meta), customer_id,
 *   status, date_created_gmt, total_amount
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Promotional {

    // ─────────────────────────────────────────────────────────────────
    // Contact list query
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch unique customers with phone numbers from wp_wc_orders.
     * Joins wp_wc_orders_meta to get billing_phone.
     *
     * @param array $filters {
     *   status       string  WC order status e.g. 'wc-completed' (default: all paid)
     *   date_from    string  Y-m-d
     *   date_to      string  Y-m-d
     *   min_spend    float   Minimum lifetime total_amount
     *   limit        int     Max rows (0 = no limit)
     * }
     * @return array  Each row: { customer_id, billing_email, phone, name, total_orders, last_order_date }
     */
    public static function get_customer_contacts( array $filters = array() ): array {
        global $wpdb;

        $orders_table = $wpdb->prefix . 'wc_orders';
        $meta_table   = $wpdb->prefix . 'wc_orders_meta';

        // Build WHERE clauses
        $wheres = array( "o.type = 'shop_order'" );
        $params = array();

        // Status filter
        $status = sanitize_text_field( $filters['status'] ?? '' );
        if ( $status ) {
            $wheres[] = 'o.status = %s';
            $params[] = $status;
        } else {
            // Default: paid orders only
            $wheres[] = "o.status IN ('wc-completed','wc-processing','wc-on-hold')";
        }

        // Date range
        if ( ! empty( $filters['date_from'] ) ) {
            $wheres[] = 'o.date_created_gmt >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $wheres[] = 'o.date_created_gmt <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $wheres );

        // Subquery: per-customer aggregates
        $having_sql = '';
        if ( ! empty( $filters['min_spend'] ) ) {
            $having_sql = $wpdb->prepare( 'HAVING SUM(o.total_amount) >= %f', (float) $filters['min_spend'] );
        }

        $limit_sql = '';
        if ( ! empty( $filters['limit'] ) ) {
            $limit_sql = $wpdb->prepare( 'LIMIT %d', (int) $filters['limit'] );
        }

        // Build full SQL
        $base_sql = "
            SELECT
                o.customer_id,
                o.billing_email,
                MAX( CASE WHEN om.meta_key = '_billing_first_name' THEN om.meta_value END ) AS first_name,
                MAX( CASE WHEN om.meta_key = '_billing_last_name'  THEN om.meta_value END ) AS last_name,
                MAX( CASE WHEN om.meta_key = '_billing_phone'      THEN om.meta_value END ) AS raw_phone,
                COUNT( o.id )           AS total_orders,
                SUM( o.total_amount )   AS lifetime_spend,
                MAX( o.date_created_gmt ) AS last_order_date
            FROM `{$orders_table}` o
            LEFT JOIN `{$meta_table}` om ON om.order_id = o.id
            WHERE $where_sql
            GROUP BY o.customer_id, o.billing_email
            $having_sql
            ORDER BY last_order_date DESC
            $limit_sql
        ";

        if ( $params ) {
            $sql = $wpdb->prepare( $base_sql, $params );
        } else {
            $sql = $base_sql;
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( empty( $rows ) ) return array();

        // Normalise phone numbers & filter out rows without a valid phone
        $contacts = array();
        foreach ( $rows as $row ) {
            $phone = self::normalise_phone( $row['raw_phone'] ?? '' );
            if ( ! $phone ) continue;

            $contacts[] = array(
                'customer_id'    => $row['customer_id'],
                'billing_email'  => $row['billing_email'],
                'first_name'     => $row['first_name'] ?? '',
                'last_name'      => $row['last_name']  ?? '',
                'phone'          => $phone,
                'total_orders'   => (int) $row['total_orders'],
                'lifetime_spend' => number_format( (float) $row['lifetime_spend'], 2 ),
                'last_order_date'=> $row['last_order_date'],
            );
        }

        // De-duplicate by phone (keep first occurrence = most recent customer)
        $seen     = array();
        $deduped  = array();
        foreach ( $contacts as $c ) {
            if ( isset( $seen[ $c['phone'] ] ) ) continue;
            $seen[ $c['phone'] ] = true;
            $deduped[] = $c;
        }

        return $deduped;
    }

    /**
     * Count contacts matching filters (no phone validation — just raw count).
     */
    public static function count_customers( array $filters = array() ): int {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'wc_orders';

        $wheres = array( "type = 'shop_order'" );
        $params = array();

        $status = sanitize_text_field( $filters['status'] ?? '' );
        if ( $status ) {
            $wheres[] = 'status = %s';
            $params[] = $status;
        } else {
            $wheres[] = "status IN ('wc-completed','wc-processing','wc-on-hold')";
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $wheres[] = 'date_created_gmt >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $wheres[] = 'date_created_gmt <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $wheres );
        $sql = "SELECT COUNT(DISTINCT billing_email) FROM `$orders_table` WHERE $where_sql";

        return $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) )
            : (int) $wpdb->get_var( $sql );
    }

    /**
     * Build bulk message array from contacts + message template.
     * Template placeholders: {first_name}, {last_name}
     */
    public static function build_bulk_messages( array $contacts, string $campaign, string $mask, string $template ): array {
        $messages = array();
        foreach ( $contacts as $c ) {
            $content = str_replace(
                array( '{first_name}', '{last_name}' ),
                array( $c['first_name'], $c['last_name'] ),
                $template
            );
            $messages[] = array(
                'campaignName' => $campaign,
                'mask'         => $mask,
                'numbers'      => $c['phone'],
                'content'      => $content,
            );
        }
        return $messages;
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    public static function normalise_phone( string $phone ): string {
        $phone = preg_replace( '/\D/', '', $phone );
        if ( empty( $phone ) ) return '';

        // Sri Lankan mobile: 07x (10 digits) → 947x
        if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '0' ) {
            $phone = '94' . substr( $phone, 1 );
        }
        // Already 94XXXXXXXXX (12 digits)
        if ( strlen( $phone ) !== 12 || substr( $phone, 0, 2 ) !== '94' ) {
            return ''; // unrecognised format — skip
        }
        return $phone;
    }

    /**
     * Returns available WC order statuses for use in filter dropdowns.
     */
    public static function get_order_statuses(): array {
        if ( function_exists( 'wc_get_order_statuses' ) ) {
            return wc_get_order_statuses();
        }
        return array(
            'wc-pending'    => 'Pending',
            'wc-processing' => 'Processing',
            'wc-on-hold'    => 'On Hold',
            'wc-completed'  => 'Completed',
            'wc-cancelled'  => 'Cancelled',
            'wc-refunded'   => 'Refunded',
            'wc-failed'     => 'Failed',
        );
    }
}
