<?php
/**
 * Hutch SMS – Admin Controller
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Admin {

    public static function init() {
        add_action( 'admin_menu',             array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_init',             array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts',  array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_post_hutch_sms_send_individual', array( __CLASS__, 'handle_send_individual' ) );
        add_action( 'admin_post_hutch_sms_send_bulk',       array( __CLASS__, 'handle_send_bulk' ) );
        add_action( 'admin_post_hutch_sms_clear_logs',      array( __CLASS__, 'handle_clear_logs' ) );
        add_action( 'admin_post_hutch_sms_clear_debug',     array( __CLASS__, 'handle_clear_debug' ) );
        add_action( 'admin_post_hutch_sms_test_login',      array( __CLASS__, 'handle_test_login' ) );
    }

    // ──────────────────────────────────────────────────────────
    // Menus
    // ──────────────────────────────────────────────────────────

    public static function register_menus() {
        add_menu_page(
            'Hutch SMS',
            'Hutch SMS',
            'manage_options',
            'hutch-sms',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-email-alt',
            56
        );
        add_submenu_page( 'hutch-sms', 'Dashboard',      'Dashboard',      'manage_options', 'hutch-sms',                   array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'hutch-sms', 'Send SMS',       'Send SMS',       'manage_options', 'hutch-sms-send',              array( __CLASS__, 'page_send' ) );
        add_submenu_page( 'hutch-sms', 'Bulk Campaign',  'Bulk Campaign',  'manage_options', 'hutch-sms-bulk',              array( __CLASS__, 'page_bulk' ) );
        add_submenu_page( 'hutch-sms', 'SMS Logs',       'SMS Logs',       'manage_options', 'hutch-sms-logs',              array( __CLASS__, 'page_logs' ) );
        add_submenu_page( 'hutch-sms', 'Debug Tools',    'Debug Tools',    'manage_options', 'hutch-sms-debug',             array( __CLASS__, 'page_debug' ) );
        add_submenu_page( 'hutch-sms', 'Settings',       'Settings',       'manage_options', 'hutch-sms-settings',          array( __CLASS__, 'page_settings' ) );
        add_submenu_page( 'hutch-sms', 'Version History','Version History','manage_options', 'hutch-sms-version',           array( __CLASS__, 'page_version' ) );
    }

    // ──────────────────────────────────────────────────────────
    // Settings API
    // ──────────────────────────────────────────────────────────

    public static function register_settings() {
        $opts = array(
            'hutch_sms_username', 'hutch_sms_password', 'hutch_sms_mask',
            'hutch_sms_debug',
            'hutch_sms_enable_order_confirm', 'hutch_sms_enable_order_complete',
            'hutch_sms_msg_order_confirm', 'hutch_sms_msg_order_complete',
        );
        foreach ( $opts as $opt ) {
            register_setting( 'hutch_sms_settings', $opt );
        }
    }

    // ──────────────────────────────────────────────────────────
    // Assets
    // ──────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'hutch-sms' ) === false ) return;
        wp_enqueue_style( 'hutch-sms-admin', HUTCH_SMS_URL . 'admin/assets/admin.css', array(), HUTCH_SMS_VERSION );
    }

    // ──────────────────────────────────────────────────────────
    // Pages
    // ──────────────────────────────────────────────────────────

    public static function page_dashboard() {
        $total   = Hutch_SMS_Logger::count_logs();
        $success = Hutch_SMS_Logger::count_logs( array( 'status' => 'success' ) );
        $errors  = Hutch_SMS_Logger::count_logs( array( 'status' => 'error' ) );
        $bulk    = Hutch_SMS_Logger::count_logs( array( 'type'   => 'bulk' ) );
        $recent  = Hutch_SMS_Logger::get_logs( array( 'limit' => 5 ) );
        require HUTCH_SMS_DIR . 'admin/views/dashboard.php';
    }

    public static function page_send() {
        $notice = get_transient( 'hutch_sms_notice' );
        delete_transient( 'hutch_sms_notice' );
        require HUTCH_SMS_DIR . 'admin/views/send.php';
    }

    public static function page_bulk() {
        $notice = get_transient( 'hutch_sms_notice' );
        delete_transient( 'hutch_sms_notice' );
        require HUTCH_SMS_DIR . 'admin/views/bulk.php';
    }

    public static function page_logs() {
        $page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $per     = 25;
        $type    = sanitize_text_field( $_GET['type']   ?? '' );
        $status  = sanitize_text_field( $_GET['status'] ?? '' );
        $logs    = Hutch_SMS_Logger::get_logs( array( 'limit' => $per, 'offset' => ( $page - 1 ) * $per, 'type' => $type, 'status' => $status ) );
        $total   = Hutch_SMS_Logger::count_logs( array( 'type' => $type, 'status' => $status ) );
        $pages   = ceil( $total / $per );
        require HUTCH_SMS_DIR . 'admin/views/logs.php';
    }

    public static function page_debug() {
        $notice    = get_transient( 'hutch_sms_notice' );
        delete_transient( 'hutch_sms_notice' );
        $debug_log = Hutch_SMS_Logger::get_debug_log( 300 );
        require HUTCH_SMS_DIR . 'admin/views/debug.php';
    }

    public static function page_settings() {
        $notice = get_transient( 'hutch_sms_notice' );
        delete_transient( 'hutch_sms_notice' );
        require HUTCH_SMS_DIR . 'admin/views/settings.php';
    }

    public static function page_version() {
        $history = get_option( 'hutch_sms_version_history', array() );
        require HUTCH_SMS_DIR . 'admin/views/version.php';
    }

    // ──────────────────────────────────────────────────────────
    // Form Handlers
    // ──────────────────────────────────────────────────────────

    public static function handle_send_individual() {
        check_admin_referer( 'hutch_sms_send_individual' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $numbers  = sanitize_text_field( $_POST['numbers'] ?? '' );
        $content  = sanitize_textarea_field( $_POST['content'] ?? '' );
        $campaign = sanitize_text_field( $_POST['campaign'] ?? 'Manual Send' );

        if ( empty( $numbers ) || empty( $content ) ) {
            set_transient( 'hutch_sms_notice', array( 'type' => 'error', 'msg' => 'Phone number and message content are required.' ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-send' ) );
            exit;
        }

        $result = Hutch_SMS_API::send_sms( array(
            'campaignName' => $campaign,
            'numbers'      => $numbers,
            'content'      => $content,
        ) );

        if ( is_wp_error( $result ) ) {
            set_transient( 'hutch_sms_notice', array( 'type' => 'error', 'msg' => 'Error: ' . $result->get_error_message() ), 60 );
        } else {
            $ref = $result['serverRef'] ?? 'N/A';
            set_transient( 'hutch_sms_notice', array( 'type' => 'success', 'msg' => "SMS sent successfully! Server Ref: $ref" ), 60 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-send' ) );
        exit;
    }

    public static function handle_send_bulk() {
        check_admin_referer( 'hutch_sms_send_bulk' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $campaign  = sanitize_text_field( $_POST['campaign'] ?? 'Bulk Campaign' );
        $mask      = sanitize_text_field( $_POST['mask'] ?? get_option( 'hutch_sms_mask', '' ) );
        $numbers   = sanitize_textarea_field( $_POST['numbers'] ?? '' );
        $content   = sanitize_textarea_field( $_POST['content'] ?? '' );

        $number_list = array_filter( array_map( 'trim', preg_split( '/[\n,]+/', $numbers ) ) );

        if ( empty( $number_list ) || empty( $content ) ) {
            set_transient( 'hutch_sms_notice', array( 'type' => 'error', 'msg' => 'At least one phone number and content are required.' ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-bulk' ) );
            exit;
        }

        $messages = array_map( function( $num ) use ( $campaign, $mask, $content ) {
            return array( 'campaignName' => $campaign, 'mask' => $mask, 'numbers' => $num, 'content' => $content );
        }, $number_list );

        $results = Hutch_SMS_API::send_bulk_sms( $messages );
        $errors  = array_filter( $results, fn( $r ) => is_wp_error( $r ) );

        if ( count( $errors ) ) {
            set_transient( 'hutch_sms_notice', array( 'type' => 'warning', 'msg' => count( $errors ) . ' batch(es) had errors. Check logs for details.' ), 60 );
        } else {
            set_transient( 'hutch_sms_notice', array( 'type' => 'success', 'msg' => count( $number_list ) . ' messages dispatched successfully.' ), 60 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-bulk' ) );
        exit;
    }

    public static function handle_clear_logs() {
        check_admin_referer( 'hutch_sms_clear_logs' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        Hutch_SMS_Logger::clear_logs();
        set_transient( 'hutch_sms_notice', array( 'type' => 'success', 'msg' => 'SMS logs cleared.' ), 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-logs' ) );
        exit;
    }

    public static function handle_clear_debug() {
        check_admin_referer( 'hutch_sms_clear_debug' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        Hutch_SMS_Logger::clear_debug_log();
        set_transient( 'hutch_sms_notice', array( 'type' => 'success', 'msg' => 'Debug log cleared.' ), 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-debug' ) );
        exit;
    }

    public static function handle_test_login() {
        check_admin_referer( 'hutch_sms_test_login' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        // Force fresh login
        delete_option( 'hutch_sms_access_token' );
        delete_option( 'hutch_sms_refresh_token' );
        delete_option( 'hutch_sms_token_expiry' );

        $result = Hutch_SMS_API::login();

        if ( is_wp_error( $result ) ) {
            set_transient( 'hutch_sms_notice', array( 'type' => 'error', 'msg' => 'Login failed: ' . $result->get_error_message() ), 60 );
        } elseif ( isset( $result['accessToken'] ) ) {
            $expiry = (int) get_option( 'hutch_sms_token_expiry', 0 );
            update_option( 'hutch_sms_access_token', $result['accessToken'] );
            if ( isset( $result['refreshToken'] ) ) update_option( 'hutch_sms_refresh_token', $result['refreshToken'] );
            set_transient( 'hutch_sms_notice', array( 'type' => 'success', 'msg' => 'Login successful! Access token retrieved.' ), 60 );
        } else {
            set_transient( 'hutch_sms_notice', array( 'type' => 'error', 'msg' => 'Unexpected response: ' . wp_json_encode( $result ) ), 60 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-debug' ) );
        exit;
    }
}
