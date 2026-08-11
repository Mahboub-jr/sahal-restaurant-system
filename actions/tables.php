<?php
/**
 * Table write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to tables.php with a flash message.
 *
 * Orders and reservations also change tables.status as part of their own
 * lifecycle (Dine-In orders, seating a reservation). This page is the
 * manual override -- adding a table, fixing its capacity, or correcting a
 * status that drifted out of sync -- not the normal way status changes.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

const TABLE_STATUSES = ['Available', 'Reserved', 'Occupied'];

function validate_table_input(): array
{
    $errors = [];
    $number   = post('table_number');
    $capacity = post_int('capacity');
    $status   = one_of(post('status'), TABLE_STATUSES, null);

    if ($number === '') {
        $errors[] = 'Table number is required.';
    } elseif (mb_strlen($number) > 50) {
        $errors[] = 'Table number must be 50 characters or fewer.';
    }
    if ($capacity <= 0) {
        $errors[] = 'Capacity must be at least 1.';
    } elseif ($capacity > 100) {
        $errors[] = 'That capacity is unrealistically large.';
    }
    if ($status === null) {
        $errors[] = 'Choose a valid status.';
    }

    return [['table_number' => $number, 'capacity' => $capacity, 'status' => $status], $errors];
}

if ($do === 'create') {
    list($data, $errors) = validate_table_input();

    if ($data['table_number'] !== ''
        && db_value('SELECT 1 FROM tables WHERE table_number = ?', [$data['table_number']]) !== null) {
        $errors[] = 'Table "' . $data['table_number'] . '" already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('tables.php');
    }

    db_run(
        'INSERT INTO tables (table_number, capacity, status) VALUES (?, ?, ?)',
        [$data['table_number'], $data['capacity'], $data['status']]
    );

    flash_success('Table "' . $data['table_number'] . '" was added.');
    redirect('tables.php');
}

if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM tables WHERE id = ?', [$id]);
    if ($existing === null) {
        flash_error('That table no longer exists.');
        redirect('tables.php');
    }

    list($data, $errors) = validate_table_input();

    if ($data['table_number'] !== $existing['table_number']
        && db_value('SELECT 1 FROM tables WHERE table_number = ? AND id <> ?', [$data['table_number'], $id]) !== null) {
        $errors[] = 'Table "' . $data['table_number'] . '" already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('tables.php');
    }

    db_run(
        'UPDATE tables SET table_number = ?, capacity = ?, status = ? WHERE id = ?',
        [$data['table_number'], $data['capacity'], $data['status'], $id]
    );

    flash_success('Table "' . $data['table_number'] . '" was updated.');
    redirect('tables.php');
}

/**
 * Blocked if an active order or upcoming reservation still points at this
 * table -- both FKs are ON DELETE SET NULL, so the database would allow
 * it, but silently detaching a live order from its table is a worse
 * outcome than asking staff to resolve it first.
 */
if ($do === 'delete') {
    $id = post_int('id');
    $table = db_one('SELECT id, table_number FROM tables WHERE id = ?', [$id]);
    if ($table === null) {
        flash_error('That table no longer exists.');
        redirect('tables.php');
    }

    $hasOrderTableId = db_value(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'table_id'",
        [DB_NAME]
    ) !== null;
    $activeOrders = $hasOrderTableId
        ? (int) db_value(
            "SELECT COUNT(*) FROM orders WHERE table_id = ? AND status NOT IN ('Completed','Cancelled')",
            [$id]
        )
        : 0;

    $hasReservations = db_value(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'reservations'",
        [DB_NAME]
    ) !== null;
    $activeReservations = $hasReservations
        ? (int) db_value(
            "SELECT COUNT(*) FROM reservations WHERE table_id = ? AND status NOT IN ('Completed','Cancelled','No-show')",
            [$id]
        )
        : 0;

    if ($activeOrders > 0 || $activeReservations > 0) {
        flash_error(
            'Table "' . $table['table_number'] . '" cannot be deleted: it has '
            . $activeOrders . ' active order(s) and ' . $activeReservations
            . ' upcoming reservation(s). Resolve those first.'
        );
        redirect('tables.php');
    }

    db_run('DELETE FROM tables WHERE id = ?', [$id]);

    flash_success('Table "' . $table['table_number'] . '" was deleted.');
    redirect('tables.php');
}

flash_error('Unrecognised action.');
redirect('tables.php');
