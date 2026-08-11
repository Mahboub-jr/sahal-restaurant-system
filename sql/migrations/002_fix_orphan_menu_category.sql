-- =====================================================================
-- Migration 002 — Repair orphaned menu_items.category_id, add FK
-- Date: 2026-08-11
-- Fixes: BUG-2 (AUDIT-ADDENDUM.md §3)
-- =====================================================================
--
-- PROBLEM
-- -------
-- menu_items id 2 ('bariis') has category_id = 1. No category with id 1
-- exists -- `categories` begins at id 4. There is no foreign key on
-- menu_items.category_id, so the orphan was accepted.
--
-- Consequence:
--   menu.php        uses INNER JOIN categories  -> 'bariis' is INVISIBLE.
--                   It cannot be edited or deleted through the interface.
--   place_order.php uses SELECT * (no join)     -> 'bariis' IS orderable.
--                   It already appears in 3 existing orders.
--
-- An item staff can sell but management cannot see.
--
-- DECISION -- PLEASE REVIEW
-- -------------------------
-- 'bariis' (rice) is reassigned to category 11 'Lunch'. This is a judgement
-- call, not something the data tells us. If rice belongs under 'Dinner' (10)
-- or a new 'Main Dishes' category in your restaurant, change @new_category_id
-- below before running.
--
-- SAFETY
-- ------
-- Non-destructive: one UPDATE plus a constraint. No rows removed.
-- Step 3 will FAIL if any orphan remains -- that failure is intentional and
-- protective. If it fails, re-run step 1 to find what else is orphaned.
-- BACK UP restaurant_db BEFORE RUNNING.
-- =====================================================================

START TRANSACTION;

SET @new_category_id = 11;   -- 11 = 'Lunch'.  <-- change if needed

-- --- 1. Find every orphan (not just the known one) -------------------
--     Expect: id 2, 'bariis', category_id 1
SELECT m.id, m.name, m.category_id, m.price
  FROM menu_items m
  LEFT JOIN categories c ON c.id = m.category_id
 WHERE m.category_id IS NOT NULL
   AND c.id IS NULL;

-- --- 2. Reassign them -------------------------------------------------
UPDATE menu_items m
  LEFT JOIN categories c ON c.id = m.category_id
   SET m.category_id = @new_category_id
 WHERE m.category_id IS NOT NULL
   AND c.id IS NULL;

-- --- 3. Prevent recurrence -------------------------------------------
--     RESTRICT: deleting a category that still holds menu items is refused,
--     rather than silently orphaning them the way it happened here.
ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_menu_items_category`
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- --- 4. Verify --------------------------------------------------------
--     Expect: zero rows
SELECT m.id, m.name, m.category_id
  FROM menu_items m
  LEFT JOIN categories c ON c.id = m.category_id
 WHERE m.category_id IS NOT NULL
   AND c.id IS NULL;

--     Expect: 20 items, every one with a category name
SELECT m.id, m.name, c.name AS category
  FROM menu_items m
  LEFT JOIN categories c ON c.id = m.category_id
 ORDER BY m.id;

COMMIT;

-- =====================================================================
-- ROLLBACK
-- =====================================================================
-- ALTER TABLE `menu_items` DROP FOREIGN KEY `fk_menu_items_category`;
-- UPDATE menu_items SET category_id = 1 WHERE id = 2;
-- =====================================================================
--
-- NOTE: categories.php currently deletes with no dependency check:
--     DELETE FROM categories WHERE id = $id
-- With this constraint in place that DELETE now throws instead of orphaning
-- menu items. categories.php must catch the error and show a readable
-- message. That is handled in the application fix accompanying this
-- migration -- do not run this migration without it.
-- =====================================================================
