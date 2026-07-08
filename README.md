# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version History
## [1.0.0] — 2026-03-01
 
### Initial Release
- OAuth 2.0 integration with Hutch Business SMS API (JWT access + refresh tokens)
- Order Confirmation SMS on `woocommerce_order_status_processing`
- Order Completion SMS on `woocommerce_order_status_completed`
- Individual SMS send from admin dashboard
- Message templates with `{first_name}`, `{order_id}`, `{total}`, `{payment_method}`, `{items_count}` placeholders
- Debug mode with log file at `wp-content/hutch-sms-debug.log`
- Admin menu with Dashboard, Settings, Send, Debug Tools tabs