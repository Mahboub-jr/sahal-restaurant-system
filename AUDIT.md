# Restaurant Management System — Project Audit

**Date:** 2026-08-11
**Auditor:** Incoming developer handover audit
**Scope:** Full repository inspection, no files modified or deleted.

---

## 0. Headline

The project is **considerably further along than the handover brief suggests**. The brief describes a system where "Menu Management is the first module that was started." In reality there are ~30 non-empty PHP pages covering orders, payments, tables, bookings, customers, employees, attendance, users, reports and settings. Much of it is functional.

The real problems are not "unfinished modules." They are:

1. **The application has effectively no authentication.** 39 of 41 PHP pages can be loaded by an anonymous visitor.
2. **Two incompatible auth systems exist**, and the one the login form uses writes a session shape that no other page reads.
3. **A shared include (`library/script.php`) throws a JavaScript exception on nearly every page**, which silently breaks the sidebar navigation.
4. **Orders have no line-item table.** Items are stored as a JSON blob in `orders.items`, which blocks quantities, reporting, and kitchen workflow.
5. **253 MB of Windows `.exe` installers are sitting inside the web root.**

---

## 1. PROJECT AUDIT

### 1.1 Actual directory structure

```
Restuarent_system/
├── css/            main.css, main.min.css                (Vali)
├── js/             jquery-3.7.0, bootstrap.min, main.js   (Vali)
│   └── plugins/    chart.js, jquery.dataTables.min.js
├── images/         banner.jpg, Sahal_logo.jpeg
├── pictures/       13 food photos (unused by app code)
├── uploads/        ~40 menu images (mixed naming conventions)
├── receites/       Abdi.pdf  (one stray generated receipt)
├── library/        conn.php, head.php, header.php, sidebar.php,
│                   footer.php, script.php, tcpdf/, wps_download/
├── vendor/         dompdf-master/
├── backend/        auth.php, menu.php (0 B), orders.php (0 B)
├── pages/          dashboard.php (0 B), menu.php, orders.php (0 B),
│                   reports.php (0 B)
├── 41 root-level .php files
└── 25 root-level Vali demo .html files
```

**No `.git` directory.** The project is not under version control. This is the single biggest process risk — there is no way to undo a bad change.

**No `.sql` file anywhere.** The database schema exists only inside the running MySQL instance. If that instance is lost, the schema is lost.

### 1.2 What currently works (A)

These pages are coherent, connect to the DB, and appear operational:

| Area | Files | Notes |
|---|---|---|
| Dashboard | `index.php` | Real KPIs from `orders`. Today/week sales, status counts, best sellers. |
| Menu | `menu.php` | Add + list + delete, joined to `categories`. Images render. |
| Categories | `categories.php` | Add / list / delete. |
| Order entry | `place_order.php`, `submit_order.php` | JS cart → JSON blob → `orders`. |
| Order management | `orders.php`, `order_history.php`, `cancelled_orders.php`, `update_order.php`, `cancel_order.php`, `complete_order.php` | Status transitions work. |
| Payments | `payments.php`, `receipt_payment.php` | |
| Tables & booking | `tables.php`, `table_booking.php`, `receipt_booking.php` | |
| Customers | `customers.php` | |
| Staff | `employees.php`, `attendance.php`, `attendance_report.php` | |
| Users | `manage_users.php`, `user_roles.php` | Passwords correctly hashed. |
| Reports | `reports.php`, `export_report.php`, `export_user_roles.php` | dompdf + TCPDF present. |
| Settings | `settings.php` | Restaurant name, tax, currency, invoice prefix, opening hours. |
| Receipts | `receipt.php` | |
| Login | `login.php` → `backend/auth.php` | Vali UI preserved, `password_verify` used. |

Vali assets (`css/main.css`, `js/main.js`, jQuery, Bootstrap) are present and correctly pathed **for root-level pages**.

### 1.3 Partially implemented (B)

- **Menu module** — Create, Read and Delete exist. **No Update/Edit.** No availability flag. No DataTables wiring (the plugin JS is present but never loaded by any PHP page). No pagination or search.
- **`pages/menu.php`** — A second, abandoned menu listing. Queries a table called `menu` (not `menu_items`) via PDO. Uses `../` includes. Links to `add-menu.php` which lives at root, not in `pages/`. This file cannot work.
- **Reports** — `reports.php` exists but `daily_report.php` and `sales_report.php` are 0 bytes.
- **Roles** — `user_roles.php` exists; `roles.php` is 0 bytes. No server-side enforcement anywhere.
- **Reservations** — `reservations.php` is 0 bytes. `table_booking.php` partially covers this need.

### 1.4 Broken (C)

**C1 — Session shape mismatch (critical).**
`login.php` posts to `backend/auth.php`, which sets:
```php
$_SESSION['user'] = $user;    // whole row
```
Every other page in the project expects:
```php
$_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']
```
Those are set only by the *other*, unused handler `auth.php` at root. **Result: after a successful login, `$_SESSION['user_role']` is never set, so every role check in the application is dead.**

**C2 — `library/script.php` crashes JavaScript on every page (critical).**
The file ends with hardcoded ECharts demo data and then:
```js
const salesChartElement = document.getElementById('salesChart');
const salesChart = echarts.init(salesChartElement, ...);
```
`#salesChart` and `#supportRequestChart` do not exist on `index.php`, `menu.php`, `orders.php`, or any other page. `echarts.init(null)` throws immediately. Because this script tag runs after `js/main.js`, the uncaught error means **the Vali sidebar treeview toggles and sidebar collapse stop working site-wide**. This is almost certainly the "styling problem" that was previously blamed on asset paths.

**C3 — `menu.php` line 5.**
```php
$_SESSION['user_role'] = $user['role'];
```
`$user` is undefined here and there is no `session_start()`. This emits warnings and, worse, is a template for accidentally *writing* to the session from a page that should only read it. The admin block below it (`<button>Add Employee</button>`, `<a href="?delete=123">Delete</a>`) is leftover scratch code rendered above `<!DOCTYPE html>`.

**C4 — `manage_users.php` redirects to a page that does not exist.**
All three handlers redirect to `users.php?msg=...`. There is no `users.php`. Every add/edit/delete of a user ends in a 404.

**C5 — `orders.php` filter block is dead code.**
Lines 7–37 build a `$where` clause and run a query into `$result`, then immediately overwrite `$sql` with an unfiltered status-only query. The customer/date filters do nothing.

**C6 — `library/footer.php`** references `img/hirgal-logo.pngk` — wrong extension, and there is no `img/` directory. Broken image on every page.

**C7 — `add-menu.php`** calls `session_start()` *after* output has already been sent by the DB/upload block above it → "headers already sent" warning. It also writes to a **different menu schema** than `menu.php` (see §3).

**C8 — Zero-byte files** that are linked or implied: `dashboard.php`, `reservations.php`, `roles.php`, `daily_report.php`, `sales_report.php`, `users_list.php`, `backend/menu.php`, `backend/orders.php`, `pages/dashboard.php`, `pages/orders.php`, `pages/reports.php`.

### 1.5 Redundant (D)

| Redundancy | Files |
|---|---|
| **Three DB connection files** | `db.php` (PDO), `conn.php` (mysqli), `library/conn.php` (mysqli, identical to `conn.php`) |
| **A fourth inline connection** | `add-menu.php` opens its own `new mysqli(...)` |
| **Two auth handlers** | `auth.php` (root, mysqli, unused) and `backend/auth.php` (PDO, used) |
| **Two menu pages** | `menu.php` (works) and `pages/menu.php` (broken) |
| **Two add-menu paths** | `add-menu.php` and the inline form inside `menu.php` |
| **Two PDF libraries** | `vendor/dompdf-master/` and `library/tcpdf/` |
| **25 Vali demo HTML pages** | `blank-page.html`, `bootstrap-components.html`, `docs.html`, `form-*.html`, `page-*.html`, `table-*.html`, `ui-cards.html`, `widgets.html`, `index.html` |
| **Unused image folder** | `pictures/` — 13 photos not referenced by any PHP file |

### 1.6 Security problems (E)

Ordered by severity.

**E1 — No access control. (Critical)**
Only `add-menu.php` and `tables.php` check for a session. Anyone who knows a URL can open `manage_users.php`, `settings.php`, `payments.php`, `reports.php` or `index.php` without logging in. Role-based access does not exist server-side at all.

**E2 — 253 MB of Windows executables in the web root. (Critical)**
```
library/wps_download/eaf19e78da1037899ada3bfea5ffe5ae-16_setup_XA_mui_Free.exe.601.1159.exe   247 MB
library/wps_wid.cid-652596770.1750601374.exe                                                    5.4 MB
```
These are WPS Office installer artifacts that were downloaded into the project by accident. They are publicly downloadable from the running server. They must be removed. **They also indicate the `library/` folder has been used as a download destination — worth a malware scan of the host machine.**

**E3 — Unrestricted image upload. (High)**
`menu.php`:
```php
$image_name = time() . '_' . basename($_FILES['food_image']['name']);
move_uploaded_file($tmp_name, $upload_dir . $image_name);
```
No MIME check, no extension whitelist, no size limit, no `getimagesize()` verification. A file named `shell.php` uploads successfully to `uploads/shell.php` and, under Apache with default config, **executes**. `add-menu.php` is worse — it uses `basename($_FILES['image']['name'])` with no prefix at all, so uploads can overwrite each other and filenames are entirely attacker-controlled.

**E4 — No CSRF protection anywhere. (High)**
Not a single token in the codebase. Every state-changing form — add user, delete menu item, change settings, cancel order — is forgeable.

**E5 — Destructive actions over GET. (High)**
```
categories.php?delete=N     customers.php?delete=N     employees.php?delete=N
manage_users.php?delete=N   payments.php?delete=N      tables.php?delete=N
table_booking.php?delete=N  cancel_order.php?id=N      complete_order.php?id=N
```
IDs are cast with `intval()` so SQL injection is blocked, but a prefetching browser, a crawler, or an `<img src>` in an email can delete records. Combined with E1 and E4, an unauthenticated visitor can wipe the menu.

**E6 — String-interpolated SQL throughout. (Medium)**
30 files use `mysqli_*` with `mysqli_real_escape_string` concatenation; only 4 use prepared statements. `real_escape_string` is applied fairly consistently, so this is not currently exploitable in most places, but it is one forgotten call away from being so. `settings.php` interpolates `{$settings['id']}` directly; `orders.php` interpolates four user-supplied filter values.

**E7 — Unescaped output (XSS). (Medium)**
`menu.php` echoes `{$item['name']}` and `{$item['description']}` raw. `categories.php` echoes name and description raw. `place_order.php` echoes `<?= $item['name'] ?>` raw into both HTML *and* a JavaScript `onclick` argument — an apostrophe in a food name breaks the page, and a `'` + script payload executes.

**E8 — `create-admin.php` is live in the web root. (Medium)**
Anyone can hit it. It is idempotent (it refuses if the admin exists), but it publishes the default credential pair `admin@example.com` / `admin123` in plain sight in the source.

**E9 — No session hardening. (Low)**
No `session_regenerate_id()` on login → session fixation. No httponly/samesite cookie params. No timeout.

**E10 — DB credentials hardcoded** as `root` with an empty password in four separate files.

### 1.7 Database / schema inconsistencies (F)

**Good news first:** the database name is **consistent**. Every connection file points at `restaurant_db`. The `restuarent_db` misspelling mentioned in the brief does not appear anywhere in the current code. Only the *folder* is misspelled (`Restuarent_system`), which is cosmetic.

**F1 — Two conflicting `menu_items` schemas.**

| Written by | Columns |
|---|---|
| `menu.php`, `place_order.php` (authoritative — has data) | `name, category_id, price, description, food_image` |
| `add-menu.php` (orphan) | `name, price, category, image, description` |

`add-menu.php` will fatal on insert against the live table. **Canonical is the `category_id` / `food_image` variant.**

**F2 — A third, phantom table `menu`.**
`pages/menu.php` queries `SELECT * FROM menu`. No other file references it. Almost certainly does not exist.

**F3 — `orders.items` is a JSON blob.** This is the most consequential schema decision in the project.
```php
$items_json = $_POST['items'];   // [{"id":1,"name":"Pizza","price":9.5}, ...]
INSERT INTO orders (customer_name, order_type, items, total_amount, status, created_at)
```
Consequences:
- **No quantity.** `place_order.php` pushes a duplicate object per click. Ordering 3 pizzas stores three copies.
- **No referential integrity.** Menu item names are frozen at order time; deleting a menu item orphans nothing but also breaks nothing detectably.
- **Reporting is done in PHP, not SQL.** `index.php` fetches *every order row ever* and decodes JSON in a loop to compute best-sellers. This will not scale past a few thousand orders.
- **"Sales by category" and "revenue by item" are impossible** without full-table scans.
- No unit price snapshot, no line subtotal, no per-line notes for the kitchen.

**F4 — No `order_items` table exists.** Required by every remaining phase (kitchen display, invoicing, inventory deduction, reports).

**F5 — `orders` has no `table_id` and no `user_id`.** Dine-in orders cannot be attached to a restaurant table or a waiter, despite `tables` and `table_bookings` existing.

**F6 — `tables` is a reserved-ish, ambiguous table name.** `SELECT * FROM tables` is legal but confusing. `restaurant_tables` is clearer.

**F7 — Live schema is unverified.** All of the above is inferred from SQL strings in the source. There is no dump in the repo and the sandbox cannot reach your MySQL. **This must be confirmed before any migration** — see §7.

Tables inferred to exist: `users`, `categories`, `menu_items`, `orders`, `customers`, `payments`, `tables`, `table_bookings`, `employees`, `attendance`, `settings`.

---

## 2. CURRENT ARCHITECTURE

```
Browser
   │
   ├── login.php ──POST──> backend/auth.php ──PDO──> users
   │                            └─ sets $_SESSION['user']  ← read by nobody
   │
   └── <any page>.php   (no guard on 39 of 41)
           │
           ├── include library/conn.php   (mysqli, global $conn)
           ├── inline SQL + inline HTML in the same file
           ├── include library/head.php     ← still Vali branding/meta
           ├── include library/sidebar.php  ← restaurant nav, good
           ├── include library/header.php   ← fake notifications, .html links
           ├── include library/footer.php   ← broken logo path
           └── include library/script.php   ← throws JS error, kills sidebar
```

Pattern: **page controller + view in one file, mysqli procedural, no layer separation.** That is acceptable for a project of this size and I do not propose replacing it with a framework. What it needs is a thin, consistent foundation underneath it.

---

## 3. RECOMMENDED ARCHITECTURE

Deliberately conservative. Root-level page files stay where they are so existing links keep working.

```
Restuarent_system/
├── config/
│   ├── config.php        BASE_URL, paths, timezone, error mode, session cookie params
│   └── database.php      ONE PDO connection, exceptions on, emulation off
├── includes/
│   ├── bootstrap.php     single entry: config + db + helpers + session_start
│   ├── auth.php          require_login(), require_role([...]), current_user(), csrf_token(), csrf_verify()
│   ├── helpers.php       e(), redirect(), flash(), money(), upload_image()
│   ├── head.php          restaurant branding, per-page $pageTitle
│   ├── header.php        real user name/role, working logout link
│   ├── sidebar.php       role-filtered nav
│   ├── footer.php
│   └── scripts.php       core JS only; page-specific JS via $pageScripts
├── actions/              POST-only, CSRF-checked, no HTML output
│   ├── menu_store.php  menu_update.php  menu_delete.php
│   ├── order_*.php     user_*.php  ...
├── uploads/menu/
├── sql/
│   ├── schema.sql        full canonical DDL
│   └── migrations/       001_add_order_items.sql, 002_...
├── _archive/             Vali demo HTML + superseded files (kept, not served)
└── *.php                 existing pages, progressively refactored
```

**Base URL solution:** `config/config.php` defines `BASE_URL` once (auto-detected from `$_SERVER['SCRIPT_NAME']`). All assets become `<?= BASE_URL ?>css/main.css`. This fixes subdirectory pages permanently without `../` juggling.

**Session shape (single standard):**
```php
$_SESSION['user'] = ['id'=>…, 'name'=>…, 'email'=>…, 'role'=>…];
```
Chosen because `backend/auth.php` — the handler actually in use — already sets `$_SESSION['user']`. Accessor `current_user()` hides the shape from pages, so the ~12 sites reading `$_SESSION['user_role']` get migrated once and never again.

---

## 4. RECOMMENDED DATABASE SCHEMA

Additive where possible. Only `orders` requires real migration.

```
users(id, name, email UNIQUE, password, role ENUM, is_active, created_at)
categories(id, name UNIQUE, description, created_at)
menu_items(id, category_id FK, name, price, description, image,
           is_available TINYINT DEFAULT 1, created_at, updated_at)
restaurant_tables(id, table_number UNIQUE, capacity, status ENUM, created_at)
customers(id, name, phone, email, address, created_at)

orders(id, order_number UNIQUE, customer_id FK NULL, customer_name,
       order_type ENUM('dine_in','takeaway'),
       table_id FK NULL, user_id FK NULL,
       subtotal, discount, tax, total_amount,
       status ENUM('Pending','Confirmed','Preparing','Ready','Served','Completed','Cancelled'),
       payment_status ENUM('unpaid','partial','paid'),
       notes, created_at, updated_at)

order_items(id, order_id FK CASCADE, menu_item_id FK, item_name,
            unit_price, quantity, subtotal, notes)          ← NEW

payments(id, order_id FK, amount, method ENUM, status, cashier_id FK, paid_at)
reservations(id, customer_name, phone, table_id FK, reserved_at,
             guests, status ENUM, notes, created_at)
inventory_items(id, name, quantity, unit, min_stock, supplier, cost, updated_at)
stock_movements(id, inventory_item_id FK, change_qty, reason, user_id FK, created_at)
employees / attendance                                       ← keep as-is
settings(id, ...)                                            ← keep as-is
```

**Migration strategy for `orders.items` → `order_items`:**
Non-destructive. Add `order_items`, run a one-off PHP backfill that decodes each `orders.items` JSON blob and inserts rows (collapsing duplicates into `quantity`), verify totals reconcile against `orders.total_amount`, then rename the column to `items_legacy_json` rather than dropping it. Nothing is lost and rollback is trivial.

---

## 5. ISSUES / RISKS SUMMARY

| # | Issue | Severity | Phase |
|---|---|---|---|
| E1 | No auth guard on 39/41 pages | **Critical** | 1 |
| C1 | Session shape mismatch → all role checks dead | **Critical** | 1 |
| E2 | 253 MB of .exe files in web root | **Critical** | 1 |
| C2 | `script.php` JS crash breaks sidebar site-wide | **High** | 1 |
| E3 | Unrestricted upload → RCE via `uploads/x.php` | **High** | 2 |
| E4 | No CSRF tokens | **High** | 2 |
| E5 | GET-based deletes | **High** | 2 |
| F3 | Orders stored as JSON blob | **High** | 3 |
| — | No version control, no schema dump | **High** | 1 |
| E7 | XSS in menu/categories/place_order | Medium | 2 |
| E6 | Interpolated SQL | Medium | ongoing |
| E8 | `create-admin.php` public | Medium | 1 |
| C4 | `manage_users.php` → 404 | Medium | 2 |
| C5 | `orders.php` dead filter code | Medium | 2 |
| D | 3 connection files, 2 auth handlers | Medium | 1 |
| E9 | No session hardening | Low | 2 |

---

## 6. FILE DISPOSITION

**Preserve (working, refactor in place):**
`index.php`, `menu.php`, `categories.php`, `orders.php`, `order_history.php`, `cancelled_orders.php`, `place_order.php`, `submit_order.php`, `update_order.php`, `payments.php`, `tables.php`, `table_booking.php`, `customers.php`, `employees.php`, `attendance.php`, `attendance_report.php`, `manage_users.php`, `user_roles.php`, `reports.php`, `settings.php`, `receipt*.php`, `export_*.php`, `login.php`, `logout.php`, `library/sidebar.php`, all of `css/`, `js/`, `images/`, `uploads/`, `vendor/`, `library/tcpdf/`

**Modify:**
`backend/auth.php` (regenerate session id, standard shape, CSRF), `library/head.php` (de-Vali), `library/header.php` (real user, real links), `library/footer.php` (fix logo), `library/script.php` (remove crashing demo charts), plus an auth guard added to every page above.

**Safe to remove — after confirmation, nothing removed yet:**
| File | Reason |
|---|---|
| `library/wps_download/*.exe`, `library/wps_wid.*.exe` | 253 MB, not project files, security risk |
| `auth.php` (root) | Superseded by `backend/auth.php` |
| `conn.php`, `library/conn.php` | Replaced by `config/database.php` |
| `add-menu.php` | Wrong schema; `menu.php` supersedes it |
| `pages/menu.php` | Wrong table name, broken paths |
| `backend/menu.php`, `backend/orders.php`, `pages/*.php` | 0 bytes |
| `dashboard.php`, `daily_report.php`, `sales_report.php`, `users_list.php`, `roles.php`, `reservations.php` | 0 bytes |
| `create-admin.php` | Move to `sql/` as a seed, remove from web root |
| 25 Vali demo `.html` files | Move to `_archive/` (keep `page-invoice.html` as receipt reference) |
| `pictures/` | Unreferenced |
| `receites/Abdi.pdf` | Stray output |

Recommendation: move to `_archive/`, do not `rm`, until the system is verified working.

---

## 7. IMPLEMENTATION ROADMAP

**Phase 1 — Safety & foundation**
1. Initialise git; commit current state untouched. Export schema dump to `sql/`.
2. Quarantine the `.exe` files.
3. `config/` + `includes/bootstrap.php` — one PDO connection, `BASE_URL`.
4. `includes/auth.php` — `require_login()`, `require_role()`, session hardening, CSRF helpers.
5. Fix `backend/auth.php`; add guard to all pages.
6. Repair `script.php` / `head.php` / `header.php` / `footer.php`.

**Phase 2 — Harden & complete menu** — secure uploads, CSRF on all forms, POST deletes, escape output, menu Edit, availability toggle, DataTables.

**Phase 3 — Orders** — `order_items` migration + backfill, quantities, table/waiter assignment, tax & discount from `settings`.

**Phase 4 — Kitchen display.**
**Phase 5 — Payments & invoice** (adapt `page-invoice.html`).
**Phase 6 — Reservations** (build on `table_bookings`).
**Phase 7 — Inventory.**
**Phase 8 — RBAC rollout across all five roles.**
**Phase 9 — Dashboard & reports on real SQL** (replace the PHP-side JSON aggregation).
**Phase 10 — Security audit, polish, testing, README + deployment guide.**

---

## 8. EXACT FIRST TASK

**Establish ground truth and a safety net — before any code changes.**

Three steps, in order:

1. **Version control.** `git init` + initial commit of the repository exactly as it is today. Nothing else can safely proceed without an undo button.
2. **Schema export.** Run `_tools/db_inspect.php` (delivered with this audit) under XAMPP. It is read-only — it runs `SHOW CREATE TABLE` and row counts against `restaurant_db` and prints the result. Paste the output back so the recommendations in §4 can be validated against reality instead of inferred from SQL strings.
3. **Quarantine the executables.** Move the two `.exe` files out of `htdocs` entirely.

Only after the real schema is confirmed does Phase 1 step 3 (the `config/` foundation) begin.
