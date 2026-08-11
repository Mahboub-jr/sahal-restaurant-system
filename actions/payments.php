<?php
/**
 * Payment write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to payments.php with a flash message.
 *
 * The duplicate-payment guard lives here, not in the schema: MariaDB CHECK
 * constraints are single-row only, so "does this payment overpay the
 * order" has to be computed from sibling rows. Recording a 'Paid' payment
 * locks the order row (SELECT ... FOR UPDATE) for the length of the
 * transaction, so two payments submitted at the same instant cannot both
 * read "not yet paid" and both go through -- which is how order 19 ended
 * up paid twice (AUDIT-ADDENDUM.md BUG-5).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager', 'cashier');
csrf_check();

$do = post('do');

const PAYMENT_METHODS = ['Cash', 'Card', 'Mobile Money'];
const PAYMENT_STATUSES = ['Paid', 'Pending'];

/**
 * Does orders.payment_status exist yet? (migration 005). Payments still
 * work without it -- the guard below is computed straight from payments
 * and orders.total_amount -- but the cached label on orders only gets
 * refreshed when this is true.
 */
function has_payment_status_column(): bool
{
    static $has = null;
    if ($has === null) {
        $has = db_value(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_status'",
            [DB_NAME]
        ) !== null;
    }
    return (bool) $has;
}

function recompute_payment_status(int $orderId): void
{
    if (!has_payment_status_column()) {
        return;
    }
    $order = db_one('SELECT total_amount FROM orders WHERE id = ?', [$orderId]);
    if ($order === null) {
        return;
    }
    $paidSum = (float) db_value(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ? AND status = 'Paid'",
        [$orderId]
    );
    $status = 'Unpaid';
    if ($paidSum > 0) {
        $status = $paidSum >= (float) $order['total_amount'] ? 'Paid' : 'Partially Paid';
    }
    db_run('UPDATE orders SET payment_status = ? WHERE id = ?', [$status, $orderId]);
}

/**
 * Shared validation for create/update. Returns [$data, $errors]; $data is
 * only meaningful when $errors is empty.
 */
function validate_payment_input(): array
{
    $errors = [];

    $orderId    = post_int('order_id');
    $customerId = post_int('customer_id');
    $amount     = post_float('amount', -1);
    $method     = one_of(post('payment_method'), PAYMENT_METHODS, null);
    $status     = one_of(post('status'), PAYMENT_STATUSES, 'Paid');
    $dateRaw    = post('payment_date');

    $order = $orderId > 0 ? db_one('SELECT id, status, total_amount FROM orders WHERE id = ?', [$orderId]) : null;
    if ($order === null) {
        $errors[] = 'Choose a real order.';
    } elseif ($order['status'] === 'Cancelled') {
        $errors[] = 'That order is cancelled -- payments cannot be recorded against it.';
    }

    if ($customerId > 0 && db_value('SELECT 1 FROM customers WHERE id = ?', [$customerId]) === null) {
        $errors[] = 'That customer no longer exists.';
    }

    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    } elseif ($amount > 99999999) {
        $errors[] = 'That amount is unrealistically large.';
    }

    if ($method === null) {
        $errors[] = 'Choose a payment method.';
    }

    $timestamp = $dateRaw !== '' ? strtotime($dateRaw) : time();
    $paymentDate = date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());

    return [
        [
            'order_id'       => $orderId,
            'customer_id'    => $customerId > 0 ? $customerId : null,
            'amount'         => round($amount, 2),
            'payment_method' => $method,
            'status'         => $status,
            'payment_date'   => $paymentDate,
        ],
        $errors,
    ];
}

/**
 * The actual duplicate-payment guard. Only 'Paid' payments count toward
 * the balance -- a 'Pending' one is a note that money is expected, not
 * money received, so it does not block anything.
 *
 * Must run inside the same transaction as the INSERT/UPDATE, after the
 * FOR UPDATE lock, or two concurrent requests can both pass this check.
 */
function guard_against_overpayment(int $orderId, float $newAmount, ?int $excludePaymentId = null): ?string
{
    $order = db_one('SELECT total_amount FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
    if ($order === null) {
        return 'That order no longer exists.';
    }

    $sql = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ? AND status = 'Paid'";
    $params = [$orderId];
    if ($excludePaymentId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludePaymentId;
    }
    $alreadyPaid = (float) db_value($sql, $params);
    $remaining   = round((float) $order['total_amount'] - $alreadyPaid, 2);

    if ($remaining <= 0) {
        return 'This order is already fully paid (total ' . money($order['total_amount'])
             . ', already paid ' . money($alreadyPaid) . '). Recording another paid payment would overpay it.';
    }
    if ($newAmount > $remaining + 0.01) {
        return 'That would overpay this order by ' . money($newAmount - $remaining)
             . '. The remaining balance is ' . money($remaining) . '.';
    }
    return null;
}

/* =====================================================================
 | Create
 |===================================================================== */
if ($do === 'create') {
    list($data, $errors) = validate_payment_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('payments.php?order_id=' . $data['order_id']);
    }

    $guardError = db_transaction(function () use ($data) {
        if ($data['status'] === 'Paid') {
            $err = guard_against_overpayment($data['order_id'], $data['amount']);
            if ($err !== null) {
                return $err;
            }
        }

        db_run(
            'INSERT INTO payments (order_id, customer_id, amount, payment_date, payment_method, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$data['order_id'], $data['customer_id'], $data['amount'], $data['payment_date'], $data['payment_method'], $data['status']]
        );

        recompute_payment_status($data['order_id']);
        return null;
    });

    if ($guardError !== null) {
        flash_error($guardError);
        redirect('payments.php?order_id=' . $data['order_id']);
    }

    flash_success('Payment of ' . money($data['amount']) . ' recorded.');
    redirect('payments.php?order_id=' . $data['order_id']);
}

/* =====================================================================
 | Update
 |===================================================================== */
if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM payments WHERE id = ?', [$id]);
    if ($existing === null) {
        flash_error('That payment no longer exists.');
        redirect('payments.php');
    }

    list($data, $errors) = validate_payment_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('payments.php?order_id=' . $data['order_id']);
    }

    $guardError = db_transaction(function () use ($id, $data, $existing) {
        if ($data['status'] === 'Paid') {
            $err = guard_against_overpayment($data['order_id'], $data['amount'], $id);
            if ($err !== null) {
                return $err;
            }
        }

        db_run(
            'UPDATE payments SET order_id = ?, customer_id = ?, amount = ?, payment_date = ?, payment_method = ?, status = ?
              WHERE id = ?',
            [$data['order_id'], $data['customer_id'], $data['amount'], $data['payment_date'], $data['payment_method'], $data['status'], $id]
        );

        recompute_payment_status((int) $existing['order_id']);
        if ((int) $existing['order_id'] !== $data['order_id']) {
            recompute_payment_status($data['order_id']);
        }
        return null;
    });

    if ($guardError !== null) {
        flash_error($guardError);
        redirect('payments.php?order_id=' . $data['order_id']);
    }

    flash_success('Payment updated.');
    redirect('payments.php?order_id=' . $data['order_id']);
}

/* =====================================================================
 | Delete
 |===================================================================== */
if ($do === 'delete') {
    $id = post_int('id');
    $payment = db_one('SELECT id, order_id, amount FROM payments WHERE id = ?', [$id]);
    if ($payment === null) {
        flash_error('That payment no longer exists.');
        redirect('payments.php');
    }

    db_transaction(function () use ($payment) {
        db_run('DELETE FROM payments WHERE id = ?', [$payment['id']]);
        recompute_payment_status((int) $payment['order_id']);
    });

    flash_success('Payment removed.');
    redirect('payments.php?order_id=' . (int) $payment['order_id']);
}

flash_error('Unrecognised action.');
redirect('payments.php');
