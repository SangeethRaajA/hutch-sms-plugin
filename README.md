# hutch-sms-plugin
A custom WordPress plugin that integrates the **Hutch SMS API** with WooCommerce to send automated SMS notifications to customers on order confirmation, order completion, and gift voucher serial key delivery.
Built for a Sri Lankan e-commerce store running WooCommerce on the theme.

---

## Features

- **Order Confirmation SMS** — fires when an order is placed and moves to processing or on-hold
- **Order Completion SMS** — fires when an order is marked completed
- **Gift Voucher Serial SMS** — automatically sends the customer their serial key when they purchase a Gift Voucher product, powered by [WooCommerce Serial Numbers by PluginEver](https://pluginever.com/plugins/woocommerce-serial-numbers/)
- **XStore Theme Compatibility** — multi-hook architecture handles all WooCommerce checkout paths (online payments, offline/COD, Elementor-based checkout)
- **Deduplication** — transient-based system ensures only one SMS is sent per order per event, regardless of how many hooks fire
- **Phone Normalisation** — handles all Sri Lankan number formats (`07XXXXXXXX`, `+94...`, `0094...`, `94...`)
- **Promotional SMS** — bulk send to filtered customer lists (by order count, date range, product purchased)
- **OAuth 2.0 Token Management** — auto-refresh of Hutch API access tokens
- **Admin Dashboard** — live character counter, send history, debug log, order diagnostic tool
- **Debug Mode** — full request/response logging to `wp-content/hutch-sms-debug.log`

---

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 8.0+
- Active Hutch Business SMS account with API credentials
- (Optional) WooCommerce Serial Numbers by PluginEver — required for Gift Voucher SMS

---

## Installation

1. Download the latest release ZIP from the [Releases](../../releases) page
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and click **Install Now**, then **Activate**
4. Go to **Hutch SMS → Settings** and enter your API credentials:
   - Username (email)
   - Password
   - SMS Mask (sender name, e.g. `ABC`)
5. Click **Test API Login** in the Debug Tools tab to verify the connection
6. Enable Order Confirmation, Order Completion, and/or Gift Voucher SMS in Settings
7. Customise your message templates using the available placeholders

---

## SMS Message Placeholders

### Order Confirmation & Completion
| Placeholder | Description |
|---|---|
| `{first_name}` | Customer's first name |
| `{last_name}` | Customer's last name |
| `{order_id}` | WooCommerce order number |
| `{total}` | Formatted order total |
| `{payment_method}` | Payment method title |
| `{items_count}` | Number of items in the order |

### Gift Voucher Serial SMS
| Placeholder | Description |
|---|---|
| `{first_name}` | Customer's first name |
| `{serial_key}` | The decrypted serial/voucher code |
| `{product_name}` | Name of the voucher product |
| `{order_id}` | WooCommerce order number |
| `{expire_date}` | Expiry date from PluginEver |
| `{validity}` | Validity period |

---

## Gift Voucher Detection

Any WooCommerce product whose name **contains "Gift Voucher"** (case-insensitive) is automatically treated as a voucher product. When such an order is placed:

- Standard Order Confirmation and Completion SMS are **skipped**
- The plugin waits for PluginEver to assign a serial key (hook priority 30, after PluginEver at 10–20)
- A Voucher Serial SMS is sent with the decrypted serial key
- If the serial is not yet available (timing edge case), a WP-Cron retry fires after 90 seconds

---

## XStore Compatibility

XStore's checkout builder routes orders through different hooks depending on payment method. This plugin hooks into all relevant lifecycle events:

| Hook | Trigger |
|---|---|
| `woocommerce_order_status_processing` | Standard flow — COD, BACS, manual admin |
| `woocommerce_payment_complete` | After successful online payment (PayHere, card, etc.) |
| `woocommerce_order_status_on-hold` | Some gateways park orders here first |
| `woocommerce_checkout_order_processed` | Fallback — fires at checkout submit for offline gateways |
| `woocommerce_order_status_completed` | Order completion |

Deduplication via WordPress transients ensures only one SMS fires per order regardless of how many of these hooks trigger.

---

## Plugin File Structure

```
hutch-sms/
├── hutch-sms.php                          # Main plugin file, version constant, loader
├── includes/
│   ├── class-hutch-sms-api.php            # OAuth 2.0 login, token refresh, send_sms()
│   ├── class-hutch-sms-woocommerce.php    # Order hooks, phone normalisation, message builder
│   ├── class-hutch-sms-voucher.php        # Gift voucher detection, serial lookup, cron retry
│   ├── class-hutch-sms-promotional.php   # Bulk/promotional SMS with customer filtering
│   └── class-hutch-sms-logger.php        # Debug log writer
└── admin/
    ├── class-hutch-sms-admin.php          # Admin menu, settings, form handlers
    ├── assets/
    │   └── admin.css
    └── views/
        ├── dashboard.php                  # Overview & send history
        ├── settings.php                   # Configuration page
        ├── send.php                       # Individual SMS send
        ├── bulk.php                       # Bulk/promotional send
        ├── debug.php                      # Debug log + order diagnostic tool
        ├── logs.php                       # SMS send log
        ├── promo.php                      # Promotional campaigns
        └── version.php                    # Version info
```

---

## Version History

See [CHANGELOG.md](CHANGELOG.md)

---

## API Reference

This plugin uses the **Hutch Business SMS REST API**. Authentication is via OAuth 2.0 (JWT). The access token expires every 24 hours and is refreshed automatically.

Endpoints used:
- `POST /auth/login` — obtain access + refresh tokens
- `POST /auth/refresh` — refresh expired access token
- `POST /sms/send` — send SMS message

---

## Development Notes

- Requires PHP 8.0+ (uses named arguments, `fn()` arrow functions, `str_contains`)
- HPOS compatible — uses `wc_get_order()` throughout, never direct `wp_posts` queries
- Gift voucher serial decryption supports PluginEver v1 (`WC_Serial_Numbers_Encryption`), v2 (`WooCommerce_Serial_Numbers\Encryption`), and manual AES-256-CBC as fallback
- All user-facing settings sanitised on save; all output escaped on render

---

## License

This is free and unencumbered software released into the public domain.