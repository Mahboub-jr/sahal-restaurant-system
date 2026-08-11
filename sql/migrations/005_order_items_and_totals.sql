-- =====================================================================
-- Migration 005 — order_items, order totals, table + waiter
-- Date: 2026-08-11
-- Implements: AUDIT-ADDENDUM.md §4 corrected migration plan
--             (follows on from BUG-3, BUG-4, BUG-6, BUG-7)
-- =====================================================================
--
-- WHY
-- ---
-- `orders.items` is a text blob (BUG-3). No quantities exist -- ordering
-- the same dish three times stores three copies. Tax and service charge
-- are configured in Settings but never applied (BUG-6). Orders cannot be
-- attributed to a table or a waiter (BUG-7).
--
-- WHAT THIS DOES
-- --------------
-- 1. Creates `order_items` -- one row per line, with a real `quantity`.
-- 2. Adds `order_number`, `table_id`, `user_id`, `subtotal`, `discount`,
--    `tax`, `service_charge`, `payment_status` to `orders`.
-- 3. Backfills `order_items` from the 20 existing orders' `items` column,
--    handling the three formats BUG-3 found:
--      - JSON with an id           -> menu_item_id set directly
--      - JSON without an id        -> matched to menu_items by EXACT name
--                                      (case-insensitive), else left NULL
--      - empty array / plain text  -> see JUDGEMENT CALLS below
-- 4. Renames `orders.items` to `orders.items_legacy_json`. New code stops
--    writing to it, but nothing is dropped -- the original text survives.
-- 5. Adds FKs: order_items.order_id -> orders, order_items.menu_item_id ->
--    menu_items, orders.table_id -> tables, orders.user_id -> users.
--
-- JUDGEMENT CALLS -- PLEASE REVIEW
-- ---------------------------------
-- * Orders 4, 5, 6, 8, 15 stored `items` as plain text ("Sambusa x3, Tea"),
--   not JSON. Per the addendum's plan these become ONE order_items row
--   each, with the raw text as item_name, unit_price NULL, quantity 1.
--   Nothing attempts to parse "x3" into a quantity or to match the words
--   to a real menu item -- that would be guessing at intent the data does
--   not confirm. Fix these by hand in the Orders screen once this lands.
-- * Order 12's line "Chips" is NOT matched to menu_items 16 'Fries
--   (Chips)', and order 15's plain-text "Bariis" is NOT matched to
--   menu_items 2 'bariis', even though both look related. Only an EXACT
--   (case-insensitive) name match is trusted; fuzzy matching risks
--   attributing history to the wrong dish. Edit by hand if you know better.
-- * Orders 10 and 13 stored `items` as `[]` with a non-zero total_amount
--   (5.00 and 49.00 respectively). There is nothing to expand, so they get
--   zero order_items rows and a NULL subtotal. This is BUG-4, pre-existing
--   and unchanged by this migration -- see the reconciliation query below.
-- * `tax` and `service_charge` are left NULL on all 20 historical orders.
--   BUG-6 established no order in this system has ever had tax or a
--   service charge applied, so there is no historical rate to backfill
--   honestly. `discount` defaults to 0.00 for the same reason.
-- * `table_id` and `user_id` are left NULL on all 20 historical orders --
--   `customer_name` is free text with no link to a table or a user account.
-- * `payment_status` is derived from the EXISTING `payments` table (paid
--   sum >= total -> Paid, paid sum > 0 -> Partially Paid, else Unpaid),
--   counting only rows where payments.status = 'Paid'. Order 19's double
--   payment (BUG-5) will therefore show as "Paid" -- this migration does
--   not touch payments; fixing BUG-5 is Phase 5.
--
-- SAFETY
-- ------
-- Additive except for the items -> items_legacy_json rename, which keeps
-- all data (nothing is dropped). MariaDB commits DDL implicitly, so the
-- transaction wrapper below documents intent rather than guaranteeing an
-- atomic rollback -- restore from your backup if any step fails partway.
-- BACK UP restaurant_db BEFORE RUNNING (phpMyAdmin > Export).
-- Requires migrations 001, 002 and 004 already applied.
-- =====================================================================

START TRANSACTION;

-- --- 1. order_items ---------------------------------------------------
CREATE TABLE `order_items` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `order_id`     INT(11) NOT NULL,
  `menu_item_id` INT(11) DEFAULT NULL,
  `item_name`    VARCHAR(150) NOT NULL,
  `unit_price`   DECIMAL(10,2) DEFAULT NULL,
  `quantity`     INT(11) NOT NULL DEFAULT 1,
  `subtotal`     DECIMAL(10,2) GENERATED ALWAYS AS (`unit_price` * `quantity`) STORED,
  `notes`        VARCHAR(255) DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_menu_item` (`menu_item_id`),
  CONSTRAINT `chk_order_items_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- 2. New columns on orders ------------------------------------------
ALTER TABLE `orders`
  ADD COLUMN `order_number`   VARCHAR(20) DEFAULT NULL AFTER `id`,
  ADD COLUMN `table_id`       INT(11) DEFAULT NULL AFTER `order_type`,
  ADD COLUMN `user_id`        INT(11) DEFAULT NULL AFTER `table_id`,
  ADD COLUMN `subtotal`       DECIMAL(10,2) DEFAULT NULL AFTER `total_amount`,
  ADD COLUMN `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
  ADD COLUMN `tax`            DECIMAL(10,2) DEFAULT NULL AFTER `discount`,
  ADD COLUMN `service_charge` DECIMAL(10,2) DEFAULT NULL AFTER `tax`,
  ADD COLUMN `payment_status` ENUM('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Unpaid' AFTER `status`;

-- The blob stays, renamed and made nullable, so no historical order text
-- is destroyed and new code has nothing it must fake to satisfy NOT NULL.
ALTER TABLE `orders`
  CHANGE COLUMN `items` `items_legacy_json` TEXT NULL DEFAULT NULL;

-- --- 3. Backfill order_items --------------------------------------------
-- One INSERT per historical line item, written out explicitly rather than
-- as a generic script: MariaDB 10.4 has no JSON_TABLE() to explode a JSON
-- array into rows, and the three data formats in BUG-3 need a judgement
-- call per row, not a one-size algorithm. Source data verified against
-- sql/restaurant_db_baseline_2026-08-11.sql and AUDIT-ADDENDUM.md.

-- Order 1 -- JSON, no id, no name match. Does not reconcile (BUG-4: total 8.50).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (1, NULL, 'shawarma iyo Bariis iyo shax', 65.00, 1);

-- Order 2 -- JSON, no id, no name match. Reconciles (4.00 = 4.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (2, NULL, 'Rooti iyo Maraq', 4.00, 1);

-- Order 3 -- JSON, no id, no name match. Does not reconcile (BUG-4: total 9.75).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (3, NULL, 'Grilled fish', 5.00, 1);

-- Order 4 -- plain text, not JSON. Price unknown.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (4, NULL, 'Sambusa x3, Tea', NULL, 1);

-- Order 5 -- plain text, not JSON. Price unknown.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (5, NULL, 'Goat Meat with Canjeero, Milk', NULL, 1);

-- Order 6 -- plain text, not JSON (trailing \r\n trimmed). Price unknown.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (6, NULL, 'shawarma iyo Bariis iyo shax', NULL, 1);

-- Order 7 -- JSON, no id, no name match. Reconciles (5.00 = 5.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (7, NULL, 'Grilled meat', 5.00, 1);

-- Order 8 -- plain text, not JSON. Price unknown.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (8, NULL, 'Burger iyo Coke', NULL, 1);

-- Order 9 -- JSON, no id, matched to menu_items 2 'bariis' by exact name.
-- unit_price kept at the order's own 1.08, NOT today's menu price of 5.00
-- -- history is what was charged, not what the item costs now.
-- Does not reconcile (BUG-4: total_amount is 7.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (9, 2, 'Bariis', 1.08, 1);

-- Order 10 -- items = '[]'. Nothing to expand -- see JUDGEMENT CALLS above.

-- Order 11 -- JSON, no id, matched to menu_items 2 'bariis'. Reconciles.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (11, 2, 'bariis', 5.00, 1);

-- Order 12 -- JSON, two lines, neither matched ('Chips' is not an exact
-- match for menu_items 16 'Fries (Chips)'). Reconciles (6.70+3.00=9.70).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (12, NULL, 'Shawarma iyo Tea', 6.70, 1),
  (12, NULL, 'Chips', 3.00, 1);

-- Order 13 -- items = '[]'. Nothing to expand -- see JUDGEMENT CALLS above.

-- Order 14 -- JSON, two lines, both matched by exact name. Reconciles (12.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (14, 2, 'bariis', 5.00, 1),
  (14, 15, 'Burger', 7.00, 1);

-- Order 15 -- plain text "Bariis" (no JSON quotes/brackets -- not valid
-- JSON). Treated as plain text per the addendum plan, so NOT matched to
-- menu_items 2, even though it is almost certainly the same dish as
-- orders 9/11/14/18/19/20. Price unknown.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (15, NULL, 'Bariis', NULL, 1);

-- Order 16 -- JSON, no id, no name match. Reconciles (8.00 = 8.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (16, NULL, 'Camel meat', 8.00, 1);

-- Order 17 -- JSON, no id, no name match. Reconciles (7.00 = 7.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (17, NULL, 'Muufo iyo Maraq', 7.00, 1);

-- Order 18 -- JSON, two lines, both matched by exact name. Reconciles (6.00).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (18, 2, 'bariis', 5.00, 1),
  (18, 3, 'Xalwo', 1.00, 1);

-- Order 19 -- JSON WITH an id (menu_item_id 2 given directly). Reconciles.
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (19, 2, 'bariis', 5.00, 1);

-- Order 20 -- JSON WITH ids for all three lines. Reconciles (7.75).
INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
  (20, 15, 'Burger', 2.00, 1),
  (20, 2, 'bariis', 5.00, 1),
  (20, 9, 'Sambuus Macaan', 0.75, 1);

-- --- 4. Backfill orders' new columns from what is now known -------------

-- subtotal = sum of the order_items just inserted (NULL where an order has
-- none -- orders 10 and 13 -- matching BUG-4 rather than hiding it).
UPDATE orders o
  LEFT JOIN (
    SELECT order_id, SUM(subtotal) AS items_subtotal
      FROM order_items
     GROUP BY order_id
  ) x ON x.order_id = o.id
   SET o.subtotal = x.items_subtotal;

-- order_number: deterministic from id, so it reproduces identically if
-- this UPDATE is ever re-run.
UPDATE orders
   SET order_number = CONCAT('ORD-', LPAD(id, 6, '0'))
 WHERE order_number IS NULL;

-- payment_status: derived from the existing payments table (Paid rows only).
UPDATE orders o
  LEFT JOIN (
    SELECT order_id, SUM(amount) AS paid_sum
      FROM payments
     WHERE status = 'Paid'
     GROUP BY order_id
  ) p ON p.order_id = o.id
   SET o.payment_status = CASE
         WHEN p.paid_sum IS NULL OR p.paid_sum = 0 THEN 'Unpaid'
         WHEN p.paid_sum >= o.total_amount           THEN 'Paid'
         ELSE 'Partially Paid'
       END;

-- --- 5. Constraints -------------------------------------------------
ALTER TABLE `orders`
  ADD UNIQUE KEY `uq_orders_order_number` (`order_number`),
  ADD KEY `idx_orders_table` (`table_id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order`     FOREIGN KEY (`order_id`)     REFERENCES `orders` (`id`)     ON DELETE CASCADE  ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- --- 6. Verify ---------------------------------------------------------
--     Expect: 23 order_items rows, spanning 18 distinct orders.
SELECT COUNT(*) AS order_item_rows, COUNT(DISTINCT order_id) AS orders_with_items
  FROM order_items;

--     Expect: exactly orders 10 and 13 (items = '[]', BUG-3/BUG-4).
SELECT o.id, o.customer_name, o.total_amount
  FROM orders o
  LEFT JOIN order_items oi ON oi.order_id = o.id
 WHERE oi.id IS NULL
 ORDER BY o.id;

--     Reconciliation report (BUG-4). Rows here are pre-existing data
--     problems that this migration surfaces, not something it fixes.
SELECT id, customer_name, subtotal, total_amount
  FROM orders
 WHERE subtotal IS NULL OR subtotal <> total_amount
 ORDER BY id;

--     Expect: 20 rows, every one with an order_number and a payment_status.
SELECT id, order_number, payment_status, subtotal, discount, tax, service_charge, total_amount
  FROM orders
 ORDER BY id;

--     Expect: zero rows (no order_items point at a missing order or menu item).
SELECT oi.id, oi.order_id, oi.menu_item_id
  FROM order_items oi
  LEFT JOIN orders o ON o.id = oi.order_id
  LEFT JOIN menu_items m ON m.id = oi.menu_item_id
 WHERE o.id IS NULL OR (oi.menu_item_id IS NOT NULL AND m.id IS NULL);

COMMIT;

-- =====================================================================
-- ROLLBACK (only if nothing has been written since -- new orders placed
-- after this migration runs will have NULL items_legacy_json, which the
-- last step below will refuse to make NOT NULL again until you fix that)
-- =====================================================================
-- ALTER TABLE `order_items` DROP FOREIGN KEY `fk_order_items_order`;
-- ALTER TABLE `order_items` DROP FOREIGN KEY `fk_order_items_menu_item`;
-- ALTER TABLE `orders` DROP FOREIGN KEY `fk_orders_table`;
-- ALTER TABLE `orders` DROP FOREIGN KEY `fk_orders_user`;
-- ALTER TABLE `orders`
--   DROP KEY `uq_orders_order_number`,
--   DROP KEY `idx_orders_table`,
--   DROP KEY `idx_orders_user`;
-- ALTER TABLE `orders` CHANGE COLUMN `items_legacy_json` `items` TEXT NOT NULL;
-- ALTER TABLE `orders`
--   DROP COLUMN `order_number`,
--   DROP COLUMN `table_id`,
--   DROP COLUMN `user_id`,
--   DROP COLUMN `subtotal`,
--   DROP COLUMN `discount`,
--   DROP COLUMN `tax`,
--   DROP COLUMN `service_charge`,
--   DROP COLUMN `payment_status`;
-- DROP TABLE `order_items`;
-- =====================================================================
