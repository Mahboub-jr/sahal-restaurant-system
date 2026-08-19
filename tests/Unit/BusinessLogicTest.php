<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * includes/business.php — compute_order_totals() is the money math behind
 * every order, extracted out of actions/orders.php specifically so it
 * could be tested without a live database (see that file's docblock).
 * The status-transition maps are the single source of truth orders.php /
 * actions/orders.php and reservations.php / actions/reservations.php both
 * read from, replacing what used to be two hand-copied arrays each.
 */
final class BusinessLogicTest extends TestCase
{
    /* --- compute_order_totals() ---------------------------------------- */

    public function testSubtotalIsPriceTimesQuantitySummedAcrossLines(): void
    {
        $items = [
            ['price' => 5.00, 'quantity' => 2],
            ['price' => 3.50, 'quantity' => 1],
        ];

        $totals = compute_order_totals($items, 0.0, 0.0, 0.0);

        $this->assertSame(13.50, $totals['subtotal']);
        $this->assertSame(0.0, $totals['discount']);
        $this->assertSame(0.0, $totals['tax']);
        $this->assertSame(0.0, $totals['service_charge']);
        $this->assertSame(13.50, $totals['total']);
    }

    public function testTaxAndServiceChargeAreAppliedAsPercentagesOfTheDiscountedSubtotal(): void
    {
        $items = [['price' => 10.00, 'quantity' => 1]];

        $totals = compute_order_totals($items, 0.0, 5.0, 10.0);

        $this->assertSame(10.00, $totals['subtotal']);
        $this->assertSame(0.50, $totals['tax']);
        $this->assertSame(1.00, $totals['service_charge']);
        $this->assertSame(11.50, $totals['total']);
    }

    public function testDiscountIsAppliedBeforeTaxAndServiceCharge(): void
    {
        $items = [['price' => 20.00, 'quantity' => 1]];

        $totals = compute_order_totals($items, 5.00, 10.0, 0.0);

        // Taxable base is 20 - 5 = 15, not the full 20.
        $this->assertSame(5.00, $totals['discount']);
        $this->assertSame(1.50, $totals['tax']);
        $this->assertSame(16.50, $totals['total']);
    }

    public function testDiscountCannotExceedTheSubtotal(): void
    {
        $items = [['price' => 5.00, 'quantity' => 1]];

        $totals = compute_order_totals($items, 100.00, 0.0, 0.0);

        $this->assertSame(5.00, $totals['discount']);
        $this->assertSame(0.00, $totals['total']);
    }

    public function testNegativeDiscountInputIsClampedToZero(): void
    {
        $items = [['price' => 5.00, 'quantity' => 1]];

        $totals = compute_order_totals($items, -10.00, 0.0, 0.0);

        $this->assertSame(0.0, $totals['discount']);
        $this->assertSame(5.00, $totals['total']);
    }

    public function testMoneyIsRoundedToTheCentAtEveryStep(): void
    {
        $items = [['price' => 3.33, 'quantity' => 3]]; // subtotal 9.99

        $totals = compute_order_totals($items, 0.0, 7.5, 0.0);

        // 9.99 * 7.5% = 0.74925, must round to 0.75, not truncate to 0.74.
        $this->assertSame(9.99, $totals['subtotal']);
        $this->assertSame(0.75, $totals['tax']);
        $this->assertSame(10.74, $totals['total']);
    }

    public function testEmptyCartHasZeroTotals(): void
    {
        $totals = compute_order_totals([], 0.0, 5.0, 10.0);

        $this->assertSame(0.0, $totals['subtotal']);
        $this->assertSame(0.0, $totals['total']);
    }

    /* --- order_status_transitions() ------------------------------------ */

    public function testPendingOrderCanMoveToPreparingOrCancelled(): void
    {
        $transitions = order_status_transitions();

        $this->assertTrue(status_transition_allowed('Pending', 'Preparing', $transitions));
        $this->assertTrue(status_transition_allowed('Pending', 'Cancelled', $transitions));
    }

    public function testAnOrderCannotSkipStraightFromPendingToCompleted(): void
    {
        $transitions = order_status_transitions();

        $this->assertFalse(status_transition_allowed('Pending', 'Completed', $transitions));
    }

    public function testCompletedAndCancelledOrdersAreTerminal(): void
    {
        $transitions = order_status_transitions();

        $this->assertSame([], $transitions['Completed']);
        $this->assertSame([], $transitions['Cancelled']);
    }

    public function testAnUnknownCurrentStatusAllowsNothing(): void
    {
        $this->assertFalse(status_transition_allowed('NotARealStatus', 'Preparing', order_status_transitions()));
    }

    /* --- reservation_status_transitions() -------------------------------- */

    public function testAConfirmedReservationCanBeSeatedCancelledOrMarkedNoShow(): void
    {
        $transitions = reservation_status_transitions();

        $this->assertTrue(status_transition_allowed('Confirmed', 'Seated', $transitions));
        $this->assertTrue(status_transition_allowed('Confirmed', 'Cancelled', $transitions));
        $this->assertTrue(status_transition_allowed('Confirmed', 'No-show', $transitions));
    }

    public function testASeatedReservationCanOnlyBeCompletedNotCancelled(): void
    {
        // Once someone is sitting at the table, "cancelled" no longer makes
        // sense -- that is the one place orders and reservations diverge.
        $transitions = reservation_status_transitions();

        $this->assertTrue(status_transition_allowed('Seated', 'Completed', $transitions));
        $this->assertFalse(status_transition_allowed('Seated', 'Cancelled', $transitions));
    }
}
