-- =====================================================================
-- Migration 003 — Reset a forgotten admin password
-- Date: 2026-08-11
-- ONE-OFF RECOVERY. Not part of the schema. Run once, then change the
-- password from inside the application.
-- =====================================================================
--
-- Sets user 1 (admin@example.com) to:
--
--     Password:  Admin@123
--
-- The hash below is bcrypt, cost 10, generated fresh for this project and
-- verified to round-trip. PHP's password_verify() accepts it as-is.
--
-- CHANGE THIS PASSWORD after logging in. It is written in plain text in a
-- file that is committed to git, so treat it as compromised from the moment
-- you use it.
-- =====================================================================

-- Which accounts exist, before changing anything:
SELECT id, name, email, role FROM users ORDER BY id;

-- Reset ONLY the admin account.
UPDATE `users`
   SET `password` = '$2y$10$u4tzqXL04fJs0m/l9NspauVR7X6mXuXOSaf/ta8sHkPJ9sCZXh2sm'
 WHERE `email` = 'admin@example.com';

-- Expect: 1 row changed.
SELECT ROW_COUNT() AS rows_updated;

-- Confirm the stored hash starts with $2y$10$ and is 60 characters:
SELECT id, email, role,
       LEFT(password, 7)  AS hash_prefix,
       CHAR_LENGTH(password) AS hash_length
  FROM users
 WHERE email = 'admin@example.com';

-- =====================================================================
-- To reset a DIFFERENT account instead, change the WHERE clause, e.g.:
--   WHERE `email` = 'nabil@gmail.com';
--
-- To reset EVERY account to Admin@123 (only if you are locked out entirely
-- and this is not shared with anyone), remove the WHERE clause. Not advised.
-- =====================================================================
