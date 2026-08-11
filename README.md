# Sahal Restaurant — Management System

PHP 8 + MariaDB restaurant administration system. Orders, menu, tables,
bookings, payments, staff, reports and settings in one dashboard.

---

## Running it

### 1. Start XAMPP

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

The project must live in `htdocs`. It currently does:

```
D:\Xampp\htdocs\Restuarent_system
```

### 2. Create the database

If `restaurant_db` does not exist yet:

1. Open <http://localhost/phpmyadmin>
2. **New** → name it `restaurant_db` → **Create**
3. Select it → **Import** → choose
   `sql/restaurant_db_baseline_2026-08-11.sql` → **Go**

If the database already exists, skip this — but **export a backup first**
(phpMyAdmin → `restaurant_db` → Export → Go) before running any migration.

### 3. Run the migrations

In phpMyAdmin, select `restaurant_db` → **SQL** tab → paste each file and run
them **in order**:

| File | What it does |
|---|---|
| `sql/migrations/001_fix_order_type_enum.sql` | Repairs `order_type`, which was silently discarding dine-in/takeaway |
| `sql/migrations/002_fix_orphan_menu_category.sql` | Reassigns an orphaned menu item and adds a foreign key |
| `sql/migrations/004_menu_items_availability.sql` | Adds the availability switch |
| `sql/migrations/005_order_items_and_totals.sql` | Adds `order_items` (real quantities), tax/service charge, table + waiter on orders |

Each file has verification queries at the bottom and a rollback block.

> **Before running 002**, open it and check `@new_category_id`. It assigns the
> orphaned item `bariis` to category 11 (Lunch) — change it if rice belongs
> somewhere else on your menu.

> **Before running 005**, read the "JUDGEMENT CALLS" block near the top of
> the file. It backfills 23 historical order line items from three different
> data formats found in the old `orders.items` blob, and a handful of those
> calls (what counts as a name match, what happens to plain-text rows) were
> made without a clear answer in the data — review them before you run it.

### 4. Open the app

<http://localhost/Restuarent_system/>

You will be sent to the sign-in page. Use an existing account from the `users`
table.

> If you do not know any password, open `create-admin.php` **once** — it now asks you to set
> your own email and password (and refuses to run if an admin already exists).
> Alternatively run `sql/migrations/003_reset_admin_password.sql`. Delete `create-admin.php` afterwards.

---

## What to check first

The foundation and these pages have been rebuilt. Load each and confirm it
behaves:

| Page | What to look for |
|---|---|
| `login.php` | New split-screen sign-in. Wrong password shows an error; 5 wrong attempts locks for a minute |
| `index.php` | Dashboard: KPI cards, 7-day revenue chart, status doughnut, recent orders |
| `menu.php` | Add, edit, delete, availability toggle, search, filter, sort, pagination |

**After running migration 005**, also check:

| Page | What to look for |
|---|---|
| `place_order.php` | Search/filter the menu grid, add items to the cart, change quantities with +/-. Choosing **Dine-In** reveals a table picker; occupied tables are disabled. The estimate at the bottom should track tax and service charge from Settings. Submit and you should land on a receipt. |
| `orders.php` | New order appears as **Pending**. Status buttons only show the moves that are actually allowed (Pending → Preparing → Ready → Completed, or Cancel). Completing/cancelling a Dine-In order should free its table back to Available — check `tables.php`. |
| `update_order.php` | Open **Edit** on a Pending/Preparing/Ready order. Existing items pre-load into the cart; legacy lines that predate migration 005 are called out separately since they cannot be edited through the cart. |
| `receipt.php` | Shows subtotal, discount, tax, service charge, total, table, waiter and payment status. Print button works. |
| `order_history.php` / `cancelled_orders.php` | Completed and Cancelled orders show up here, not on `orders.php`. |
| `index.php` best sellers | Card should no longer say "Approximate" — quantities are now real. |
| `kitchen.php` | Three columns: Pending, Preparing, Ready. "Start preparing" / "Mark ready" buttons move a ticket to the next column and disappear once it reaches Ready — completing is still done from `orders.php`, not here. A ticket sitting 10+ minutes in one stage gets a warning-coloured border. The page auto-refreshes every 25 seconds. Each line item has an 86 button (⊘ icon) that marks that menu item unavailable immediately, so it stops appearing on new orders — click it, then check the same item shows "Off menu" instead of the button, and that it also shows unavailable on `menu.php`. |

Also worth testing:

- **Sidebar collapse** (desktop) and the **drawer** (narrow window) — these were
  broken site-wide before
- **Dark mode** — the moon icon in the top bar
- **Signing out**, then trying to open `index.php` directly — it should bounce
  you to the login page
- Signing in as a **waiter** and trying to open `manage_users.php` — you should
  get a clear "access denied" screen, not the page

---

## Architecture

```
config/
  config.php        Environment, BASE_URL, upload rules, role list
  database.php      The single PDO connection + query helpers
includes/
  bootstrap.php     Entry point — loads everything, starts the session
  auth.php          Login, roles, CSRF, gates
  helpers.php       Escaping, URLs, flash messages, secure uploads
  legacy_guard.php  Auth guard for pages not yet converted
  layout/           head, sidebar, topbar, flash, foot
actions/            POST-only write handlers (no HTML)
assets/
  css/app.css       Design system
  js/app.js         Shell behaviour
sql/
  restaurant_db_baseline_*.sql
  migrations/
library/            Legacy Vali includes — shrinking as pages convert
```

### Writing a new page

```php
<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title = 'Suppliers';
include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <h1 class="page-head__title">Suppliers</h1>
</div>

<?php include __DIR__ . '/includes/layout/app_end.php'; ?>
```

### Rules

- Escape every output with `e()`
- Never interpolate a value into SQL — pass parameters to `db_all()` / `db_run()`
- Every POST form carries `<?= csrf_field() ?>`; every handler calls `csrf_check()`
- Deletes are POST forms, never links
- Assets and links go through `url()`, never a relative path

---

## Current state

**Done** — audit, git baseline, schema captured, two live data bugs fixed,
foundation (config, PDO, auth, RBAC, CSRF, secure uploads, BASE_URL),
new design system, login / dashboard / menu rebuilt, auth guards on all pages.
Orders rebuilt: `order_items` with real quantities, tax/service charge applied
from Settings, table + waiter attribution, server-side pricing (the client
can no longer set its own total), status transitions restricted to sane
moves, and Dine-In orders occupy/free their table automatically. Kitchen
display added (Pending → Preparing → Ready), sharing the same status-change
endpoint as Orders. Chef can 86 an item straight from a ticket, which flips
its menu availability immediately (reuses the existing toggle from Menu
items, scoped to that one action only).

**Next** — payments (including the BUG-5 duplicate-payment guard), inventory,
reservations, then converting the remaining pages to the new layout.

See `AUDIT.md` and `AUDIT-ADDENDUM.md` for the full findings.

---

## Known limitations

- **Pages not yet converted** still use the old Vali styling. They are secured
  and functional, but visually inconsistent — that is expected mid-migration.
- **Five roles are supported in code**; only `admin` and `waiter` exist in the
  database so far. Assign the rest from **Users**.
- **Historical orders (1–20) have gaps migration 005 could not fill honestly**:
  5 orders stored their items as plain text with no price, 2 stored `[]` with
  a nonzero total, and several totals still do not reconcile with their items
  (pre-existing data problems documented as BUG-3/BUG-4 in `AUDIT-ADDENDUM.md`,
  surfaced — not fixed — by the migration's verification queries).
- **Payments do not yet enforce anything.** `orders.payment_status` exists and
  is backfilled from the current `payments` table, but nothing stops a second
  payment from being recorded against an already-paid order (BUG-5). That
  guard is part of the Payments phase.
- **Editing an order with pre-migration-005 line items** (free-text, no
  `menu_item_id`) drops those specific lines if you save changes — the cart
  can only represent real, priced menu items. `update_order.php` calls this
  out before you save.

---

## Before going live

- [ ] Set `APP_ENV` to `production` in `config/config.php`
- [ ] Move credentials into `config/config.local.php` (gitignored)
- [ ] Give MySQL a real user and password — not `root` with no password
- [ ] Delete `create-admin.php` and `_tools/`
- [ ] Delete `_archive/quarantine/`
- [ ] Enable MySQL strict mode (see the note in migration 001)
- [ ] Serve over HTTPS so session cookies get the `secure` flag
