<?php
/**
 * Category write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to categories.php with a flash message.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

function validate_category_input(): array
{
    $errors = [];
    $name = post('name');
    $desc = post('description');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Category name must be 100 characters or fewer.';
    }
    if (mb_strlen($desc) > 1000) {
        $errors[] = 'Description is too long.';
    }

    return [['name' => $name, 'description' => $desc !== '' ? $desc : null], $errors];
}

if ($do === 'create') {
    list($data, $errors) = validate_category_input();

    if (db_value('SELECT 1 FROM categories WHERE name = ?', [$data['name']]) !== null) {
        $errors[] = 'A category named "' . $data['name'] . '" already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('categories.php');
    }

    db_run('INSERT INTO categories (name, description) VALUES (?, ?)', [$data['name'], $data['description']]);

    flash_success('"' . $data['name'] . '" was added.');
    redirect('categories.php');
}

if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM categories WHERE id = ?', [$id]);
    if ($existing === null) {
        flash_error('That category no longer exists.');
        redirect('categories.php');
    }

    list($data, $errors) = validate_category_input();

    if ($data['name'] !== $existing['name']
        && db_value('SELECT 1 FROM categories WHERE name = ? AND id <> ?', [$data['name'], $id]) !== null) {
        $errors[] = 'A category named "' . $data['name'] . '" already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('categories.php');
    }

    db_run('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$data['name'], $data['description'], $id]);

    flash_success('"' . $data['name'] . '" was updated.');
    redirect('categories.php');
}

/**
 * Deleting a category with menu items still assigned to it would either
 * orphan them (how 'bariis' became unmanageable -- AUDIT-ADDENDUM.md BUG-2)
 * or, since migration 002 added fk_menu_items_category ON DELETE RESTRICT,
 * simply fail at the database. Checked here first for a readable message.
 */
if ($do === 'delete') {
    $id = post_int('id');
    $category = db_one('SELECT id, name FROM categories WHERE id = ?', [$id]);
    if ($category === null) {
        flash_error('That category no longer exists.');
        redirect('categories.php');
    }

    $inUse = (int) db_value('SELECT COUNT(*) FROM menu_items WHERE category_id = ?', [$id]);
    if ($inUse > 0) {
        flash_error(
            '"' . $category['name'] . '" cannot be deleted: ' . $inUse . ' menu item'
            . ($inUse === 1 ? '' : 's') . ' still belong to it. Reassign or remove those first.'
        );
        redirect('categories.php');
    }

    db_run('DELETE FROM categories WHERE id = ?', [$id]);

    flash_success('"' . $category['name'] . '" was deleted.');
    redirect('categories.php');
}

flash_error('Unrecognised action.');
redirect('categories.php');
