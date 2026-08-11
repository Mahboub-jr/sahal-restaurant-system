<?php
/**
 * Customer write operations.
 *
 * POST only, CSRF-checked, role-gated. No HTML is produced here -- every
 * path ends in a redirect back to customers.php with a flash message.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin', 'manager', 'cashier');
csrf_check();

$do = post('do');

function validate_customer_input(): array
{
    $errors  = [];
    $name    = post('name');
    $phone   = post('phone');
    $email   = post('email');
    $address = post('address');

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name must be 100 characters or fewer.';
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'That email address does not look valid.';
    }
    if (mb_strlen($phone) > 20) {
        $errors[] = 'Phone must be 20 characters or fewer.';
    }

    return [
        [
            'name'    => $name,
            'phone'   => $phone !== '' ? $phone : null,
            'email'   => $email !== '' ? $email : null,
            'address' => $address !== '' ? $address : null,
        ],
        $errors,
    ];
}

if ($do === 'create') {
    list($data, $errors) = validate_customer_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('customers.php');
    }

    db_run(
        'INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)',
        [$data['name'], $data['phone'], $data['email'], $data['address']]
    );

    flash_success('"' . $data['name'] . '" was added.');
    redirect('customers.php');
}

if ($do === 'update') {
    $id = post_int('id');
    if (db_value('SELECT 1 FROM customers WHERE id = ?', [$id]) === null) {
        flash_error('That customer no longer exists.');
        redirect('customers.php');
    }

    list($data, $errors) = validate_customer_input();

    if ($errors !== []) {
        flash_error(implode(' ', $errors));
        redirect('customers.php');
    }

    db_run(
        'UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?',
        [$data['name'], $data['phone'], $data['email'], $data['address'], $id]
    );

    flash_success('"' . $data['name'] . '" was updated.');
    redirect('customers.php');
}

/**
 * payments.customer_id is a foreign key with no ON DELETE action (RESTRICT
 * by default), so the database would already refuse this -- checked first
 * here so the message is readable instead of a raw SQL error.
 */
if ($do === 'delete') {
    $id = post_int('id');
    $customer = db_one('SELECT id, name FROM customers WHERE id = ?', [$id]);
    if ($customer === null) {
        flash_error('That customer no longer exists.');
        redirect('customers.php');
    }

    $paymentCount = (int) db_value('SELECT COUNT(*) FROM payments WHERE customer_id = ?', [$id]);
    if ($paymentCount > 0) {
        flash_error(
            '"' . $customer['name'] . '" cannot be deleted: linked to ' . $paymentCount
            . ' payment record(s). That history has to stay intact.'
        );
        redirect('customers.php');
    }

    db_run('DELETE FROM customers WHERE id = ?', [$id]);

    flash_success('"' . $customer['name'] . '" was deleted.');
    redirect('customers.php');
}

flash_error('Unrecognised action.');
redirect('customers.php');
