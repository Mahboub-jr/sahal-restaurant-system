-- =====================================================================
-- Migration 006 — tighten payments for the duplicate-payment guard
-- Date: 2026-08-11
-- Fixes: BUG-5 (AUDIT-ADDENDUM.md §3) going forward
-- =====================================================================
--
-- WHY
-- ---
-- `payments` allows NULL in every column except `id`, and nothing stops a
-- second payment being recorded against an order that is already fully
-- paid -- that is exactly how order 19 ended up paid twice (BUG-5).
--
-- This migration only tightens the schema (a payment must have a real,
-- positive amount, method and date). The actual duplicate-payment guard --
-- rejecting a new payment that would exceed the order's remaining balance
-- -- is application logic in actions/payments.php, because it needs to sum
-- sibling rows and compare against orders.total_amount, which a CHECK
-- constraint cannot do (MariaDB CHECK constraints are single-row only).
--
-- WHAT THIS DOES
-- --------------
-- 1. `amount`         -> NOT NULL, CHECK (amount > 0). A payment of 0 or
--                        less is not a payment.
-- 2. `payment_date`   -> NOT NULL DEFAULT CURRENT_TIMESTAMP.
-- 3. `payment_method` -> NOT NULL (still no default -- staff must choose).
-- 4. `status`         -> NOT NULL (keeps its existing DEFAULT 'Paid').
--
-- Order 19's existing double payment ($5 + $5 against a $5 order) is left
-- exactly as-is -- this migration does not rewrite financial history. It
-- will show clearly as overpaid on the new invoice page; resolving it
-- (e.g. a refund) is a manual decision, not something to script.
--
-- SAFETY
-- ------
-- If any existing row has a NULL amount/payment_date/payment_method, step 2
-- below will fail and roll back -- that failure is intentional. Run the
-- inspection query first; every row in the current live database has all
-- four fields populated, so this is expected to pass cleanly.
-- BACK UP restaurant_db BEFORE RUNNING (phpMyAdmin > Export).
-- =====================================================================

START TRANSACTION;

-- --- 1. Inspect first --------------------------------------------------
--     Expect: zero rows. If this returns anything, stop and fix those rows
--     by hand before continuing -- the ALTER below will fail on them anyway.
SELECT id, order_id, amount, payment_date, payment_method, status
  FROM payments
 WHERE amount IS NULL OR amount <= 0
    OR payment_date IS NULL
    OR payment_method IS NULL
    OR status IS NULL;

-- --- 2. Tighten columns --------------------------------------------------
ALTER TABLE `payments`
  MODIFY `amount` DECIMAL(10,2) NOT NULL,
  MODIFY `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `payment_method` ENUM('Cash','Card','Mobile Money') NOT NULL,
  MODIFY `status` ENUM('Paid','Pending') NOT NULL DEFAULT 'Paid';

ALTER TABLE `payments`
  ADD CONSTRAINT `chk_payments_amount` CHECK (`amount` > 0);

-- --- 3. Verify -----------------------------------------------------------
--     Expect: 9 rows, all columns populated, matching what step 1 showed clean.
SELECT id, order_id, customer_id, amount, payment_date, payment_method, status
  FROM payments
 ORDER BY id;

--     Order 19's known double payment, surfaced for manual review (BUG-5):
--     expect one row, paid_sum 10.00 against total_amount 5.00.
SELECT o.id, o.order_number, o.total_amount,
       SUM(p.amount) AS paid_sum,
       SUM(p.amount) - o.total_amount AS overpaid_by
  FROM orders o
  JOIN payments p ON p.order_id = o.id AND p.status = 'Paid'
 GROUP BY o.id, o.order_number, o.total_amount
HAVING SUM(p.amount) > o.total_amount;

COMMIT;

-- =====================================================================
-- ROLLBACK
-- =====================================================================
-- ALTER TABLE `payments` DROP CONSTRAINT `chk_payments_amount`;
-- ALTER TABLE `payments`
--   MODIFY `amount` DECIMAL(10,2) DEFAULT NULL,
--   MODIFY `payment_date` DATETIME DEFAULT NULL,
--   MODIFY `payment_method` ENUM('Cash','Card','Mobile Money') DEFAULT NULL,
--   MODIFY `status` ENUM('Paid','Pending') DEFAULT 'Paid';
-- =====================================================================
