# Audit Addendum — Schema Verified Against Live Database

**Date:** 2026-08-11
**Source:** phpMyAdmin dump of `restaurant_db`, MariaDB 10.4.32, PHP 8.0.30
**Baseline stored at:** `sql/restaurant_db_baseline_2026-08-11.sql`

This addendum supersedes the inferred conclusions in §1.7 and §4 of `AUDIT.md`.

---

## 1. What the audit got right

- Database name is `restaurant_db`, consistent. No `restuarent_db` exists.
- Canonical `menu_items` schema is the `category_id` / `food_image` variant. **`add-menu.php` is confirmed dead** — it inserts into columns `category` and `image` which do not exist. It would fatal on every submission.
- **No `menu` table exists.** `pages/menu.php` is confirmed unrunnable.
- `orders.items` is a `text` blob. No `order_items` table.
- All 7 user passwords are bcrypt `$2y$10$`. No plaintext, no MD5.
- No `inventory_items`, `stock_movements`, `reservations`, or `roles` tables.

## 2. What the audit got wrong

| Audit said | Reality |
|---|---|
| `menu_items` may lack `created_at` | It has `created_at timestamp DEFAULT current_timestamp()` |
| `orders` may need `updated_at` | Already has it, with `ON UPDATE current_timestamp()` |
| Foreign keys likely absent | **Three FK constraints exist**: `attendance→employees`, `payments→orders`, `payments→customers`, `table_bookings→customers`, `table_bookings→tables` |
| `users` may need role normalisation | `role` is `varchar(50)`, not ENUM. Only `admin` and `waiter` are in use |

**11 tables, all InnoDB, utf8mb4_general_ci:**
`attendance` (3), `categories` (9), `customers` (5), `employees` (4), `menu_items` (20), `orders` (20), `payments` (9), `settings` (1), `tables` (11), `table_bookings` (3), `users` (7)

---

## 3. NEW — live bugs found in the data

### BUG-1 — `order_type` is silently discarded on every new order. (Critical)

```sql
`order_type` enum('Dine-In','Takeaway') NOT NULL
```

But `place_order.php` submits:

```html
<option>Dine In</option>      <!-- space, no hyphen -->
<option>Take Away</option>    <!-- two words -->
```

Neither string matches the ENUM. MariaDB is running in non-strict mode, so instead of erroring it **inserts the empty string `''` and continues**. The evidence is in the data — the three most recent real orders:

| id | customer | order_type | created |
|---|---|---|---|
| 17 | Nuur Cali | `''` | 2025-06-08 |
| 19 | Faisal | `''` | 2025-06-24 |
| 20 | RAxma | `''` | 2025-06-24 |

Orders 1–16 have correct values because they were seeded directly via SQL, not through the form. **Every order placed through the actual UI since the form was written has lost its dine-in/takeaway flag.** This silently breaks any takeaway-vs-dine-in reporting and will break table assignment in Phase 3.

Fix is one line in `place_order.php` — use `value="Dine-In"` / `value="Takeaway"`. The three damaged rows cannot be recovered automatically; they need a manual call or to be left as unknown.

### BUG-2 — A menu item is invisible in the menu manager. (High)

`menu_items` id 2, `bariis`, has `category_id = 1`. **No category with id 1 exists** — `categories` starts at id 4. There is no FK on `menu_items.category_id`, so the orphan was allowed.

Consequence:

- `menu.php` uses `JOIN categories c ON m.category_id = c.id` — an **inner** join, so `bariis` **does not appear in the menu list and cannot be edited or deleted through the UI.**
- `place_order.php` uses `SELECT * FROM menu_items` with no join, so **`bariis` is still orderable by staff.** It appears in 3 existing orders.

An item you cannot manage but customers can order. Needs a category reassignment, then a FK constraint to prevent recurrence.

### BUG-3 — `orders.items` holds three incompatible formats. (High — affects migration)

| Format | Rows | Example |
|---|---|---|
| Valid JSON with `id` | 19, 20 | `[{"id":"15","name":"Burger","price":2}]` |
| Valid JSON **without** `id` | 1,2,3,7,9,11,12,14,16,17,18 | `[{"name":"Bariis","price":1.08}]` |
| Empty array | 10, 13 | `[]` — yet `total_amount` is 5.00 and 49.00 |
| **Plain text, not JSON** | 4,5,6,8,15 | `Sambusa x3, Tea` / `Burger iyo Coke` |

Only 2 of 20 rows carry a `menu_item_id`. The migration to `order_items` therefore cannot rely on foreign keys for historical data — it must fall back to name matching, then to storing the raw text with a null `menu_item_id`. My §4 plan assumed uniform JSON; it does not hold.

### BUG-4 — `total_amount` does not reconcile with `items`. (Medium)

| Order | Items sum | `total_amount` |
|---|---|---|
| 1 | 65.00 | 8.50 |
| 3 | 5.00 | 9.75 |
| 9 | 1.08 | 7.00 |
| 13 | 0.00 (`[]`) | 49.00 |
| 19 | 5.00 | 5.00 ✓ |
| 20 | 7.75 | 7.75 ✓ |

Only orders created through `place_order.php` reconcile. The seeded rows do not. The backfill must **not** assume the two agree — it should preserve `total_amount` as authoritative and flag the mismatches rather than recomputing.

### BUG-5 — Order 19 was paid twice. (Medium)

`payments` rows 7 and 8 are both `order_id=19, 5.00, Cash`, one minute apart. Order 19's total is 5.00. There is no guard against overpayment or duplicate submission, and `orders` has no `payment_status` column to mark it settled. Payment 4 is 45.00 against order 4 whose total is 4.25.

### BUG-6 — Tax and service charge are configured but never applied. (Medium)

`settings` holds `tax_rate = 5.00` and `service_charge = 10.00`. The `orders` table has **no `subtotal`, `tax`, `discount`, or `service_charge` columns**, and no code reads those settings during order creation. The settings page is writing configuration that nothing consumes.

### BUG-7 — `users` and `employees` are two unrelated people tables. (Medium)

`employees` (Ilyaas, Haaji, Mashmash, Abdikarin) has `position` as free text — `'waiter'`, `'cheif'` [sic]. `users` has `role` — `admin`, `waiter`. There is no link between them. Employee 3 `Mashmash` uses `admin@example.com`, the same address as user 1 `Admin`. Orders cannot be attributed to a waiter because `orders` has no `user_id`.

---

## 4. Corrected migration plan for `orders.items`

Revised for the three data formats found:

```
1. CREATE TABLE order_items (id, order_id FK CASCADE, menu_item_id FK NULL,
                             item_name, unit_price, quantity, subtotal, notes)
2. ALTER orders ADD subtotal, discount, tax, service_charge, payment_status,
                    table_id NULL, user_id NULL, order_number
3. Backfill script, per row:
     a. json_decode(items)
        - array & non-empty  -> one order_items row per element,
                                collapse duplicates into quantity,
                                resolve menu_item_id by id, else by name match,
                                else NULL
        - array & empty      -> no rows; log for review
        - not JSON           -> single order_items row, item_name = raw text,
                                unit_price = NULL, quantity = 1, log for review
     b. Never recompute total_amount. Compare against the sum and write a
        reconciliation report; do not silently "fix" the 4 mismatched rows.
4. RENAME orders.items -> items_legacy_json.  Do not drop.
5. Verification query must show: 20 orders in, 20 accounted for,
   0 rows lost, mismatches listed explicitly.
```

## 5. Corrected constraint work

Add after data is cleaned, not before:

```sql
-- only after menu_items.category_id = 1 is reassigned
ALTER TABLE menu_items
  ADD CONSTRAINT fk_menu_items_category
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT;

ALTER TABLE users ADD UNIQUE KEY uq_users_email (email);   -- already UNIQUE, verified
ALTER TABLE categories ADD UNIQUE KEY uq_categories_name (name);
ALTER TABLE tables ADD UNIQUE KEY uq_tables_number (table_number);
```

Note `tables` currently allows duplicate `table_number`, and the existing data has `T01`–`T10` plus `T011` — a likely typo for `T11`.

---

## 6. Revised priority

BUG-1 and BUG-2 are corrupting or hiding data **today** and are both small fixes. They should be handled during Phase 2 alongside the menu work, not deferred to Phase 3. The remaining order-schema changes stay in Phase 3 as planned.
