<?php
/**
 * Employee write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to employees.php with a flash message.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

const EMPLOYEE_STATUSES = ['Active', 'Inactive'];

function validate_employee_input(): array
{
    $errors   = [];
    $name     = post('name');
    $position = post('position');
    $phone    = post('phone');
    $email    = post('email');
    $status   = one_of(post('status'), EMPLOYEE_STATUSES, 'Active');

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name must be 100 characters or fewer.';
    }
    if ($position === '') {
        $errors[] = 'Position is required.';
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'That email address does not look valid.';
    }

    return [
        [
            'name'     => $name,
            'position' => $position !== '' ? $position : null,
            'phone'    => $phone !== '' ? $phone : null,
            'email'    => $email !== '' ? $email : null,
            'status'   => $status,
        ],
        $errors,
    ];
}

if ($do === 'create') {
    list($data, $errors) = validate_employee_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('employees.php');
    }

    db_run(
        'INSERT INTO employees (name, position, phone, email, status, join_date) VALUES (?, ?, ?, ?, ?, CURDATE())',
        [$data['name'], $data['position'], $data['phone'], $data['email'], $data['status']]
    );

    flash_success('"' . $data['name'] . '" was added.');
    redirect('employees.php');
}

if ($do === 'update') {
    $id = post_int('id');
    if (db_value('SELECT 1 FROM employees WHERE id = ?', [$id]) === null) {
        flash_error('That employee no longer exists.');
        redirect('employees.php');
    }

    list($data, $errors) = validate_employee_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('employees.php');
    }

    db_run(
        'UPDATE employees SET name = ?, position = ?, phone = ?, email = ?, status = ? WHERE id = ?',
        [$data['name'], $data['position'], $data['phone'], $data['email'], $data['status'], $id]
    );

    flash_success('"' . $data['name'] . '" was updated.');
    redirect('employees.php');
}

/**
 * attendance.employee_id is a foreign key with no ON DELETE action, so the
 * database would already refuse this -- checked first for a readable message.
 */
if ($do === 'delete') {
    $id = post_int('id');
    $employee = db_one('SELECT id, name FROM employees WHERE id = ?', [$id]);
    if ($employee === null) {
        flash_error('That employee no longer exists.');
        redirect('employees.php');
    }

    $attendanceCount = (int) db_value('SELECT COUNT(*) FROM attendance WHERE employee_id = ?', [$id]);
    if ($attendanceCount > 0) {
        flash_error(
            '"' . $employee['name'] . '" cannot be deleted: has ' . $attendanceCount
            . ' attendance record(s). Set status to Inactive instead, to keep that history intact.'
        );
        redirect('employees.php');
    }

    db_run('DELETE FROM employees WHERE id = ?', [$id]);

    flash_success('"' . $employee['name'] . '" was deleted.');
    redirect('employees.php');
}

flash_error('Unrecognised action.');
redirect('employees.php');
