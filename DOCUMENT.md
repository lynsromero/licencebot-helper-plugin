# LicenceBot Auto-Delivery Integration — Implementation Document

**Project:** ac-serial-numbers WordPress Plugin  
**Date:** May 19, 2026  
**Status:** ✅ Implemented — Ready for Testing  
**LicenceBot Base URL:** `https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1`

---

## 1. Original Problem

### Symptoms
- WooCommerce orders showed **"Order waiting for assigning serial numbers"** even after payment completed
- **"Sync Licenses from LicenceBot"** button did nothing (returned 0 synced, no error)
- LicenceBot's delivery pipeline was live and processing orders server-side
- Stock Manager in the plugin was working (fetching products from LicenceBot API)

### Root Cause Analysis

Three specific bugs were identified:

| # | Bug | Impact |
|---|-----|--------|
| 1 | **Webhook Secret Mismatch** | LicenceBot sends `X-Webhook-Secret: license_api_keys.auth_secret` but plugin validated against `_ac_serial_numbers_webhook_secret` (a different locally-generated value). Result: 401 rejection → handler never runs → no keys stored |
| 2 | **Serial Keys Stored Without Encryption** | Webhook handler inserted keys as plaintext via raw `$wpdb->insert()`. Admin UI reads keys through `ac_serial_numbers_decrypt_key()` which expects encrypted data → garbage/empty display |
| 3 | **Webhook URL Registered Wrong** | Plugin registered `rest_url('ac-serial-numbers/v1/webhook/')` but LicenceBot pushes to `/wp-json/ac-serial-numbers/v1/order/update/` |
| 4 | **Reseller Code Path Commented Out** | The automatic key-fetch fallback (`ac_serial_numbers_order_connect_serial_numbers()`) had its entire `reseller` branch commented out (lines 262-334) — only `custom_source` (local DB lookup) was active |
| 5 | **Missing `return` Statements** | `webhook_handler()` created `WP_REST_Response` objects on lines 188, 199, 208 but never returned them |

---

## 2. The LicenceBot Delivery Pipeline (Server-Side)

LicenceBot's server-side pipeline is already live and enforced:

```
Payment Complete (WooCommerce)
    → POST /functions/v1/woo-order-webhook (Woo sends order data)
    → Verify HMAC signature (X-WC-Webhook-Signature)
    → Resolve Store ID from woo_stores
    → claim_license_order_delivery(store_id, woo_order_id) — idempotent lock
    → Get buyer email from billing.email
    → Map line items → license_products (meta → mapping table → bundle expansion)
    → FIFO claim from license_serial_numbers (mark sold, decrement stock)
    → Email buyer via sendViaPlatformSmtp (keys + download + guide + tracking)
    → Push keys to WordPress via callStoreWebhook (X-Webhook-Secret header)
    → Update store_orders to delivered, fan-out alerts
```

### Key Server-Side Details
- **Idempotency:** `claim_license_order_delivery(store_id, woo_order_id)` RPC prevents double-delivery
- **HMAC verification:** crypto.subtle HMAC-SHA256 over raw body, base64 compare against store's `consumer_secret`
- **FIFO index:** `idx_license_serial_numbers_fifo (product_id, status, created_at) WHERE status='available'`
- **Timeout:** 10s on LicenceBot's side for the WP webhook call

---

## 3. Payload Format (LicenceBot → WordPress Webhook)

### Endpoint
```
POST {store_url}/wp-json/ac-serial-numbers/v1/order/update/
```

### Headers
```
Content-Type: application/json
X-Webhook-Secret: <auth_secret from license_api_keys for this store>
```

### JSON Body
```json
{
  "invoice_no": 12345,
  "orderData": [
    {
      "cp_id": 678,
      "serialKeys": [
        {
          "serialNumber": "ABCD-EFGH-IJKL-MNOP",
          "activationGuide": "https://example.com/guide",
          "activation_limit": 1,
          "supplierId": "",
          "client_product_id": 678
        }
      ]
    }
  ]
}
```

### Field Reference
| Field | Type | Notes |
|-------|------|-------|
| `invoice_no` | number | WooCommerce order ID |
| `orderData` | array | One entry per delivered product line |
| `orderData[].cp_id` | number | WooCommerce product ID |
| `orderData[].serialKeys` | array | One entry per delivered key (qty > 1 → multiple keys) |
| `serialKeys[].serialNumber` | string | The license key string |
| `serialKeys[].activationGuide` | string \| null | Per-key guide URL; falls back to product's activation_guide_link |
| `serialKeys[].activation_limit` | number | Currently always 1 |
| `serialKeys[].supplierId` | string | Currently always "" (reserved) |
| `serialKeys[].client_product_id` | number | Same as cp_id (duplicated for convenience) |

### Notes
- Fire-and-once per order — LicenceBot uses `claim_license_order_delivery()` for idempotency
- Plugin must return 2xx response; body is logged but not parsed
- If product is out of stock, it is omitted from `orderData`
- Bundled products are pre-expanded into individual `cp_id` entries

---

## 4. All API Routes & Methods

### 4.1 LicenceBot → WordPress (Inbound Webhooks)

| Route | Method | Handler | Auth | Purpose |
|-------|--------|---------|------|---------|
| `/wp-json/ac-serial-numbers/v1/order/update/` | POST | `webhook_order_update_handler()` | `X-Webhook-Secret` header | **Primary** — Receives delivered serial keys from LicenceBot |
| `/wp-json/ac-serial-numbers/v1/webhook/` | POST | `webhook_handler()` | `X-Webhook-Secret` header | **Legacy** — Same payload, also sends order status back to LicenceBot |

### 4.2 WordPress → LicenceBot (Outbound API Calls)

| Route | Method | Purpose | Called By |
|-------|--------|---------|-----------|
| `POST /functions/v1/register-helper-store` | POST | Register store with LicenceBot, receive `store_id`, `store_token`, `api_endpoint`, `api_key`, `license_auth_secret` | `register_with_licencebot()` on plugin activation / re-connect |
| `POST /functions/v1/complete-helper-setup` | POST | Exchange one-time setup token for org credentials | `handle_licencebot_setup_token()` when user comes from LicenceBot redirect |
| `GET {api_endpoint}/product/stocks-all` | GET | Fetch all products with stock data | `ac_fetch_products_data()` — Stock Manager page |
| `GET {api_endpoint}/product/stocks-status` | GET | Fetch license status counts | `ac_serial_numbers_get_license_counts()` — admin dashboard |
| `POST {api_endpoint}/shop/new-order` | POST | Request serial keys for an order (fallback pull) | `ac_serial_numbers_get_serial_numbers()` — reseller code path |
| `POST {api_endpoint}/store/incoming/orders` | POST | Send order data to LicenceBot on status change | `AC_Serial_Numbers_Cart_Tracking` — `woocommerce_order_status_changed` hook |
| `POST {api_endpoint}/store/updating/orders` | POST | Request new keys manually | `ac_serial_numbers_request_new_keys()` AJAX — "Request Keys" button |
| `POST {api_endpoint}/store/notify/order/status` | POST | Notify LicenceBot of order status update | `send_order_status()` — webhook handler response |

### 4.3 WordPress REST API (Internal)

| Route | Method | Purpose |
|-------|--------|---------|
| `GET /wc/v1/products/ids/` | GET | Get all product IDs (authenticated) |
| `GET /wc/v1/products/updated/` | GET | Get recently updated products (authenticated) |

### 4.4 WordPress AJAX Endpoints

| Action | Purpose |
|--------|---------|
| `ac_serial_numbers_search_products` | Search products for Select2 dropdowns |
| `ac_serial_numbers_decrypt_key` | Decrypt a serial key for admin display |
| `ac_serial_numbers_refresh_counts` | Refresh license counts from LicenceBot API |
| `ac_serial_numbers_sync_order` | Sync serial numbers for a specific order from LicenceBot |
| `ac_serial_numbers_update_product_key_source` | Update product key source (custom/reseller) |
| `ac_serial_numbers_update_product_key_source_from_order_page` | Update key source from order page |
| `ac_serial_numbers_update_product_mapping` | Map local product to remote LicenceBot product |
| `ac_serial_numbers_update_product_mapping_from_shop_order` | Map product from order page |
| `ac_serial_numbers_clear_transient_data` | Clear cached product data |
| `ac_serial_numbers_request_new_keys` | Manually request keys for an order |

---

## 5. What Was Done — Phase by Phase

### Phase 1: Save `license_auth_secret` from Registration Response

**File:** `ac-serial-numbers.php`

| Change | Line(s) | Description |
|--------|---------|-------------|
| Added constant | 69 | `define('AC_SERIAL_OPT_AUTH_SECRET', '_ac_serial_auth_secret')` |
| Fixed webhook URL | 404 | Changed `rest_url('ac-serial-numbers/v1/webhook/')` → `rest_url('ac-serial-numbers/v1/order/update/')` |
| Save in registration | 569-572 | `register_with_licencebot()` now saves `$body['license_auth_secret']` to `wp_options` |
| Save in setup token | 675-678 | `handle_licencebot_setup_token()` now saves `$body['license_auth_secret']` to `wp_options` |

**Why:** The server now returns `license_auth_secret` (from `license_api_keys.auth_secret`) in the registration response. The plugin saves it so the webhook handler can validate incoming requests.

---

### Phase 2: Fix Webhook Handler Auth Secret Validation

**File:** `includes/class-ac-serial-numbers-webhook.php`

| Change | Line(s) | Description |
|--------|---------|-------------|
| Updated `/webhook/` permission callback | 20-27 | Checks `_ac_serial_auth_secret` first, fallback to `_ac_serial_numbers_webhook_secret` |
| Updated `/order/update/` permission callback | 32-39 | Same logic — primary auth secret, fallback to local secret |

**Before:**
```php
$secret_key = get_option('_ac_serial_numbers_webhook_secret');
return $provided_key === $secret_key;
```

**After:**
```php
$auth_secret = get_option('_ac_serial_auth_secret');
if (empty($auth_secret)) {
    $auth_secret = get_option('_ac_serial_numbers_webhook_secret');
}
return $provided_key === $auth_secret;
```

**Why:** LicenceBot sends `license_api_keys.auth_secret` as `X-Webhook-Secret`, not the locally-generated webhook secret. This change makes the handler accept the correct value.

---

### Phase 3: Fix Webhook Handler Bugs

**File:** `includes/class-ac-serial-numbers-webhook.php`

#### 3a. `webhook_order_update_handler()` — Complete Rewrite

| Fix | Description |
|-----|-------------|
| **Encryption** | Serial keys now wrapped with `ac_serial_numbers_encrypt_key()` before database insert |
| **Idempotency** | Checks for existing key (`product_id` + `serial_key` + `order_id`) before insert — prevents duplicates on webhook retry |
| **Date fields** | Uses `current_time('mysql')` instead of non-existent `createdAt`/`updatedAt` fields |
| **Structured logging** | Logs to WooCommerce logger with source `licencebot-webhook-delivery` — records: received, inserted count, skipped count, errors |
| **Return values** | All code paths return proper `WP_REST_Response` with `inserted` and `skipped` counts |
| **Null safety** | Uses null coalescing (`??`) for all optional fields |

#### 3b. `webhook_handler()` — Same Fixes + Missing Returns

| Fix | Description |
|-----|-------------|
| **Missing `return`** | Lines 188, 199, 208 previously created `WP_REST_Response` objects but never returned them — now properly returned |
| **Encryption** | Same as above |
| **Idempotency** | Same as above |
| **Logging** | Same as above |

**Logging Format:**
```
[source] => licencebot-webhook-delivery
message: "Webhook: Received for order #12345 with 2 product entries"
message: "Webhook: Order #12345 — Inserted: 2, Skipped (duplicate): 0"
message: "Webhook: Order #12345 status updated to completed (full delivery)"
```

---

### Phase 4: Uncomment & Fix Reseller Code Path (Fallback Pull)

**File:** `includes/ac-serial-numbers-functions.php`

| Change | Line(s) | Description |
|--------|---------|-------------|
| Uncommented reseller branch | 258-315 | The entire `elseif ( 'reseller' === $source )` block was commented out — now active |
| Added encryption | 277 | Keys encrypted with `ac_serial_numbers_encrypt_key()` before insert |
| Added idempotency | 279-288 | Duplicate key check before insert |
| Added logging | 261-262, 308-313 | Structured logging with source `licencebot-reseller-delivery` |
| Error handling | 309-313 | Logs API response reasons and invalid responses |

**How it works:**
1. When an order transitions to `processing` or `completed`, `maybe_assign_serial_numbers()` fires
2. For each line item, if key source is "TIC Reseller Panel", the plugin calls `ac_serial_numbers_get_serial_numbers()`
3. This sends `POST {api_endpoint}/shop/new-order` with order + customer data
4. LicenceBot returns serial keys → plugin inserts them into `wp_serial_numbers` table
5. This acts as a **fallback** if the webhook from LicenceBot fails or is delayed

**Note:** The primary delivery path is the webhook push from LicenceBot. This reseller path is a safety net.

---

### Phase 5: Add Settings Field for Manual Auth Secret

**File:** `includes/admin/settings/ac-serial-numbers-settings-general.php`

| Change | Line(s) | Description |
|--------|---------|-------------|
| Added constant definitions | 4-15 | Defines `AC_SERIAL_OPT_AUTH_SECRET`, `AC_SERIAL_OPT_STORE_ID`, etc. for settings file context |
| Added auth secret variable | 48 | `$auth_secret = get_option( AC_SERIAL_OPT_AUTH_SECRET )` |
| Added webhook URL variable | 49 | `$webhook_url = rest_url( 'ac-serial-numbers/v1/order/update/' )` |
| Added settings field | 280-289 | "LicenceBot Webhook Auth Secret" — text input, read-only when auto-configured, editable when empty |

**Settings Field Behavior:**
- If `license_auth_secret` was received during registration → field is read-only, shows the value
- If empty → field is editable, admin can paste the `auth_secret` from LicenceBot Dashboard → API Keys

---

### Phase 6: Update Connection Status UI

**File:** `includes/admin/settings/ac-serial-numbers-settings-general.php`

| Change | Line(s) | Description |
|--------|---------|-------------|
| Auth secret status | 57-66 | Shows "✅ Webhook Auth Secret: Configured" or "⚠️ Webhook Auth Secret: Missing" |
| Webhook URL display | 66 | Shows the registered webhook URL for verification |

**Connection Status Box (when connected):**
```
✅ Connected to LicenceBot
Store ID: <uuid>
Connected on: May 19, 2026 12:00 PM
✅ Webhook Auth Secret: Configured (delivery webhooks will be authenticated)
Webhook URL: https://example.com/wp-json/ac-serial-numbers/v1/order/update/
[Disconnect]
```

---

## 6. Files Modified

| File | Changes | Risk |
|------|---------|------|
| `ac-serial-numbers.php` | Added constant, fixed webhook URL, save `license_auth_secret` in 2 places | 🟢 LOW |
| `includes/class-ac-serial-numbers-webhook.php` | Fixed auth validation, encryption, idempotency, logging, return values, removed duplicate code block | 🟢 LOW |
| `includes/ac-serial-numbers-functions.php` | Uncommented reseller path, added encryption + idempotency + logging | 🟡 MEDIUM |
| `includes/admin/settings/ac-serial-numbers-settings-general.php` | Added auth secret settings field, updated connection status UI, added constant definitions | 🟢 LOW |

---

## 7. Data Flow — Complete End-to-End

```
┌─────────────────────────────────────────────────────────────────────┐
│  1. CUSTOMER CHECKS OUT ON WOOCOMMERCE                              │
│     Payment completes → order status: processing                    │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  2. PLUGIN SENDS ORDER TO LICENCEBOT                                │
│     Hook: woocommerce_order_status_changed                          │
│     Class: AC_Serial_Numbers_Cart_Tracking                          │
│     POST {api_endpoint}/store/incoming/orders                       │
│     Headers: api-key, Content-Type: application/json                │
│     Body: invoice_no, customer, items (with _ac_remote_product_id)  │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  3. LICENCEBOT PROCESSES ORDER (Server-Side Pipeline)               │
│     a. Verify HMAC signature (X-WC-Webhook-Signature)               │
│     b. Resolve Store ID from woo_stores                             │
│     c. claim_license_order_delivery(store_id, woo_order_id)         │
│        → Idempotent: returns already_delivered if duplicate         │
│     d. Map line items → license_products                            │
│     e. FIFO claim from license_serial_numbers                       │
│     f. Email buyer via sendViaPlatformSmtp                          │
│     g. Push keys to WordPress via callStoreWebhook                  │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  4. LICENCEBOT PUSHES KEYS TO WORDPRESS WEBHOOK                     │
│     POST {store_url}/wp-json/ac-serial-numbers/v1/order/update/     │
│     Headers: X-Webhook-Secret: license_api_keys.auth_secret         │
│     Body: { invoice_no, orderData: [{ cp_id, serialKeys: [...] }] } │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  5. WORDPRESS WEBHOOK HANDLER RECEIVES KEYS                         │
│     a. Validate X-Webhook-Secret against _ac_serial_auth_secret     │
│     b. For each key: encrypt, check duplicate, insert into DB       │
│     c. Log to WooCommerce logger (source: licencebot-webhook-delivery) │
│     d. Update order status to "completed"                           │
│     e. Return 200 with { inserted, skipped }                        │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  6. SERIAL NUMBERS APPEAR IN ORDER ADMIN                            │
│     WooCommerce → Orders → [Order] → Serial Numbers metabox         │
│     Keys are decrypted via ac_serial_numbers_decrypt_key()          │
│     Displayed with activation guide and support email               │
└─────────────────────────────────────────────────────────────────────┘
```

### Fallback Path (If Webhook Fails)

```
┌─────────────────────────────────────────────────────────────────────┐
│  Order status → processing/completed                                │
│     Hook: woocommerce_order_status_processing / _completed          │
│     Handler: AC_Serial_Numbers_Handler::maybe_assign_serial_numbers │
│     Function: ac_serial_numbers_order_connect_serial_numbers()      │
│                                                                      │
│     For each line item with source = "reseller":                     │
│       1. Call ac_serial_numbers_get_serial_numbers()                │
│       2. POST {api_endpoint}/shop/new-order                         │
│       3. LicenceBot returns serialKeys                              │
│       4. Encrypt, deduplicate, insert into wp_serial_numbers        │
│       5. Log to WooCommerce logger (source: licencebot-reseller-delivery) │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 8. Database Tables Involved

### WordPress Tables

| Table | Purpose |
|-------|---------|
| `wp_options` | Stores: `ac_serial_numbers_api_endpoint`, `ac_serial_numbers_api_key`, `_ac_serial_auth_secret`, `_ac_serial_store_id`, `_ac_serial_store_token`, `_ac_serial_registered_at`, `_ac_serial_last_error`, `_ac_serial_numbers_webhook_secret`, `wcsn_pkey` (encryption key) |
| `wp_serial_numbers` | Stores all serial keys with columns: `id`, `serial_key` (encrypted), `product_id`, `activation_limit`, `activation_count`, `order_id`, `vendor_id`, `status`, `validity`, `expire_date`, `source`, `created_date`, `order_date` |
| `wp_serial_numbers_activations` | Stores activation records for software licensing |
| `wp_woocommerce_api_keys` | WooCommerce REST API keys (auto-created by plugin) |

### LicenceBot Tables (Server-Side, Reference)

| Table | Purpose |
|-------|---------|
| `woo_stores` | Store registration data, `webhook_secret`, `consumer_key`, `consumer_secret` |
| `license_api_keys` | Per-store API keys, `auth_secret` (used for X-Webhook-Secret) |
| `license_serial_numbers` | Serial key inventory, `status`, `sold_at`, `order_id`, `customer_email` |
| `license_products` | Product catalog, `stock_count`, `sold_count`, `woo_product_id` mapping |
| `store_orders` | Order tracking, `delivered_keys`, `status`, `fulfilled_at` |
| `inbound_webhook_logs` | Webhook audit trail, `signature_valid`, `step`, `status` |
| `organizations` | Org-level config, `helper_install_token`, `api_endpoint`, `api_key` |

---

## 9. Security Model

### Authentication Flow

| Direction | Method | Secret Source |
|-----------|--------|---------------|
| Woo → LicenceBot (order data) | `api-key` header | `ac_serial_numbers_api_key` (from registration response) |
| LicenceBot → Woo (webhook) | `X-Webhook-Secret` header | `license_api_keys.auth_secret` (server-side) |
| Plugin → LicenceBot (registration) | `org_token` in body | Baked into plugin at download time |
| Plugin → LicenceBot (setup token) | One-time `lb_token` | Generated by LicenceBot, 15-min TTL |

### Data Protection

- **Serial keys** are encrypted in the database using AES-256-CBC with a site-specific key (`wcsn_pkey`)
- **Encryption key** is generated from: `sha256(time + home_url + random_salt)` — unique per site
- **Webhook auth** uses exact string comparison (`===`) — no timing attacks
- **Nonce verification** on all admin actions and AJAX requests

---

## 10. Testing Checklist

### Pre-Deployment
- [ ] Verify `license_auth_secret` is returned in registration response from LicenceBot
- [ ] Verify each product has: "Sell Serial Numbers" checked, "Serial Key Source" = "TIC Reseller Panel", "Assign Product" mapped

### Post-Deployment
- [ ] Re-register store: Disconnect → Connect to LicenceBot
- [ ] Verify `_ac_serial_auth_secret` is populated in `wp_options`
- [ ] Verify webhook URL in settings matches: `/wp-json/ac-serial-numbers/v1/order/update/`
- [ ] Place test order → payment complete
- [ ] Check WooCommerce logs for `licencebot-webhook-delivery-*`:
  - [ ] "Webhook: Received for order #..."
  - [ ] "Webhook: Order #... — Inserted: X, Skipped: Y"
  - [ ] "Webhook: Order #... status updated to completed"
- [ ] Verify serial numbers appear in order admin → Serial Numbers metabox
- [ ] Verify keys are readable (not garbled) — decryption working
- [ ] Test idempotency: trigger order status change again → no duplicate keys
- [ ] Test fallback: temporarily block webhook → verify reseller path pulls keys

---

## 11. Troubleshooting

### Webhook Returns 401
- Check `_ac_serial_auth_secret` in `wp_options` — should match `license_api_keys.auth_secret` from LicenceBot
- If empty, re-register the store or manually paste the value in settings
- Check WooCommerce logs for failed auth attempts

### Keys Not Appearing
- Verify product has "Serial Key Source" set to "TIC Reseller Panel"
- Verify product is mapped to a LicenceBot product ("Assign Product" dropdown)
- Check WooCommerce logs for `licencebot-webhook-delivery-*` and `licencebot-reseller-delivery-*`
- Verify `ac_serial_numbers_api_endpoint` and `ac_serial_numbers_api_key` are set

### Keys Appear Garbled
- Encryption is working correctly — keys are stored encrypted
- Admin UI should decrypt them automatically via `ac_serial_numbers_decrypt_key()`
- If garbled, check that `wcsn_pkey` option exists and hasn't changed

### Duplicate Keys
- Idempotency check prevents duplicates on both webhook and reseller paths
- If duplicates appear, check that the duplicate check query is using the correct encrypted key comparison

---

## 12. Future Considerations

### Potential Enhancements
1. **Webhook retry handling** — If LicenceBot retries (timeout), the idempotency check handles it, but adding a retry counter in logs would help debugging
2. **Partial delivery tracking** — When `orderData` has fewer keys than ordered quantity, log the discrepancy
3. **Health check endpoint** — Add a GET endpoint that LicenceBot can ping to verify the WordPress site is reachable before sending webhooks
4. **Batch webhook processing** — If LicenceBot sends multiple orders in one webhook, handle them as a batch
5. **Webhook signature versioning** — If LicenceBot changes the auth mechanism, add version detection

### Monitoring
- WooCommerce logs are the primary observability tool
- Log sources to monitor:
  - `licencebot-webhook-delivery` — inbound webhook processing
  - `licencebot-reseller-delivery` — fallback pull mechanism
  - `wcsn-webhook-order-update` — legacy webhook (still active)
  - `wc-serial-numbers` — general plugin operations

---

## 13. Version History

| Version | Date | Changes |
|---------|------|---------|
| 3.1.7 | May 19, 2026 | Fixed auto-delivery: auth secret mismatch, encryption, idempotency, webhook URL, reseller fallback |
| 3.1.2 | Prior | Added `ac_serial_numbers_get_license_counts()`, `ac_serial_numbers_sync_order_serials()`, `ac_serial_numbers_clear_license_counts_cache()` |
| 2.0.7 | Prior | Added LicenceBot auto-connect: registration, setup token exchange, admin notices, connection status UI |
| 1.2.0 | Prior | Major refactor: HPOS compatibility, encryption, REST API, WooCommerce integration |
| 1.0.0 | Original | Initial plugin release |
