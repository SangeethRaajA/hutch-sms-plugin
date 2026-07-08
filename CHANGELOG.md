# Changelog

All notable changes to the Hutch Bulk SMS plugin are documented here.

---

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

---

## [1.4.2] — 2026-03-07

### Fixed
- Gift voucher serial keys being sent in encrypted form (Base64-encoded AES ciphertext) instead of the plain text voucher code
- Root cause: PluginEver stores all serial keys AES-256-CBC encrypted in `wp_serial_numbers`; prior code read the raw column value without decryption

### Added
- `decrypt_serial()` method with 4-layer fallback: PluginEver v1 class → PluginEver v2 class → helper function → manual AES-256-CBC
- `aes_decrypt()` manual decryption using WordPress `AUTH_KEY` as key base (mirrors PluginEver's internal scheme)
- `get_via_serial_object_api()` — attempts PluginEver v2+ namespaced `Serial` model which returns pre-decrypted keys
- Decryption applied to all three query paths (Serial Object API, Legacy API, direct DB)

---

## [1.4.1] — 2026-03-06

### Fixed
- `{serial_key}` placeholder in voucher message template not being replaced (sent literally as `{serial_key}` in SMS)
- Root cause: message builder only recognised `{serial}` as the placeholder token

### Changed
- Message builder now replaces both `{serial}` and `{serial_key}` with the actual serial key value
- Settings page placeholder hints updated to show `{serial_key}` (consistent with template)
- Default voucher message template updated to use `{serial_key}`

---

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

---

## [1.3.0] — 2026-03-05

### Added
- XStore theme compatibility via multi-hook architecture
- Hooks: `woocommerce_order_status_processing`, `woocommerce_payment_complete`, `woocommerce_order_status_on-hold`, `woocommerce_checkout_order_processed`
- Deduplication transient system — prevents duplicate SMS when multiple hooks fire for the same order
- Fallback phone lookup: order meta (`_billing_phone`) → user meta (`billing_phone`) — handles cases where XStore checkout doesn't populate standard billing phone field
- Order Diagnostic Tool in Debug Tools — inspects any order ID and shows: status, payment method, phone (raw/meta/user/normalised), SMS enable flags, dedup state, and a plain-English summary
- HPOS detection in Plugin Info panel
- XStore theme detection in Plugin Info panel

### Fixed
- WooCommerce 8+ compatibility — replaced deprecated direct `WP_Post` order access with `wc_get_order()`
- Phone normalisation now handles all Sri Lankan formats: `07XXXXXXXX`, `947XXXXXXXX`, `+947XXXXXXXX`, `0094XXXXXXXXX`

---

## [1.2.0] — 2026-03-04

### Added
- Gift Voucher Serial Number SMS feature
- PluginEver WooCommerce Serial Numbers integration (`wp_serial_numbers` table)
- Serial key query with fallback: PluginEver API → direct DB (order_id + order_item_id + product_id, then order_id + product_id)
- WP-Cron retry after 90 seconds if PluginEver hasn't assigned serials yet at hook time
- Gift Voucher section in Settings with category selector, message template, character counter
- PluginEver detection badge on Settings page

---

## [1.1.1] — 2026-03-03

### Fixed
- WooCommerce 8+ compatibility with HPOS (High-Performance Order Storage)
- Phone normalisation edge cases for IDD prefix format (`0094...`)

---

## [1.1.0] — 2026-03-02

### Added
- Promotional/bulk SMS feature — send to filtered customer segments
- Customer filters: minimum order count, date range, specific product purchased
- Bulk send view with live preview of recipient count

---

## [1.0.0] — 2026-03-01

### Initial Release
- OAuth 2.0 integration with Hutch Business SMS API (JWT access + refresh tokens)
- Order Confirmation SMS on `woocommerce_order_status_processing`
- Order Completion SMS on `woocommerce_order_status_completed`
- Individual SMS send from admin dashboard
- Message templates with `{first_name}`, `{order_id}`, `{total}`, `{payment_method}`, `{items_count}` placeholders
- Debug mode with log file at `wp-content/hutch-sms-debug.log`
- Admin menu with Dashboard, Settings, Send, Debug Tools tabs
