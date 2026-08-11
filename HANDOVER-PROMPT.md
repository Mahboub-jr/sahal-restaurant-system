# Prompts for finishing the build

Two ready-to-paste prompts. Use **A** in this conversation, **B** if you ever
start a fresh session and the assistant has no memory of the project.

A note before you use either: asking for *everything in one reply* is the one
approach that reliably fails. Nobody can run PHP inside the assistant's
sandbox, so a single giant pass produces thousands of lines of unverified code
where the first error hides the next ten. Both prompts below are written to
work **one pass at a time**. Keep it that way.

---

## A — Continue in this conversation

> Continue with pass 3, the orders rebuild.
>
> I've run migrations 001, 002 and 004, and login.php, index.php and menu.php
> all load correctly.
>
> Work one pass at a time. Finish pass 3 completely, commit it, tell me exactly
> what to run and what to click to verify, then stop and wait for me. Don't
> start pass 4 until I confirm pass 3 works.
>
> For each pass: write the migration as a separate numbered file with
> verification queries and a rollback block, don't apply it yourself, follow the
> conventions already in README.md, and flag any judgement call where the data
> doesn't give a clear answer instead of guessing.

Swap "pass 3" for whichever pass you're on. The remaining passes are:

| # | Pass |
|---|---|
| 3 | Orders rebuild — `order_items`, quantities, tax/service charge, table + waiter |
| 4 | Kitchen display |
| 5 | Payments, invoice, duplicate-payment guard |
| 6 | Inventory + reservations |
| 7 | Convert the 26 legacy pages to the new layout |
| 8 | Reports on real SQL, polish, testing |

---

## B — Fresh session, no prior context

> I'm continuing work on a PHP 8 / MariaDB restaurant management system in
> `D:\Xampp\htdocs\Restuarent_system` (XAMPP, MariaDB 10.4, PHP 8.0).
>
> **Read these first, in order, before writing any code:**
> - `README.md` — architecture, conventions, current state
> - `AUDIT.md` — the original audit of the inherited codebase
> - `AUDIT-ADDENDUM.md` — findings verified against the real database, including
>   six live data bugs
> - `sql/restaurant_db_baseline_2026-08-11.sql` — the real schema
> - `git log` — five commits of history explaining what changed and why
>
> **What's already done:** audit, git baseline, two live data bugs fixed
> (`order_type` ENUM mismatch, orphaned menu item), and a foundation —
> `config/` with one PDO connection and `BASE_URL`, `includes/` with auth,
> RBAC, CSRF and secure uploads, a new Bootstrap 5 design system in `assets/`,
> and login, dashboard and menu rebuilt on it. All 41 pages have auth guards.
>
> **What's left:**
> 1. Orders rebuild — `order_items` table, quantities, tax and service charge
>    from settings, `table_id` and `user_id` on orders
> 2. Kitchen display (Pending → Preparing → Ready)
> 3. Payments, invoice, duplicate-payment guard
> 4. Inventory and reservations — neither exists
> 5. Convert the 26 remaining legacy pages to `includes/layout/`
> 6. Reports on real SQL, polish, testing
>
> **How I want you to work:**
> - One item at a time. Finish it, commit it, tell me what to run and what to
>   click, then stop and wait for me to confirm before starting the next.
> - Migrations go in `sql/migrations/` as separate numbered files with
>   verification queries and a rollback block. Never apply them yourself —
>   I run them in phpMyAdmin after backing up.
> - Follow the conventions in README.md: `e()` on all output, bound parameters
>   only, `csrf_field()` in every POST form, deletes as POST, `url()` for all
>   links and assets.
> - PHP 8.0 — no enums, no readonly, no `never`.
> - Never invent data. If the database doesn't answer a question, say so and
>   ask me.
> - If you find something broken that I haven't mentioned, tell me before
>   fixing it.
>
> Start with item 1. Confirm you've read the audit files first.

---

## Things worth telling it as you go

- **Paste real error text.** "It didn't work" costs a round trip; a stack trace
  gets it fixed immediately.
- **Say when a judgement call is wrong.** Migration 002 put `bariis` under
  Lunch because the data couldn't say — that kind of guess needs your correction.
- **Push back on scope creep.** If it starts rewriting something you didn't ask
  about, stop it.
- **Back up before every migration.** phpMyAdmin → `restaurant_db` → Export → Go.

## Things not to ask for

- "Do everything at once" — produces unverifiable code.
- "Rewrite it in Laravel/React" — throws away a working system.
- "Skip the migrations, just change the code" — the schema and the code have to
  move together.
