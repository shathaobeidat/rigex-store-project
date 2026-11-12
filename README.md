# REGIX Store

REGIX is a PHP and MySQL store for PC hardware, new and used components, and PC build recommendations. It includes a public storefront, customer accounts and cart, checkout, and an administration dashboard.

The interface uses a dark background, lime accents, a shared REGIX logo, and responsive layouts. Display headings use Anton on campaign pages and Oxanium in administration; supporting text uses Onest or Inter.

## Run locally with XAMPP

1. Place the project in `C:\xampp\htdocs\regix_store`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open [phpMyAdmin](http://localhost/phpmyadmin) and create a database named `regix_db` using `utf8mb4`.
4. Import the team's full SQL database export. **A full schema/seed export is not currently included in this repository.** The migration under `backend/migrations/` does not create the complete database.
5. Set the database connection values in `backend/config/config.php`.
6. Open [the landing page](http://localhost/regix_store/frontend/User/index.php).

Requirements:

- PHP 8.0 or later, with PDO MySQL, sessions, JSON, and mbstring.
- MySQL or MariaDB, with the project's full schema and foreign keys.
- PHP cURL and a Stripe test configuration for card checkout.
- Internet access for Google Fonts, remote build-concept images, and external scripts such as Chart.js and the optional Lenis enhancement.

The current application uses direct PHP includes and browser assets. There is no Composer or npm installation step in the repository.

### Database connection

`backend/config/config.php` defines `$DB_connValues` with `host`, `dbname`, `username`, and `password`. Use credentials appropriate to your local installation; XAMPP commonly uses `localhost` and `root` locally.

Get a PDO connection through:

```php
require_once __DIR__ . '/path/to/backend/config/db.php';

$db = DB_conn::getConnection();
```

Adjust the include path relative to the calling file. Keep the configuration name `$DB_connValues` consistent with `db.php`.

### Database areas

| Table | Used for |
| --- | --- |
| `users` | Account details, roles, authentication, and profiles |
| `categories` | Product groups and category images |
| `products` | Names, prices, stock, condition, availability, and images |
| `carts` | Customer cart ownership and lifecycle |
| `cart_items` | Products and quantities in a cart |
| `orders` | Delivery details, order status, totals, and customer ownership |
| `order_items` | Products and quantities associated with an order |
| `payments` | Payment methods, payment status, and order/cart associations |
| `pc_component_specs` | Component types and specifications used by the PC builder |

This is an overview of the tables used by these features, not a replacement for the complete SQL schema. Many queries exclude records with a non-null `deleted_at` value.

For an older database whose payments are linked only through carts, inspect and apply `backend/migrations/20261031_payment_order.sql` **once** if `payments.order_id` is missing. It adds the order association and backfills carts that have exactly one associated order. Do not rerun the `ADD COLUMN` migration if the column already exists.

## Main pages

URLs below assume the folder name is `regix_store`.

| Page | Local URL |
| --- | --- |
| Landing page | [User/index.php](http://localhost/regix_store/frontend/User/index.php) |
| Store and product filters | [User/store.php](http://localhost/regix_store/frontend/User/store.php) |
| PC builder | [User/pc_builder.php](http://localhost/regix_store/frontend/User/pc_builder.php) |
| Customer profile | [User/userprofile.php](http://localhost/regix_store/frontend/User/userprofile.php) |
| Checkout | [User/checkout.php](http://localhost/regix_store/frontend/User/checkout.php) |
| Login | [auth/login.php](http://localhost/regix_store/backend/auth/login.php) |
| Sign up | [auth/signup.php](http://localhost/regix_store/backend/auth/signup.php) |
| Admin overview | [admin/dashboard.php](http://localhost/regix_store/frontend/admin/dashboard.php) |
| Admin categories | [admin/categories.php](http://localhost/regix_store/frontend/admin/categories.php) |
| Admin products | [admin/products.php](http://localhost/regix_store/frontend/admin/products.php) |
| Admin orders | [admin/orders.php](http://localhost/regix_store/frontend/admin/orders.php) |
| Admin customers/users | [admin/users.php](http://localhost/regix_store/frontend/admin/users.php) |
| Admin profile | [admin/adminprofile.php](http://localhost/regix_store/frontend/admin/adminprofile.php) |

## Public storefront

- Shared header, navigation, account menu, search dialog, cart drawer, and footer live in `frontend/User/layout.php`.
- The landing page has a layered hardware hero, introductory loading animation, scrolling marquee, store introduction, featured products, build concepts, setup-focus tabs, and a compact store invitation.
- Featured products use database records. The landing page selects available best sellers associated with paid, non-cancelled orders, falling back to recent available products when none qualify.
- Product cards have aligned prices and actions, condition labels, stock counts, and category placeholders when no product image is configured.
- The store filters products immediately by search text, category, and condition. Selected category buttons have a lime underline; condition uses a segmented control. Reset clears the active filters, and filter state is reflected in the URL.
- The cart supports adding products, updating quantities, removing items, and opening checkout for customer accounts.
- Customer profiles support editing personal details, changing a password, and viewing recent orders.
- Responsive layouts adapt navigation, filters, cards, profiles, and the builder to smaller screens. Motion effects include reduced-motion alternatives.

The older illustrative CPU/GPU/RAM price estimator was removed from the landing page and replaced with a link to the store. The separate `pc_builder.php` feature remains available. The additional landing-page category directory and grouped `AVAILABLE NOW` sections were also removed; categories remain available as filters in the store.

## PC build recommendations

`frontend/User/pc_builder.php` keeps its own recommendation workflow inside the shared storefront layout:

1. Select Gaming, Workstation, or Everyday use.
2. Enter a maximum budget in JOD.
3. Select a gaming resolution where applicable.
4. Generate a recommendation and review any alternatives and compatibility details.

The live build brief updates as selections change. Components come from the available catalog and `pc_component_specs`. Missing or incomplete specifications may require verification; performance scores are relative rankings, not predicted FPS for a complete PC.

Related classes:

- `ModelFactory.php`: constructs the dashboard and builder services.
- `PCBuilderClass.php`: catalog access and build-generation coordination.
- `BuildOptimizerClass.php`: configuration optimization.
- `CompatibilityClass.php`: component compatibility checks.
- `PerformanceRankingClass.php`: performance ranking.

`frontend/User/export_pc_specs.php` is a helper tied to a fixed list of product IDs. Review those IDs before using it with another database.

## Administration

`frontend/layout.php` provides the shared admin shell. It includes consistent page headings, navigation, an internally scrollable sidebar, and a workspace dropdown with profile and sign-out links. The admin profile link opens `frontend/admin/adminprofile.php`.

Management pages include:

- **Overview:** reporting period controls, refresh/reset actions, statistics, charts, and order reporting.
- **Categories:** category management, images, search, product/image filters, and sorting.
- **Products:** product management, stock and availability labels, expandable details, update/delete/reactivate actions, and a table that wraps or stacks on narrow screens.
- **Orders:** database-backed summaries, search/filter/sort controls, CSV export, update dialogs, and deletion actions.
- **Users:** account management, role/status filters, sorting, and a compact add-user toolbar.
- **Admin profile:** account detail and password forms styled to match the dashboard.

Order updates use the `Order` model and `OrderSaveMode::UPDATE`. Order status and payment status are separate fields. Order deletion through the model uses soft deletion.

Supported order statuses are `pending`, `confirmed`, `shipped`, `delivered`, and `cancelled`. The interface may use a customer-friendly label for a stored status.

The orders summary includes total orders, orders needing attention, orders in transit, and gross sales. Consult `Order::getSummary()` for the exact queries and comparison periods rather than hard-coding dashboard values.

### Two different order classes

Do not confuse these files:

| File | Responsibility |
| --- | --- |
| `backend/classes/ordersClass.php` | `Order` CRUD, admin listing/summaries, and status updates; defines `OrderSaveMode` string constants |
| `backend/classes/OrderClass.php` | Checkout order creation, inventory/cart handling, and payment lifecycle |

`OrderSaveMode` is a constants class in the current code, not a native PHP enum. Preserve the exact filename and letter case in includes, especially when moving to Linux hosting.

## Login and access

Login stores `user_id`, `email`, `user_name`, and `user_role` in the session. Admin pages use `frontend/admin/guard.php` to verify the account role against the database. Hiding an admin link does not grant or revoke access.

Create accounts through sign-up or import the team's account data. There is no documented default admin password. To promote an existing account in a local database, use its exact email:

```sql
UPDATE users
SET role = 'admin'
WHERE email = 'your-existing-account@example.com'
  AND deleted_at IS NULL;
```

Sign out and back in after a role change so the session reflects the account's role. Logout is handled by `backend/auth/logout.php`.

## Checkout and payments

Checkout requires a customer account and a nonempty active cart. It collects delivery details and offers cash on delivery or Stripe checkout. Order creation and payment handling use database records and inventory checks.

`backend/config/stripe.php` is included in the repository and reads configuration from environment variables. For local XAMPP development, create the ignored file `backend/config/stripe.local.php` with your own Stripe test key:

```php
<?php
declare(strict_types=1);

define('STRIPE_SECRET_KEY', 'sk_test_your_key');
define('SITE_URL', 'http://localhost/category%20and%20payments/frontend/User');
```

This local override is ignored by Git and must not be committed. Each teammate should create their own copy with their own key and local project URL. Alternatively, set `STRIPE_SECRET_KEY` and `SITE_URL` in the PHP/Apache environment. When `STRIPE_SECRET_KEY` is empty, cash on delivery remains available but card checkout cannot be started. Do not commit real secret keys.

`StripeClass.php` calls the Stripe API through PHP cURL. Payment return handlers are `payment_success.php` and `payment_cancel.php`.

Prices are displayed in JOD. The current card checkout uses the **test conversion of 1 JOD = 1.41 USD** defined in `PaymentCurrency.php`; this is a fixed application setting, not a live exchange-rate feed. Production payment behavior and deployment configuration require separate review.

## Project structure

```text
regix_store/
├── README.md
├── backend/
│   ├── auth/          # Login, sign-up, logout
│   ├── classes/       # Database models, builder, order and payment logic
│   ├── config/        # Session helpers and database configuration
│   └── migrations/    # Incremental schema upgrades, not a full SQL dump
└── frontend/
    ├── layout.php     # Shared admin shell
    ├── User/          # Storefront, profiles, builder, cart and checkout
    │   └── layout.php # Shared public shell
    ├── admin/         # Administration pages and access guard
    ├── assets/        # REGIX branding and product images
    ├── css/           # Shared and page-specific styling
    └── js/            # Navigation, filters, dialogs, charts and builder UI
```

Product image paths are stored relative to `frontend/`, for example `assets/products/example.png`. Current public product templates accept image paths under `assets/products/` with simple filenames. The generated campaign hardware asset is `frontend/assets/regix-hero-tower.png`; its prompt is recorded in `frontend/assets/regix-hero-prompt.txt`.

## Styling and interaction files

| Area | Files |
| --- | --- |
| Public shell/cart/search | `store.css`, `storefront.css`, `store.js` |
| Campaign landing page | `landing.css`, `cinematic.css`, `cinematic.js` |
| Store catalog and filters | `store-catalog.css`, `store.js` |
| Customer profile | `userprofile.css` |
| PC builder | `pc_builder.css`, `pc_builder.js` |
| Shared admin appearance | `admin.css`, `admin-unified.css`, `typography.css`, `admin.js` |
| Dashboard | `Dashboard.css`, `Dashboard.js` |
| Orders and dialogs | `order-update.css`, `orders.js` |
| Admin profile | `admin-profile.css` |

Keep page-specific rules scoped to their page/container so changes do not unexpectedly restyle other pages. Several stylesheets have modification-time query strings to invalidate browser caches. Use **Ctrl + F5** when a local styling change appears stale.

## Manual checks after changes

- Open landing, store, and builder pages; confirm the shared header and navigation match.
- Test product search, combined category/condition filters, no-result messaging, reset, and URL state.
- Sign in as a customer; add an available product, change its cart quantity, and remove it.
- Test checkout using the configured local payment method. Do not treat a successful page load as a completed payment test.
- Sign in as an admin; check summaries, order update/delete feedback, and CSV export.
- Check customer/admin profiles and their respective navigation links.
- Move through both builder steps; check live budget updates and recommendation/error states with your database.
- Check narrow-screen layouts for horizontal overflow, readable controls, and usable sidebar/navigation scrolling.

From Git Bash, PHP syntax checks can be run with the XAMPP executable:

```bash
/c/xampp/php/php.exe -l frontend/User/index.php
/c/xampp/php/php.exe -l frontend/User/layout.php
/c/xampp/php/php.exe -l frontend/User/pc_builder.php
/c/xampp/php/php.exe -l frontend/admin/orders.php
```

There is no repository-wide automated test runner configured. Run checks appropriate to the files changed and manually verify database-dependent flows.

## Working together with Git

Use a separate branch for each change and open a pull request for review. Do not discard another contributor's work to remove a conflict.

```bash
git status
git switch -c your-feature-branch
# Edit and verify your changes, then stage only the intended files.
git add path/to/changed-file
git commit -m "Describe the change"
git push -u origin your-feature-branch
```

If `git pull` says local changes would be overwritten, commit the intended changes or stash them before pulling. If a merge produces conflicts:

1. Read both versions of each conflicted block.
2. Merge the intended behavior; accepting both blocks can duplicate markup or leave PHP loops unclosed.
3. Remove conflict markers and check for duplicate dialog IDs, missing variables, broken links, and syntax errors.
4. Stage the resolved files and complete the merge with `git -c core.editor=true commit --no-edit` when a merge is in progress.

Avoid force-pushing shared `main`. A rejected push usually means remote work must be integrated first.

## Common local issues

| Symptom | What to check |
| --- | --- |
| Database connection failure | MySQL running, database imported, and `$DB_connValues` matching your installation |
| Missing table/column | Full SQL export version, PC specifications table, and whether the payment-order migration is required |
| Card checkout is unavailable | Set `STRIPE_SECRET_KEY` or create the ignored `backend/config/stripe.local.php` described above |
| `Unexpected token '<'` during an order action | Inspect the network response for a login redirect or PHP error instead of JSON; verify the admin session |
| Styles look unchanged | Ctrl + F5, stylesheet URLs, and page-specific body classes |
| Admin page redirects | Existing account, current database role, and login session |
| Builder cannot recommend a configuration | Available stock, component specifications, budget, and compatibility requirements |
| PHP parse error after a merge | Remaining conflict markers, mismatched `if`/`foreach` blocks, or duplicated code |

This README documents the current source and local setup. It does not imply that every payment, checkout, or recommendation path has been tested in production.
