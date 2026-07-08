<?php
/**
 * Hutch SMS – Admin Controller v1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Hutch_SMS_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Form handlers
        add_action( 'admin_post_hutch_sms_send_individual',  array( __CLASS__, 'handle_send_individual' ) );
        add_action( 'admin_post_hutch_sms_send_bulk',        array( __CLASS__, 'handle_send_bulk' ) );
        add_action( 'admin_post_hutch_sms_send_promo',       array( __CLASS__, 'handle_send_promo' ) );
        add_action( 'admin_post_hutch_sms_preview_contacts', array( __CLASS__, 'handle_preview_contacts' ) );
        add_action( 'admin_post_hutch_sms_clear_logs',       array( __CLASS__, 'handle_clear_logs' ) );
        add_action( 'admin_post_hutch_sms_clear_debug',      array( __CLASS__, 'handle_clear_debug' ) );
        add_action( 'admin_post_hutch_sms_test_login',        array( __CLASS__, 'handle_test_login' ) );
        add_action( 'admin_post_hutch_sms_diagnose_order',   array( __CLASS__, 'handle_diagnose_order' ) );
    }

    // ──────────────────────────────────────────────────────────
    // Menus
    // ──────────────────────────────────────────────────────────

    public static function register_menus() {
        add_menu_page( 'Hutch SMS', 'Hutch SMS', 'manage_options', 'hutch-sms',
            array( __CLASS__, 'page_dashboard' ), 'dashicons-email-alt', 56 );

        add_submenu_page( 'hutch-sms', 'Dashboard',          'Dashboard',          'manage_options', 'hutch-sms',                array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'hutch-sms', 'Send SMS',           'Send SMS',           'manage_options', 'hutch-sms-send',           array( __CLASS__, 'page_send' ) );
        add_submenu_page( 'hutch-sms', 'Promotional',        'Promotional',        'manage_options', 'hutch-sms-promo',          array( __CLASS__, 'page_promo' ) );
        add_submenu_page( 'hutch-sms', 'Bulk Campaign',      'Bulk Campaign',      'manage_options', 'hutch-sms-bulk',           array( __CLASS__, 'page_bulk' ) );
        add_submenu_page( 'hutch-sms', 'SMS Logs',           'SMS Logs',           'manage_options', 'hutch-sms-logs',           array( __CLASS__, 'page_logs' ) );
        add_submenu_page( 'hutch-sms', 'Debug Tools',        'Debug Tools',        'manage_options', 'hutch-sms-debug',          array( __CLASS__, 'page_debug' ) );
        add_submenu_page( 'hutch-sms', 'Settings',           'Settings',           'manage_options', 'hutch-sms-settings',       array( __CLASS__, 'page_settings' ) );
        add_submenu_page( 'hutch-sms', 'Version History',    'Version History',    'manage_options', 'hutch-sms-version',        array( __CLASS__, 'page_version' ) );
    }

    // ──────────────────────────────────────────────────────────
    // Settings API
    // ──────────────────────────────────────────────────────────

    public static function register_settings() {
        $opts = array(
            'hutch_sms_username', 'hutch_sms_password', 'hutch_sms_mask',
            'hutch_sms_debug',
            'hutch_sms_offline_methods',
            // Order SMS
            'hutch_sms_enable_order_confirm', 'hutch_sms_enable_order_complete',
            'hutch_sms_msg_order_confirm', 'hutch_sms_msg_order_complete',
            // Voucher SMS
            'hutch_sms_enable_voucher_sms', 'hutch_sms_voucher_product_ids', 'hutch_sms_msg_voucher',
        );
        foreach ( $opts as $opt ) {
            register_setting( 'hutch_sms_settings', $opt );
        }

        // Wire the offline methods option into the WooCommerce class filter
        add_filter( 'hutch_sms_offline_payment_methods', function() {
            $raw = get_option( 'hutch_sms_offline_methods', 'bacs,cheque,cod' );
            return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        } );
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

    public static function page_promo() {
        $notice   = get_transient( 'hutch_sms_notice' );
        delete_transient( 'hutch_sms_notice' );
        $contacts = get_transient( 'hutch_sms_preview_contacts' );
        delete_transient( 'hutch_sms_preview_contacts' );
        $statuses = Hutch_SMS_Promotional::get_order_statuses();
        require HUTCH_SMS_DIR . 'admin/views/promo.php';
    }

    public static function page_bulk() {
        $notice = get_transient( 'hutch_sms_notice' );
        delete_transient( 'hutch_sms_notice' );
        require HUTCH_SMS_DIR . 'admin/views/bulk.php';
    }

    public static function page_logs() {
        $page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $per    = 25;
        $type   = sanitize_text_field( $_GET['type']   ?? '' );
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        $logs   = Hutch_SMS_Logger::get_logs( array( 'limit' => $per, 'offset' => ( $page - 1 ) * $per, 'type' => $type, 'status' => $status ) );
        $total  = Hutch_SMS_Logger::count_logs( array( 'type' => $type, 'status' => $status ) );
        $pages  = ceil( $total / $per );
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
            self::set_notice( 'error', 'Phone number and message content are required.' );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-send' ) ); exit;
        }

        $result = Hutch_SMS_API::send_sms( array(
            'campaignName' => $campaign, 'numbers' => $numbers, 'content' => $content,
        ) );

        if ( is_wp_error( $result ) ) {
            self::set_notice( 'error', 'Error: ' . $result->get_error_message() );
        } else {
            self::set_notice( 'success', 'SMS sent! Server Ref: ' . ( $result['serverRef'] ?? 'N/A' ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-send' ) ); exit;
    }

    /**
     * Preview contacts from wp_wc_orders before sending.
     */
    public static function handle_preview_contacts() {
        check_admin_referer( 'hutch_sms_preview_contacts' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $filters = self::extract_promo_filters();
        $contacts = Hutch_SMS_Promotional::get_customer_contacts( array_merge( $filters, array( 'limit' => 200 ) ) );

        set_transient( 'hutch_sms_preview_contacts', $contacts, 300 ); // 5 min cache
        self::set_notice( 'success', count( $contacts ) . ' unique contacts found with valid phone numbers.' );
        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-promo' ) ); exit;
    }

    /**
     * Send promotional bulk SMS to all filtered customers from wp_wc_orders.
     */
    public static function handle_send_promo() {
        check_admin_referer( 'hutch_sms_send_promo' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $campaign = sanitize_text_field( $_POST['campaign'] ?? 'Promo Campaign' );
        $mask     = sanitize_text_field( $_POST['mask']     ?? get_option( 'hutch_sms_mask', '' ) );
        $content  = sanitize_textarea_field( $_POST['content'] ?? '' );

        if ( empty( $content ) ) {
            self::set_notice( 'error', 'Message content is required.' );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-promo' ) ); exit;
        }

        $filters  = self::extract_promo_filters();
        $contacts = Hutch_SMS_Promotional::get_customer_contacts( $filters );

        if ( empty( $contacts ) ) {
            self::set_notice( 'warning', 'No contacts found matching the selected filters.' );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-promo' ) ); exit;
        }

        $messages = Hutch_SMS_Promotional::build_bulk_messages( $contacts, $campaign, $mask, $content );
        $results  = Hutch_SMS_API::send_bulk_sms( $messages );
        $err_count = count( array_filter( $results, fn( $r ) => is_wp_error( $r ) ) );

        if ( $err_count ) {
            self::set_notice( 'warning', count( $contacts ) . ' contacts targeted. ' . $err_count . ' batch(es) had errors — check SMS Logs.' );
        } else {
            self::set_notice( 'success', 'Promotional SMS sent to ' . count( $contacts ) . ' customers successfully.' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-promo' ) ); exit;
    }

    public static function handle_send_bulk() {
        check_admin_referer( 'hutch_sms_send_bulk' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $campaign    = sanitize_text_field( $_POST['campaign'] ?? 'Bulk Campaign' );
        $mask        = sanitize_text_field( $_POST['mask']     ?? get_option( 'hutch_sms_mask', '' ) );
        $numbers     = sanitize_textarea_field( $_POST['numbers'] ?? '' );
        $content     = sanitize_textarea_field( $_POST['content'] ?? '' );
        $number_list = array_filter( array_map( 'trim', preg_split( '/[\n,]+/', $numbers ) ) );

        if ( empty( $number_list ) || empty( $content ) ) {
            self::set_notice( 'error', 'At least one phone number and content are required.' );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-bulk' ) ); exit;
        }

        $messages = array_map( function( $num ) use ( $campaign, $mask, $content ) {
            return array( 'campaignName' => $campaign, 'mask' => $mask, 'numbers' => $num, 'content' => $content );
        }, $number_list );

        $results   = Hutch_SMS_API::send_bulk_sms( $messages );
        $err_count = count( array_filter( $results, fn( $r ) => is_wp_error( $r ) ) );

        $err_count
            ? self::set_notice( 'warning', $err_count . ' batch(es) had errors. Check logs.' )
            : self::set_notice( 'success', count( $number_list ) . ' messages dispatched successfully.' );

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-bulk' ) ); exit;
    }

    public static function handle_clear_logs() {
        check_admin_referer( 'hutch_sms_clear_logs' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        Hutch_SMS_Logger::clear_logs();
        self::set_notice( 'success', 'SMS logs cleared.' );
        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-logs' ) ); exit;
    }

    public static function handle_clear_debug() {
        check_admin_referer( 'hutch_sms_clear_debug' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        Hutch_SMS_Logger::clear_debug_log();
        self::set_notice( 'success', 'Debug log cleared.' );
        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-debug' ) ); exit;
    }

    public static function handle_test_login() {
        check_admin_referer( 'hutch_sms_test_login' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        delete_option( 'hutch_sms_access_token' );
        delete_option( 'hutch_sms_refresh_token' );
        delete_option( 'hutch_sms_token_expiry' );

        $result = Hutch_SMS_API::login();

        if ( is_wp_error( $result ) ) {
            self::set_notice( 'error', 'Login failed: ' . $result->get_error_message() );
        } elseif ( isset( $result['accessToken'] ) ) {
            update_option( 'hutch_sms_access_token', $result['accessToken'] );
            if ( isset( $result['refreshToken'] ) ) update_option( 'hutch_sms_refresh_token', $result['refreshToken'] );
            self::set_notice( 'success', 'Login successful! Access token retrieved.' );
        } else {
            self::set_notice( 'error', 'Unexpected response: ' . wp_json_encode( $result ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-debug' ) ); exit;
    }

    public static function handle_diagnose_order() {
        check_admin_referer( 'hutch_sms_diagnose_order' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $order_id = (int) ( $_POST['diag_order_id'] ?? 0 );
        $order    = $order_id ? wc_get_order( $order_id ) : null;

        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            set_transient( 'hutch_sms_diag_result', array(
                'order_id'    => $order_id,
                'order_found' => false,
            ), 120 );
            wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-debug' ) );
            exit;
        }

        // Gather all phone sources
        $phone_raw    = $order->get_billing_phone();
        $phone_meta   = $order->get_meta( '_billing_phone', true );
        $phone_user   = $order->get_customer_id() ? get_user_meta( $order->get_customer_id(), 'billing_phone', true ) : '';

        // Pick best available raw phone
        $best_raw = $phone_raw ?: $phone_meta ?: $phone_user ?: '';
        $phone_normalised = $best_raw ? Hutch_SMS_WooCommerce::normalise_phone( $best_raw ) : '';

        set_transient( 'hutch_sms_diag_result', array(
            'order_id'             => $order_id,
            'order_found'          => true,
            'status'               => $order->get_status(),
            'payment_method'       => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'phone_raw'            => $phone_raw,
            'phone_meta'           => $phone_meta,
            'phone_user'           => $phone_user,
            'phone_normalised'     => $phone_normalised,
            'first_name'           => $order->get_billing_first_name(),
            'last_name'            => $order->get_billing_last_name(),
            'billing_email'        => $order->get_billing_email(),
            'customer_id'          => $order->get_customer_id(),
            'total'                => strip_tags( $order->get_formatted_order_total() ),
        ), 120 );

        wp_safe_redirect( admin_url( 'admin.php?page=hutch-sms-debug' ) );
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────────

    private static function set_notice( string $type, string $msg ) {
        set_transient( 'hutch_sms_notice', array( 'type' => $type, 'msg' => $msg ), 60 );
    }

    private static function extract_promo_filters(): array {
        return array(
            'status'    => sanitize_text_field( $_POST['filter_status']    ?? '' ),
            'date_from' => sanitize_text_field( $_POST['filter_date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['filter_date_to']   ?? '' ),
            'min_spend' => (float) ( $_POST['filter_min_spend'] ?? 0 ),
        );
    }
}
