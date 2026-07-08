# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version
## [1.4.3] — 2026-03-07
 
### Fixed
- Gift voucher serial key decryption failing on all paths — raw encrypted value (e.g. `btcx7ZDF5YnIHS0bOvwcgg==`) was being sent in SMS instead of the actual voucher code
### Added
- **PluginEver availability logger** — on each voucher order, debug log now lists every PluginEver class and function present on the server, making it straightforward to identify which API surface to use
- **`wc_serial_numbers_get_serial_number($id)` path** — re-fetches each DB row by primary key ID through PluginEver's own public function (returns decrypted data)
- **`wp_salt()`-based AES key candidates** — PluginEver derives its encryption key from `wp_salt()` not raw `AUTH_KEY`; manual decryption now tries `wp_salt('auth')`, `wp_salt('secure_auth')`, and `wp_salt('logged_in')` as key sources
- **`wc_serial_numbers_get_serial_numbers()` path** — new Method 1 using PluginEver's public query helper function before trying class-based APIs
### Changed
- Serial key query now has 4 layered fallback methods: (1) `wc_serial_numbers_get_serial_numbers()`, (2) namespaced Serial model object API, (3) legacy `WC_Serial_Numbers_Query`, (4) direct DB query + decrypt-by-ID