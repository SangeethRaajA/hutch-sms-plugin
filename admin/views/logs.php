<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> SMS Logs</h1>

    <div class="hutch-box">
        <h2>Filter Logs</h2>
        <form method="get">
            <input type="hidden" name="page" value="hutch-sms-logs">
            <select name="type">
                <option value="">All Types</option>
                <option value="individual" <?php selected( $type, 'individual' ); ?>>Individual</option>
                <option value="bulk"       <?php selected( $type, 'bulk' ); ?>>Bulk</option>
            </select>
            &nbsp;
            <select name="status">
                <option value="">All Statuses</option>
                <option value="success" <?php selected( $status, 'success' ); ?>>Success</option>
                <option value="error"   <?php selected( $status, 'error' ); ?>>Error</option>
            </select>
            &nbsp;
            <?php submit_button( 'Filter', 'secondary', '', false ); ?>
            &nbsp;
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=hutch-sms-logs' ) ); ?>" class="button">Reset</a>
        </form>
    </div>

    <div class="hutch-box">
        <h2>
            Log Entries
            <span style="font-weight:400;font-size:13px;margin-left:12px;color:#6c757d;"><?php echo esc_html( $total ); ?> total records</span>
        </h2>

        <?php if ( empty( $logs ) ) : ?>
            <p>No log entries found.</p>
        <?php else : ?>
            <table class="hutch-log-table">
                <thead>
                    <tr>
                        <th>#</th><th>Date / Time</th><th>Type</th><th>Numbers</th><th>Campaign</th><th class="cell-msg">Message</th><th>Status</th><th class="cell-resp">Response</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['id'] ); ?></td>
                        <td style="white-space:nowrap"><?php echo esc_html( $row['sent_at'] ); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr( $row['type'] ); ?>"><?php echo esc_html( $row['type'] ); ?></span></td>
                        <td><?php echo esc_html( $row['numbers'] ); ?></td>
                        <td><?php echo esc_html( $row['campaign'] ); ?></td>
                        <td class="cell-msg"><?php echo esc_html( $row['message'] ); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
                        <td class="cell-resp"><?php echo esc_html( $row['response'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div style="margin-top:16px">
                <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'paged' => $i, 'type' => $type, 'status' => $status ), admin_url( 'admin.php?page=hutch-sms-logs' ) ) ); ?>"
                       class="button <?php echo $i === $page ? 'button-primary' : ''; ?>"><?php echo esc_html( $i ); ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="margin-top:20px">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Clear all SMS logs? This cannot be undone.');">
                <?php wp_nonce_field( 'hutch_sms_clear_logs' ); ?>
                <input type="hidden" name="action" value="hutch_sms_clear_logs">
                <?php submit_button( 'Clear All Logs', 'delete', '', false ); ?>
            </form>
        </div>
    </div>
</div>
