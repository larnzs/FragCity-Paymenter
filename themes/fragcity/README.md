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

Main-site links point to `https://fragcity.ir`; the store link uses Paymenter's current home route. The brand logo is served from the main site's public assets.

## Assets and build

The theme uses the existing Paymenter Vite entry points:

- `js/app.js`
- `css/app.css`

The shared header/footer styles are imported by `css/app.css`; their scripts are imported by `js/app.js`. The storefront uses `css/fragcity/storefront-marketplace.css`. No extra Vite entry-point configuration is required.

The layout lets the page use the window as its vertical scroll container. This keeps the shared header's scroll-direction and frosted-glass behavior synchronized with the actual page scroll, while the customer sidebar remains fixed below the 72px header.

After deployment, run the normal Paymenter frontend production build and clear the Laravel view cache if the rendered shell does not refresh.
