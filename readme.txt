=== iyzico Kolai for WooCommerce ===
Contributors: iyzico
Tags: woocommerce, iyzico, refund, rest-api, payment
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 5.0
WC tested up to: 9.8

Connects WooCommerce to the Kolai API (products, orders, shipping, contracts, reviews) and propagates iyzico refunds and cancellations automatically.

== Description ==

iyzico Kolai for WooCommerce exposes a signed REST API that lets the Kolai platform read your catalogue and create/manage orders in WooCommerce, and it forwards WooCommerce refund/cancel actions to iyzico automatically.

= Features =

* **REST API** under `/wp-json/kolai/v1` covering products, shipping options, orders, contracts (distance-sales / pre-information texts) and product reviews.
* **Signed authentication** — HMAC-SHA256 request signing with per-endpoint scope mapping.
* **iyzico refund / cancel integration** — refunds run through the native WooCommerce "Refund" button via a hidden `kolai-app` gateway; order cancellations are forwarded to iyzico on the `cancelled` status transition. The iyzipay-php SDK is bundled.
* **Structured logging** — an optional, DB-backed log subsystem with an admin page (level, retention, live table with filtering) and a daily cleanup cron.
* **Admin settings** — Kolai API key/secret, clarification-text page, seller/contract details, and iyzico refund API credentials (key/secret/environment).
* **HPOS & Blocks ready** — declares compatibility with WooCommerce High-Performance Order Storage and the Cart/Checkout Blocks.

= REST API overview =

All endpoints return a consistent JSON envelope with `status`, `systemTime`, `errorCode`/`errorMessage` and a `data` payload. Pagination metadata is returned inside the response body. See the project documentation for full request/response examples and error codes.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload the `iyzipay-kolai-for-woocommerce` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress **Plugins** screen.
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Go to **WP Admin → Kolai** and enter your Kolai API Key / Secret Key, select the clarification-text page, and (for refunds/cancellations) enter your iyzico API Key / Secret Key and environment (Sandbox or Production).

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and active; the plugin shows an admin notice and stays inactive otherwise.

= Is the iyzico gateway shown at checkout? =

No. The bundled `kolai-app` gateway is hidden from checkout — orders are created through the Kolai REST API. The gateway exists only so the native WooCommerce "Refund" button can forward refunds to iyzico.

= Is it compatible with High-Performance Order Storage (HPOS)? =

Yes. The plugin uses the WooCommerce CRUD APIs and declares HPOS compatibility, as well as compatibility with the Cart/Checkout Blocks.

= Where are the logs stored? =

In a dedicated database table. Logging is off by default and can be enabled, with a configurable level and retention period, from **WP Admin → Kolai → Logs**.

== Changelog ==

= 1.5.0 =
* iyzico refund / cancel integration: WooCommerce refunds and cancellations are forwarded to iyzico automatically. Refunds run through the native "Refund" button on the `kolai-app` gateway (`process_refund`); the amount is distributed across the stored `itemTransactions`. Cancellations run on the `woocommerce_order_status_cancelled` hook via the stored `paymentId`.
* Bundled the iyzipay-php SDK under `includes/vendor/iyzipay-php/`.
* Added iyzico API Key / Secret Key / Environment (sandbox–production) settings.
* Declared WooCommerce HPOS and Cart/Checkout Blocks compatibility; added translation template and tr_TR / nl_NL translations.

= 1.3.0 =
* `PATCH /orders/{orderId}` now stores optional `paymentId` and `itemTransactions` as order meta (`kolai_payment_id`, `kolai_item_transactions`).

= 1.2.0 =
* Reviews / ratings: added `GET /products/{id}/reviews` (pagination + status/rating/modified_after filters) and `GET /reviews/{id}`. Returns approved reviews by default. New scopes `RETRIEVE_REVIEWS`, `RETRIEVE_REVIEW`; new error codes `6000-6004`. PII fields (author email/IP/agent) are intentionally omitted from responses.

= 1.1.1 =
* HTTP/2 fix: `/products` pagination metadata moved from custom `X-Kolai-*` headers into the response body (`pagination` field) to avoid proxy/HTTP-2 protocol errors.
* DB migration: the logs table is created automatically when the plugin version changes (including FTP/Git file updates) — no deactivate/reactivate required.
* Logger guard: `Kolai_Logger::is_enabled()` returns false when the table is missing; write attempts are skipped silently.

= 1.1.0 =
* Logging: added the DB-backed structured log subsystem, admin page, level/retention settings and a daily cleanup cron.
* Product list optimisation: `/products` is now always paginated (`page`, `per_page`, max 200), with lighter list payloads, bulk cache priming/batch term fetch to remove N+1 queries, and `?ids=` / `?modified_after=` filters. Added a `MAX_VARIATIONS_PER_PRODUCT = 100` cap. Added structured log points to the auth, request and service layers.

= 1.0.3 =
* Previous stable release.

== Upgrade Notice ==

= 1.5.0 =
Adds automatic iyzico refund/cancel propagation, the bundled iyzipay-php SDK, HPOS/Blocks compatibility and translations. Enter your iyzico API credentials under WP Admin → Kolai after upgrading.
