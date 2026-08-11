-- =====================================================================
-- Migration 001 — Repair orders.order_type
-- Date: 2026-08-11
-- Fixes: BUG-1 (AUDIT-ADDENDUM.md §3)
-- =====================================================================
--
-- PROBLEM
-- -------
-- `orders`.`order_type` is enum('Dine-In','Takeaway') NOT NULL.
-- Two pages submit values that are not members of that ENUM:
--
--   place_order.php   -> "Dine In"  /  "Take Away"   (space, no hyphen)
--   update_order.php  -> "Delivery" (offered in the UI, absent from the ENUM)
--
-- MariaDB is not running in strict mode, so instead of rejecting the value
-- it stores the empty string '' and the insert succeeds silently. Orders
-- 17, 19 and 20 -- every order placed through the UI -- lost their type.
--
-- DECISIONS
-- ---------
-- 'Unknown'  is added so the three damaged rows can be labelled honestly
--            rather than guessed at. Reporting should exclude them explicitly.
-- 'Delivery' is added because update_order.php already offers it. The ENUM is
--            being brought into line with an intent the UI already expressed;
--            this is not new scope. Remove it here AND from update_order.php
--            line 82 if delivery is not a service you offer.
--
-- SAFETY
-- ------
-- Non-destructive. Widens the ENUM and relabels '' rows. No data is removed.
-- Run inside a transaction; verification queries are at the bottom.
-- BACK UP restaurant_db BEFORE RUNNING (phpMyAdmin > Export).
-- =====================================================================

START TRANSACTION;

-- --- 1. Inspect what is there now -----------------------------------
--     Expect: '' x3  (orders 17, 19, 20)
SELECT order_type, COUNT(*) AS rows_affected
  FROM orders
 GROUP BY order_type;

-- --- 2. Widen the ENUM ----------------------------------------------
--     'Unknown' must exist before step 3 can write it.
ALTER TABLE `orders`
  MODIFY `order_type`
  ENUM('Dine-In','Takeaway','Delivery','Unknown')
  NOT NULL DEFAULT 'Unknown';

-- --- 3. Relabel the damaged rows ------------------------------------
UPDATE `orders`
   SET `order_type` = 'Unknown'
 WHERE `order_type` = '';

-- --- 4. Verify -------------------------------------------------------
--     Expect: no '' remains; Unknown = 3
SELECT order_type, COUNT(*) AS rows_now
  FROM orders
 GROUP BY order_type
 ORDER BY rows_now DESC;

--     The three affected orders, for your records:
SELECT id, customer_name, order_type, total_amount, created_at
  FROM orders
 WHERE order_type = 'Unknown'
 ORDER BY id;

COMMIT;

-- =====================================================================
-- ROLLBACK (only if nothing has been written since)
-- =====================================================================
-- UPDATE `orders` SET `order_type` = '' WHERE `order_type` = 'Unknown';
-- ALTER TABLE `orders`
--   MODIFY `order_type` ENUM('Dine-In','Takeaway') NOT NULL;
-- =====================================================================

-- =====================================================================
-- STRONGLY RECOMMENDED, SEPARATELY
-- =====================================================================
-- This class of bug -- invalid data accepted silently -- is only possible
-- because MariaDB is in permissive mode. Turn it off in
-- C:\xampp\mysql\bin\my.ini under [mysqld]:
--
--   sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
--
-- then restart MySQL. After this, a bad ENUM value raises an error instead
-- of being quietly replaced by ''. Do this only after the application code
-- is fixed, or existing pages may start throwing errors.
-- =====================================================================
