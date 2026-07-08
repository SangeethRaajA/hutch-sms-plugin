# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version
## [1.4.1] — 2026-03-06
 
### Fixed
- `{serial_key}` placeholder in voucher message template not being replaced (sent literally as `{serial_key}` in SMS)
- Root cause: message builder only recognised `{serial}` as the placeholder token
### Changed
- Message builder now replaces both `{serial}` and `{serial_key}` with the actual serial key value
- Settings page placeholder hints updated to show `{serial_key}` (consistent with template)
- Default voucher message template updated to use `{serial_key}`