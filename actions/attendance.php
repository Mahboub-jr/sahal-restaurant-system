<?php
/**
 * Attendance write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to attendance.php with a flash message.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager');
csrf_check();

$do = post('do');

const ATTENDANCE_STATUSES = ['Present', 'Absent', 'Leave'];

if ($do === 'create') {
    $employeeId = post_int('employee_id');
    $date       = post('date');
    $status     = one_of(post('status'), ATTENDANCE_STATUSES, null);

    $errors = [];
    if ($employeeId <= 0 || db_value('SELECT 1 FROM employees WHERE id = ?', [$employeeId]) === null) {
        $errors[] = 'Choose a real employee.';
    }
    if (strtotime($date) === false) {
        $errors[] = 'Choose a valid date.';
    }
    if ($status === null) {
        $errors[] = 'Choose a valid status.';
    }

    if ($errors === []
        && db_value('SELECT 1 FROM attendance WHERE employee_id = ? AND date = ?', [$employeeId, $date]) !== null) {
        $errors[] = 'Attendance was already recorded for this employee on this date.';
    }

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('attendance.php');
    }

    db_run(
        'INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, ?)',
        [$employeeId, $date, $status]
    );

    flash_success('Attendance recorded.');
    redirect('attendance.php');
}

if ($do === 'delete') {
    $id = post_int('id');
    if (db_value('SELECT 1 FROM attendance WHERE id = ?', [$id]) === null) {
        flash_error('That attendance record no longer exists.');
        redirect('attendance.php');
    }

    db_run('DELETE FROM attendance WHERE id = ?', [$id]);

    flash_success('Attendance record removed.');
    redirect('attendance.php');
}

flash_error('Unrecognised action.');
redirect('attendance.php');
