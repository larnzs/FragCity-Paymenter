# FragCitySupportAPI

A dedicated, private support and accounting bridge between the FragCity website and Paymenter.

**Compatibility target:** Paymenter v1.5.9  
**Extension version:** 1.0.0  
**Extension path:** `extensions/Others/FragCitySupportAPI`

This extension is separate from both:

- `extensions/Others/FragCityAPI` — existing account/server bridge; unchanged.
- `extensions/Servers/FragCityCS2` — CS2 provisioning extension; unchanged.

It does not patch Paymenter Core or create a second ticket system.

## Ownership model

Paymenter remains the source of truth for support:

- Tickets and their IDs
- Ticket subject, department, priority and status
- Customer and staff messages
- Ticket-to-service association
- Message attachments
- Staff assignment and all admin-side changes

The extension writes through Paymenter's native Eloquent models (`Ticket`, `TicketMessage`, and `TicketMessageAttachment`). Native observers and events therefore remain in the normal application path.

FragCity remains the customer-facing UI. The browser must call the FragCity backend, never this Paymenter endpoint directly. FragCity's backend signs each request and supplies the customer's SteamID64. The extension resolves SteamID64 through the existing `fragcity_user_links` mapping created by FragCityAPI. A caller cannot select a Paymenter `user_id` or read another customer's tickets by changing an ID.

## Endpoints

All routes use **POST + JSON + HMAC-SHA256**.

| Endpoint | Purpose |
|---|---|
| `/api/v1/fragcity/support/ping` | Verify bridge connectivity |
| `/api/v1/fragcity/support/tickets/list` | List the customer's tickets |
| `/api/v1/fragcity/support/tickets/show` | Read one ticket and its message thread |
| `/api/v1/fragcity/support/tickets/create` | Create a ticket and its first message |
| `/api/v1/fragcity/support/tickets/reply` | Add a customer reply |
| `/api/v1/fragcity/support/tickets/close` | Close an owned ticket |
| `/api/v1/fragcity/support/tickets/reopen` | Reopen an owned ticket |
| `/api/v1/fragcity/support/attachments/download` | Download an attachment belonging to an owned ticket |
| `/api/v1/fragcity/support/accounting/summary` | Read invoice counts and outstanding amounts by currency |
| `/api/v1/fragcity/support/accounting/invoices/list` | List the customer's invoices |
| `/api/v1/fragcity/support/accounting/invoices/show` | Read an invoice, its items and its transactions |
| `/api/v1/fragcity/support/accounting/transactions/list` | List recorded invoice transactions |

### Ticket creation example

```json
{
  "steamid64": "7656119XXXXXXXXXX",
  "subject": "My server is not starting",
  "department": "Support",
  "priority": "high",
  "service_id": 123,
  "message": "Please check my server. It remains offline after restart."
}
```

The supported priorities match Paymenter's customer ticket form: `low`, `medium`, and `high`.

The `department` must match one of Paymenter's configured `ticket_departments` values when departments are configured. When a `service_id` is supplied, the extension verifies that the service belongs to the resolved Paymenter user.

### Reply example

```json
{
  "steamid64": "7656119XXXXXXXXXX",
  "ticket_id": 456,
  "message": "I have added the requested information."
}
```

A customer must explicitly reopen a closed ticket before replying.

### Attachments

Attachments are optional JSON entries on ticket creation and replies:

```json
{
  "steamid64": "7656119XXXXXXXXXX",
  "ticket_id": 456,
  "message": "Screenshot attached.",
  "attachments": [
    {
      "filename": "server-console.png",
      "content_base64": "iVBORw0KGgoAAAANSUhEUg..."
    }
  ]
}
```

Each file is limited to 10 MiB, a request can contain up to five files, and total decoded attachment size is limited to 20 MiB. The extension detects MIME type from the decoded bytes and generates the storage filename itself. Responses expose attachment UUIDs, not filesystem paths. Download requests must be signed and are checked against ticket ownership.

Set PHP's request-body limit high enough for base64 overhead (at least `post_max_size=30M`). Keep HTTPS enabled.

## Accounting boundary

Accounting endpoints are **read-only**. They expose only the authenticated customer's Paymenter invoices, invoice line items and recorded invoice transactions. Amounts are returned as decimal strings with the invoice's original `currency_code`; the extension does not perform currency conversion.

This is not the FragCity wallet API:

- Paymenter credits are not exposed as the FragCity wallet balance.
- This extension does not add credits, create payment transactions, mark invoices paid, or initiate gateway payments.
- FragCity remains the canonical wallet and payment-settlement owner. Any combined customer ledger should be assembled by the FragCity backend from its wallet ledger plus the Paymenter invoice records returned here.

## Authentication

Configure a dedicated secret for this extension. Do not reuse the existing FragCityAPI bridge secret.

Headers:

- `X-FragCity-Timestamp`: Unix timestamp in seconds.
- `X-FragCity-Nonce`: 32–64 hexadecimal characters, unique per request.
- `X-FragCity-Signature`: lowercase or uppercase hex HMAC-SHA256.

Signed message:

```text
timestamp + "\n" + nonce + "\n" + raw_json_body
```

The timestamp must be within five minutes. Nonces are atomically stored in Laravel Cache for ten minutes. Replayed nonces are rejected. The extension can optionally restrict requests to exact source IPs.

Generate a dedicated secret on a trusted server:

```bash
openssl rand -hex 32
```

Keep the secret only in Paymenter's encrypted extension settings and FragCity's private runtime configuration. Never expose it to browser JavaScript, HTML, source maps or logs.

## Example response shapes

Ticket list:

```json
{
  "success": true,
  "data": [
    {
      "id": 456,
      "subject": "My server is not starting",
      "status": "open",
      "priority": "high",
      "department": "Support",
      "service_id": 123,
      "message_count": 2,
      "created_at": "2026-11-07T10:00:00+00:00",
      "updated_at": "2026-11-07T10:10:00+00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "last_page": 1,
    "total": 1
  }
}
```

Accounting summary:

```json
{
  "success": true,
  "data": {
    "invoice_counts": {
      "total": 3,
      "pending": 1,
      "paid": 2,
      "cancelled": 0
    },
    "balances_by_currency": [
      {
        "currency_code": "IRR",
        "outstanding_amount": "250000.00",
        "invoiced_amount": "1250000.00"
      }
    ],
    "source": "paymenter_invoices_and_transactions",
    "wallet_included": false
  }
}
```

All examples are illustrative; IDs, currencies, departments and timestamps are not production values.

## Installation

Copy the extension to:

```text
extensions/Others/FragCitySupportAPI/
```

Then run on the Paymenter host:

```bash
cd /var/www/paymenter
composer dump-autoload
php artisan optimize:clear
```

Enable **FragCity Support API** in **Admin → Extensions → Others**, configure its dedicated secret, and optionally configure the exact source IP allowlist.

The existing FragCityAPI must remain installed because it owns the `fragcity_user_links` identity mapping. Do not uninstall it or alter its routes as part of this extension's deployment.

## Security and operational notes

- Browser-to-website authentication and CSRF protection belong to FragCity's PHP backend.
- Paymenter accepts only signed server-to-server requests.
- Every customer data query is scoped to the Paymenter user resolved from the signed SteamID64.
- Ticket/service/attachment ownership is checked before returning data or performing an action.
- Ticket messages are stored in Paymenter's native tables so normal observers and event listeners can run.
- The API never accepts client-supplied Paymenter user IDs.
- No Paymenter Core files, existing FragCityAPI files, FragCityCS2 files, themes, or production server settings are changed by this extension.
