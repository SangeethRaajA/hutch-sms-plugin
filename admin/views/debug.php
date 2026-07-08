<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Debug Tools</h1>

    <?php if ( $notice ) : ?>
        <div class="hutch-notice <?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['msg'] ); ?></div>
    <?php endif; ?>

    <!-- ── XStore / WooCommerce Order Diagnostic ── -->
    <div class="hutch-box">
        <h2>🔍 Order SMS Diagnostic</h2>
        <p>Enter a recent WooCommerce order ID to inspect exactly what our plugin sees — hooks, phone number, order status, and payment method. This explains why an SMS did or did not send.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <?php wp_nonce_field( 'hutch_sms_diagnose_order' ); ?>
            <input type="hidden" name="action" value="hutch_sms_diagnose_order">
            <input type="number" name="diag_order_id" placeholder="Order ID e.g. 1042"
                   value="<?php echo esc_attr( $_GET['diag_id'] ?? '' ); ?>"
                   style="width:180px" min="1" required>
            <?php submit_button( 'Run Diagnostic', 'secondary', '', false ); ?>
        </form>

        <?php
        $diag = get_transient( 'hutch_sms_diag_result' );
        delete_transient( 'hutch_sms_diag_result' );
        if ( $diag ) :
        ?>
        <div style="margin-top:20px">
            <h3 style="margin-bottom:10px">Diagnostic Results — Order #<?php echo esc_html( $diag['order_id'] ); ?></h3>
            <table class="form-table" style="background:#f8f9fa;border-radius:6px;padding:12px">
                <tr>
                    <th style="width:200px">Order Found</th>
                    <td>
                        <?php if ( $diag['order_found'] ) : ?>
                            <span class="badge badge-success">✓ Yes</span>
                        <?php else : ?>
                            <span class="badge badge-error">✗ No — Order ID does not exist</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( $diag['order_found'] ) : ?>
                <tr>
                    <th>Order Status</th>
                    <td><code><?php echo esc_html( $diag['status'] ); ?></code>
                        <?php
                        $confirm_statuses = array( 'wc-processing', 'wc-on-hold' );
                        $complete_status  = 'wc-completed';
                        if ( in_array( 'wc-' . $diag['status'], $confirm_statuses, true ) || $diag['status'] === 'processing' || $diag['status'] === 'on-hold' ) {
                            echo ' <span class="badge badge-success">Would trigger Confirmation SMS</span>';
                        } elseif ( $diag['status'] === 'completed' || $diag['status'] === 'wc-completed' ) {
                            echo ' <span class="badge badge-success">Would trigger Completion SMS</span>';
                        } else {
                            echo ' <span style="color:#856404;background:#fff3cd;padding:2px 8px;border-radius:10px;font-size:11px">Status will not trigger SMS</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Payment Method</th>
                    <td>
                        <code><?php echo esc_html( $diag['payment_method'] ); ?></code>
                        &nbsp;(<?php echo esc_html( $diag['payment_method_title'] ); ?>)
                        <?php
                        $offline = array_filter( array_map( 'trim', explode( ',', get_option( 'hutch_sms_offline_methods', 'bacs,cheque,cod' ) ) ) );
                        if ( in_array( $diag['payment_method'], $offline, true ) ) {
                            echo '<br><small style="color:#155724">✓ Offline method — SMS fires at checkout via <code>woocommerce_checkout_order_processed</code></small>';
                        } else {
                            echo '<br><small style="color:#0c5460">✓ Online method — SMS fires via <code>woocommerce_payment_complete</code></small>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Billing Phone (raw)</th>
                    <td>
                        <?php if ( $diag['phone_raw'] ) : ?>
                            <code><?php echo esc_html( $diag['phone_raw'] ); ?></code>
                        <?php else : ?>
                            <span class="badge badge-error">✗ EMPTY — No phone number saved on this order</span>
                            <br><small style="color:#721c24">This is why SMS is not sending. Check your XStore checkout form has a phone field and it is required.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Phone from Order Meta</th>
                    <td>
                        <?php if ( $diag['phone_meta'] ) : ?>
                            <code><?php echo esc_html( $diag['phone_meta'] ); ?></code>
                        <?php else : ?>
                            <em style="color:#6c757d">Empty</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Phone from User Profile</th>
                    <td>
                        <?php if ( $diag['phone_user'] ) : ?>
                            <code><?php echo esc_html( $diag['phone_user'] ); ?></code>
                        <?php else : ?>
                            <em style="color:#6c757d">Empty or guest order</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Normalised Phone</th>
                    <td>
                        <?php if ( $diag['phone_normalised'] ) : ?>
                            <span class="badge badge-success">✓ <?php echo esc_html( $diag['phone_normalised'] ); ?></span>
                            <br><small>This is the number that would be sent to Hutch API</small>
                        <?php else : ?>
                            <span class="badge badge-error">✗ Could not normalise — Check format below</span>
                            <br><small style="color:#721c24">
                                Expected formats: <code>07XXXXXXXX</code> (local), <code>947XXXXXXXX</code> (intl), <code>+947XXXXXXXX</code>, <code>0094XXXXXXXXX</code>
                            </small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Confirmation SMS setting</th>
                    <td><?php echo get_option( 'hutch_sms_enable_order_confirm' ) ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-error">Disabled — turn on in Settings</span>'; ?></td>
                </tr>
                <tr>
                    <th>Completion SMS setting</th>
                    <td><?php echo get_option( 'hutch_sms_enable_order_complete' ) ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-error">Disabled — turn on in Settings</span>'; ?></td>
                </tr>
                <tr>
                    <th>Dedup key (confirm)</th>
                    <td>
                        <?php $sent = get_transient( 'hutch_sms_confirm_sent_' . $diag['order_id'] ); ?>
                        <?php if ( $sent ) : ?>
                            <span style="color:#856404;background:#fff3cd;padding:2px 8px;border-radius:10px;font-size:11px">
                                ⚠ Confirmation already sent for this order — dedup will block retries
                            </span>
                        <?php else : ?>
                            <em style="color:#6c757d">Not sent yet / dedup cleared</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Customer</th>
                    <td>
                        <?php echo esc_html( $diag['first_name'] . ' ' . $diag['last_name'] ); ?>
                        &nbsp;|&nbsp; <?php echo esc_html( $diag['billing_email'] ); ?>
                        <?php if ( $diag['customer_id'] ) echo ' &nbsp;|&nbsp; User ID: ' . (int) $diag['customer_id']; ?>
                    </td>
                </tr>
                <tr>
                    <th>Order Total</th>
                    <td><?php echo esc_html( $diag['total'] ); ?></td>
                </tr>
                <tr>
                    <th>Summary</th>
                    <td>
                        <?php if ( $diag['phone_normalised'] && ( get_option( 'hutch_sms_enable_order_confirm' ) || get_option( 'hutch_sms_enable_order_complete' ) ) ) : ?>
                            <div style="background:#d4edda;color:#155724;padding:10px 14px;border-radius:6px;font-weight:600">
                                ✓ This order looks correct — SMS should send when order status changes.
                                If it still doesn't send, enable Debug Mode below and check the debug log after placing a new order.
                            </div>
                        <?php elseif ( ! $diag['phone_normalised'] ) : ?>
                            <div style="background:#f8d7da;color:#721c24;padding:10px 14px;border-radius:6px;font-weight:600">
                                ✗ No valid phone number found. This is the reason SMS is not sending.
                                See the <strong>Fix</strong> section below.
                            </div>
                        <?php else : ?>
                            <div style="background:#fff3cd;color:#856404;padding:10px 14px;border-radius:6px;font-weight:600">
                                ⚠ Phone is valid but SMS is disabled in Settings. Enable Order Confirmation or Completion SMS.
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if ( $diag['order_found'] && ! $diag['phone_raw'] ) : ?>
            <div style="margin-top:16px;background:#fff3cd;border-left:4px solid #ffc107;padding:14px 16px;border-radius:4px">
                <strong>📋 How to fix missing phone number with XStore:</strong><br><br>
                XStore's checkout builder lets you customise the checkout layout using Elementor. If the phone field
                is missing or not mapped to the standard WooCommerce <code>billing_phone</code> field, no phone
                number will be saved to the order.<br><br>
                <strong>Steps to verify:</strong>
                <ol style="margin:8px 0 0 20px;line-height:2">
                    <li>Go to <strong>WooCommerce → Settings → Accounts &amp; Privacy</strong> — confirm "Phone" field is set to <strong>Required</strong></li>
                    <li>Go to your XStore Checkout Builder (Elementor) and confirm the checkout form includes a <strong>Billing Phone</strong> field using the native <code>[woocommerce_checkout]</code> shortcode or the XStore Checkout element with Phone enabled</li>
                    <li>Place a test order and check <strong>WooCommerce → Orders → [order] → Billing details</strong> — if phone appears there, our plugin will find it</li>
                    <li>If phone appears in the order but SMS still doesn't send, come back here and run the diagnostic on that order ID</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── API Connection Test ── -->
    <div class="hutch-box">
        <h2>API Connection Test</h2>
        <p>Test the credentials configured in Settings by triggering a fresh login.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'hutch_sms_test_login' ); ?>
            <input type="hidden" name="action" value="hutch_sms_test_login">
            <?php submit_button( 'Test API Login', 'primary button-hutch', '', false ); ?>
        </form>
    </div>

    <!-- ── Token Info ── -->
    <div class="hutch-box">
        <h2>Token Information</h2>
        <?php
        $access_token  = get_option( 'hutch_sms_access_token', '' );
        $refresh_token = get_option( 'hutch_sms_refresh_token', '' );
        $token_expiry  = (int) get_option( 'hutch_sms_token_expiry', 0 );
        $token_valid   = $token_expiry > time();
        ?>
        <table class="form-table">
            <tr>
                <th>Access Token</th>
                <td><?php echo $access_token ? esc_html( substr( $access_token, 0, 40 ) ) . '…' : '<em>None stored</em>'; ?></td>
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
                <td><?php echo $refresh_token ? esc_html( substr( $refresh_token, 0, 40 ) ) . '…' : '<em>None stored</em>'; ?></td>
            </tr>
        </table>
    </div>

    <!-- ── Debug Log ── -->
    <div class="hutch-box">
        <h2>Debug Log</h2>
        <?php if ( ! get_option( 'hutch_sms_debug', false ) ) : ?>
            <div class="hutch-notice warning">
                Debug mode is <strong>disabled</strong>.
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-settings' ) ); ?>">Enable in Settings</a>
                to capture full API request/response detail and hook execution traces.
            </div>
        <?php else : ?>
            <p>Showing last 300 lines from <code><?php echo esc_html( WP_CONTENT_DIR . '/hutch-sms-debug.log' ); ?></code></p>
        <?php endif; ?>

        <textarea class="hutch-debug-log" readonly><?php echo esc_textarea( $debug_log ?: '— No debug entries yet —' ); ?></textarea>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;display:inline"
              onsubmit="return confirm('Clear the debug log?');">
            <?php wp_nonce_field( 'hutch_sms_clear_debug' ); ?>
            <input type="hidden" name="action" value="hutch_sms_clear_debug">
            <?php submit_button( 'Clear Debug Log', 'delete', '', false ); ?>
        </form>
    </div>

    <!-- ── Plugin Info ── -->
    <div class="hutch-box">
        <h2>Plugin Info</h2>
        <table class="form-table">
            <tr><th>Plugin Version</th>     <td><?php echo esc_html( HUTCH_SMS_VERSION ); ?></td></tr>
            <tr><th>WordPress Version</th>  <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><th>PHP Version</th>        <td><?php echo esc_html( phpversion() ); ?></td></tr>
            <tr><th>WooCommerce</th>        <td><?php echo class_exists( 'WooCommerce' ) ? '<span class="badge badge-success">Active — v' . esc_html( WC()->version ) . '</span>' : '<span class="badge badge-error">Not Active</span>'; ?></td></tr>
            <tr><th>HPOS Active</th>        <td><?php
                $hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
                        \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                echo $hpos ? '<span class="badge badge-success">Yes — using wp_wc_orders</span>' : '<span style="color:#6c757d">No — using wp_posts</span>';
            ?></td></tr>
            <tr><th>XStore Theme</th>       <td><?php
                $theme = wp_get_theme();
                $is_xstore = stripos( $theme->get('Name'), 'xstore' ) !== false || stripos( $theme->get('Template'), 'xstore' ) !== false;
                echo $is_xstore
                    ? '<span class="badge badge-success">Detected — ' . esc_html( $theme->get('Name') ) . ' v' . esc_html( $theme->get('Version') ) . '</span>'
                    : '<span style="color:#6c757d">' . esc_html( $theme->get('Name') ) . '</span>';
            ?></td></tr>
            <tr><th>Offline Methods</th>    <td><code><?php echo esc_html( get_option( 'hutch_sms_offline_methods', 'bacs,cheque,cod' ) ); ?></code></td></tr>
            <tr><th>API Base URL</th>        <td><?php echo esc_html( Hutch_SMS_API::BASE_URL ); ?></td></tr>
            <tr><th>Debug Mode</th>         <td><?php echo get_option( 'hutch_sms_debug' ) ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-error">OFF</span>'; ?></td></tr>
        </table>
    </div>
</div>
