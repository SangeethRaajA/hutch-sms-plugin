# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version History

## [1.2.0] — 2026-03-04
### Added
- Gift Voucher Serial Number SMS feature
- PluginEver WooCommerce Serial Numbers integration (`wp_serial_numbers` table)
- Serial key query with fallback: PluginEver API → direct DB (order_id + order_item_id + product_id, then order_id + product_id)
- WP-Cron retry after 90 seconds if PluginEver hasn't assigned serials yet at hook time
- Gift Voucher section in Settings with category selector, message template, character counter
- PluginEver detection badge on Settings page

## [1.1.0] — 2026-03-02
### Added
- Promotional/bulk SMS feature — send to filtered customer segments
- Customer filters: minimum order count, date range, specific product purchased
- Bulk send view with live preview of recipient count

### Fixed
- WooCommerce 8+ compatibility with HPOS (High-Performance Order Storage)
- Phone normalisation edge cases for IDD prefix format (`0094...`)

## [1.0.0] — 2026-03-01
### Initial Release
- OAuth 2.0 integration with Hutch Business SMS API (JWT access + refresh tokens)
- Order Confirmation SMS on `woocommerce_order_status_processing`
- Order Completion SMS on `woocommerce_order_status_completed`
- Individual SMS send from admin dashboard
- Message templates with `{first_name}`, `{order_id}`, `{total}`, `{payment_method}`, `{items_count}` placeholders
- Debug mode with log file at `wp-content/hutch-sms-debug.log`
- Admin menu with Dashboard, Settings, Send, Debug Tools tabs