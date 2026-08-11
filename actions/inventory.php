<?php
/**
 * Inventory item write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to inventory.php with a flash message.
 *
 * quantity_on_hand is never written directly by create/update -- it only
 * ever moves through a stock_movements row (see actions/stock_movements.php),
 * so there is always an audit trail of who changed it and why. The one
 * exception is the starting balance at creation time, which is recorded as
 * an actual 'Received' movement rather than a bare number, for the same
 * reason.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

function inventory_schema_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = db_value(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'inventory_items'",
            [DB_NAME]
        ) !== null;
    }
    return (bool) $ready;
}

if (!inventory_schema_ready()) {
    flash_error('Run sql/migrations/007_reservations_and_inventory.sql before managing inventory.');
    redirect('inventory.php');
}

function validate_inventory_input(): array
{
    $errors = [];

    $name         = post('name');
    $unit         = post('unit');
    $reorderLevel = post_float('reorder_level', 0);
    $costPerUnit  = post('cost_per_unit') !== '' ? post_float('cost_per_unit', -1) : null;
    $supplier     = post('supplier');
    $notes        = post('notes');

    if ($name === '') {
        $errors[] = 'Item name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Item name must be 100 characters or fewer.';
    }

    if ($unit === '') {
        $errors[] = 'Unit is required (e.g. kg, l, pcs).';
    } elseif (mb_strlen($unit) > 20) {
        $errors[] = 'Unit must be 20 characters or fewer.';
    }

    if ($reorderLevel < 0) {
        $errors[] = 'Reorder level cannot be negative.';
    }

    if ($costPerUnit !== null && $costPerUnit < 0) {
        $errors[] = 'Cost per unit cannot be negative.';
    }

    return [
        [
            'name'           => $name,
            'unit'           => $unit,
            'reorder_level'  => round($reorderLevel, 2),
            'cost_per_unit'  => $costPerUnit !== null ? round($costPerUnit, 2) : null,
            'supplier'       => $supplier !== '' ? $supplier : null,
            'notes'          => $notes !== '' ? $notes : null,
        ],
        $errors,
    ];
}

/* =====================================================================
 | Create
 |===================================================================== */
if ($do === 'create') {
    list($data, $errors) = validate_inventory_input();

    $initialQty = post_float('initial_quantity', 0);
    if ($initialQty < 0) {
        $errors[] = 'Starting quantity cannot be negative.';
    }

    if ($data['name'] !== '' && db_value('SELECT 1 FROM inventory_items WHERE name = ?', [$data['name']]) !== null) {
        $errors[] = 'An item named "' . $data['name'] . '" already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('inventory.php');
    }

    db_transaction(function () use ($data, $initialQty) {
        db_run(
            'INSERT INTO inventory_items (name, unit, quantity_on_hand, reorder_level, cost_per_unit, supplier, notes)
             VALUES (?, ?, 0, ?, ?, ?, ?)',
            [$data['name'], $data['unit'], $data['reorder_level'], $data['cost_per_unit'], $data['supplier'], $data['notes']]
        );
        $itemId = db_last_id();

        if ($initialQty > 0) {
            db_run(
                'INSERT INTO stock_movements (inventory_item_id, type, change_qty, reason, user_id)
                 VALUES (?, ?, ?, ?, ?)',
                [$itemId, 'Received', round($initialQty, 2), 'Initial stock', user_id()]
            );
            db_run('UPDATE inventory_items SET quantity_on_hand = ? WHERE id = ?', [round($initialQty, 2), $itemId]);
        }
    });

    flash_success('"' . $data['name'] . '" added to inventory.');
    redirect('inventory.php');
}

/* =====================================================================
 | Update
 |===================================================================== */
if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM inventory_items WHERE id = ?', [$id]);
    if ($existing === null) {
        flash_error('That inventory item no longer exists.');
        redirect('inventory.php');
    }

    list($data, $errors) = validate_inventory_input();

    if ($data['name'] !== $existing['name']
        && db_value('SELECT 1 FROM inventory_items WHERE name = ? AND id <> ?', [$data['name'], $id]) !== null) {
        $errors[] = 'An item named "' . $data['name'] . '" already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('inventory.php');
    }

    db_run(
        'UPDATE inventory_items
            SET name = ?, unit = ?, reorder_level = ?, cost_per_unit = ?, supplier = ?, notes = ?
          WHERE id = ?',
        [$data['name'], $data['unit'], $data['reorder_level'], $data['cost_per_unit'], $data['supplier'], $data['notes'], $id]
    );

    flash_success('"' . $data['name'] . '" was updated.');
    redirect('inventory.php');
}

/* =====================================================================
 | Delete
 |=====================================================================
 | Blocked once any stock movement exists, to keep the ledger meaningful --
 | zero the item out with a movement instead of deleting its history.
 */
if ($do === 'delete') {
    $id = post_int('id');
    $item = db_one('SELECT id, name FROM inventory_items WHERE id = ?', [$id]);
    if ($item === null) {
        flash_error('That inventory item no longer exists.');
        redirect('inventory.php');
    }

    $movementCount = (int) db_value('SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id = ?', [$id]);
    if ($movementCount > 0) {
        flash_warning(
            '"' . $item['name'] . '" has ' . $movementCount . ' stock movement'
            . ($movementCount === 1 ? '' : 's') . ' recorded and was not deleted, to keep that history intact.'
        );
        redirect('inventory.php');
    }

    db_run('DELETE FROM inventory_items WHERE id = ?', [$id]);

    flash_success('"' . $item['name'] . '" was removed from inventory.');
    redirect('inventory.php');
}

flash_error('Unrecognised action.');
redirect('inventory.php');
