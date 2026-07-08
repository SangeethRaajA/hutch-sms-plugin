<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Settings</h1>

    <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="hutch-notice success">Settings saved successfully.</div>
    <?php endif; ?>

    <form method="post" action="options.php" class="hutch-form">
        <?php settings_fields( 'hutch_sms_settings' ); ?>

        <!-- ── API Credentials ── -->
        <div class="hutch-box">
            <h2>API Credentials</h2>
            <table class="form-table">
                <tr>
                    <th><label for="hs-username">Username / Email</label></th>
                    <td>
                        <input type="text" id="hs-username" name="hutch_sms_username"
                               value="<?php echo esc_attr( get_option( 'hutch_sms_username', '' ) ); ?>"
                               placeholder="your@email.com" autocomplete="off">
                        <p class="description">Your Hutch Bulk SMS portal login email.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-password">Password</label></th>
                    <td>
                        <input type="password" id="hs-password" name="hutch_sms_password"
                               value="<?php echo esc_attr( get_option( 'hutch_sms_password', '' ) ); ?>"
                               autocomplete="new-password">
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-mask">Default Sender Mask</label></th>
                    <td>
                        <input type="text" id="hs-mask" name="hutch_sms_mask"
                               value="<?php echo esc_attr( get_option( 'hutch_sms_mask', '' ) ); ?>"
                               placeholder="YourBrand" maxlength="11">
                        <p class="description">Shown to recipients as sender name (max 11 chars). Must be pre-approved by Hutch.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php if ( class_exists( 'WooCommerce' ) ) : ?>

        <!-- ── Order SMS ── -->
        <div class="hutch-box">
            <h2>Order SMS Messages</h2>
            <p>Available placeholders: <code>{order_id}</code> <code>{first_name}</code> <code>{last_name}</code> <code>{total}</code> <code>{payment_method}</code> <code>{items_count}</code></p>
            <table class="form-table">
                <tr>
                    <th>Order Confirmation SMS</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_enable_order_confirm" value="1"
                                   <?php checked( get_option( 'hutch_sms_enable_order_confirm' ), 1 ); ?>>
                            Enable — fires when order status changes to <em>Processing</em>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-confirm">Confirmation Message</label></th>
                    <td>
                        <textarea id="hs-msg-confirm" name="hutch_sms_msg_order_confirm"><?php
                            echo esc_textarea( get_option( 'hutch_sms_msg_order_confirm',
                                'Hi {first_name}, thank you for your order #{order_id} of {total}. We are processing it now!' ) );
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>Order Completion SMS</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_enable_order_complete" value="1"
                                   <?php checked( get_option( 'hutch_sms_enable_order_complete' ), 1 ); ?>>
                            Enable — fires when order status changes to <em>Completed</em>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-complete">Completion Message</label></th>
                    <td>
                        <textarea id="hs-msg-complete" name="hutch_sms_msg_order_complete"><?php
                            echo esc_textarea( get_option( 'hutch_sms_msg_order_complete',
                                'Hi {first_name}, your order #{order_id} has been completed. Thank you for shopping with us!' ) );
                        ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Gift Voucher SMS ── -->
        <div class="hutch-box">
            <h2>Gift Voucher Serial Number SMS</h2>
            <p>
                When a customer purchases a gift voucher product, the plugin looks up the serial key from
                <code>wp_serial_numbers</code> (matched by <code>order_id</code> + <code>product_id</code>)
                and sends it via SMS automatically.
            </p>
            <p>Available placeholders: <code>{serial}</code> <code>{product_name}</code> <code>{order_id}</code> <code>{first_name}</code> <code>{expire_date}</code> <code>{validity}</code></p>
            <table class="form-table">
                <tr>
                    <th>Gift Voucher SMS</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_enable_voucher_sms" value="1"
                                   <?php checked( get_option( 'hutch_sms_enable_voucher_sms' ), 1 ); ?>>
                            Enable — fires when order containing a voucher product is completed or processing
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-voucher-ids">Gift Voucher Product IDs</label></th>
                    <td>
                        <input type="text" id="hs-voucher-ids" name="hutch_sms_voucher_product_ids"
                               value="<?php echo esc_attr( get_option( 'hutch_sms_voucher_product_ids', '' ) ); ?>"
                               placeholder="42, 87, 103">
                        <p class="description">
                            Comma-separated WooCommerce product IDs that are gift vouchers.
                            You can find a product's ID by hovering over it in <strong>Products → All Products</strong>.<br>
                            Only orders containing one of these products will trigger the voucher SMS.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-voucher">Voucher Message Template</label></th>
                    <td>
                        <textarea id="hs-msg-voucher" name="hutch_sms_msg_voucher"><?php
                            echo esc_textarea( get_option( 'hutch_sms_msg_voucher',
                                'Hi {first_name}, your gift voucher for {product_name} is: {serial}. Valid until: {expire_date}. Order #{order_id}.' ) );
                        ?></textarea>
                        <p class="description">One SMS is sent per serial number. If a customer buys 2 vouchers, they receive 2 SMS messages.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php endif; ?>

        <!-- ── XStore / Payment Gateway Settings ── -->
        <div class="hutch-box">
            <h2>XStore & Payment Gateway Settings</h2>
            <p>
                XStore's checkout sends orders through standard WooCommerce — the plugin hooks into multiple order events
                to ensure SMS fires regardless of payment method. Configure which gateways are "offline" (COD, bank transfer)
                so the correct hook is used.
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="hs-offline-methods">Offline Payment Method IDs</label></th>
                    <td>
                        <input type="text" id="hs-offline-methods" name="hutch_sms_offline_methods"
                               value="<?php echo esc_attr( get_option( 'hutch_sms_offline_methods', 'bacs,cheque,cod' ) ); ?>">
                        <p class="description">
                            Comma-separated WooCommerce payment method IDs that <strong>do not</strong> trigger
                            <code>woocommerce_payment_complete</code>. Default: <code>bacs,cheque,cod</code>.<br>
                            You can find a gateway's ID in <strong>WooCommerce → Settings → Payments</strong>.
                            For these methods, the SMS fires immediately at checkout submission instead of waiting for payment confirmation.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Developer Options ── -->
        <div class="hutch-box">
            <h2>Developer Options</h2>
            <table class="form-table">
                <tr>
                    <th>Debug Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_debug" value="1"
                                   <?php checked( get_option( 'hutch_sms_debug' ), 1 ); ?>>
                            Write API request/response details to
                            <code><?php echo esc_html( WP_CONTENT_DIR . '/hutch-sms-debug.log' ); ?></code>
                        </label>
                        <p class="description" style="color:#c00">
                            <strong>Warning:</strong> May log sensitive data. Disable on production when not actively debugging.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Save Settings', 'primary button-hutch' ); ?>
    </form>
</div>
