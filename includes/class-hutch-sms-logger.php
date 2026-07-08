<?php
/**
 * Hutch SMS Logger
 * Stores SMS history in a custom DB table and writes debug logs.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Logger {

    const TABLE_SUFFIX = 'hutch_sms_log';

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id         BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            sent_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            type       VARCHAR(20)  NOT NULL DEFAULT 'individual',
            numbers    TEXT         NOT NULL,
            campaign   VARCHAR(255) NOT NULL DEFAULT '',
            message    TEXT         NOT NULL,
            status     VARCHAR(20)  NOT NULL DEFAULT 'pending',
            response   TEXT,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Log an SMS event to the database.
     */
    public static function log( array $data ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::TABLE_SUFFIX,
            array(
                'sent_at'  => current_time( 'mysql' ),
                'type'     => $data['type']     ?? 'individual',
                'numbers'  => $data['numbers']  ?? '',
                'campaign' => $data['campaign'] ?? '',
                'message'  => $data['message']  ?? '',
                'status'   => $data['status']   ?? 'pending',
                'response' => $data['response'] ?? '',
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Write a debug entry to a flat log file (when debug mode is enabled).
     */
    public static function debug( string $message ) {
        if ( ! get_option( 'hutch_sms_debug', false ) ) return;

        $log_file = WP_CONTENT_DIR . '/hutch-sms-debug.log';
        $entry    = '[' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL;

        // Keep file under 1 MB
        if ( file_exists( $log_file ) && filesize( $log_file ) > 1024 * 1024 ) {
            $lines = file( $log_file );
            $lines = array_slice( $lines, (int) ( count( $lines ) / 2 ) );
            file_put_contents( $log_file, implode( '', $lines ) );
        }

        file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Retrieve log entries from the DB.
     */
    public static function get_logs( array $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE_SUFFIX;
        $limit  = (int) ( $args['limit']  ?? 50 );
        $offset = (int) ( $args['offset'] ?? 0 );
        $type   = sanitize_text_field( $args['type'] ?? '' );
        $status = sanitize_text_field( $args['status'] ?? '' );

        $where  = array( '1=1' );
        $values = array();

        if ( $type ) {
            $where[]  = 'type = %s';
            $values[] = $type;
        }
        if ( $status ) {
            $where[]  = 'status = %s';
            $values[] = $status;
        }

        $where_sql = implode( ' AND ', $where );

        if ( $values ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE $where_sql ORDER BY sent_at DESC LIMIT %d OFFSET %d",
                array_merge( $values, array( $limit, $offset ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table ORDER BY sent_at DESC LIMIT %d OFFSET %d",
                $limit, $offset
            );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Count log entries.
     */
    public static function count_logs( array $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE_SUFFIX;
        $type   = sanitize_text_field( $args['type'] ?? '' );
        $status = sanitize_text_field( $args['status'] ?? '' );

        $where  = array( '1=1' );
        $values = array();

        if ( $type ) {
            $where[]  = 'type = %s';
            $values[] = $type;
        }
        if ( $status ) {
            $where[]  = 'status = %s';
            $values[] = $status;
        }

        $where_sql = implode( ' AND ', $where );

        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where_sql", $values ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where_sql" );
    }

    /**
     * Clear all logs.
     */
    public static function clear_logs() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::TABLE_SUFFIX );
    }

    /**
     * Read the debug log file contents.
     */
    public static function get_debug_log( int $lines = 200 ): string {
        $log_file = WP_CONTENT_DIR . '/hutch-sms-debug.log';
        if ( ! file_exists( $log_file ) ) return '';

        $all   = file( $log_file );
        $slice = array_slice( $all, - $lines );
        return implode( '', array_reverse( $slice ) );
    }

    /**
     * Clear the debug log file.
     */
    public static function clear_debug_log() {
        $log_file = WP_CONTENT_DIR . '/hutch-sms-debug.log';
        if ( file_exists( $log_file ) ) {
            file_put_contents( $log_file, '' );
        }
    }
}
