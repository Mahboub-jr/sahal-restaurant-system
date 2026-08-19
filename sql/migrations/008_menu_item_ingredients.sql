-- =====================================================================
-- Migration 008 — link menu items to inventory (recipes)
-- Date: 2026-08-19
-- Closes: "Inventory has no link to menu items or orders" (README's
--         Known Limitations, pass 6)
-- =====================================================================
--
-- WHY
-- ---
-- Pass 6 built inventory as a standalone stock ledger on purpose --
-- placing an order never touched it. This migration adds the missing
-- link: a recipe (bill of materials) per menu item, so that placing a
-- Dine-In/Takeaway/Delivery order automatically records a 'Used' stock
-- movement for every ingredient the dish consumes.
--
-- WHAT THIS DOES
-- --------------
-- Creates `menu_item_ingredients` (menu_item_id, inventory_item_id,
-- quantity_required) -- how much of one inventory item ONE unit of a dish
-- consumes. Starts empty: there is no existing recipe data anywhere to
-- backfill from, and guessing that "a burger uses 0.2 kg of beef" would be
-- inventing data, not migrating it. Every recipe is entered by hand from
-- menu.php after this runs.
--
-- WHAT THIS DOES NOT DO (see README "Known limitations" for the honest
-- version of this feature, not the aspirational one)
-- ---------------------------------------------------------------------
-- * Editing an order's items does NOT reverse-and-reapply stock
--   consumption. Only creating a new order consumes stock. Getting edits
--   right means reversing the original consumption before applying the
--   new one -- a second, separable piece of work, not bundled in here.
-- * Cancelling an order does NOT return consumed stock automatically.
-- * Consuming stock never blocks placing an order, even past zero.
--   A kitchen has to be able to serve food regardless of what the
--   inventory ledger says; a negative quantity_on_hand after an order is
--   a signal to recount or restock, not a bug -- unlike a manual stock
--   movement typo, which the existing guard in actions/stock_movements.php
--   still refuses.
--
-- SAFETY
-- ------
-- Purely additive; nothing existing is modified. Both foreign keys point
-- at tables added in migration 007 -- run that first if you have not.
-- BACK UP restaurant_db BEFORE RUNNING (phpMyAdmin > Export).
-- =====================================================================

START TRANSACTION;

CREATE TABLE `menu_item_ingredients` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `menu_item_id`       INT(11) NOT NULL,
  `inventory_item_id`  INT(11) NOT NULL,
  `quantity_required`  DECIMAL(10,3) NOT NULL COMMENT 'Consumed per ONE unit of the dish',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menu_item_ingredients` (`menu_item_id`, `inventory_item_id`),
  KEY `idx_menu_item_ingredients_inventory` (`inventory_item_id`),
  CONSTRAINT `chk_menu_item_ingredients_qty` CHECK (`quantity_required` > 0),
  CONSTRAINT `fk_menu_item_ingredients_menu_item`
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_menu_item_ingredients_inventory_item`
    FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`)
    -- RESTRICT, not CASCADE: deleting a stock item that a recipe still
    -- depends on should fail loudly, the same way menu_items.category_id
    -- has refused to orphan since migration 002.
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- Verify -----------------------------------------------------------
--     Expect: table exists, 0 rows (nothing to backfill).
SELECT COUNT(*) AS menu_item_ingredients_rows FROM menu_item_ingredients;

COMMIT;

-- =====================================================================
-- ROLLBACK
-- =====================================================================
-- DROP TABLE `menu_item_ingredients`;
-- =====================================================================
