<?php
/**
 * Menu item write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here — every
 * path ends in a redirect back to menu.php with a flash message.
 *
 * This is the pattern the rest of the application will move to: pages
 * render, actions mutate. It replaces the old arrangement where menu.php
 * held an unguarded INSERT, an unvalidated upload and a DELETE all mixed
 * into the same file as the markup.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

/* =====================================================================
 | Helpers
 |===================================================================== */

/**
 * Validate the fields shared by create and update.
 * Returns [$data, $errors].
 */
function validate_menu_input(): array
{
    $errors = [];

    $name        = post('name');
    $categoryId  = post_int('category_id');
    $price       = post_float('price', -1);
    $description = post('description');
    $available   = isset($_POST['is_available']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Item name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Item name must be 100 characters or fewer.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please choose a category.';
    } else {
        $exists = db_value('SELECT 1 FROM categories WHERE id = ?', [$categoryId]);
        if ($exists === null) {
            // Exactly the condition that produced the orphaned 'bariis' row.
            $errors[] = 'That category no longer exists.';
        }
    }

    if ($price < 0) {
        $errors[] = 'Price must be zero or greater.';
    } elseif ($price > 99999999) {
        $errors[] = 'That price is unrealistically large.';
    }

    if (mb_strlen($description) > 2000) {
        $errors[] = 'Description is too long.';
    }

    return [
        [
            'name'         => $name,
            'category_id'  => $categoryId,
            'price'        => round($price, 2),
            'description'  => $description,
            'is_available' => $available,
        ],
        $errors,
    ];
}

/**
 * Does menu_items.is_available exist yet? Migration 003 may not have been
 * run, and the module should degrade rather than fatal.
 */
function has_availability_column(): bool
{
    static $has = null;
    if ($has === null) {
        $has = db_value(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'menu_items'
                AND COLUMN_NAME = 'is_available'",
            [DB_NAME]
        ) !== null;
    }
    return (bool) $has;
}

/* =====================================================================
 | Create
 |===================================================================== */
if ($do === 'create') {
    list($data, $errors) = validate_menu_input();

    $upload = upload_image($_FILES['food_image'] ?? null, 'menu');
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    if ($errors !== []) {
        // The image already landed on disk; do not leave it orphaned.
        if ($upload['ok'] && $upload['filename'] !== null) {
            delete_upload($upload['filename'], 'menu');
        }
        flash_error(implode(' ', $errors));
        redirect('menu.php');
    }

    if (has_availability_column()) {
        db_run(
            'INSERT INTO menu_items (name, category_id, price, description, food_image, is_available)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['category_id'], $data['price'],
                $data['description'], $upload['filename'], $data['is_available'],
            ]
        );
    } else {
        db_run(
            'INSERT INTO menu_items (name, category_id, price, description, food_image)
             VALUES (?, ?, ?, ?, ?)',
            [
                $data['name'], $data['category_id'], $data['price'],
                $data['description'], $upload['filename'],
            ]
        );
    }

    flash_success('“' . $data['name'] . '” was added to the menu.');
    redirect('menu.php');
}

/* =====================================================================
 | Update
 |===================================================================== */
if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM menu_items WHERE id = ?', [$id]);

    if ($existing === null) {
        flash_error('That menu item no longer exists.');
        redirect('menu.php');
    }

    list($data, $errors) = validate_menu_input();

    $upload = upload_image($_FILES['food_image'] ?? null, 'menu');
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    if ($errors !== []) {
        if ($upload['ok'] && $upload['filename'] !== null) {
            delete_upload($upload['filename'], 'menu');
        }
        flash_error(implode(' ', $errors));
        redirect('menu.php');
    }

    // Keep the current image unless a new one was supplied, or removal was
    // explicitly requested.
    $image = $existing['food_image'];
    $removeImage = post('remove_image') === '1';

    if ($upload['filename'] !== null) {
        $image = $upload['filename'];
        delete_upload($existing['food_image'], 'menu');
    } elseif ($removeImage) {
        delete_upload($existing['food_image'], 'menu');
        $image = null;
    }

    if (has_availability_column()) {
        db_run(
            'UPDATE menu_items
                SET name = ?, category_id = ?, price = ?, description = ?,
                    food_image = ?, is_available = ?
              WHERE id = ?',
            [
                $data['name'], $data['category_id'], $data['price'],
                $data['description'], $image, $data['is_available'], $id,
            ]
        );
    } else {
        db_run(
            'UPDATE menu_items
                SET name = ?, category_id = ?, price = ?, description = ?, food_image = ?
              WHERE id = ?',
            [
                $data['name'], $data['category_id'], $data['price'],
                $data['description'], $image, $id,
            ]
        );
    }

    flash_success('“' . $data['name'] . '” was updated.');
    redirect('menu.php');
}

/* =====================================================================
 | Toggle availability
 |===================================================================== */
if ($do === 'toggle') {
    if (!has_availability_column()) {
        flash_warning('Run migration 004 to enable the availability switch.');
        redirect('menu.php');
    }

    $id = post_int('id');
    $item = db_one('SELECT id, name, is_available FROM menu_items WHERE id = ?', [$id]);

    if ($item === null) {
        flash_error('That menu item no longer exists.');
        redirect('menu.php');
    }

    $next = ((int) $item['is_available']) === 1 ? 0 : 1;
    db_run('UPDATE menu_items SET is_available = ? WHERE id = ?', [$next, $id]);

    flash_success('“' . $item['name'] . '” is now '
        . ($next === 1 ? 'available' : 'unavailable') . '.');
    redirect('menu.php');
}

/* =====================================================================
 | Delete
 |=====================================================================
 | Deleting an item that appears in past orders would rewrite history.
 | Because orders still store items as a JSON blob there is no foreign key
 | to lean on, so the check is a text search — imperfect, but it stops the
 | obvious mistakes until order_items exists.
 */
if ($do === 'delete') {
    $id = post_int('id');
    $item = db_one('SELECT id, name, food_image FROM menu_items WHERE id = ?', [$id]);

    if ($item === null) {
        flash_error('That menu item no longer exists.');
        redirect('menu.php');
    }

    $usedIn = (int) db_value(
        'SELECT COUNT(*) FROM orders WHERE items LIKE ?',
        ['%"' . $item['name'] . '"%']
    );

    if ($usedIn > 0 && post('force') !== '1') {
        if (has_availability_column()) {
            db_run('UPDATE menu_items SET is_available = 0 WHERE id = ?', [$id]);
            flash_warning(
                '“' . $item['name'] . '” appears in ' . $usedIn . ' past order'
                . ($usedIn === 1 ? '' : 's') . ', so it was marked unavailable '
                . 'instead of deleted. That keeps order history intact.'
            );
        } else {
            flash_warning(
                '“' . $item['name'] . '” appears in ' . $usedIn . ' past order'
                . ($usedIn === 1 ? '' : 's') . ' and was not deleted, to keep '
                . 'order history intact.'
            );
        }
        redirect('menu.php');
    }

    delete_upload($item['food_image'], 'menu');
    db_run('DELETE FROM menu_items WHERE id = ?', [$id]);

    flash_success('“' . $item['name'] . '” was removed from the menu.');
    redirect('menu.php');
}

flash_error('Unrecognised action.');
redirect('menu.php');
