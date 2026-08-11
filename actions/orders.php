<?php
/**
 * Order write operations.
 *
 * POST only, CSRF-checked, role-gated per action. No HTML is produced here
 * -- every path ends in a redirect back to the relevant page with a flash
 * message. Mirrors the pattern actions/menu.php established.
 *
 * Prices are NEVER trusted from the client. The old submit_order.php /
 * update_order.php read `total_amount` and `item_price[]` straight out of
 * $_POST, which meant anyone with devtools could set their own total. Here
 * the client sends only a menu_item_id and a quantity; the price is looked
 * up from menu_items and the total is computed here from settings.tax_rate
 * and settings.service_charge (AUDIT-ADDENDUM.md BUG-6).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_login();
csrf_check();

const ORDER_TYPES     = ['Dine-In', 'Takeaway', 'Delivery'];
const ORDER_STATUSES  = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
const TERMINAL_STATUS = ['Completed', 'Cancelled'];

/**
 * Does the order_items migration exist yet? Everything in this file needs
 * it -- unlike menu_items.is_available, there is no reduced-functionality
 * mode to fall back to.
 */
function orders_schema_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = db_value(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
            [DB_NAME]
        ) !== null;
    }
    return (bool) $ready;
}

if (!orders_schema_ready()) {
    flash_error('Run sql/migrations/005_order_items_and_totals.sql before placing or editing orders.');
    redirect('orders.php');
}

/**
 * Decode the cart the browser posted (JSON array of {id, qty}), collapse
 * duplicate menu_item_ids into one line, and re-price every line from the
 * database. Returns [$items, $errors].
 */
function load_cart_items(): array
{
    $decoded = json_decode(post('cart'), true);
    if (!is_array($decoded) || $decoded === []) {
        return [[], ['Your order has no items.']];
    }

    $qtyById = [];
    foreach ($decoded as $line) {
        if (!is_array($line)) {
            continue;
        }
        $id  = (int) ($line['id'] ?? 0);
        $qty = (int) ($line['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0) {
            continue;
        }
        $qtyById[$id] = ($qtyById[$id] ?? 0) + $qty;
    }

    if ($qtyById === []) {
        return [[], ['Your order has no valid items.']];
    }

    $hasAvailability = db_value(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'menu_items' AND COLUMN_NAME = 'is_available'",
        [DB_NAME]
    ) !== null;

    $ids          = array_keys($qtyById);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $columns      = 'id, name, price' . ($hasAvailability ? ', is_available' : '');
    $rows         = db_all("SELECT $columns FROM menu_items WHERE id IN ($placeholders)", $ids);

    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }

    $items  = [];
    $errors = [];
    foreach ($qtyById as $id => $qty) {
        if (!isset($byId[$id])) {
            $errors[] = 'One of the items in your cart is no longer on the menu.';
            continue;
        }
        $row = $byId[$id];
        if ($hasAvailability && (int) ($row['is_available'] ?? 1) === 0) {
            $errors[] = '"' . $row['name'] . '" is currently unavailable.';
            continue;
        }
        if ($qty > 50) {
            $errors[] = '"' . $row['name'] . '" has an unrealistic quantity.';
            continue;
        }
        $items[] = [
            'menu_item_id' => $id,
            'name'         => (string) $row['name'],
            'price'        => (float) $row['price'],
            'quantity'     => $qty,
        ];
    }

    return [$items, $errors];
}

/**
 * subtotal, discount, tax, service_charge, total -- all rounded to cents.
 * Tax and service charge are computed on (subtotal - discount), which is
 * the ordinary convention and the only one settings.php's two rates could
 * mean, since neither order form has ever offered a choice.
 */
function compute_totals(array $items, float $discountInput): array
{
    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += $it['price'] * $it['quantity'];
    }
    $subtotal = round($subtotal, 2);
    $discount = round(max(0.0, min($discountInput, $subtotal)), 2);

    $taxRate     = (float) setting('tax_rate', 0);
    $serviceRate = (float) setting('service_charge', 0);
    $taxableBase = $subtotal - $discount;

    $tax           = round($taxableBase * $taxRate / 100, 2);
    $serviceCharge = round($taxableBase * $serviceRate / 100, 2);
    $total         = round($subtotal - $discount + $tax + $serviceCharge, 2);

    return [
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'tax'            => $tax,
        'service_charge' => $serviceCharge,
        'total'          => $total,
    ];
}

$do = post('do');

/* =====================================================================
 | Create
 |===================================================================== */
if ($do === 'create') {
    require_role('admin', 'manager', 'cashier', 'waiter');

    $customer      = post('customer_name');
    $orderType     = one_of(post('order_type'), ORDER_TYPES, null);
    $tableId       = post_int('table_id');
    $discountInput = post_float('discount', 0);

    list($items, $errors) = load_cart_items();

    if ($customer === '') {
        $errors[] = 'Customer name is required.';
    } elseif (mb_strlen($customer) > 100) {
        $errors[] = 'Customer name must be 100 characters or fewer.';
    }
    if ($orderType === null) {
        $errors[] = 'Choose a valid order type.';
    }

    if ($orderType === 'Dine-In') {
        if ($tableId <= 0) {
            $errors[] = 'Choose a table for a dine-in order.';
        } else {
            $table = db_one('SELECT id, table_number, status FROM tables WHERE id = ?', [$tableId]);
            if ($table === null) {
                $errors[] = 'That table no longer exists.';
            } elseif ($table['status'] !== 'Available') {
                $errors[] = 'Table ' . $table['table_number'] . ' is not available right now.';
            }
        }
    } else {
        $tableId = null;
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('place_order.php');
    }

    $totals  = compute_totals($items, $discountInput);
    $orderId = db_transaction(function () use ($customer, $orderType, $tableId, $items, $totals) {
        db_run(
            'INSERT INTO orders
                (customer_name, order_type, table_id, user_id, total_amount,
                 subtotal, discount, tax, service_charge, status, payment_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $customer, $orderType, $tableId, user_id(), $totals['total'],
                $totals['subtotal'], $totals['discount'], $totals['tax'], $totals['service_charge'],
                'Pending', 'Unpaid',
            ]
        );
        $id = db_last_id();

        db_run(
            'UPDATE orders SET order_number = ? WHERE id = ?',
            ['ORD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $id]
        );

        foreach ($items as $it) {
            db_run(
                'INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity)
                 VALUES (?, ?, ?, ?, ?)',
                [$id, $it['menu_item_id'], $it['name'], $it['price'], $it['quantity']]
            );
        }

        if ($tableId !== null) {
            db_run("UPDATE tables SET status = 'Occupied' WHERE id = ? AND status = 'Available'", [$tableId]);
        }

        return $id;
    });

    flash_success('Order placed.');
    redirect('receipt.php?id=' . $orderId);
}

/* =====================================================================
 | Update (edit an order still in progress)
 |===================================================================== */
if ($do === 'update') {
    require_role('admin', 'manager', 'cashier');

    $id    = post_int('id');
    $order = db_one('SELECT * FROM orders WHERE id = ?', [$id]);

    if ($order === null) {
        flash_error('That order no longer exists.');
        redirect('orders.php');
    }
    if (in_array($order['status'], TERMINAL_STATUS, true)) {
        flash_error('Order #' . $id . ' is ' . $order['status'] . ' and can no longer be edited.');
        redirect('orders.php');
    }

    $customer      = post('customer_name');
    $orderType     = one_of(post('order_type'), ORDER_TYPES, null);
    $tableId       = post_int('table_id');
    $discountInput = post_float('discount', 0);
    $oldTableId    = $order['table_id'] !== null ? (int) $order['table_id'] : null;

    list($items, $errors) = load_cart_items();

    if ($customer === '') {
        $errors[] = 'Customer name is required.';
    } elseif (mb_strlen($customer) > 100) {
        $errors[] = 'Customer name must be 100 characters or fewer.';
    }
    if ($orderType === null) {
        $errors[] = 'Choose a valid order type.';
    }

    if ($orderType === 'Dine-In') {
        if ($tableId <= 0) {
            $errors[] = 'Choose a table for a dine-in order.';
        } else {
            $table = db_one('SELECT id, table_number, status FROM tables WHERE id = ?', [$tableId]);
            if ($table === null) {
                $errors[] = 'That table no longer exists.';
            } elseif ($table['status'] !== 'Available' && $tableId !== $oldTableId) {
                $errors[] = 'Table ' . $table['table_number'] . ' is not available right now.';
            }
        }
    } else {
        $tableId = null;
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('update_order.php?id=' . $id);
    }

    $totals = compute_totals($items, $discountInput);

    db_transaction(function () use ($id, $customer, $orderType, $tableId, $oldTableId, $items, $totals) {
        db_run(
            'UPDATE orders
                SET customer_name = ?, order_type = ?, table_id = ?, total_amount = ?,
                    subtotal = ?, discount = ?, tax = ?, service_charge = ?
              WHERE id = ?',
            [
                $customer, $orderType, $tableId, $totals['total'],
                $totals['subtotal'], $totals['discount'], $totals['tax'], $totals['service_charge'],
                $id,
            ]
        );

        db_run('DELETE FROM order_items WHERE order_id = ?', [$id]);
        foreach ($items as $it) {
            db_run(
                'INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity)
                 VALUES (?, ?, ?, ?, ?)',
                [$id, $it['menu_item_id'], $it['name'], $it['price'], $it['quantity']]
            );
        }

        if ($oldTableId !== $tableId) {
            if ($oldTableId !== null) {
                db_run("UPDATE tables SET status = 'Available' WHERE id = ? AND status = 'Occupied'", [$oldTableId]);
            }
            if ($tableId !== null) {
                db_run("UPDATE tables SET status = 'Occupied' WHERE id = ? AND status = 'Available'", [$tableId]);
            }
        }
    });

    flash_success('Order #' . $id . ' was updated.');
    redirect('orders.php');
}

/* =====================================================================
 | Status transitions (replaces GET-based complete_order.php / cancel_order.php)
 |===================================================================== */
if ($do === 'set_status') {
    $id   = post_int('id');
    $next = post('status');

    $order = db_one('SELECT id, status, table_id FROM orders WHERE id = ?', [$id]);
    if ($order === null) {
        flash_error('That order no longer exists.');
        redirect('orders.php');
    }

    $allowedNext = [
        'Pending'   => ['Preparing', 'Cancelled'],
        'Preparing' => ['Ready', 'Cancelled'],
        'Ready'     => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
    ];

    if (!in_array($next, ORDER_STATUSES, true)
        || !in_array($next, $allowedNext[$order['status']] ?? [], true)) {
        flash_error('Order #' . $id . ' cannot move from ' . $order['status'] . ' to ' . $next . '.');
        redirect('orders.php');
    }

    // Cancelling and completing carry more authority than day-to-day
    // progress -- the same split the legacy cancel_order.php /
    // complete_order.php role lists already enforced.
    if ($next === 'Cancelled') {
        require_role('admin', 'manager');
    } elseif ($next === 'Completed') {
        require_role('admin', 'manager', 'cashier');
    } else {
        require_role('admin', 'manager', 'cashier', 'waiter');
    }

    db_run('UPDATE orders SET status = ? WHERE id = ?', [$next, $id]);

    if (in_array($next, TERMINAL_STATUS, true) && $order['table_id'] !== null) {
        db_run("UPDATE tables SET status = 'Available' WHERE id = ? AND status = 'Occupied'", [$order['table_id']]);
    }

    flash_success('Order #' . $id . ' is now ' . $next . '.');
    redirect('orders.php');
}

flash_error('Unrecognised action.');
redirect('orders.php');
