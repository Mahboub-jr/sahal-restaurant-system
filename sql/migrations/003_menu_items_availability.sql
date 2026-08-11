-- =====================================================================
-- Migration 003 — Menu item availability and audit column
-- Date: 2026-08-11
-- =====================================================================
--
-- WHY
-- ---
-- The brief asks for an availability flag on menu items (§11). Today the
-- only way to stop selling something is to delete it, which destroys its
-- history and orphans it from past orders.
--
-- `updated_at` is added for the same reason `orders` already has one: to
-- know when a price last changed.
--
-- SAFETY
-- ------
-- Purely additive. Existing rows default to available, which matches the
-- current behaviour exactly. Nothing is removed or rewritten.
-- BACK UP restaurant_db BEFORE RUNNING.
-- =====================================================================

START TRANSACTION;

ALTER TABLE `menu_items`
  ADD COLUMN `is_available` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = orderable, 0 = temporarily off the menu'
    AFTER `food_image`,
  ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL
    ON UPDATE CURRENT_TIMESTAMP
    AFTER `created_at`;

-- Index the columns the menu list actually filters and sorts on.
ALTER TABLE `menu_items`
  ADD INDEX `idx_menu_items_available` (`is_available`),
  ADD INDEX `idx_menu_items_category`  (`category_id`);

-- --- Verify -----------------------------------------------------------
--     Expect 20 rows, all is_available = 1
SELECT is_available, COUNT(*) AS items
  FROM menu_items
 GROUP BY is_available;

COMMIT;

-- =====================================================================
-- ROLLBACK
-- =====================================================================
-- ALTER TABLE `menu_items`
--   DROP INDEX `idx_menu_items_available`,
--   DROP INDEX `idx_menu_items_category`,
--   DROP COLUMN `is_available`,
--   DROP COLUMN `updated_at`;
-- =====================================================================
