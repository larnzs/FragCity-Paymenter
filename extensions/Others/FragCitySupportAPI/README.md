# FragCitySupportAPI

A minimal private identity bridge between the FragCity website backend and Paymenter.

- **Compatibility target:** Paymenter v1.5.9
- **Extension version:** 1.2.0
- **Path:** `extensions/Others/FragCitySupportAPI`

This extension is intentionally small. Paymenter remains the system of record for tickets, messages, invoices and invoice items. The website uses Paymenter's official Admin API for those operations.

## Responsibility split

### FragCitySupportAPI

The extension exposes only:

- `POST /api/v1/fragcity/support/ping` — authenticated connectivity check
- `POST /api/v1/fragcity/support/identity/resolve` — resolve a SteamID64 to its linked Paymenter user ID

It does not create or list tickets, post messages, update ticket status, manage attachments, read invoices, read transactions, or modify accounting records.

### Paymenter native Admin API

Use Paymenter's official API at `/api/v1/admin` for:

- Ticket list, create, show and update
- Ticket message list/read and create
- Invoice list and show
- Invoice item details through the invoice `items` relationship

Do not create a second ticket or invoice database in FragCity.

## Paymenter API Key setup

Create a dedicated key in **Admin → API Keys**, for example:

```text
FragCity Support Website
```

Enable only these permissions:

| Permission | Why it is needed |
|---|---|
| View Tickets | List tickets and verify ownership |
| Create Tickets | Create customer tickets |
| Update Tickets | Close or reopen customer tickets |
| View Ticket Messages | Read ticket conversations |
| Create Ticket Messages | Send customer messages |
| View Invoices | List and inspect invoices |
| View Invoice Items | Read invoice line items |

Do not enable Delete, Impersonate, User Create/Update, Invoice Create/Update, Invoice Item Create/Update, Orders, Services, Coupons or Credits. `View Users` is not needed for Steam identity resolution.

Keep the API key only in FragCity's private server-side settings. Never send it to the browser or JavaScript. If the Paymenter API Key screen offers an IP allowlist, restrict it to the FragCity backend's public outbound IP.

## Native Paymenter Admin API contract

Base URL:

```text
https://billing.fragcity.ir/api/v1/admin
```

Headers:

```http
Authorization: Bearer YOUR_PAYMENTER_ADMIN_API_KEY
Accept: application/json
Content-Type: application/json
```

### Tickets

List the authenticated customer's tickets:

```http
GET /tickets?filter[user_id]=123&sort=-updated_at&per_page=20
```

Load a customer's ticket and its messages while applying both ownership filters:

```http
GET /tickets?filter[id]=456&filter[user_id]=123&include=messages
```

The Ticket API supports the `id` and `user_id` filters and the `messages` include in Paymenter v1.5.9. Prefer the filtered query above for customer detail views. If using `GET /tickets/{id}` or `PATCH /tickets/{id}`, first verify that the ticket belongs to the resolved Paymenter user.

Create a ticket:

```http
POST /tickets
Content-Type: application/json

{
  "subject": "CS2 server support",
  "user_id": 123,
  "department": "Support",
  "priority": "medium",
  "status": "open"
}
```

Paymenter v1.5.9 accepts `subject`, `user_id`, optional configured `department`, `priority` (`low`, `medium`, `high`) and `status` (`open`, `closed`, `replied`). The request defaults priority to `medium` and status to `open` when omitted. The department must match the values configured in Paymenter. Since this extension no longer has a ticket-options endpoint, keep the website's department choices aligned with the Paymenter configuration.

Create the first message or a customer reply:

```http
POST /ticket-messages
Content-Type: application/json

{
  "message": "Please check my server.",
  "user_id": 123,
  "ticket_id": 456
}
```

Paymenter validates `message` (maximum 5,000 characters), `user_id` and `ticket_id`. Ticket creation and first-message creation are two separate API calls. The website backend should handle partial failure carefully and must not create duplicate tickets when retrying.

Close or reopen a ticket:

```http
PATCH /tickets/456
Content-Type: application/json

{
  "status": "closed"
}
```

Use `open` to reopen. The customer website should only expose the status actions intended for customers; staff replies and staff-side management remain in Paymenter.

The Ticket Messages list endpoint does not provide a `ticket_id` filter in v1.5.9. Read a conversation through the Ticket resource using `include=messages`.

### Invoices and invoice items

List the customer's invoices with line items:

```http
GET /invoices?filter[user_id]=123&include=items&per_page=20
```

Inspect one invoice using both ownership filters:

```http
GET /invoices?filter[id]=789&filter[user_id]=123&include=items
```

The Invoice resource exposes invoice ID, status, currency code, due date and timestamps. Included Invoice Items expose description, quantity and unit price.

**Native API accounting limitation:** Paymenter v1.5.9 does not expose invoice transactions, a remaining balance, or the computed invoice total in its Invoice API resource. Do not present a calculated line-item sum as the authoritative payable amount where tax or payment allocations may apply. This implementation deliberately does not add a custom accounting endpoint; if the website later needs the exact transaction ledger or remaining balance, that requirement must be addressed separately.

### Attachments

Paymenter v1.5.9's Admin API can include existing message attachments when reading messages, but its `CreateTicketMessageRequest` accepts only `message`, `user_id` and `ticket_id`; it does not provide a native Admin API upload field/endpoint. This minimal bridge does not add a custom upload route. The first integration pass should use text-only messages unless a separate attachment requirement is approved.

## Customer ownership and security

Paymenter's Admin API key is administrative, not a customer-scoped token. A query filter is not itself an authorization boundary.

FragCity's PHP backend must:

1. Authenticate the customer using the FragCity session.
2. Read the customer's SteamID64 from its server-side authenticated user record, not from an arbitrary browser field.
3. Resolve the SteamID64 through the signed `identity/resolve` endpoint and use the returned Paymenter user ID. Never accept `paymenter_user_id` from browser input.
4. Always include `filter[user_id]=<resolved Paymenter user ID>` in ticket and invoice collection queries.
5. Before viewing or updating an individual ticket, verify ownership using both `filter[id]` and `filter[user_id]`.
6. Before `POST /ticket-messages`, verify that `ticket_id` belongs to the resolved user, then set `user_id` server-side to that same resolved ID.
7. Never allow the browser to choose `user_id`, `ticket_id` ownership, or an arbitrary Paymenter invoice ID without a backend ownership check.

The key must remain in server-side FragCity settings. The customer browser calls only FragCity's own backend.

## Identity bridge

The mapping is maintained by the existing `FragCityAPI` extension in the `fragcity_user_links` table:

- `steamid64`
- `user_id` (Paymenter user ID)

Keep `FragCityAPI` enabled. This extension only reads the existing mapping; it does not create, update or delete links.

Request:

```http
POST /api/v1/fragcity/support/identity/resolve
Content-Type: application/json
```

```json
{
  "steamid64": "7656119XXXXXXXXXX"
}
```

Successful response:

```json
{
  "success": true,
  "data": {
    "steamid64": "7656119XXXXXXXXXX",
    "paymenter_user_id": 123,
    "linked": true
  }
}
```

Possible errors:

- `fragcity_account_not_linked` — no mapping exists for that SteamID64
- `linked_paymenter_user_missing` — mapping exists but the Paymenter user no longer exists
- `validation_failed` — SteamID64 is not a 17-digit string
- `identity_lookup_failed` — unexpected server-side lookup failure

## HMAC authentication

Use a dedicated secret for this extension. Do not reuse the existing FragCity server-bridge secret.

Generate a 32-byte secret:

```bash
openssl rand -hex 32
```

Store it only in the encrypted extension setting and FragCity's private server-side runtime settings.

Required headers:

- `X-FragCity-Timestamp` — Unix timestamp in seconds
- `X-FragCity-Nonce` — 32–64 hexadecimal characters, unique per request
- `X-FragCity-Signature` — HMAC-SHA256 hexadecimal digest

Signed message (the body must be the exact raw JSON bytes sent):

```text
timestamp + "\n" + nonce + "\n" + raw_json_body
```

The timestamp must be within five minutes. Nonces are atomically stored in Laravel Cache for ten minutes and cannot be reused. The optional source IP allowlist accepts exact IP addresses; HMAC authentication remains mandatory.

## Installation and update

On the Paymenter server:

```bash
cd /opt/fragcity-paymenter
git pull --ff-only origin main

cd /var/www/paymenter
rsync -a --delete \
  /opt/fragcity-paymenter/extensions/Others/FragCitySupportAPI/ \
  extensions/Others/FragCitySupportAPI/

chown -R root:www-data extensions/Others/FragCitySupportAPI
find extensions/Others/FragCitySupportAPI -type d -exec chmod 755 {} \\;
find extensions/Others/FragCitySupportAPI -type f -exec chmod 644 {} \\;

composer dump-autoload --optimize --no-interaction
php artisan optimize:clear
```

No migration or queue-worker restart is required. Keep both the existing `FragCityAPI` and `FragCityCS2 v1.4.0` extensions unchanged.

## Out of scope

- Duplicate ticket, message, invoice or invoice-item storage
- Customer-facing Paymenter panel or staff ticket UI in FragCity
- Ticket-to-Service linking
- Custom attachment upload/download API
- Custom invoice transaction/accounting API
- Payment settlement, invoice status mutation, or Paymenter credit changes
- Paymenter Core modifications
