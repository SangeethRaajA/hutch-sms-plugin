<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Send Individual SMS</h1>

    <?php if ( $notice ) : ?>
        <div class="hutch-notice <?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['msg'] ); ?></div>
    <?php endif; ?>

    <div class="hutch-box">
        <h2>Send SMS</h2>
        <p>Use this for order confirmations, notifications, or any single/multi-recipient message.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hutch-form">
            <?php wp_nonce_field( 'hutch_sms_send_individual' ); ?>
            <input type="hidden" name="action" value="hutch_sms_send_individual">
            <table class="form-table">
                <tr>
                    <th><label for="hs-campaign">Campaign Name</label></th>
                    <td><input type="text" id="hs-campaign" name="campaign" value="Manual Send" placeholder="Campaign Name" required></td>
                </tr>
                <tr>
                    <th><label for="hs-numbers">Phone Number(s)</label></th>
                    <td>
                        <input type="text" id="hs-numbers" name="numbers" placeholder="94771234567,94771234568" required>
                        <p class="description">Use international format (e.g. 94771234567). Separate multiple numbers with commas.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hs-content">Message</label></th>
                    <td>
                        <textarea id="hs-content" name="content" placeholder="Your message here..." required></textarea>
                        <p class="description">Standard SMS: 160 chars. Each additional 160 chars = 1 extra SMS credit.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Send SMS', 'primary button-hutch', 'submit', false ); ?>
        </form>
    </div>

    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
    <div class="hutch-box">
        <h2>WooCommerce Order SMS</h2>
        <p>Configure automated order messages in <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-settings' ) ); ?>">Settings → WooCommerce</a>.</p>
        <table class="widefat" style="max-width:600px">
            <tr>
                <td><strong>Order Confirmation</strong> (status: processing)</td>
                <td><?php echo get_option( 'hutch_sms_enable_order_confirm' ) ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-error">Disabled</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>Order Completion</strong> (status: completed)</td>
                <td><?php echo get_option( 'hutch_sms_enable_order_complete' ) ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-error">Disabled</span>'; ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
</div>
