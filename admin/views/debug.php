<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Debug Tools</h1>

    <?php if ( $notice ) : ?>
        <div class="hutch-notice <?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['msg'] ); ?></div>
    <?php endif; ?>

    <div class="hutch-box">
        <h2>API Connection Test</h2>
        <p>Test the credentials configured in Settings by triggering a fresh login. Tokens will be stored on success.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'hutch_sms_test_login' ); ?>
            <input type="hidden" name="action" value="hutch_sms_test_login">
            <?php submit_button( 'Test API Login', 'primary button-hutch', '', false ); ?>
        </form>
    </div>

    <div class="hutch-box">
        <h2>Token Information</h2>
        <?php
        $access_token   = get_option( 'hutch_sms_access_token', '' );
        $refresh_token  = get_option( 'hutch_sms_refresh_token', '' );
        $token_expiry   = (int) get_option( 'hutch_sms_token_expiry', 0 );
        $token_valid    = $token_expiry > time();
        ?>
        <table class="form-table">
            <tr>
                <th>Access Token</th>
                <td><?php echo $access_token ? esc_html( substr( $access_token, 0, 40 ) ) . '...' : '<em>None stored</em>'; ?></td>
            </tr>
            <tr>
                <th>Token Expiry</th>
                <td>
                    <?php if ( $token_expiry ) : ?>
                        <?php echo esc_html( date( 'Y-m-d H:i:s', $token_expiry ) ); ?>
                        <div class="token-status <?php echo $token_valid ? 'valid' : 'expired'; ?>">
                            <span class="dot"></span>
                            <?php echo $token_valid ? 'Valid (' . human_time_diff( time(), $token_expiry ) . ' remaining)' : 'Expired'; ?>
                        </div>
                    <?php else : ?>
                        <em>No token retrieved yet.</em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Refresh Token</th>
                <td><?php echo $refresh_token ? esc_html( substr( $refresh_token, 0, 40 ) ) . '...' : '<em>None stored</em>'; ?></td>
            </tr>
        </table>
    </div>

    <div class="hutch-box">
        <h2>Debug Log</h2>
        <?php if ( ! get_option( 'hutch_sms_debug', false ) ) : ?>
            <div class="hutch-notice warning">Debug mode is <strong>disabled</strong>. Enable it in <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-settings' ) ); ?>">Settings</a> to capture API request/response details.</div>
        <?php else : ?>
            <p>Showing last 300 lines from <code><?php echo esc_html( WP_CONTENT_DIR . '/hutch-sms-debug.log' ); ?></code></p>
        <?php endif; ?>

        <textarea class="hutch-debug-log" readonly><?php echo esc_textarea( $debug_log ?: '— No debug entries yet —' ); ?></textarea>

        <div style="margin-top:12px">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Clear debug log?');">
                <?php wp_nonce_field( 'hutch_sms_clear_debug' ); ?>
                <input type="hidden" name="action" value="hutch_sms_clear_debug">
                <?php submit_button( 'Clear Debug Log', 'delete', '', false ); ?>
            </form>
        </div>
    </div>

    <div class="hutch-box">
        <h2>Plugin Info</h2>
        <table class="form-table">
            <tr><th>Plugin Version</th><td><?php echo esc_html( HUTCH_SMS_VERSION ); ?></td></tr>
            <tr><th>WordPress Version</th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><th>PHP Version</th><td><?php echo esc_html( phpversion() ); ?></td></tr>
            <tr><th>WooCommerce</th><td><?php echo class_exists( 'WooCommerce' ) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-error">Not Active</span>'; ?></td></tr>
            <tr><th>API Base URL</th><td><?php echo esc_html( Hutch_SMS_API::BASE_URL ); ?></td></tr>
            <tr><th>Debug Mode</th><td><?php echo get_option( 'hutch_sms_debug' ) ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-error">OFF</span>'; ?></td></tr>
        </table>
    </div>
</div>
