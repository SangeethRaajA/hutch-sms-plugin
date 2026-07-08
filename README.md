# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version History
## [1.1.0] — 2026-03-02
 
### Added
- Promotional/bulk SMS feature — send to filtered customer segments
- Customer filters: minimum order count, date range, specific product purchased
- Bulk send view with live preview of recipient count

### Fixed
- WooCommerce 8+ compatibility with HPOS (High-Performance Order Storage)
- Phone normalisation edge cases for IDD prefix format (`0094...`)