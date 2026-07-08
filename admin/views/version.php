<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Version History</h1>

    <div class="hutch-box">
        <h2>Current Version: <?php echo esc_html( HUTCH_SMS_VERSION ); ?></h2>
        <p>Developed by <strong>Sangeeth</strong></p>

        <table class="hutch-version-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Release Date</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $current = get_option( 'hutch_sms_version', '1.0.0' );
                $changelog = array(
                    array(
                        'version' => '1.0.0',
                        'date'    => 'March 2026',
                        'notes'   => 'Initial release. Features: OAuth 2.0 login & auto token renewal, individual SMS, bulk SMS campaign (up to 50 per batch), WooCommerce order confirmation & completion hooks, SMS log table with filters & pagination, debug tools with live API test, version history.',
                    ),
                );
                // Merge with stored history (future updates will append here)
                $stored = get_option( 'hutch_sms_version_history', array() );

                foreach ( $changelog as $entry ) :
                    $is_current = $entry['version'] === $current;
                ?>
                <tr class="<?php echo $is_current ? 'current-version' : ''; ?>">
                    <td>
                        <?php echo esc_html( $entry['version'] ); ?>
                        <?php if ( $is_current ) echo '&nbsp;<span class="badge badge-success">Current</span>'; ?>
                    </td>
                    <td><?php echo esc_html( $entry['date'] ); ?></td>
                    <td><?php echo esc_html( $entry['notes'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:20px;color:#6c757d;font-size:12px">
            Plugin constant: <code>HUTCH_SMS_VERSION</code> = <strong><?php echo esc_html( HUTCH_SMS_VERSION ); ?></strong><br>
            Future updates will increment this constant and append a new row to this table.
        </p>
    </div>
</div>
