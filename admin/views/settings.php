<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Settings</h1>

    <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="hutch-notice success">Settings saved successfully.</div>
    <?php endif; ?>

    <form method="post" action="options.php" class="hutch-form">
        <?php settings_fields( 'hutch_sms_settings' ); ?>

        <div class="hutch-box">
            <h2>API Credentials</h2>
            <table class="form-table">
                <tr>
                    <th><label for="hs-username">Username / Email</label></th>
                    <td>
                        <input type="text" id="hs-username" name="hutch_sms_username" value="<?php echo esc_attr( get_option( 'hutch_sms_username', '' ) ); ?>" placeholder="your@email.com" autocomplete="off">
                        <p class="description">Your Hutch Bulk SMS portal login email.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-password">Password</label></th>
                    <td>
                        <input type="password" id="hs-password" name="hutch_sms_password" value="<?php echo esc_attr( get_option( 'hutch_sms_password', '' ) ); ?>" autocomplete="new-password">
                        <p class="description">Stored encrypted at rest. Avoid reusing personal passwords.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-mask">Default Sender Mask</label></th>
                    <td>
                        <input type="text" id="hs-mask" name="hutch_sms_mask" value="<?php echo esc_attr( get_option( 'hutch_sms_mask', '' ) ); ?>" placeholder="YourBrand" maxlength="11">
                        <p class="description">Sender name shown to recipients (max 11 chars). Must be pre-approved by Hutch.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php if ( class_exists( 'WooCommerce' ) ) : ?>
        <div class="hutch-box">
            <h2>WooCommerce – Order SMS</h2>
            <p>Use placeholders: <code>{order_id}</code>, <code>{first_name}</code>, <code>{last_name}</code>, <code>{total}</code></p>
            <table class="form-table">
                <tr>
                    <th>Order Confirmation SMS</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_enable_order_confirm" value="1" <?php checked( get_option( 'hutch_sms_enable_order_confirm' ), 1 ); ?>>
                            Enable (fires when order status → <em>processing</em>)
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-confirm">Confirmation Message</label></th>
                    <td>
                        <textarea id="hs-msg-confirm" name="hutch_sms_msg_order_confirm"><?php echo esc_textarea( get_option( 'hutch_sms_msg_order_confirm', 'Thank you for your order #{order_id}! We are processing it now.' ) ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>Order Completion SMS</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_enable_order_complete" value="1" <?php checked( get_option( 'hutch_sms_enable_order_complete' ), 1 ); ?>>
                            Enable (fires when order status → <em>completed</em>)
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-msg-complete">Completion Message</label></th>
                    <td>
                        <textarea id="hs-msg-complete" name="hutch_sms_msg_order_complete"><?php echo esc_textarea( get_option( 'hutch_sms_msg_order_complete', 'Your order #{order_id} has been delivered. Thank you for shopping with us!' ) ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <div class="hutch-box">
            <h2>Developer Options</h2>
            <table class="form-table">
                <tr>
                    <th>Debug Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="hutch_sms_debug" value="1" <?php checked( get_option( 'hutch_sms_debug' ), 1 ); ?>>
                            Write API request/response details to <code><?php echo esc_html( WP_CONTENT_DIR . '/hutch-sms-debug.log' ); ?></code>
                        </label>
                        <p class="description" style="color:#c00"><strong>Note:</strong> Debug mode may log sensitive data. Disable on production when not needed.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Save Settings', 'primary button-hutch' ); ?>
    </form>
</div>
