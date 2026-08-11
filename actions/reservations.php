<?php
/**
 * Reservation write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to reservations.php with a flash message.
 *
 * Reservations share `tables.status` with orders (Available/Reserved/
 * Occupied): assigning a table reserves it, seating it occupies it, and
 * finishing/cancelling frees it -- the same lifecycle actions/orders.php
 * already runs for Dine-In orders, just with 'Reserved' as the extra
 * in-between state a booking (but not a walk-in order) needs.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager', 'waiter', 'cashier');
csrf_check();

$do = post('do');

const RESERVATION_STATUSES = ['Pending', 'Confirmed', 'Seated', 'Completed', 'Cancelled', 'No-show'];
const RESERVATION_TERMINAL  = ['Completed', 'Cancelled', 'No-show'];

function reservations_schema_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = db_value(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'reservations'",
            [DB_NAME]
        ) !== null;
    }
    return (bool) $ready;
}

if (!reservations_schema_ready()) {
    flash_error('Run sql/migrations/007_reservations_and_inventory.sql before managing reservations.');
    redirect('reservations.php');
}

/**
 * Free (only if still held by this reservation's own table) or reserve/
 * occupy a table to match a reservation's current status. Called after
 * every write, inside the same transaction.
 */
function sync_table_for_reservation(?int $tableId, string $status): void
{
    if ($tableId === null) {
        return;
    }
    if (in_array($status, RESERVATION_TERMINAL, true)) {
        db_run("UPDATE tables SET status = 'Available' WHERE id = ? AND status IN ('Reserved','Occupied')", [$tableId]);
    } elseif ($status === 'Seated') {
        db_run("UPDATE tables SET status = 'Occupied' WHERE id = ?", [$tableId]);
    } else {
        // Pending or Confirmed: hold the table without a live order on it.
        db_run("UPDATE tables SET status = 'Reserved' WHERE id = ? AND status = 'Available'", [$tableId]);
    }
}

function free_table(?int $tableId): void
{
    if ($tableId !== null) {
        db_run("UPDATE tables SET status = 'Available' WHERE id = ? AND status IN ('Reserved','Occupied')", [$tableId]);
    }
}

/**
 * Shared validation for create/update. Returns [$data, $errors].
 */
function validate_reservation_input(?int $currentTableId = null): array
{
    $errors = [];

    $customerName = post('customer_name');
    $phone        = post('phone');
    $customerId   = post_int('customer_id');
    $partySize    = post_int('party_size');
    $tableId      = post_int('table_id');
    $notes        = post('notes');
    $reservedRaw  = post('reserved_at');
    $status       = one_of(post('status'), ['Pending', 'Confirmed'], 'Confirmed');

    if ($customerName === '') {
        $errors[] = 'Customer name is required.';
    } elseif (mb_strlen($customerName) > 100) {
        $errors[] = 'Customer name must be 100 characters or fewer.';
    }

    if ($partySize <= 0) {
        $errors[] = 'Party size must be at least 1.';
    } elseif ($partySize > 100) {
        $errors[] = 'That party size is unrealistically large.';
    }

    $reservedTs = strtotime($reservedRaw);
    if ($reservedTs === false) {
        $errors[] = 'Choose a valid date and time.';
    }

    if ($customerId > 0 && db_value('SELECT 1 FROM customers WHERE id = ?', [$customerId]) === null) {
        $errors[] = 'That customer no longer exists.';
    }

    $table = null;
    if ($tableId > 0) {
        $table = db_one('SELECT id, table_number, status FROM tables WHERE id = ?', [$tableId]);
        if ($table === null) {
            $errors[] = 'That table no longer exists.';
        } elseif ($table['status'] !== 'Available' && $tableId !== $currentTableId) {
            $errors[] = 'Table ' . $table['table_number'] . ' is not available right now.';
        }
    } else {
        $tableId = null;
    }

    if (mb_strlen($notes) > 255) {
        $errors[] = 'Notes must be 255 characters or fewer.';
    }

    return [
        [
            'customer_name' => $customerName,
            'phone'         => $phone !== '' ? $phone : null,
            'customer_id'   => $customerId > 0 ? $customerId : null,
            'party_size'    => $partySize,
            'table_id'      => $tableId,
            'notes'         => $notes !== '' ? $notes : null,
            'reserved_at'   => $reservedTs !== false ? date('Y-m-d H:i:s', $reservedTs) : null,
            'status'        => $status,
        ],
        $errors,
    ];
}

/* =====================================================================
 | Create
 |===================================================================== */
if ($do === 'create') {
    list($data, $errors) = validate_reservation_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('reservations.php');
    }

    db_transaction(function () use ($data) {
        db_run(
            'INSERT INTO reservations (customer_name, phone, customer_id, party_size, table_id, reserved_at, status, notes, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_name'], $data['phone'], $data['customer_id'], $data['party_size'],
                $data['table_id'], $data['reserved_at'], $data['status'], $data['notes'], user_id(),
            ]
        );
        sync_table_for_reservation($data['table_id'], $data['status']);
    });

    flash_success('Reservation for ' . $data['customer_name'] . ' recorded.');
    redirect('reservations.php');
}

/* =====================================================================
 | Update (details, not status -- see set_status below)
 |===================================================================== */
if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM reservations WHERE id = ?', [$id]);
    if ($existing === null) {
        flash_error('That reservation no longer exists.');
        redirect('reservations.php');
    }
    if (in_array($existing['status'], RESERVATION_TERMINAL, true)) {
        flash_error('That reservation is ' . $existing['status'] . ' and can no longer be edited.');
        redirect('reservations.php');
    }

    $oldTableId = $existing['table_id'] !== null ? (int) $existing['table_id'] : null;
    list($data, $errors) = validate_reservation_input($oldTableId);

    // Editing keeps whatever status the reservation is already at (Pending/
    // Confirmed) rather than the create form's default -- status changes go
    // through set_status only.
    $data['status'] = $existing['status'];

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('reservations.php');
    }

    db_transaction(function () use ($id, $data, $oldTableId) {
        db_run(
            'UPDATE reservations
                SET customer_name = ?, phone = ?, customer_id = ?, party_size = ?,
                    table_id = ?, reserved_at = ?, notes = ?
              WHERE id = ?',
            [
                $data['customer_name'], $data['phone'], $data['customer_id'], $data['party_size'],
                $data['table_id'], $data['reserved_at'], $data['notes'], $id,
            ]
        );

        if ($oldTableId !== $data['table_id']) {
            free_table($oldTableId);
            sync_table_for_reservation($data['table_id'], $data['status']);
        }
    });

    flash_success('Reservation updated.');
    redirect('reservations.php');
}

/* =====================================================================
 | Status transitions
 |===================================================================== */
if ($do === 'set_status') {
    $id   = post_int('id');
    $next = post('status');

    $reservation = db_one('SELECT id, status, table_id FROM reservations WHERE id = ?', [$id]);
    if ($reservation === null) {
        flash_error('That reservation no longer exists.');
        redirect('reservations.php');
    }

    $allowedNext = [
        'Pending'   => ['Confirmed', 'Cancelled'],
        'Confirmed' => ['Seated', 'Cancelled', 'No-show'],
        'Seated'    => ['Completed'],
        'Completed' => [],
        'Cancelled' => [],
        'No-show'   => [],
    ];

    if (!in_array($next, RESERVATION_STATUSES, true)
        || !in_array($next, $allowedNext[$reservation['status']] ?? [], true)) {
        flash_error('That reservation cannot move from ' . $reservation['status'] . ' to ' . $next . '.');
        redirect('reservations.php');
    }

    $tableId = $reservation['table_id'] !== null ? (int) $reservation['table_id'] : null;

    db_transaction(function () use ($id, $next, $tableId) {
        db_run('UPDATE reservations SET status = ? WHERE id = ?', [$next, $id]);
        sync_table_for_reservation($tableId, $next);
    });

    flash_success('Reservation is now ' . $next . '.');
    redirect('reservations.php');
}

/* =====================================================================
 | Delete
 |===================================================================== */
if ($do === 'delete') {
    require_role('admin', 'manager');

    $id = post_int('id');
    $reservation = db_one('SELECT id, table_id FROM reservations WHERE id = ?', [$id]);
    if ($reservation === null) {
        flash_error('That reservation no longer exists.');
        redirect('reservations.php');
    }

    db_transaction(function () use ($reservation) {
        free_table($reservation['table_id'] !== null ? (int) $reservation['table_id'] : null);
        db_run('DELETE FROM reservations WHERE id = ?', [$reservation['id']]);
    });

    flash_success('Reservation deleted.');
    redirect('reservations.php');
}

flash_error('Unrecognised action.');
redirect('reservations.php');
