SUPERSEDED FILES — moved 2026-08-11, not deleted
================================================

add-menu.php
  Inserted into menu_items (name, price, category, image, description).
  Those columns do not exist; the live table uses category_id and
  food_image. This file would fatal on every submission. menu.php now
  handles create/edit/delete properly. See AUDIT-ADDENDUM.md F1.

auth.php  (root)
  A second, unused login handler using mysqli with string-interpolated SQL.
  login.php never posted here — it posted to backend/auth.php. Both are now
  replaced by attempt_login() in includes/auth.php.

pages/
  menu.php    queried a table called `menu` that does not exist, using ../
              includes that resolved incorrectly.
  dashboard.php, orders.php, reports.php — all 0 bytes.

backend/
  auth.php    superseded by includes/auth.php.
  menu.php, orders.php — both 0 bytes.

Nothing here is referenced by the running application. Delete the folder
once you are satisfied the system works.
