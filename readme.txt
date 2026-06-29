=== iyzico Kolai for WooCommerce ===
Contributors: iyzico
Tags: woocommerce, iyzico, refund, rest-api, payment
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 5.0
WC tested up to: 10.9.1

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

== External services ==

This plugin connects to the **iyzico payment API** to forward refunds and cancellations for orders created through the Kolai platform. No catalogue, order or customer data is sent to iyzico during normal browsing — calls happen only when a merchant performs a refund or a cancellation.

* **When a refund is issued** (via the WooCommerce "Refund" button on a `kolai-app` order), the plugin sends the iyzico `paymentTransactionId`, the refund amount, the currency and a correlation id to `https://api.iyzipay.com` (or `https://sandbox-api.iyzipay.com` in sandbox mode).
* **When an order is cancelled**, the plugin sends the stored iyzico `paymentId` and a correlation id to the same endpoint.
* Requests are authenticated with the iyzico API key/secret you configure under **WP Admin → Kolai**. The endpoint (sandbox vs. production) is selected by the iyzico environment setting.

The Kolai platform also calls **into** this site over the signed `/wp-json/kolai/v1` REST API to read products and create/manage orders; that traffic is inbound and authenticated with the Kolai API key/secret.

iyzico is a third-party service. Its use is subject to iyzico's terms and privacy policy:

* Terms of use: https://www.iyzico.com/en/terms-of-use
* Privacy policy: https://www.iyzico.com/en/privacy-policy

== Changelog ==

= 1.7.0 =
Security & reliability hardening pass (post-review remediation).

* Refunds: multi-transaction refunds are now atomic and recoverable — an order-scoped lock prevents concurrent double-refunds, each attempt carries a unique idempotency id, a durable operation ledger is kept, a failed local persist after a successful remote refund stops and is flagged for reconciliation, and a partial remote success is preserved as a local WooCommerce refund record instead of being discarded.
* Authentication: requests are rejected unless both the API key and secret are configured (fail-closed), the client id is compared in constant time, and replays are blocked via single-use request salts plus an optional signed `timestamp` (see AUTH.md).
* Orders: a successful payment now goes through WooCommerce's `payment_complete()` lifecycle (transaction id, `date_paid`, `woocommerce_payment_complete`); failed order creation no longer leaves orphan order shells; the discount invariant is verified.
* Product pricing: `salePrice` is emitted **only when the sale is actually active** (`is_on_sale()`), so scheduled or expired sale prices are no longer advertised as live; variation truncation is surfaced as explicit `variationsTruncated` / `variationsMax` metadata; the explicit `?ids=` list is bounded.
* Shipping: shipment quotes and order shipping honour real product quantities (and variations); `/shipment-options` accepts an optional per-product `quantity` (backward compatible).
* Privacy & logging: request logging redacts personal/contact/tax/payment fields and caps the stored payload size; direct `error_log()` calls moved to WooCommerce's structured logger.
* Admin: accessibility fixes (labels, ARIA, live status), responsive/RTL CSS, and masked secret fields (leave blank to keep the existing value).
* Compatibility & metadata: declares Product Block Editor compatibility; tested up to WooCommerce 10.9.x / WordPress 6.9; localized remaining admin JS strings; synced translation catalogs.

= 1.6.0 =
* Tax: product endpoints now return tax-inclusive `price`/`salePrice` with an `includedTax` / `taxPrice` / `taxPercentage` breakdown that honours the store's tax settings; order `discountAmount` is applied as a tax-aware negative fee so tax-inclusive (KDV) totals stay consistent.

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

= 1.7.0 =
Security & reliability hardening: atomic/recoverable refunds, fail-closed authentication with replay protection, payment_complete() lifecycle, quantity-aware shipping, active-only sale prices and request-log PII redaction. After upgrading, ensure BOTH the Kolai API key and secret are set (requests are now rejected if the secret is empty).

= 1.6.0 =
Adds tax-inclusive product pricing fields (includedTax / taxPrice / taxPercentage) and tax-aware order discounts.

= 1.5.0 =
Adds automatic iyzico refund/cancel propagation, the bundled iyzipay-php SDK, HPOS/Blocks compatibility and translations. Enter your iyzico API credentials under WP Admin → Kolai after upgrading.
