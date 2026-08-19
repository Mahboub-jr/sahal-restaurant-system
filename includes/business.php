<?php
/**
 * Pure business-logic functions shared between a page and its
 * actions/*.php handler, and exercised directly by tests/.
 *
 * Nothing in this file touches the database, the session, or $_POST/$_GET
 * -- every function here is a plain calculation: given the same arguments,
 * it always returns the same result. That is what makes it possible to
 * `require` this file from a PHPUnit test without dragging in a live DB
 * connection, CSRF checks, or an HTTP request -- unlike actions/orders.php
 * or actions/reservations.php, which run their guard clauses (require_post,
 * require_role, csrf_check) the moment they are loaded.
 *
 * Loaded by includes/bootstrap.php, after helpers.php.
 */

declare(strict_types=1);

/**
 * subtotal, discount, tax, service_charge, total -- all rounded to cents.
 * Tax and service charge are computed on (subtotal - discount), which is
 * the ordinary convention and the only one settings.php's two rates could
 * mean, since no order form has ever offered a choice.
 *
 * $items is a list of ['price' => float, 'quantity' => int]. $taxRate and
 * $serviceRate are percentages (5.0 means 5%), read from Settings by the
 * caller -- kept as parameters here, not read from the DB directly, so
 * this function has no side effects and needs no DB connection to test.
 */
function compute_order_totals(array $items, float $discountInput, float $taxRate, float $serviceRate): array
{
    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += $it['price'] * $it['quantity'];
    }
    $subtotal = round($subtotal, 2);
    $discount = round(max(0.0, min($discountInput, $subtotal)), 2);

    $taxableBase = $subtotal - $discount;

    $tax           = round($taxableBase * $taxRate / 100, 2);
    $serviceCharge = round($taxableBase * $serviceRate / 100, 2);
    $total         = round($subtotal - $discount + $tax + $serviceCharge, 2);

    return [
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'tax'            => $tax,
        'service_charge' => $serviceCharge,
        'total'          => $total,
    ];
}

/**
 * What an order is allowed to move to next, keyed by its current status.
 * Single source of truth for orders.php (which statuses to show a button
 * for) and actions/orders.php (which statuses to actually accept) -- the
 * two were previously two hand-copied arrays that could silently drift
 * apart.
 */
function order_status_transitions(): array
{
    return [
        'Pending'   => ['Preparing', 'Cancelled'],
        'Preparing' => ['Ready', 'Cancelled'],
        'Ready'     => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
    ];
}

/**
 * Same idea as order_status_transitions(), for reservations.php /
 * actions/reservations.php.
 */
function reservation_status_transitions(): array
{
    return [
        'Pending'   => ['Confirmed', 'Cancelled'],
        'Confirmed' => ['Seated', 'Cancelled', 'No-show'],
        'Seated'    => ['Completed'],
        'Completed' => [],
        'Cancelled' => [],
        'No-show'   => [],
    ];
}

/**
 * True if $current is allowed to move to $next, per the given transition
 * map (order_status_transitions() or reservation_status_transitions()).
 */
function status_transition_allowed(string $current, string $next, array $transitions): bool
{
    return in_array($next, $transitions[$current] ?? [], true);
}
