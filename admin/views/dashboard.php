<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> SMS Dashboard</h1>

    <div class="hutch-cards">
        <div class="hutch-card">
            <div class="card-label">Total Sent</div>
            <div class="card-value orange"><?php echo esc_html( $total ); ?></div>
        </div>
        <div class="hutch-card">
            <div class="card-label">Successful</div>
            <div class="card-value green"><?php echo esc_html( $success ); ?></div>
        </div>
        <div class="hutch-card">
            <div class="card-label">Errors</div>
            <div class="card-value red"><?php echo esc_html( $errors ); ?></div>
        </div>
        <div class="hutch-card">
            <div class="card-label">Bulk Batches</div>
            <div class="card-value"><?php echo esc_html( $bulk ); ?></div>
        </div>
        <div class="hutch-card">
            <div class="card-label">Plugin Version</div>
            <div class="card-value" style="font-size:20px"><?php echo esc_html( HUTCH_SMS_VERSION ); ?></div>
        </div>
    </div>

    <?php
    $token_expiry = (int) get_option( 'hutch_sms_token_expiry', 0 );
    $token_valid  = $token_expiry > time();
    ?>
    <div class="hutch-box">
        <h2>API Token Status</h2>
        <div class="token-status <?php echo $token_valid ? 'valid' : 'expired'; ?>">
            <span class="dot"></span>
            <?php if ( $token_valid ) : ?>
                Token valid — expires <?php echo esc_html( date( 'Y-m-d H:i:s', $token_expiry ) ); ?>
            <?php else : ?>
                No valid token. A new token will be obtained automatically on next send, or use the <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-debug' ) ); ?>">Debug Tools</a> to test login.
            <?php endif; ?>
        </div>
    </div>

    <div class="hutch-box">
        <h2>Recent Activity (last 5)</h2>
        <?php if ( empty( $recent ) ) : ?>
            <p>No messages sent yet.</p>
        <?php else : ?>
            <table class="hutch-log-table">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Numbers</th><th>Campaign</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $recent as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['sent_at'] ); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr( $row['type'] ); ?>"><?php echo esc_html( $row['type'] ); ?></span></td>
                        <td><?php echo esc_html( $row['numbers'] ); ?></td>
                        <td><?php echo esc_html( $row['campaign'] ); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-logs' ) ); ?>">View all logs →</a></p>
        <?php endif; ?>
    </div>

    <div class="hutch-box">
        <h2>Quick Links</h2>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-send' ) ); ?>" class="button button-primary button-hutch">Send Individual SMS</a>
        &nbsp;
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-bulk' ) ); ?>" class="button button-primary">Bulk Campaign</a>
        &nbsp;
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-settings' ) ); ?>" class="button button-secondary">Settings</a>
    </div>
</div>
