<?php
/**
 * User account write operations.
 *
 * POST only, CSRF-checked, admin only. No HTML is produced here -- every
 * path ends in a redirect back to manage_users.php with a flash message.
 *
 * Two safety guards the old page had neither of: you cannot delete your
 * own account (locks you out with no recovery but the DB), and you cannot
 * delete or demote the last remaining admin (locks EVERYONE out).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin');
csrf_check();

$do = post('do');

function admin_count(int $excludeId = 0): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?",
        [$excludeId]
    );
}

function validate_user_input(): array
{
    $errors = [];
    $name  = post('name');
    $email = post('email');
    $role  = one_of(post('role'), array_keys(ROLES), null);

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name must be 100 characters or fewer.';
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'A valid email address is required.';
    }
    if ($role === null) {
        $errors[] = 'Choose a valid role.';
    }

    return [['name' => $name, 'email' => $email, 'role' => $role], $errors];
}

if ($do === 'create') {
    list($data, $errors) = validate_user_input();

    $password = post('password');
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($data['email'] !== '' && db_value('SELECT 1 FROM users WHERE email = ?', [$data['email']]) !== null) {
        $errors[] = 'A user with that email already exists.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('manage_users.php');
    }

    db_run(
        'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
        [$data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['role']]
    );

    flash_success('"' . $data['name'] . '" was added.');
    redirect('manage_users.php');
}

if ($do === 'update') {
    $id = post_int('id');
    $existing = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if ($existing === null) {
        flash_error('That user no longer exists.');
        redirect('manage_users.php');
    }

    list($data, $errors) = validate_user_input();

    if ($data['email'] !== $existing['email']
        && db_value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], $id]) !== null) {
        $errors[] = 'A user with that email already exists.';
    }

    if ($existing['role'] === 'admin' && $data['role'] !== 'admin' && admin_count($id) === 0) {
        $errors[] = 'You cannot demote the last remaining admin.';
    }

    $newPassword = post('password');
    if ($newPassword !== '' && mb_strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('manage_users.php');
    }

    if ($newPassword !== '') {
        db_run(
            'UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?',
            [$data['name'], $data['email'], $data['role'], password_hash($newPassword, PASSWORD_DEFAULT), $id]
        );
    } else {
        db_run(
            'UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?',
            [$data['name'], $data['email'], $data['role'], $id]
        );
    }

    // Editing your own account (e.g. changing your own role or password)
    // should not leave the session claiming a role you no longer have.
    if ($id === user_id()) {
        $_SESSION['user']['name']  = $data['name'];
        $_SESSION['user']['email'] = $data['email'];
        $_SESSION['user']['role']  = $data['role'];
    }

    flash_success('"' . $data['name'] . '" was updated.');
    redirect('manage_users.php');
}

if ($do === 'delete') {
    $id = post_int('id');
    $user = db_one('SELECT id, name, role FROM users WHERE id = ?', [$id]);
    if ($user === null) {
        flash_error('That user no longer exists.');
        redirect('manage_users.php');
    }

    if ($id === user_id()) {
        flash_error('You cannot delete your own account while signed in as it.');
        redirect('manage_users.php');
    }
    if ($user['role'] === 'admin' && admin_count($id) === 0) {
        flash_error('You cannot delete the last remaining admin.');
        redirect('manage_users.php');
    }

    db_run('DELETE FROM users WHERE id = ?', [$id]);

    flash_success('"' . $user['name'] . '" was deleted.');
    redirect('manage_users.php');
}

flash_error('Unrecognised action.');
redirect('manage_users.php');
