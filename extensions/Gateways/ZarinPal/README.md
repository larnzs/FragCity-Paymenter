# ZarinPal Gateway

Paymenter v1.5.9 gateway extension for ZarinPal v4.

## Configuration

Enable **ZarinPal Gateway** under Paymenter Admin → Extensions → Gateways, then configure:

- Merchant ID: the production or sandbox ZarinPal merchant ID.
- Paymenter currency unit: choose Toman only when the Paymenter currency's stored values are Toman; otherwise choose Rial.
- Sandbox mode: use only with a ZarinPal sandbox merchant.
- Description prefix: a short invoice description shown in the payment gateway.

The gateway converts Toman to Rial exactly once when calling ZarinPal. It records the successful payment through Paymenter's `ExtensionHelper::addPayment()`, so Paymenter remains responsible for settling the invoice and dispatching server provisioning.

## Flow

1. Native Paymenter Checkout creates the Order, Service, Invoice and native Service Config records.
2. The invoice payment screen invokes this gateway.
3. The gateway requests a ZarinPal authority and stores a short-lived, unguessable callback token bound to that exact invoice, currency, amount and authority.
4. The customer pays at ZarinPal.
5. The callback verifies the authority server-to-server with ZarinPal before Paymenter records the transaction as successful.
6. Paymenter's normal invoice observer and provisioning job create the CS2 server.

The browser callback is never treated as proof of payment. The callback token prevents an authority from being replayed against a different invoice. Duplicate callbacks for a successful transaction are idempotent.

## Important

- The ZarinPal request and verify API use Rial. The gateway's Toman setting multiplies the Paymenter amount by 10; the Rial setting leaves it unchanged.
- Use HTTPS on the Paymenter host and a persistent shared cache store in production so callback state survives application restarts and reaches the same cache backend on every web node.
- Test the full request → payment → callback → invoice settlement → server provisioning sequence with a sandbox merchant before enabling the gateway for customers.
