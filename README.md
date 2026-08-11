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
| `sql/migrations/003_menu_items_availability.sql` | Adds the availability switch |

Each file has verification queries at the bottom and a rollback block.

> **Before running 002**, open it and check `@new_category_id`. It assigns the
> orphaned item `bariis` to category 11 (Lunch) — change it if rice belongs
> somewhere else on your menu.

### 4. Open the app

<http://localhost/Restuarent_system/>

You will be sent to the sign-in page. Use an existing account from the `users`
table.

> If you do not know any password, open `create-admin.php` **once** to create
> `admin@example.com` / `admin123`, sign in, change the password from
> **Users**, then delete `create-admin.php`. It must not stay on a live server.

---

## What to check first

The foundation and three pages have been rebuilt. Load each and confirm it
behaves:

| Page | What to look for |
|---|---|
| `login.php` | New split-screen sign-in. Wrong password shows an error; 5 wrong attempts locks for a minute |
| `index.php` | Dashboard: KPI cards, 7-day revenue chart, status doughnut, recent orders |
| `menu.php` | Add, edit, delete, availability toggle, search, filter, sort, pagination |

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

**Next** — `order_items` migration (quantities and real reporting), kitchen
display, inventory, reservations, then converting the remaining pages to the
new layout.

See `AUDIT.md` and `AUDIT-ADDENDUM.md` for the full findings.

---

## Known limitations

- **Orders store items as a JSON blob.** No quantities: adding the same dish
  three times stores three copies. Best-seller figures are approximate until
  the `order_items` migration lands.
- **Pages not yet converted** still use the old Vali styling. They are secured
  and functional, but visually inconsistent — that is expected mid-migration.
- **Five roles are supported in code**; only `admin` and `waiter` exist in the
  database so far. Assign the rest from **Users**.
- **`tax_rate` and `service_charge`** are configurable in Settings but not yet
  applied to orders — `orders` has no column for them. Part of the orders rebuild.

---

## Before going live

- [ ] Set `APP_ENV` to `production` in `config/config.php`
- [ ] Move credentials into `config/config.local.php` (gitignored)
- [ ] Give MySQL a real user and password — not `root` with no password
- [ ] Delete `create-admin.php` and `_tools/`
- [ ] Delete `_archive/quarantine/`
- [ ] Enable MySQL strict mode (see the note in migration 001)
- [ ] Serve over HTTPS so session cookies get the `secure` flag
