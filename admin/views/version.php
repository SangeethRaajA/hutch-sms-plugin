<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Version History</h1>

    <div class="hutch-box">
        <h2>Current Version: <?php echo esc_html( HUTCH_SMS_VERSION ); ?></h2>
        <p>Developed by <strong>Sangeeth</strong></p>

        <table class="hutch-version-table">
            <thead>
                <tr><th>Version</th><th>Release Date</th><th>Changes</th></tr>
            </thead>
            <tbody>
                <?php
                $changelog = array(
                    array(
                        'version' => '1.0.0',
                        'date'    => 'March 2026',
                        'notes'   => array(
                            'Initial release.',
                            'OAuth 2.0 login with automatic access token renewal via refresh token.',
                            'Individual SMS sending (manual).',
                            'Bulk SMS campaign (manual number list, batched to 50/request).',
                            'WooCommerce: Order Confirmation SMS (on processing status).',
                            'WooCommerce: Order Completion SMS (on completed status).',
                            'SMS log table with filters and pagination.',
                            'Debug tools with live API connection test.',
                            'Version history tracking.',
                        ),
                    ),
                    array(
                        'version' => '1.1.0',
                        'date'    => 'March 2026',
                        'notes'   => array(
                            'NEW: Gift Voucher Serial Number SMS — reads wp_serial_numbers table, matches by order_id + order_item_id + product_id, sends serial key to customer on order completion.',
                            'NEW: Promotional Campaign page — pulls unique customer contacts with valid phone numbers directly from wp_wc_orders.',
                            'NEW: Promotional contact filters — filter by order status, date range, minimum lifetime spend.',
                            'NEW: Contact list preview — preview all matching customers before sending (shows name, phone, email, order count, lifetime spend).',
                            'IMPROVED: Order SMS message placeholders expanded — added {payment_method} and {items_count}.',
                            'IMPROVED: Phone normalisation — invalid format numbers are now skipped gracefully.',
                            'IMPROVED: Settings page — added Gift Voucher configuration section with product ID input and message template.',
                            'IMPROVED: Version history now tracks install vs upgrade automatically.',
                        ),
                    ),
                );

                $current = HUTCH_SMS_VERSION;
                foreach ( $changelog as $entry ) :
                    $is_current = $entry['version'] === $current;
                ?>
                <tr class="<?php echo $is_current ? 'current-version' : ''; ?>">
                    <td style="vertical-align:top;white-space:nowrap">
                        <strong><?php echo esc_html( $entry['version'] ); ?></strong>
                        <?php if ( $is_current ) echo ' &nbsp;<span class="badge badge-success">Current</span>'; ?>
                    </td>
                    <td style="vertical-align:top;white-space:nowrap"><?php echo esc_html( $entry['date'] ); ?></td>
                    <td>
                        <ul style="margin:0;padding-left:18px">
                        <?php foreach ( $entry['notes'] as $note ) : ?>
                            <li style="margin-bottom:3px"><?php echo esc_html( $note ); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:20px;color:#6c757d;font-size:12px">
            Plugin constant: <code>HUTCH_SMS_VERSION</code> = <strong><?php echo esc_html( HUTCH_SMS_VERSION ); ?></strong>
        </p>
    </div>
</div>
