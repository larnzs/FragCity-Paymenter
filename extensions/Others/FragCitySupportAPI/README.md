# FragCitySupportAPI

A small private compatibility bridge between FragCity and Paymenter.

**Compatibility target:** Paymenter v1.5.9  
**Extension version:** 1.1.0  
**Extension path:** \`extensions/Others/FragCitySupportAPI\`

This extension deliberately does **not** duplicate Paymenter's native ticket or invoice APIs.

## Responsibility split

### Paymenter Admin API — primary API

Use Paymenter's built-in API at \`/api/v1/admin\` for:

- Ticket list, create, show and update
- Ticket message list-through-ticket-include and create
- Invoice list and show
- Invoice item details through invoice includes

### FragCitySupportAPI — compatibility bridge only

Use this extension only for the small capabilities the native API does not provide:

- Resolve the authenticated FragCity \`SteamID64\` to its linked Paymenter \`user_id\`
- Read Paymenter's configured ticket departments and customer ticket options
- Upload and download ticket message attachments
- Read invoice transactions (Paymenter v1.5.9 has no invoice-transactions Admin API resource)

The extension does not create, list, update, close or delete tickets. It does not create or update invoices. It does not change Paymenter Core.

## Paymenter API Key setup

Create a separate API Key in **Admin → API Keys** named:

\`\`\`text
FragCity Support Website
\`\`\`

Enable only these seven permissions:

| Permission | Why it is needed |
|---|---|
| View Tickets | List and verify ticket ownership |
| Create Tickets | Create a support ticket |
| Update Tickets | Close and reopen a ticket |
| View Ticket Messages | Include messages in a ticket response |
| Create Ticket Messages | Send the first message and later replies |
| View Invoices | List and verify invoice ownership |
| View Invoice Items | Include invoice line items |

Do not enable any Delete, Impersonate, User Create/Update, Invoice Create/Update, Invoice Item Create/Update, Order, Service, Coupon or Credit permissions.

\`View Users\` is optional only if FragCity needs staff/customer names from Paymenter user relationships. It is not required for the SteamID64-to-user-ID lookup performed by this extension.

Set the API Key's **Allowed IP Addresses** to the public outbound IP of the FragCity PHP backend. Keep the raw token only in private server-side settings. Never send it to browser JavaScript.

### Important API Key security boundary

Paymenter's Admin API Key is administrative, not a per-customer token. The \`filter[user_id]\` query narrows returned records but is not an authorization boundary.

Therefore:

1. FragCity must authenticate the customer using its own session.
2. FragCity must resolve the customer's Paymenter ID through the signed \`identity/resolve\` endpoint. Never accept \`paymenter_user_id\` from browser input.
3. FragCity must add \`filter[user_id]=<resolved Paymenter ID>\` to ticket and invoice list queries.
4. Before using a direct \`GET /tickets/{id}\`, \`PATCH /tickets/{id}\`, \`GET /invoices/{id}\`, or \`POST /ticket-messages\` route, FragCity must first verify ticket or invoice ownership with a list query filtered by both object ID and the resolved \`user_id\`.
5. For \`POST /ticket-messages\`, FragCity must set \`user_id\` to the resolved Paymenter ID and must verify that \`ticket_id\` belongs to that same user. Never accept either ID from browser input.
6. When updating a ticket, send only the intended fields such as \`status\`; never pass a client-supplied \`user_id\`.
7. The Admin API Key must remain on the PHP server. The browser must never call Paymenter's Admin API directly.

## Native Paymenter Admin API usage

Base URL:

\`\`\`text
https://billing.fragcity.ir/api/v1/admin
\`\`\`

Authentication header:

\`\`\`http
Authorization: Bearer YOUR_PAYMENTER_ADMIN_API_KEY
Accept: application/json
Content-Type: application/json
\`\`\`

The API key token is stored in Paymenter as a SHA-256 hash and is shown only once when created.

### Tickets

List a customer's tickets:

\`\`\`http
GET /tickets?filter[user_id]=123&sort=-updated_at&per_page=20
\`\`\`

Read a customer's ticket and its messages:

\`\`\`http
GET /tickets/456?include=messages
\`\`\`

Before calling the direct show route, verify ownership with:

\`\`\`http
GET /tickets?filter[id]=456&filter[user_id]=123
\`\`\`

Create the ticket:

\`\`\`http
POST /tickets
Content-Type: application/json

{
  "subject": "My CS2 server is not starting",
  "user_id": 123,
  "department": "Support",
  "priority": "medium"
}
\`\`\`

Paymenter defaults ticket priority to \`medium\` and status to \`open\` when omitted. Allowed priorities are \`low\`, \`medium\`, and \`high\`. The department must match one of the server's configured \`ticket_departments\` values.

Create the first message or a reply:

\`\`\`http
POST /ticket-messages
Content-Type: application/json

{
  "message": "Please check my server. It remains offline after restart.",
  "user_id": 123,
  "ticket_id": 456
}
\`\`\`

Close or reopen a ticket:

\`\`\`http
PATCH /tickets/456
Content-Type: application/json

{
  "status": "closed"
}
\`\`\`

Use \`open\` to reopen. Allowed statuses in Paymenter v1.5.9 are \`open\`, \`closed\`, and \`replied\`.

**Important:** Ticket creation and first-message creation are two separate Paymenter API calls. FragCity should persist its local action state and handle a retry if the second request fails. Do not create duplicate tickets on a retry.

The Ticket Messages collection endpoint does not expose a \`ticket_id\` filter in v1.5.9. For a ticket conversation, use the ticket show route with \`include=messages\`.

### Invoices and invoice items

List a customer's invoices:

\`\`\`http
GET /invoices?filter[user_id]=123&per_page=20
\`\`\`

Read a specific invoice with its line items. Verify ownership first using a filtered invoice list query, just as with tickets:

\`\`\`http
GET /invoices?filter[id]=789&filter[user_id]=123
GET /invoices/789?include=items
\`\`\`

The native Invoice resource exposes status, currency code and due date. Invoice items expose description, quantity and unit price. Calculate the displayed invoice total in FragCity by summing \`quantity * price\` for the included items.

Paymenter v1.5.9 does not include the invoice total, remaining balance or invoice transactions in the standard Invoice resource. The transaction fallback below exists for the latter.

## FragCitySupportAPI endpoints

All endpoints use POST with JSON and the dedicated HMAC secret configured on this extension.

| Endpoint | Purpose |
|---|---|
| \`/api/v1/fragcity/support/ping\` | Verify signed bridge connectivity |
| \`/api/v1/fragcity/support/identity/resolve\` | Resolve SteamID64 to Paymenter user ID |
| \`/api/v1/fragcity/support/tickets/options\` | Read configured ticket departments, priorities and limits |
| \`/api/v1/fragcity/support/attachments/upload\` | Upload a file to an owned customer message |
| \`/api/v1/fragcity/support/attachments/download\` | Download a file belonging to an owned ticket |
| \`/api/v1/fragcity/support/accounting/transactions/list\` | Read the customer's Paymenter invoice transactions |

### Identity resolution

Request:

\`\`\`json
{
  "steamid64": "7656119XXXXXXXXXX"
}
\`\`\`

Response:

\`\`\`json
{
  "success": true,
  "data": {
    "steamid64": "7656119XXXXXXXXXX",
    "paymenter_user_id": 123,
    "linked": true
  }
}
\`\`\`

The SteamID64 is resolved using the existing \`fragcity_user_links\` table. The website must not accept a Paymenter \`user_id\` from browser input; the PHP backend resolves it from its authenticated customer's SteamID64.

### Ticket options

The response contains:

- Paymenter's configured department values
- Supported priorities: \`low\`, \`medium\`, \`high\`
- Whether customer-side closing is disabled
- Attachment limits and supported file MIME types

The website should fetch these options rather than hard-code the department list.

### Attachment upload

Paymenter's native Admin API has no ticket-message attachment upload endpoint. After creating the message through Paymenter's native \`POST /ticket-messages\`, send:

\`\`\`json
{
  "steamid64": "7656119XXXXXXXXXX",
  "ticket_id": 456,
  "message_id": 987,
  "filename": "server-console.png",
  "content_base64": "iVBORw0KGgoAAAANSUhEUg..."
}
\`\`\`

The extension verifies that the ticket belongs to the resolved user and that the target message was authored by that same customer. Up to five files are allowed per message. Each file is limited to 10 MiB. Supported detected MIME types are JPEG, PNG, WebP, GIF, PDF, plain text, CSV, JSON and ZIP.

The response returns an attachment UUID. Use the signed \`attachments/list\` endpoint to obtain the attachment UUIDs and metadata for all messages in an owned ticket:

\`\`\`json
{
  "steamid64": "7656119XXXXXXXXXX",
  "ticket_id": 456
}
\`\`\`

The list response is grouped by message ID and does not expose storage paths. Use the returned UUID with the download endpoint. Download is also ownership-checked.

The PHP request-body limit must allow base64 overhead. Configure \`post_max_size\` to at least \`15M\` if uploads are enabled.

### Read-only transaction list

Request:

\`\`\`json
{
  "steamid64": "7656119XXXXXXXXXX",
  "status": "succeeded",
  "page": 1,
  "per_page": 20
}
\`\`\`

Available statuses: \`processing\`, \`succeeded\`, \`failed\`.

This endpoint only reads Paymenter transactions belonging to the resolved user. It cannot create, update, delete or settle a transaction.

## HMAC authentication

Use a dedicated secret for this extension. Do not reuse the existing FragCityAPI server-bridge secret.

Headers:

- \`X-FragCity-Timestamp\`: Unix timestamp in seconds
- \`X-FragCity-Nonce\`: 32–64 hexadecimal characters, unique per request
- \`X-FragCity-Signature\`: HMAC-SHA256 hex digest

Signed message:

\`\`\`text
timestamp + "\n" + nonce + "\n" + raw_json_body
\`\`\`

Timestamp must be within five minutes. Nonces are atomically stored in Laravel Cache for ten minutes. Replayed requests are rejected. The optional IP allowlist accepts exact source IP addresses.

Generate a dedicated secret:

\`\`\`bash
openssl rand -hex 32
\`\`\`

Store it only in Paymenter's encrypted extension settings and FragCity's private server-side runtime settings. Never expose it to the browser or logs.

## Installation and update

Copy this extension to:

\`\`\`text
extensions/Others/FragCitySupportAPI/
\`\`\`

Then on the Paymenter host:

\`\`\`bash
cd /var/www/paymenter
composer dump-autoload --optimize --no-interaction
php artisan optimize:clear
\`\`\`

Keep the existing \`FragCityAPI\` extension installed. It owns the \`fragcity_user_links\` mapping. Do not uninstall it or modify \`FragCityCS2\`.

No new database migrations are required for this extension.

## Out of scope

- No Paymenter Core modifications
- No duplicate ticket, message, invoice or invoice-item tables
- No customer-facing Paymenter API Key in browser code
- No Paymenter credit / FragCity wallet synchronization
- No payment settlement or marking invoices paid
- No ticket deletion from the FragCity customer site
