# WooCommerce REST API Connector

![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759B?logo=wordpress&logoColor=white)
![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?logo=woocommerce&logoColor=white)
![Tests 34 passing](https://img.shields.io/badge/tests-34%20passing-2E7D32)

WooCommerce REST API Connector is a technical sample WordPress plugin that demonstrates how a WooCommerce store can synchronize completed orders to a configurable external REST API without blocking the checkout or order-status request.

The external API is fictional. The project focuses on production-minded WooCommerce patterns: asynchronous processing, deterministic idempotency, bounded retries, safe logging, settings sanitization, and testable integration logic.

## Requirements

- PHP 8.0+
- WordPress 6.5+
- WooCommerce 8.0+
- Composer for development tooling

The plugin relies on WooCommerce's bundled Action Scheduler and does not include its own copy.

## Architecture

- `woocommerce-rest-api-connector.php` bootstraps the plugin and performs WooCommerce dependency checks.
- `includes/class-settings.php` adds a WooCommerce settings tab for API configuration and connection testing.
- `includes/class-order-handler.php` listens for completed orders and schedules asynchronous work.
- `includes/class-sync-service.php` coordinates payload generation, API submission, state tracking, retries, and idempotency.
- `includes/class-order-payload.php` maps WooCommerce orders into a normalized API payload.
- `includes/class-api-client.php` sends requests through the WordPress safe HTTP API.
- `includes/class-admin-order-actions.php` adds failed-order manual retry.
- `includes/class-logger.php` writes sanitized operational logs through WooCommerce logging.

## Synchronization Flow

1. A WooCommerce order transitions to `completed`.
2. The plugin confirms synchronization is enabled.
3. It avoids duplicate scheduling if the order is already pending, processing, or synced.
4. It enqueues `wcrac_sync_order` through Action Scheduler.
5. The background worker loads the order, builds the payload, sends the API request, and records the result in private order metadata.

The order-status hook schedules work only. It does not send the external HTTP request inline.

## Configuration

Settings live under WooCommerce settings in the `REST API Connector` tab:

- Enable synchronization
- API base URL
- API token
- Request timeout
- Test Connection

API base URLs must use HTTPS by default. Plain HTTP is rejected unless the development-only `WCRAC_ALLOW_INSECURE_HTTP` constant is explicitly defined as true. Do not enable that override in production.

The Test Connection action calls:

```http
GET /api/v1/health
```

For this sample, the API token is stored in WordPress options. A production deployment may choose to inject secrets from environment variables, server configuration, or a managed secret store depending on hosting requirements. Leaving the token field blank preserves the stored token, and the plugin never needs to display the token value in HTML.

## Fictional API Contract

Order synchronization sends:

```http
POST /api/v1/orders
Authorization: Bearer <api-token>
Content-Type: application/json
Idempotency-Key: <stable-key>
```

Example payload:

```json
{
  "order_id": 1234,
  "order_number": "1234",
  "status": "completed",
  "currency": "USD",
  "total": "149.95",
  "customer": {
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Smith"
  },
  "billing": {},
  "shipping": {},
  "items": [
    {
      "product_id": 42,
      "variation_id": 0,
      "sku": "ABC-100",
      "name": "Example Product",
      "quantity": 2,
      "total": "99.90"
    }
  ]
}
```

Any HTTP 2xx response is considered successful. An empty successful response body is valid. If a non-empty 2xx response body is present, it must be valid JSON; malformed JSON is treated as an API contract failure and is not retried automatically. External ID extraction is optional and uses an `id` field when present.

## Retry Strategy

The plugin allows three total automated HTTP attempts per synchronization cycle:

- Attempt 1: immediate async execution
- Attempt 2: 5 minutes after a retryable failure from attempt 1
- Attempt 3: 15 minutes after a retryable failure from attempt 2

If attempt 3 fails, the order is marked `failed` and requires manual retry.

Retryable failures:

- WordPress HTTP/network errors
- connection timeouts
- HTTP 408
- HTTP 429
- HTTP 5xx
- unexpected worker exceptions after the order has loaded

Ordinary HTTP 4xx responses are not automatically retried. HTTP 409 is not retried unless a future external API contract defines a safe reason to do so.

## Manual Retry

Manual retry is available only for orders with failed synchronization status. It does not run an HTTP request in the admin request. Instead, it enqueues the normal asynchronous sync action, resets the automated attempt count to `0`, clears the previous error, and preserves the existing idempotency key.

The new manual cycle can again perform at most three automated HTTP attempts.

## Idempotency

Each order gets a deterministic idempotency key generated from:

- the site URL from `home_url()`
- the WooCommerce order ID

The generated key is stored in private order metadata and reused for automated retries and manual retry cycles. Server-side duplicate prevention ultimately depends on the external API honoring the `Idempotency-Key` header.

## Order Metadata

The plugin stores private order metadata:

- `_wcrac_sync_status`
- `_wcrac_sync_attempt_count`
- `_wcrac_last_sync_at`
- `_wcrac_last_error`
- `_wcrac_external_id`
- `_wcrac_idempotency_key`

It does not store full API response bodies or unnecessary customer data in metadata.

## Logging

Logs are written through WooCommerce logging with source `woocommerce-rest-api-connector`.

Logs include operational fields such as order ID, attempt count, HTTP status, and sanitized errors. They do not include API tokens, authorization headers, full request payloads, or complete remote response bodies.

## Security

The plugin follows WordPress and WooCommerce conventions:

- WooCommerce dependency checks avoid fatal errors when WooCommerce is unavailable.
- Settings are sanitized and constrained.
- API URLs require HTTPS by default.
- Configured outbound URLs are rejected for localhost, loopback, private, link-local, and obvious metadata endpoints.
- Outbound API calls use `wp_safe_remote_request()` when available.
- Admin actions check capabilities.
- The Test Connection action uses a nonce.
- Admin output is escaped.
- API credentials are not exposed in logs, notices, order notes, or URLs.

The SSRF protections are intentionally modest and appropriate for a technical sample. A production system may need organization-specific allowlists, egress controls, or secret-management policies.

## Testing

Install development dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

Run PHP syntax checks manually:

```bash
php -l woocommerce-rest-api-connector.php
php -l includes/class-sync-service.php
```

The unit tests cover idempotency generation, retry classification, retry delays, retry scheduling failure, maximum attempt limits, manual retry rules, payload mapping, API response handling, URL validation, and settings sanitization. They do not require a live WooCommerce store or a real SaaS API.

## Extension Points

To adapt this sample to a real SaaS API:

- Update `Api_Client` endpoints and response parsing.
- Adjust `Order_Payload` to match the external schema.
- Replace or extend idempotency assumptions based on the API's documented behavior.
- Add integration tests around the real API contract using mocked HTTP responses.
- Consider injecting secrets from infrastructure instead of storing them in WordPress options.

## Limitations

- The external API is fictional.
- Manual retry is intentionally simple and uses WooCommerce's order action dropdown.
- The basic test suite uses shims and mocks rather than a full WordPress/WooCommerce integration environment.
- No frontend functionality is included because the sample focuses on admin configuration and background synchronization.
- Remote duplicate prevention relies on the external API honoring `Idempotency-Key`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
