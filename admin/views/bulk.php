<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Bulk SMS Campaign</h1>

    <?php if ( $notice ) : ?>
        <div class="hutch-notice <?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['msg'] ); ?></div>
    <?php endif; ?>

    <div class="hutch-box">
        <h2>Send Bulk Campaign</h2>
        <p>Send the same promotional message to many recipients. Batches are split automatically (max 50 per API call).</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hutch-form">
            <?php wp_nonce_field( 'hutch_sms_send_bulk' ); ?>
            <input type="hidden" name="action" value="hutch_sms_send_bulk">
            <table class="form-table">
                <tr>
                    <th><label for="hb-campaign">Campaign Name</label></th>
                    <td><input type="text" id="hb-campaign" name="campaign" value="Promo Campaign" placeholder="Campaign Name" required></td>
                </tr>
                <tr>
                    <th><label for="hb-mask">Sender Mask</label></th>
                    <td>
                        <input type="text" id="hb-mask" name="mask" value="<?php echo esc_attr( get_option( 'hutch_sms_mask', '' ) ); ?>" placeholder="Your sender name">
                        <p class="description">Overrides the default mask for this campaign.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hb-numbers">Phone Numbers</label></th>
                    <td>
                        <textarea id="hb-numbers" name="numbers" class="big-textarea" placeholder="94771234567&#10;94771234568&#10;94771234569" required></textarea>
                        <p class="description">One number per line, or comma-separated. International format (94XXXXXXXXX). No hard limit — batched automatically.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="hb-content">Message Content</label></th>
                    <td>
                        <textarea id="hb-content" name="content" class="big-textarea" placeholder="Your promotional message..." required></textarea>
                        <p class="description">This same message is sent to all recipients above.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Launch Campaign', 'primary button-hutch', 'submit', false ); ?>
        </form>
    </div>
</div>
