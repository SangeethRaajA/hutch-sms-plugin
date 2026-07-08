<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Pre-load data needed for this page
$voucher_cat_slug = get_option( 'hutch_sms_voucher_category', 'gift-vouchers' );
$voucher_products = class_exists('Hutch_SMS_Voucher') ? Hutch_SMS_Voucher::get_voucher_category_products( $voucher_cat_slug ) : array();
$pluginever_active = class_exists('Hutch_SMS_Voucher') ? Hutch_SMS_Voucher::plugin_ever_active() : false;
$wc_categories     = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Settings</h1>

    <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="hutch-notice success">✓ Settings saved successfully.</div>
    <?php endif; ?>
    <?php if ( isset( $_GET['test_sent'] ) ) : ?>
        <div class="hutch-notice success">✓ Test SMS sent — check your phone.</div>
    <?php endif; ?>
    <?php if ( isset( $_GET['test_error'] ) ) : ?>
        <div class="hutch-notice error">✗ Test SMS failed: <?php echo esc_html( urldecode( $_GET['test_error'] ) ); ?></div>
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
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>📦 Order Confirmation SMS</h2>
            <p style="color:#6c757d;margin-bottom:16px">Sent to the customer immediately when their order is placed and payment confirmed.</p>

            <table class="form-table">
                <tr>
                    <th>Enable</th>
                    <td>
                        <label class="hutch-toggle">
                            <input type="checkbox" name="hutch_sms_enable_order_confirm" value="1"
                                   id="toggle-confirm"
                                   <?php checked( get_option('hutch_sms_enable_order_confirm'), 1 ); ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left:10px;vertical-align:middle;color:#6c757d">
                            Fires on: <code>Processing</code>, <code>On-Hold</code>, or payment completion
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-confirm">Message Template</label></th>
                    <td>
                        <textarea id="hs-msg-confirm" name="hutch_sms_msg_order_confirm"
                                  class="sms-template" data-counter="counter-confirm"
                                  rows="3"><?php echo esc_textarea( get_option('hutch_sms_msg_order_confirm',
                            'Hi {first_name}, thank you for your order #{order_id} of {total}. We are processing it now! - Nadiyas') ); ?></textarea>
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

            <!-- Test Send -->
            <div class="hutch-test-panel" id="test-confirm-panel">
                <h3>🧪 Test — Send a sample Order Confirmation SMS</h3>
                <p>Picks a real recent order from your store and sends the confirmation SMS to the customer's phone number.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <?php wp_nonce_field('hutch_sms_test_order'); ?>
                    <input type="hidden" name="action" value="hutch_sms_test_order">
                    <input type="hidden" name="sms_type" value="confirm">
                    <label style="display:flex;gap:8px;align-items:center">
                        <span>Order ID:</span>
                        <input type="number" name="test_order_id" placeholder="e.g. 1042" min="1"
                               style="width:130px" required>
                    </label>
                    <label style="display:flex;gap:8px;align-items:center">
                        <span>Override phone (optional):</span>
                        <input type="text" name="test_override_phone" placeholder="07XXXXXXXX"
                               style="width:150px">
                    </label>
                    <?php submit_button('Send Test SMS', 'secondary hutch-test-btn', '', false); ?>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             3. ORDER COMPLETION SMS
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>✅ Order Completion SMS</h2>
            <p style="color:#6c757d;margin-bottom:16px">Sent when you mark the order as <strong>Completed</strong> from the WooCommerce orders screen.</p>

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
                    <th><label for="hs-msg-complete">Message Template</label></th>
                    <td>
                        <textarea id="hs-msg-complete" name="hutch_sms_msg_order_complete"
                                  class="sms-template" data-counter="counter-complete"
                                  rows="3"><?php echo esc_textarea( get_option('hutch_sms_msg_order_complete',
                            'Hi {first_name}, your order #{order_id} has been completed. Thank you for shopping with us! - Nadiyas') ); ?></textarea>
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

            <div class="hutch-test-panel">
                <h3>🧪 Test — Send a sample Order Completion SMS</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <?php wp_nonce_field('hutch_sms_test_order'); ?>
                    <input type="hidden" name="action" value="hutch_sms_test_order">
                    <input type="hidden" name="sms_type" value="complete">
                    <label style="display:flex;gap:8px;align-items:center">
                        Order ID:
                        <input type="number" name="test_order_id" placeholder="e.g. 1042" min="1"
                               style="width:130px" required>
                    </label>
                    <label style="display:flex;gap:8px;align-items:center">
                        Override phone:
                        <input type="text" name="test_override_phone" placeholder="07XXXXXXXX"
                               style="width:150px">
                    </label>
                    <?php submit_button('Send Test SMS', 'secondary hutch-test-btn', '', false); ?>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             4. GIFT VOUCHER SMS
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>🎁 Gift Voucher Serial Number SMS</h2>
            <p style="color:#6c757d;margin-bottom:4px">
                Automatically sends the serial key to the customer when they purchase a product in the
                <strong>Gift Vouchers</strong> category. Uses
                <strong>WooCommerce Serial Numbers by PluginEver</strong>.
            </p>

            <!-- PluginEver status indicator -->
            <?php if ( $pluginever_active ) : ?>
                <div style="background:#d4edda;color:#155724;padding:8px 14px;border-radius:4px;margin-bottom:16px;font-size:13px">
                    ✓ WooCommerce Serial Numbers (PluginEver) detected — table <code><?php global $wpdb; echo esc_html($wpdb->prefix . 'wc_serial_numbers'); ?></code> found.
                </div>
            <?php else : ?>
                <div style="background:#fff3cd;color:#856404;padding:8px 14px;border-radius:4px;margin-bottom:16px;font-size:13px">
                    ⚠ WooCommerce Serial Numbers plugin table not found. Install and activate
                    <strong>WooCommerce Serial Numbers by PluginEver</strong> for this feature to work.
                </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th>Enable</th>
                    <td>
                        <label class="hutch-toggle">
                            <input type="checkbox" name="hutch_sms_enable_voucher_sms" value="1"
                                   <?php checked( get_option('hutch_sms_enable_voucher_sms'), 1 ); ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left:10px;vertical-align:middle;color:#6c757d">
                            Send serial number SMS when a gift voucher product is purchased
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-voucher-cat">Gift Voucher Category</label></th>
                    <td>
                        <select id="hs-voucher-cat" name="hutch_sms_voucher_category">
                            <?php if ( ! is_wp_error($wc_categories) ) : ?>
                                <?php foreach ( $wc_categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr($cat->slug); ?>"
                                        <?php selected( $voucher_cat_slug, $cat->slug ); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->slug); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="description">
                            Any product in this category will trigger the voucher serial SMS.
                            Your store uses: <a href="https://www.nadiyas.com/product-category/gift-vouchers/" target="_blank">nadiyas.com/product-category/gift-vouchers/</a>
                        </p>

                        <?php if ( ! empty($voucher_products) ) : ?>
                        <div style="margin-top:10px;background:#f8f9fa;border-radius:4px;padding:10px 14px">
                            <strong style="font-size:12px">Products in this category (<?php echo count($voucher_products); ?>):</strong>
                            <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px">
                            <?php foreach ( $voucher_products as $p ) : ?>
                                <span style="background:#e8f5e9;color:#2e7d32;padding:2px 10px;border-radius:12px;font-size:12px">
                                    <?php echo esc_html($p->get_name()); ?> <em style="color:#6c757d">#<?php echo $p->get_id(); ?></em>
                                </span>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else : ?>
                        <div style="margin-top:10px;color:#856404;background:#fff3cd;padding:6px 12px;border-radius:4px;font-size:12px">
                            No published products found in the <strong><?php echo esc_html($voucher_cat_slug); ?></strong> category.
                            Save settings after selecting the correct category to refresh this list.
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-voucher">Voucher Message Template</label></th>
                    <td>
                        <textarea id="hs-msg-voucher" name="hutch_sms_msg_voucher"
                                  class="sms-template" data-counter="counter-voucher"
                                  rows="3"><?php echo esc_textarea( get_option('hutch_sms_msg_voucher',
                            'Hi {first_name}, your gift voucher is: {serial}. Valid until: {expire_date}. Order #{order_id}. - Nadiyas') ); ?></textarea>
                        <div class="sms-meta-row">
                            <span class="sms-placeholders">
                                <code>{first_name}</code> <code>{serial}</code> <code>{product_name}</code>
                                <code>{order_id}</code> <code>{expire_date}</code> <code>{validity}</code>
                            </span>
                            <span class="sms-counter" id="counter-voucher"></span>
                        </div>
                        <p class="description">One SMS per serial key. If a customer buys 2 vouchers they receive 2 messages.</p>
                    </td>
                </tr>
            </table>

            <!-- Gift Voucher Test -->
            <div class="hutch-test-panel hutch-test-voucher">
                <h3>🧪 Test — Send a Gift Voucher Serial SMS</h3>
                <p>
                    Enter an order ID that contains a gift voucher product. The plugin will look up the
                    serial from <code><?php global $wpdb; echo esc_html($wpdb->prefix . 'wc_serial_numbers'); ?></code>
                    and send the SMS exactly as it would on a real purchase.
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <?php wp_nonce_field('hutch_sms_test_voucher'); ?>
                    <input type="hidden" name="action" value="hutch_sms_test_voucher">
                    <label style="display:flex;gap:8px;align-items:center">
                        Order ID:
                        <input type="number" name="test_order_id" placeholder="e.g. 1085" min="1"
                               style="width:130px" required>
                    </label>
                    <label style="display:flex;gap:8px;align-items:center">
                        Override phone:
                        <input type="text" name="test_override_phone" placeholder="07XXXXXXXX"
                               style="width:150px">
                        <small style="color:#6c757d">Leave blank to use the order's billing phone</small>
                    </label>
                    <?php submit_button('Send Voucher Test', 'secondary hutch-test-btn', '', false); ?>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             5. PAYMENT GATEWAY CONFIG
        ══════════════════════════════════════════ -->
        <div class="hutch-box">
            <h2>⚙️ WooCommerce / XStore Settings</h2>
            <p style="color:#6c757d;margin-bottom:12px">
                The plugin hooks into multiple order events to cover all payment gateways.
                Offline methods (COD, bank transfer) send the SMS at checkout submission;
                online gateways send after payment is confirmed.
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="hs-offline-methods">Offline Payment Method IDs</label></th>
                    <td>
                        <input type="text" id="hs-offline-methods" name="hutch_sms_offline_methods"
                               value="<?php echo esc_attr( get_option('hutch_sms_offline_methods','bacs,cheque,cod') ); ?>">
                        <p class="description">
                            Comma-separated IDs. Default: <code>bacs,cheque,cod</code>.<br>
                            Find IDs under <strong>WooCommerce → Settings → Payments</strong>.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php endif; ?>

        <!-- ══════════════════════════════════════════
             6. DEVELOPER OPTIONS
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
                            Write full API logs to
                            <code><?php echo esc_html( WP_CONTENT_DIR . '/hutch-sms-debug.log' ); ?></code>
                        </span>
                        <p class="description" style="color:#c00;margin-top:4px">
                            <strong>Warning:</strong> Logs credentials and phone numbers. Disable in production.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button('Save Settings', 'primary button-hutch'); ?>
    </form>
</div>

<style>
/* ── Toggle switch ── */
.hutch-toggle { position:relative;display:inline-block;width:46px;height:24px;vertical-align:middle }
.hutch-toggle input { opacity:0;width:0;height:0 }
.hutch-toggle .slider { position:absolute;cursor:pointer;inset:0;background:#ccc;border-radius:24px;transition:.3s }
.hutch-toggle .slider:before { content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s }
.hutch-toggle input:checked + .slider { background:#e8530a }
.hutch-toggle input:checked + .slider:before { transform:translateX(22px) }

/* ── SMS meta row ── */
.sms-meta-row { display:flex;justify-content:space-between;align-items:flex-start;margin-top:5px;flex-wrap:wrap;gap:6px }
.sms-placeholders code { background:#f0f0f0;padding:1px 6px;border-radius:3px;font-size:11px;margin:1px }
.sms-counter { font-size:12px;color:#6c757d;white-space:nowrap;padding-top:2px }
.sms-counter.warning { color:#e8530a;font-weight:600 }

/* ── Test panel ── */
.hutch-test-panel { background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;padding:16px 18px;margin-top:18px }
.hutch-test-panel h3 { margin:0 0 6px;font-size:14px;color:#333 }
.hutch-test-panel p { margin:0 0 12px;color:#6c757d;font-size:13px }
.hutch-test-voucher { border-color:#c3e6cb;background:#f0fff4 }
.hutch-test-btn { background:#fff !important;border-color:#e8530a !important;color:#e8530a !important }
.hutch-test-btn:hover { background:#e8530a !important;color:#fff !important }
</style>

<script>
(function(){
    // SMS character counter
    function updateCounter(ta) {
        var counterId = ta.dataset.counter;
        if (!counterId) return;
        var el = document.getElementById(counterId);
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
