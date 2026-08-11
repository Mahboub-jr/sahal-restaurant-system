<?php
/**
 * Stock movement write operations.
 *
 * POST only, CSRF-checked, role-gated. Append-only by design -- there is
 * no update or delete here. A mistake is corrected with another movement
 * (type=Correction), never by editing or removing the original row, so
 * the ledger always explains how quantity_on_hand got to where it is.
 *
 * The guard: an Out-direction movement (Used, Wasted, or a Correction
 * chosen as "decrease") is rejected if it would take quantity_on_hand
 * below zero. The item row is locked (SELECT ... FOR UPDATE) for the
 * length of the transaction so two movements submitted at once cannot
 * both read the same starting balance and both go through.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

const MOVEMENT_TYPES = ['Received', 'Used', 'Wasted', 'Correction'];

function stock_movements_schema_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = db_value(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'stock_movements'",
            [DB_NAME]
        ) !== null;
    }
    return (bool) $ready;
}

if (!stock_movements_schema_ready()) {
    flash_error('Run sql/migrations/007_reservations_and_inventory.sql before recording stock movements.');
    redirect('stock_movements.php');
}

if ($do === 'create') {
    $itemId    = post_int('inventory_item_id');
    $type      = one_of(post('type'), MOVEMENT_TYPES, null);
    $magnitude = post_float('quantity', -1);
    $direction = post('direction'); // only meaningful when type = Correction
    $reason    = post('reason');

    $errors = [];
    $item = $itemId > 0 ? db_one('SELECT id, name, quantity_on_hand FROM inventory_items WHERE id = ?', [$itemId]) : null;
    if ($item === null) {
        $errors[] = 'Choose a real inventory item.';
    }
    if ($type === null) {
        $errors[] = 'Choose a movement type.';
    }
    if ($magnitude <= 0) {
        $errors[] = 'Quantity must be greater than zero.';
    } elseif ($magnitude > 999999) {
        $errors[] = 'That quantity is unrealistically large.';
    }
    if (mb_strlen($reason) > 255) {
        $errors[] = 'Reason must be 255 characters or fewer.';
    }

    // Direction: Received always adds stock; Used/Wasted always remove it;
    // Correction is the only type where the person recording it chooses.
    $signedQty = null;
    if ($type === 'Received') {
        $signedQty = $magnitude;
    } elseif ($type === 'Used' || $type === 'Wasted') {
        $signedQty = -$magnitude;
    } elseif ($type === 'Correction') {
        if ($direction === 'increase') {
            $signedQty = $magnitude;
        } elseif ($direction === 'decrease') {
            $signedQty = -$magnitude;
        } else {
            $errors[] = 'Choose whether this correction increases or decreases stock.';
        }
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('stock_movements.php');
    }

    $guardError = db_transaction(function () use ($itemId, $type, $signedQty, $reason) {
        $locked = db_one('SELECT quantity_on_hand FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId]);
        if ($locked === null) {
            return 'That inventory item no longer exists.';
        }

        $resultingQty = round((float) $locked['quantity_on_hand'] + $signedQty, 2);
        if ($resultingQty < 0) {
            return 'That would take stock below zero (currently ' . round((float) $locked['quantity_on_hand'], 2)
                 . '). Check the quantity, or record what is actually on hand as a correction.';
        }

        db_run(
            'INSERT INTO stock_movements (inventory_item_id, type, change_qty, reason, user_id)
             VALUES (?, ?, ?, ?, ?)',
            [$itemId, $type, $signedQty, $reason !== '' ? $reason : null, user_id()]
        );

        db_run('UPDATE inventory_items SET quantity_on_hand = ? WHERE id = ?', [$resultingQty, $itemId]);
        return null;
    });

    if ($guardError !== null) {
        flash_error($guardError);
        redirect('stock_movements.php');
    }

    flash_success('Stock movement recorded.');
    redirect('stock_movements.php');
}

flash_error('Unrecognised action.');
redirect('stock_movements.php');
