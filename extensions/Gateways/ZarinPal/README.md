# FragCity ZarinPal Gateway

Paymenter v1.5.9 Gateway extension for ZarinPal v4. It settles native Paymenter invoices. It does not create orders, services, invoices, wallet balances or provisioning jobs itself.

## Install

1. Upload the ZarinPal-v1.0.1.zip package through Paymenter Admin → Extensions → Upload Extension.
2. Install ZarinPal from Admin → Extensions → Gateways.
3. Configure the gateway and enable it.
4. Enable Paymenter Credits under Admin → Settings → Credits to expose the native customer wallet.

## Configuration

- Merchant ID: the ZarinPal production or sandbox merchant ID.
- Paymenter currency code: the exact currency code used by the invoice, normally IRR for FragCity.
- Paymenter currency unit: choose Rial when Paymenter stores values in Rial. Choose Toman only when the configured Paymenter currency values are in Toman.
- Sandbox mode: enable only for a sandbox merchant.
- Payment description prefix: optional short text shown by ZarinPal.

FragCity's current Paymenter currency is configured as IRR with Rial as its display suffix, so the correct initial configuration is IRR + Rial. The request explicitly sends `currency: IRR`; when Toman is selected it sends `currency: IRT` and converts the amount to Rial. Do not multiply these invoice amounts by 10 again.

## Native wallet / Credits

Paymenter 1.5.9 already provides the wallet ledger and customer pages through Credits. Enable it in the global Credits settings; the Gateway itself does not implement or duplicate the wallet balance:

- credits_enabled: enable the wallet.
- credits_minimum_deposit: minimum top-up amount, in the selected Paymenter currency.
- credits_maximum_deposit: maximum single top-up amount, in the selected Paymenter currency.
- credits_maximum_credit: maximum balance allowed, in the selected Paymenter currency.
- credits_auto_use: automatically use sufficient credits for recurring invoices.

The customer creates a native Paymenter credit-deposit invoice from Account → Credits. This gateway pays that invoice like any other invoice. Only after Paymenter records a successful transaction does its native paid-invoice processor add the deposit to the customer's Credit balance. Customers can then use Credits to pay service invoices; Paymenter records those as credit transactions.

Set all wallet limits in the configured currency's stored unit. With IRR + Rial, enter Rial amounts, not Toman amounts.

## Payment and safety model

1. Paymenter creates the invoice; the gateway receives the server-calculated outstanding amount.
2. The gateway locks the invoice row and creates one durable payment-attempt record. A concurrent request reuses the active authority instead of creating another one.
3. The request record stores a hash of a random callback token, invoice ID, exact currency and amount, an encrypted merchant ID, currency unit and sandbox/live environment. Neither the callback token nor merchant ID is stored in plaintext.
4. The callback must match both the token and the stored authority. The gateway then claims the attempt with a short database-backed verification lease to prevent concurrent callback processing.
5. The gateway verifies the authority server-to-server with the merchant and environment saved for that attempt.
6. The verify request is sent with the exact stored Rial amount; ZarinPal must return success code 100 and a reference ID. The invoice must still be pending with the same outstanding amount and currency.
7. The gateway records the Paymenter transaction through ExtensionHelper::addPayment(). Paymenter remains responsible for invoice settlement, wallet crediting and native service provisioning.

Payment-attempt state is stored in zarinpal_gateway_payments, not Laravel Cache. Unique token, authority and ZarinPal reference constraints protect this extension's attempt ledger. The stable Paymenter transaction ID is zarinpal:{authority}. If a callback is repeated after Paymenter recorded the transaction but before the gateway updated its ledger, the matching successful Paymenter transaction is detected and the attempt is marked succeeded without charging or crediting twice. ZarinPal code 101 is not accepted as a new payment: it is recoverable only when that exact successful Paymenter transaction already exists; otherwise the attempt is marked for manual reconciliation.

If ZarinPal reports an already-verified payment but does not return enough receipt data to reconcile it with Paymenter, or if the invoice changed after payment started, the attempt is marked manual_review and a critical log is written. It is deliberately not silently credited to a different invoice.

## Operational requirements

- Serve Paymenter over HTTPS.
- Keep the Paymenter database backed up.
- Confirm the extension migration exists after installation. The extension runs its migration from its install/upgrade lifecycle hook.
- Test sandbox top-up and a real service invoice separately before enabling production payments.
- Do not edit Paymenter Core or trust browser query values as proof of payment.
