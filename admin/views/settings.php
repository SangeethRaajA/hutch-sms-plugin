<?php if ( ! defined( 'ABSPATH' ) ) exit;

$pluginever_active = class_exists( 'Hutch_SMS_Voucher' ) ? Hutch_SMS_Voucher::plugin_ever_active() : false;
?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Settings</h1>

    <?php if ( isset( $_GET['settings-updated'] ) && ! isset( $_GET['test_sent'] ) ) : ?>
        <div class="hutch-notice success">✓ Settings saved successfully.</div>
    <?php endif; ?>

    <form method="post" action="options.php" class="hutch-form">
        <?php settings_fields( 'hutch_sms_settings' ); ?>

        <!-- ══════════════════════════════════════════
             1. API CREDENTIALS
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>🔑 API Credentials</h2>
            <table class="form-table">
                <tr>
                    <th><label for="hs-username">Username / Email</label></th>
                    <td>
                        <input type="text" id="hs-username" name="hutch_sms_username"
                               value="<?php echo esc_attr( get_option('hutch_sms_username','') ); ?>"
                               placeholder="your@email.com" autocomplete="off">
                        <p class="description">Your Hutch Bulk SMS portal login email.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-password">Password</label></th>
                    <td>
                        <input type="password" id="hs-password" name="hutch_sms_password"
                               value="<?php echo esc_attr( get_option('hutch_sms_password','') ); ?>"
                               autocomplete="new-password">
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-mask">Sender Mask</label></th>
                    <td>
                        <input type="text" id="hs-mask" name="hutch_sms_mask"
                               value="<?php echo esc_attr( get_option('hutch_sms_mask','') ); ?>"
                               placeholder="Nadiyas" maxlength="11">
                        <p class="description">Shown as sender name (max 11 chars, pre-approved by Hutch).</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php if ( class_exists('WooCommerce') ) : ?>

        <!-- ══════════════════════════════════════════
             2. ORDER CONFIRMATION SMS
             (Not sent for Gift Voucher orders)
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>📦 Order Confirmation SMS</h2>
            <p style="color:#6c757d;margin-bottom:4px">
                Sent when a customer's order is placed and payment confirmed.
                <strong>Not sent for Gift Voucher orders</strong> — those receive the voucher serial SMS instead.
            </p>
            <table class="form-table">
                <tr>
                    <th>Enable</th>
                    <td>
                        <label class="hutch-toggle">
                            <input type="checkbox" name="hutch_sms_enable_order_confirm" value="1"
                                   <?php checked( get_option('hutch_sms_enable_order_confirm'), 1 ); ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left:10px;vertical-align:middle;color:#6c757d">
                            Fires on: <code>Processing</code> or <code>On-Hold</code> status
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-confirm">Message</label></th>
                    <td>
                        <textarea id="hs-msg-confirm" name="hutch_sms_msg_order_confirm"
                                  class="sms-template" data-counter="counter-confirm" rows="3"><?php
                            echo esc_textarea( get_option('hutch_sms_msg_order_confirm',
                                'Hi {first_name}, thank you for your order #{order_id} of {total}. We are processing it now! - Nadiyas') );
                        ?></textarea>
                        <div class="sms-meta-row">
                            <span class="sms-placeholders">
                                <code>{first_name}</code> <code>{last_name}</code> <code>{order_id}</code>
                                <code>{total}</code> <code>{payment_method}</code> <code>{items_count}</code>
                            </span>
                            <span class="sms-counter" id="counter-confirm"></span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══════════════════════════════════════════
             3. ORDER COMPLETION SMS
             (Not sent for Gift Voucher orders)
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>✅ Order Completion SMS</h2>
            <p style="color:#6c757d;margin-bottom:4px">
                Sent when an order is marked as <strong>Completed</strong>.
                <strong>Not sent for Gift Voucher orders</strong> — those receive the voucher serial SMS instead.
            </p>
            <table class="form-table">
                <tr>
                    <th>Enable</th>
                    <td>
                        <label class="hutch-toggle">
                            <input type="checkbox" name="hutch_sms_enable_order_complete" value="1"
                                   <?php checked( get_option('hutch_sms_enable_order_complete'), 1 ); ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left:10px;vertical-align:middle;color:#6c757d">
                            Fires on: <code>Completed</code> status
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-complete">Message</label></th>
                    <td>
                        <textarea id="hs-msg-complete" name="hutch_sms_msg_order_complete"
                                  class="sms-template" data-counter="counter-complete" rows="3"><?php
                            echo esc_textarea( get_option('hutch_sms_msg_order_complete',
                                'Hi {first_name}, your order #{order_id} has been completed. Thank you for shopping with us! - Nadiyas') );
                        ?></textarea>
                        <div class="sms-meta-row">
                            <span class="sms-placeholders">
                                <code>{first_name}</code> <code>{last_name}</code> <code>{order_id}</code>
                                <code>{total}</code> <code>{payment_method}</code> <code>{items_count}</code>
                            </span>
                            <span class="sms-counter" id="counter-complete"></span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══════════════════════════════════════════
             4. GIFT VOUCHER SMS
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>🎁 Gift Voucher Serial Number SMS</h2>
            <p style="color:#6c757d;margin-bottom:10px">
                Sends the serial key automatically when a customer purchases any product whose name contains
                <strong>"Gift Voucher"</strong>. Uses <strong>WooCommerce Serial Numbers by PluginEver</strong>
                (<code>wp_serial_numbers</code>).
                No Order Confirmation or Completion SMS is sent for these orders.
            </p>

            <?php if ( $pluginever_active ) : ?>
                <div class="hutch-plugin-status ok">
                    ✓ WooCommerce Serial Numbers (PluginEver) detected —
                    <code><?php global $wpdb; echo esc_html( $wpdb->prefix . 'serial_numbers' ); ?></code> found.
                </div>
            <?php else : ?>
                <div class="hutch-plugin-status warn">
                    ⚠ Table <code><?php global $wpdb; echo esc_html( $wpdb->prefix . 'serial_numbers' ); ?></code> not found.
                    Install and activate <strong>WooCommerce Serial Numbers by PluginEver</strong> for this feature to work.
                </div>
            <?php endif; ?>

            <table class="form-table" style="margin-top:14px">
                <tr>
                    <th>Enable</th>
                    <td>
                        <label class="hutch-toggle">
                            <input type="checkbox" name="hutch_sms_enable_voucher_sms" value="1"
                                   <?php checked( get_option('hutch_sms_enable_voucher_sms'), 1 ); ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left:10px;vertical-align:middle;color:#6c757d">
                            Fires when an order contains a product named <em>"Gift Voucher"</em>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-voucher">Message</label></th>
                    <td>
                        <textarea id="hs-msg-voucher" name="hutch_sms_msg_voucher"
                                  class="sms-template" data-counter="counter-voucher" rows="3"><?php
                            echo esc_textarea( get_option('hutch_sms_msg_voucher',
                                'Hi {first_name}, your gift voucher is: {serial_key}. Valid until: {expire_date}. Order #{order_id}. - Nadiyas') );
                        ?></textarea>
                        <div class="sms-meta-row">
                            <span class="sms-placeholders">
                                <code>{first_name}</code> <code>{serial_key}</code> <code>{product_name}</code>
                                <code>{order_id}</code> <code>{expire_date}</code> <code>{validity}</code>
                            </span>
                            <span class="sms-counter" id="counter-voucher"></span>
                        </div>
                        <p class="description">One SMS per serial key. A customer buying 2 vouchers receives 2 SMS messages.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php endif; // WooCommerce active ?>

        <!-- ══════════════════════════════════════════
             5. DEVELOPER OPTIONS
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>🔧 Developer Options</h2>
            <table class="form-table">
                <tr>
                    <th>Debug Mode</th>
                    <td>
                        <label class="hutch-toggle">
                            <input type="checkbox" name="hutch_sms_debug" value="1"
                                   <?php checked( get_option('hutch_sms_debug'), 1 ); ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left:10px;vertical-align:middle">
                            Log API requests to
                            <code><?php echo esc_html( WP_CONTENT_DIR . '/hutch-sms-debug.log' ); ?></code>
                        </span>
                        <p class="description" style="color:#c00;margin-top:4px">
                            <strong>Warning:</strong> Logs phone numbers and credentials. Disable when not debugging.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button('Save Settings', 'primary button-hutch'); ?>
    </form>
</div>

<style>
.hutch-toggle { position:relative;display:inline-block;width:46px;height:24px;vertical-align:middle }
.hutch-toggle input { opacity:0;width:0;height:0 }
.hutch-toggle .slider { position:absolute;cursor:pointer;inset:0;background:#ccc;border-radius:24px;transition:.3s }
.hutch-toggle .slider:before { content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s }
.hutch-toggle input:checked + .slider { background:#e8530a }
.hutch-toggle input:checked + .slider:before { transform:translateX(22px) }

.sms-meta-row { display:flex;justify-content:space-between;align-items:flex-start;margin-top:5px;flex-wrap:wrap;gap:6px }
.sms-placeholders code { background:#f0f0f0;padding:1px 6px;border-radius:3px;font-size:11px;margin:1px }
.sms-counter { font-size:12px;color:#6c757d;white-space:nowrap;padding-top:2px }
.sms-counter.warning { color:#e8530a;font-weight:600 }

.hutch-plugin-status { padding:8px 14px;border-radius:4px;margin-bottom:4px;font-size:13px }
.hutch-plugin-status.ok   { background:#d4edda;color:#155724 }
.hutch-plugin-status.warn { background:#fff3cd;color:#856404 }
</style>

<script>
(function(){
    function updateCounter(ta) {
        var el = document.getElementById(ta.dataset.counter);
        if (!el) return;
        var len = ta.value.length;
        var sms = Math.ceil(len / 160) || 1;
        el.textContent = len + ' chars / ' + sms + ' SMS';
        el.className = 'sms-counter' + (len > 160 ? ' warning' : '');
    }
    document.querySelectorAll('.sms-template').forEach(function(ta){
        updateCounter(ta);
        ta.addEventListener('input', function(){ updateCounter(ta); });
    });
})();
</script>
