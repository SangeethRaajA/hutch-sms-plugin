# hutch-sms-plugin
A custom WordPress plugin that integrates the Hutch SMS API with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.

## Version
## [1.4.2] — 2026-03-07
 
### Fixed
- Gift voucher serial keys being sent in encrypted form (Base64-encoded AES ciphertext) instead of the plain text voucher code
- Root cause: PluginEver stores all serial keys AES-256-CBC encrypted in `wp_serial_numbers`; prior code read the raw column value without decryption
### Added
- `decrypt_serial()` method with 4-layer fallback: PluginEver v1 class → PluginEver v2 class → helper function → manual AES-256-CBC
- `aes_decrypt()` manual decryption using WordPress `AUTH_KEY` as key base (mirrors PluginEver's internal scheme)
- `get_via_serial_object_api()` — attempts PluginEver v2+ namespaced `Serial` model which returns pre-decrypted keys
- Decryption applied to all three query paths (Serial Object API, Legacy API, direct DB)