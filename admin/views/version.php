<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Version History</h1>

    <div class="hutch-box">
        <h2>Current Version: <?php echo esc_html( HUTCH_SMS_VERSION ); ?></h2>
        <p>Developed by <strong>Sangeeth</strong></p>

        <table class="hutch-version-table">
            <thead>
                <tr><th style="width:90px">Version</th><th style="width:110px">Date</th><th>Changes</th></tr>
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
                            'Individual SMS (manual).',
                            'Bulk SMS campaign (manual number list, batched 50/request).',
                            'WooCommerce: Order Confirmation SMS on processing status.',
                            'WooCommerce: Order Completion SMS on completed status.',
                            'SMS log table with filters and pagination.',
                            'Debug tools with live API connection test.',
                        ),
                    ),
                    array(
                        'version' => '1.1.0',
                        'date'    => 'March 2026',
                        'notes'   => array(
                            'NEW: Gift Voucher Serial Number SMS — reads wp_serial_numbers, sends serial key to customer on order completion.',
                            'NEW: Promotional Campaign page — pulls unique contacts from wp_wc_orders.',
                            'NEW: Promotional filters — by order status, date range, minimum lifetime spend.',
                            'NEW: Contact list preview before sending.',
                            'IMPROVED: Order SMS placeholders — added {payment_method} and {items_count}.',
                            'IMPROVED: Settings page — added Gift Voucher configuration section.',
                        ),
                    ),
                    array(
                        'version' => '1.1.1',
                        'date'    => 'March 2026',
                        'notes'   => array(
                            'BUGFIX: WooCommerce order status hooks were receiving null $order object in WC 8+. Now uses 1-arg hook + wc_get_order() to safely load the order — fixes orders not triggering SMS.',
                            'BUGFIX: Phone normalisation rejected valid Sri Lankan numbers (11 digits: 94XXXXXXXXX). Added full handling for 07x (local), 947x (intl), +947x, and 0094x formats.',
                            'BUGFIX: Voucher SMS used same broken 2-arg hook pattern. Fixed to match.',
                            'IMPROVED: Plugin init moved to plugins_loaded priority 20 (was default 10) to guarantee WooCommerce is fully loaded before hooks are registered.',
                            'IMPROVED: Voucher SMS now schedules a WP-Cron retry (90s) if no serials are found yet — handles cases where the Serial Numbers plugin writes records after the order hook fires.',
                            'IMPROVED: Debug logging added throughout order + voucher flow for easier troubleshooting.',
                        ),
                    ),
                );

                $current = HUTCH_SMS_VERSION;
                foreach ( array_reverse( $changelog ) as $entry ) :
                    $is_current = $entry['version'] === $current;
                ?>
                <tr class="<?php echo $is_current ? 'current-version' : ''; ?>">
                    <td style="vertical-align:top;white-space:nowrap;font-weight:700">
                        <?php echo esc_html( $entry['version'] ); ?>
                        <?php if ( $is_current ) echo '<br><span class="badge badge-success" style="margin-top:4px">Current</span>'; ?>
                    </td>
                    <td style="vertical-align:top;white-space:nowrap;color:#6c757d"><?php echo esc_html( $entry['date'] ); ?></td>
                    <td>
                        <ul style="margin:4px 0;padding-left:18px">
                        <?php foreach ( $entry['notes'] as $note ) : ?>
                            <li style="margin-bottom:4px"><?php echo esc_html( $note ); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:16px;color:#6c757d;font-size:12px">
            <code>HUTCH_SMS_VERSION</code> = <strong><?php echo esc_html( HUTCH_SMS_VERSION ); ?></strong>
        </p>
    </div>
</div>
