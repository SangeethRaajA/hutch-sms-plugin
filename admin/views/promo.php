<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap hutch-wrap">
    <h1><span class="hutch-logo">HUTCH</span> Promotional SMS Campaign</h1>
    <p class="hutch-page-desc">Target customers who have already purchased from your store. Contact list is pulled live from <code>wp_wc_orders</code>.</p>

    <?php if ( $notice ) : ?>
        <div class="hutch-notice <?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['msg'] ); ?></div>
    <?php endif; ?>

    <!-- ── STEP 1: Filter contacts ── -->
    <div class="hutch-box">
        <h2>Step 1 — Filter Your Customer List</h2>
        <p>Choose which customers to include based on their order history.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hutch-form" id="promo-filter-form">
            <?php wp_nonce_field( 'hutch_sms_preview_contacts' ); ?>
            <input type="hidden" name="action" value="hutch_sms_preview_contacts">

            <table class="form-table">
                <tr>
                    <th><label for="filter-status">Order Status</label></th>
                    <td>
                        <select id="filter-status" name="filter_status">
                            <option value="">All Paid Orders (Processing, On Hold, Completed)</option>
                            <?php foreach ( $statuses as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Leave blank to include all orders with paid statuses.</p>
                    </td>
                </tr>
                <tr>
                    <th>Order Date Range</th>
                    <td>
                        <input type="date" name="filter_date_from" placeholder="From" style="max-width:160px">
                        &nbsp;to&nbsp;
                        <input type="date" name="filter_date_to" placeholder="To" style="max-width:160px">
                        <p class="description">Leave blank for all dates.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="filter-min-spend">Minimum Lifetime Spend (LKR)</label></th>
                    <td>
                        <input type="number" id="filter-min-spend" name="filter_min_spend" min="0" step="0.01" placeholder="0.00" style="max-width:160px">
                        <p class="description">Only include customers whose total orders exceed this amount. Leave blank or 0 for all.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Preview Contact List', 'secondary', '', false ); ?>
        </form>
    </div>

    <!-- ── Contact Preview Table ── -->
    <?php if ( ! empty( $contacts ) ) : ?>
    <div class="hutch-box">
        <h2>Contact Preview — <?php echo count( $contacts ); ?> Unique Customers Found</h2>
        <p style="color:#155724;background:#d4edda;padding:8px 12px;border-radius:4px;display:inline-block">
            ✓ These <?php echo count( $contacts ); ?> phone numbers will be used when you send the campaign below.
        </p>

        <div style="max-height:340px;overflow-y:auto;margin-top:12px;">
            <table class="hutch-log-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Orders</th>
                        <th>Lifetime Spend</th>
                        <th>Last Order</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $contacts as $i => $c ) : ?>
                    <tr>
                        <td><?php echo esc_html( $i + 1 ); ?></td>
                        <td><?php echo esc_html( trim( $c['first_name'] . ' ' . $c['last_name'] ) ); ?></td>
                        <td><code><?php echo esc_html( $c['phone'] ); ?></code></td>
                        <td><?php echo esc_html( $c['billing_email'] ); ?></td>
                        <td><?php echo esc_html( $c['total_orders'] ); ?></td>
                        <td>LKR <?php echo esc_html( $c['lifetime_spend'] ); ?></td>
                        <td><?php echo esc_html( substr( $c['last_order_date'], 0, 10 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p style="margin-top:8px;color:#6c757d;font-size:12px">
            Note: This preview is cached for 5 minutes. Re-run Step 1 to refresh. Contacts without a valid mobile number are automatically excluded.
        </p>
    </div>
    <?php endif; ?>

    <!-- ── STEP 2: Compose & send ── -->
    <div class="hutch-box">
        <h2>Step 2 — Compose & Send Promotional Message</h2>
        <p>The same filters applied above will be used to fetch the final contact list when you hit <strong>Send Campaign</strong>.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hutch-form"
              onsubmit="return confirm('Send this promotional SMS to all matching customers? This cannot be undone.');">
            <?php wp_nonce_field( 'hutch_sms_send_promo' ); ?>
            <input type="hidden" name="action" value="hutch_sms_send_promo">

            <!-- Re-include filter fields as hidden so they carry through to send handler -->
            <div id="promo-hidden-filters">
                <input type="hidden" name="filter_status"    id="h_status">
                <input type="hidden" name="filter_date_from" id="h_date_from">
                <input type="hidden" name="filter_date_to"   id="h_date_to">
                <input type="hidden" name="filter_min_spend" id="h_min_spend">
            </div>

            <table class="form-table">
                <tr>
                    <th><label for="promo-campaign">Campaign Name</label></th>
                    <td><input type="text" id="promo-campaign" name="campaign" value="Promotional Campaign" required></td>
                </tr>
                <tr>
                    <th><label for="promo-mask">Sender Mask</label></th>
                    <td>
                        <input type="text" id="promo-mask" name="mask" value="<?php echo esc_attr( get_option( 'hutch_sms_mask', '' ) ); ?>">
                        <p class="description">Overrides the default mask for this campaign.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="promo-content">Message</label></th>
                    <td>
                        <textarea id="promo-content" name="content" class="big-textarea"
                            placeholder="Hi {first_name}, special offer just for you! Use code SAVE20 for 20% off your next order." required></textarea>
                        <p class="description">
                            Placeholders: <code>{first_name}</code>, <code>{last_name}</code>.<br>
                            160 chars = 1 SMS. Sending to <strong><?php echo $contacts ? count( $contacts ) : 'previewed contact count'; ?></strong> recipients.
                        </p>
                    </td>
                </tr>
            </table>

            <!-- Re-apply filters visually -->
            <div class="hutch-box" style="background:#f8f9fa;margin-top:8px">
                <h2 style="font-size:13px;margin:0 0 10px">Apply Same Filters to Send</h2>
                <table class="form-table" style="margin:0">
                    <tr>
                        <th><label>Order Status</label></th>
                        <td>
                            <select name="filter_status">
                                <option value="">All Paid Orders</option>
                                <?php foreach ( $statuses as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Date Range</th>
                        <td>
                            <input type="date" name="filter_date_from" style="max-width:150px">
                            &nbsp;to&nbsp;
                            <input type="date" name="filter_date_to" style="max-width:150px">
                        </td>
                    </tr>
                    <tr>
                        <th>Min Spend (LKR)</th>
                        <td><input type="number" name="filter_min_spend" min="0" step="0.01" placeholder="0.00" style="max-width:130px"></td>
                    </tr>
                </table>
            </div>

            <?php submit_button( 'Send Campaign Now', 'primary button-hutch', '', false ); ?>
            <span style="margin-left:12px;color:#6c757d;font-size:13px">Messages will be batched in groups of 50 per API call.</span>
        </form>
    </div>
</div>
