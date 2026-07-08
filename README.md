# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version
## [1.4.0] — 2026-03-06
 
### Fixed
- PluginEver table name corrected from `wp_wc_serial_numbers` to `wp_serial_numbers` (confirmed from `DESCRIBE wp_serial_numbers` output)
- `plugin_ever_active()` detection updated to check correct table name
- Gift voucher category setting was defaulting to wrong category ("Accessories") due to stale option
### Changed
- **Gift voucher detection** changed from WooCommerce category-based lookup to product name matching — any product whose name contains "Gift Voucher" (case-insensitive) is now treated as a voucher
- Added `order_has_voucher($order)` public method to `Hutch_SMS_Voucher` class for use by WooCommerce hooks
- **Order Confirmation SMS** now skips entirely for Gift Voucher orders (voucher SMS handles notification)
- **Order Completion SMS** now skips entirely for Gift Voucher orders
- Checkout-processed hook also skips confirmation for Gift Voucher orders
### Removed
- All test SMS panels from Settings page (Order Confirmation test, Order Completion test, Gift Voucher test)
- Gift Voucher Category dropdown from Settings
- XStore & Payment Gateway Settings section (Offline Payment Method IDs setting)
- `handle_test_order()` and `handle_test_voucher()` handler methods from Admin class
- `hutch_sms_offline_methods` and `hutch_sms_voucher_category` from registered settings
- `get_voucher_category_products()` helper method
### Simplified
- Settings page Gift Voucher section now shows only: Enable toggle, Message template, character counter
- PluginEver status badge shows correct `wp_serial_numbers` table path