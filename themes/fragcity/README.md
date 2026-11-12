# FragCity Paymenter Theme

The `fragcity` theme is the branded storefront and customer-area theme for Paymenter.

## Storefront

The home page is a product-first marketplace, not a second marketing landing page:

- Store categories and visible products are read from Paymenter's existing category/product collections.
- Product names, images, stock state and displayed prices come from Paymenter.
- The home page does not hard-code catalog prices, availability or product inventory.
- Checkout, configurable options, invoices and customer account actions continue to use Paymenter's native routes and billing flow.
- The home page includes category discovery, product cards, purchase benefits and a three-step ordering guide.

The category, product-detail and checkout views use a shared FragCity storefront design:

- Category listings show the category image/description, subcategories, product cards, stock state and Paymenter-formatted starting prices.
- Product details render the saved product description as styled rich content (headings, lists, tables, links, code blocks and media).
- Checkout separates plan selection, product Config Options, Egg startup fields and the order summary.
- RAM, CPU, SSD/Storage and Player Slots Config Options are displayed as discrete range controls when their Paymenter Config Option names match those resources. Each slider maps its position to the existing Config Option child ID through Livewire entanglement; it does not submit arbitrary resource values.
- Other Config Options and FragCityCS2 checkout fields keep Paymenter's native select/radio/input controls.
- The order summary uses Paymenter's live `$total`, subtotal, tax and setup-fee values. No browser-side price calculation or custom checkout endpoint is introduced.

## Shared FragCity site chrome

The storefront header and footer use the same component styles and visual structure as the main FragCity website:

- Header styles are copied from `FragCity-RawPHPbackend/css/header.css`.
- Footer styles are copied from `FragCity-RawPHPbackend/css/footer.css`.
- The Blade components live in `views/components/navigation/index.blade.php` and `views/components/navigation/footer.blade.php`.
- Header interactions are implemented in `js/fragcity-site-header.js`, using the main site's sticky-header, dropdown, mobile-drawer and Performance Mode behavior.
- Footer readiness hook is in `js/fragcity-site-footer.js`.

The main site's PHP session, Steam/FACEIT lookups, wallet database queries and SPA navigation are deliberately not imported into Paymenter. The visual header is preserved, while login, registration, account links, avatar and logout are supplied by Paymenter's own authentication/navigation layer. Footer login and registration links also point to Paymenter's native routes.

Main-site links point to `https://fragcity.ir`; the store link points to `/` so visitors always land on the Billing storefront root. The brand logo is served from the main site's public assets.

## Customer portal

The signed-in customer area now uses the same FragCity orange/charcoal visual language as the main website:

- Dashboard: account overview, live service/invoice/ticket widgets and direct account shortcuts.
- Services: service inventory cards and a branded service detail surface. Paymenter service actions, cancellation and extension-provided service views remain native.
- Invoices: invoice history, invoice details, transaction status and PDF download remain connected to Paymenter's native invoice records.
- Tickets: ticket list, detail conversation, reply editor, attachment upload and close actions remain the native Livewire ticket workflow.
- Account: personal details, security/session controls, 2FA, credits, saved payment methods and notification preferences keep their existing Livewire actions.
- Cart: editable items, quantity controls, coupon application, taxes, total and checkout remain backed by Paymenter's Cart class and Livewire Cart component.
- Payment method modal: saved methods, native gateway choices and Paymenter credits are rendered as selectable FragCity option cards. The native `processPayment` action and the gateway's returned provider form are not replaced.

The customer-area styling is isolated in `css/fragcity/customer-portal.css` and imported from the existing `css/app.css` entry point. It includes responsive layouts, keyboard focus states and reduced-motion handling. The shared modal component accepts a `variant` prop; invoice payment uses `variant="payment"` for the branded payment selector while other modals use the default surface.

The repository does not vendor or replace Paymenter Core. The optional, narrowly scoped Paymenter v1.5.9 patch for keeping the GSLT out of URL-synchronized Checkout state is documented in `deployment/README.md`; it changes only the Checkout component and is required before enabling the GSLT text Config Option. All other FragCity behavior stays in the theme and extensions. The Store navigation points directly to the Billing root `/`. FragCity SSO also returns a root destination to the literal `/` path instead of generating it from the configured application URL, so a path suffix in `APP_URL` cannot redirect the storefront to `/dashboard`. For customers who have Paymenter TOTP enabled, FragCityAPI uses a branded SSO-specific TOTP screen that preserves the original return path after successful verification; it uses Paymenter's native TOTP verifier, rate limiter, replay cache and Login action.

## Assets and build

The theme uses the existing Paymenter Vite entry points:

- `js/app.js`
- `css/app.css`

The shared header/footer styles are imported by `css/app.css`; their scripts are imported by `js/app.js`. The storefront uses `css/fragcity/storefront-marketplace.css` and the customer area uses `css/fragcity/customer-portal.css`. No extra Vite entry-point configuration is required.

The layout lets the page use the window as its vertical scroll container. This keeps the shared header's scroll-direction and frosted-glass behavior synchronized with the actual page scroll, while the customer sidebar remains fixed below the 72px header.

After deployment, run the normal Paymenter frontend production build and clear the Laravel view cache if the rendered shell does not refresh.
