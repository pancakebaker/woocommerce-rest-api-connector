=== WooCommerce REST API Connector ===
Contributors: technical-sample
Tags: woocommerce, rest api, orders, action scheduler
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Demonstrates asynchronous synchronization of completed WooCommerce orders to a fictional external REST API.

== Description ==

WooCommerce REST API Connector is a technical sample plugin for WordPress and WooCommerce. It demonstrates how completed WooCommerce orders can be normalized and sent to an external REST API using WooCommerce's bundled Action Scheduler and the WordPress safe HTTP API.

The fictional external API accepts completed order data at POST /api/v1/orders and exposes a GET /api/v1/health endpoint for connection testing.

== Features ==

* WooCommerce settings tab for API base URL, token, enablement, and timeout.
* HTTPS API URLs by default, with a development-only insecure HTTP override.
* Test Connection action.
* Completed-order listener.
* Asynchronous synchronization with Action Scheduler.
* Three total automated HTTP attempts with 5-minute and 15-minute retry delays.
* Retry handling for network errors, HTTP 408, HTTP 429, HTTP 5xx, and unexpected worker errors.
* Stable idempotency key per site and order, preserved across retries and manual retry cycles.
* Private order metadata for sync state.
* WooCommerce logging without exposing tokens, full payloads, or full remote response bodies.
* Failed-order manual retry via WooCommerce admin order actions.
* Focused PHPUnit tests.

== Installation ==

1. Upload the plugin folder to wp-content/plugins.
2. Activate WooCommerce.
3. Activate WooCommerce REST API Connector.
4. Configure the plugin under WooCommerce > Settings > REST API Connector.

== Frequently Asked Questions ==

= Does this connect to a real SaaS API? =

No. The API contract is fictional and intended for technical demonstration.

= Does the plugin bundle Action Scheduler? =

No. It relies on WooCommerce's bundled Action Scheduler.

= Can I use HTTP instead of HTTPS? =

HTTPS is required by default. Plain HTTP is only allowed when the development-only WCRAC_ALLOW_INSECURE_HTTP constant is explicitly enabled.

= What happens on manual retry? =

Manual retry is only available for failed synchronization. It starts a new controlled attempt cycle, resets the attempt count, preserves the idempotency key, and schedules async work.

= Are API tokens logged? =

No. Logs avoid API tokens, Authorization headers, full request payloads, and full remote response bodies.

== Changelog ==

= 0.1.0 =
* Initial technical sample.