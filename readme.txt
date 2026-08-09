=== LicenceBot Helper Plugin ===
Contributors: ticlimited
Tags: licencebot, license key, serial number, woocommerce, live chat, cart recovery, sales counter, visitor alerts
Requires at least: 6.0
Tested up to: 6.8.0
Requires PHP: 7.4
Stable tag: 3.7.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 6.0.0
WC tested up to: 10.7.0

Auto-connects your WooCommerce store with LicenceBot for live chat, cart recovery, sales counters, visitor alerts, license key tools, and secure serial number / license key delivery.

== Description ==

**LicenceBot Helper Plugin** bridges your WooCommerce store with the [LicenceBot](https://licencebot.com) dashboard. After a one-click connection it unlocks a full suite of growth and licensing tools — while keeping every request inside the WordPress HTTP API with transient caching and graceful fallbacks.

The plugin is a rebrand of the classic **Serial Numbers** plugin for WooCommerce, extended with the LicenceBot auto-connect layer.

= LicenceBot auto-connect =

* One-click connect with an org token baked in at download, or exchanged via a setup-token flow (no API keys to copy).
* Registers a store ID, store token and a secure webhook secret with LicenceBot.
* Pushes order updates to LicenceBot over the REST webhook.
* Pulls the latest widget configuration from the LicenceBot dashboard (cached via transients) and auto-registers enabled features.
* Manual API Endpoint + API Key entry still works as a fallback.

= Growth & social-proof widgets =

* Live chat widget
* Cart abandonment recovery
* Sales counters and visitor alerts auto-injected on WooCommerce product pages (above/below add-to-cart, above price, below title)
* Sales popups, sales notifications and sales countdowns
* Email-gated coupon box
* Newsletter signup forms
* Contact form and floating contact button
* Support tickets

= Shortcodes =

| Shortcode | Purpose |
| --- | --- |
| `[licencebot_sales_notification]` | Floating "X just bought …" toaster |
| `[licencebot_sales_counter]` | Static "N sales today" counter |
| `[licencebot_visitor_alerts]` | "N visitors viewing this page" badge |
| `[licencebot_coupon_box]` | Email-gated coupon claim form |
| `[licencebot_newsletter_signup]` | Inline newsletter signup form |
| `[licencebot_sales_popup]` | Centered social-proof popup |
| `[licencebot_popup]` | Generic configurable popup |
| `[licencebot_contact_form]` | Contact form |
| `[licencebot_support_tickets]` | Support ticket form |
| `[licencebot_check_key]` | Check Windows / Office key status |
| `[licencebot_get_cid]` | Generate confirmation ID from an installation ID |
| `[licencebot_redeem]` | Microsoft key redeem helper |

The three key-tool shortcodes submit through the WordPress REST proxy (`licencebot/v1/key-tools`), so no LicenceBot credentials ever reach the browser.

= Serial number & license key management =

* Sell serial numbers, license keys, activation keys, PINs and gift codes on WooCommerce
* Automatic license key delivery on completed orders
* Activation limit, validity (days) and expiry date per key
* Serial / license keys encrypted at rest
* "See Your License Key" button on order-received and view-order pages
* Revoke and reuse keys from refunded, cancelled and failed orders
* Low-stock notifications and a dedicated stock manager
* Volume license import, activations list and order tracking screen
* Software activation / validation API
* View log that records who viewed a key, from which IP and when

= Compatibility =

* WooCommerce High-Performance Order Storage (HPOS) compatible
* WordPress tested up to 6.8, WooCommerce tested up to 10.7
* Auto-update checker backed by the LicenceBot update server

**Requirements:** WooCommerce 6.0+ and PHP 7.4+.

= Connecting to LicenceBot =

1. Download this plugin from your LicenceBot dashboard (the org token is baked in) or install it from the plugin screen.
2. Open **Serial Keys** → **Settings** in the WordPress admin.
3. Click **Connect** (or **Re-connect**). LicenceBot stores your Store ID, webhook secret and API credentials automatically.
4. Toggle the helper features you want from the LicenceBot dashboard; they sync back to your store automatically.

If you installed the plugin manually, enter your API Endpoint and API Key under **Settings**.

== Installation ==

1. Upload the `licencebot-helper` folder to `/wp-content/plugins/`, or install the plugin from your LicenceBot dashboard.
2. Activate the plugin through the Plugins screen in WordPress (WooCommerce must be active).
3. Open **Serial Keys** in the admin menu and follow the connection prompts.

== Frequently Asked Questions ==

= Is my data affected by the rename to licencebot-helper? =

No. Serial keys, activations, settings and the LicenceBot connection are all preserved. The database tables (`serial_numbers`, `serial_numbers_activations`, `serial_view_log`) and stored options are reused as-is.

= Do I need to re-register the webhook after updating? =

No. The webhook endpoint used by LicenceBot remains unchanged, so existing connections keep working.

= Can I still use the serial number / license key features? =

Yes. All serial number, license key and activation features remain available under the **Serial Keys** admin menu.

= How do I use the shortcodes? =

Add them to any page or post, e.g. `[licencebot_sales_counter]`. Each shortcode can be enabled or disabled from the plugin's **Shortcodes** settings tab.

== Changelog ==

= 3.7.5 =
* Rebranded as LicenceBot Helper Plugin (plugin folder renamed to licencebot-helper).
* Updated plugin update links to the new plugin path.

= 3.7.4 =
* Bug fixes and maintenance.

= 3.7.3 =
* Bug fixes and maintenance.
