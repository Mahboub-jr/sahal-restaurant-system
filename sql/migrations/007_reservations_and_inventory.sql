-- =====================================================================
-- Migration 007 — reservations, inventory_items, stock_movements
-- Date: 2026-08-11
-- Implements: AUDIT.md §4 target schema, Phases 6-7 of the roadmap
-- =====================================================================
--
-- WHY
-- ---
-- `reservations.php` has been a 0-byte file since before this rebuild
-- started (AUDIT.md C8) -- `table_booking.php` covers part of the need on
-- the thin `table_bookings` table (customer_id, table_id, booking_time,
-- status only -- no party size, no notes, no link to who took the call).
-- `inventory_items` and `stock_movements` do not exist at all.
--
-- WHAT THIS DOES
-- --------------
-- 1. Creates `reservations` -- customer_name as free text (same choice
--    already made for orders and payments) with an OPTIONAL link to a
--    `customers` row, plus party_size, notes, table_id, and user_id (who
--    took the reservation -- orders.user_id set the precedent).
-- 2. Backfills the 3 existing `table_bookings` rows into it.
-- 3. Renames `table_bookings` to `table_bookings_legacy`. Nothing dropped.
-- 4. Creates `inventory_items` (name, unit, quantity_on_hand, reorder_level,
--    cost_per_unit, supplier) and `stock_movements` (an append-only ledger:
--    every change to quantity_on_hand is a signed change_qty row, never an
--    in-place edit, so there is always an audit trail of who changed what
--    and why). Both start empty -- there is no existing inventory data
--    anywhere to backfill from.
--
-- JUDGEMENT CALLS -- PLEASE REVIEW
-- ---------------------------------
-- * table_bookings has no `party_size` column at all. The 3 backfilled
--   rows get NULL, not a guessed number. New reservations require it.
-- * table_bookings has no `created_at`. `reserved_at` (renamed from
--   `booking_time`) is used as the closest available stand-in.
-- * Status mapping: 'Booked' -> 'Confirmed', 'Seated' -> 'Seated',
--   'Cancelled' -> 'Cancelled'. The old ENUM had no concept of a
--   newly-requested-but-unconfirmed reservation ('Pending' in the new
--   ENUM), so nothing backfills into that status.
-- * inventory_items.name is UNIQUE. If you stock the same ingredient under
--   two different names today, decide on one before adding it here.
--
-- SAFETY
-- ------
-- Additive except for the table_bookings rename, which keeps all data.
-- MariaDB commits DDL implicitly, so the transaction wrapper documents
-- intent rather than guaranteeing atomic rollback.
-- BACK UP restaurant_db BEFORE RUNNING (phpMyAdmin > Export).
-- =====================================================================

START TRANSACTION;

-- --- 1. reservations -----------------------------------------------------
CREATE TABLE `reservations` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(100) NOT NULL,
  `phone`         VARCHAR(20) DEFAULT NULL,
  `customer_id`   INT(11) DEFAULT NULL,
  `party_size`    INT(11) DEFAULT NULL,
  `table_id`      INT(11) DEFAULT NULL,
  `reserved_at`   DATETIME NOT NULL,
  `status`        ENUM('Pending','Confirmed','Seated','Completed','Cancelled','No-show')
                  NOT NULL DEFAULT 'Pending',
  `notes`         VARCHAR(255) DEFAULT NULL,
  `user_id`       INT(11) DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_reservations_table` (`table_id`),
  KEY `idx_reservations_customer` (`customer_id`),
  KEY `idx_reservations_reserved_at` (`reserved_at`),
  CONSTRAINT `chk_reservations_party_size` CHECK (`party_size` IS NULL OR `party_size` > 0),
  CONSTRAINT `fk_reservations_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- 2. Backfill from table_bookings --------------------------------------
INSERT INTO reservations (customer_name, phone, customer_id, table_id, reserved_at, status, created_at)
  SELECT c.name, c.phone, b.customer_id, b.table_id, b.booking_time,
         CASE b.status
           WHEN 'Booked'    THEN 'Confirmed'
           WHEN 'Seated'    THEN 'Seated'
           WHEN 'Cancelled' THEN 'Cancelled'
           ELSE 'Pending'
         END,
         b.booking_time
    FROM table_bookings b
    JOIN customers c ON c.id = b.customer_id;

-- --- 3. Retire table_bookings ----------------------------------------------
RENAME TABLE `table_bookings` TO `table_bookings_legacy`;

-- --- 4. inventory_items ----------------------------------------------------
CREATE TABLE `inventory_items` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(100) NOT NULL,
  `unit`              VARCHAR(20) NOT NULL COMMENT 'e.g. kg, l, pcs',
  `quantity_on_hand`  DECIMAL(10,2) NOT NULL DEFAULT 0.00
                      COMMENT 'Cache of SUM(stock_movements.change_qty); recomputed after every movement, never edited directly',
  `reorder_level`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_per_unit`     DECIMAL(10,2) DEFAULT NULL,
  `supplier`          VARCHAR(100) DEFAULT NULL,
  `notes`             VARCHAR(255) DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_items_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- 5. stock_movements ------------------------------------------------
-- Append-only ledger. quantity_on_hand is derived from this table, not the
-- other way around -- correcting a mistake means adding a new 'Correction'
-- row, not editing or deleting an old one.
CREATE TABLE `stock_movements` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `inventory_item_id`  INT(11) NOT NULL,
  `type`               ENUM('Received','Used','Wasted','Correction') NOT NULL,
  `change_qty`         DECIMAL(10,2) NOT NULL COMMENT 'Signed: positive adds stock, negative removes it',
  `reason`             VARCHAR(255) DEFAULT NULL,
  `user_id`            INT(11) DEFAULT NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_item` (`inventory_item_id`),
  KEY `idx_stock_movements_created` (`created_at`),
  CONSTRAINT `chk_stock_movements_nonzero` CHECK (`change_qty` <> 0),
  CONSTRAINT `fk_stock_movements_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- 6. Verify -----------------------------------------------------------
--     Expect: 3 rows -- Confirmed, Seated, Seated.
SELECT id, customer_name, phone, table_id, reserved_at, status FROM reservations ORDER BY id;

--     Expect: table_bookings_legacy still has the same 3 original rows.
SELECT COUNT(*) AS legacy_rows_preserved FROM table_bookings_legacy;

--     Expect: both new tables exist and are empty.
SELECT
  (SELECT COUNT(*) FROM inventory_items) AS inventory_items_rows,
  (SELECT COUNT(*) FROM stock_movements) AS stock_movements_rows;

COMMIT;

-- =====================================================================
-- ROLLBACK
-- =====================================================================
-- DROP TABLE `stock_movements`;
-- DROP TABLE `inventory_items`;
-- RENAME TABLE `table_bookings_legacy` TO `table_bookings`;
-- DROP TABLE `reservations`;
-- =====================================================================
