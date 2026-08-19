<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * includes/helpers.php — the small, dependency-free utilities. Anything in
 * that file which touches the database (money(), setting()) is out of
 * scope for a unit test; see includes/business.php's tests for the parts
 * of the app that are pure and DB-free.
 */
final class HelpersTest extends TestCase
{
    /* --- e() ---------------------------------------------------------- */

    public function testEscapesHtmlSpecialCharacters(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            e('<script>alert(1)</script>')
        );
    }

    public function testEscapesQuotesInBothDirections(): void
    {
        $this->assertSame('&quot;curly&quot; &amp; &#039;straight&#039;', e('"curly" & \'straight\''));
    }

    public function testEscapeTreatsNullAsEmptyString(): void
    {
        $this->assertSame('', e(null));
    }

    public function testEscapeCoercesNonStringScalars(): void
    {
        $this->assertSame('42', e(42));
        $this->assertSame('3.5', e(3.5));
    }

    /* --- ejs() ---------------------------------------------------------- */

    public function testEjsProducesValidJsonForAScript(): void
    {
        $encoded = ejs(['name' => '<b>Bariis</b>', 'price' => 5.25]);
        $decoded = json_decode($encoded, true);

        $this->assertSame('<b>Bariis</b>', $decoded['name']);
        // 5.25 has no whole-number ambiguity, unlike 5.0 (which json_decode
        // would hand back as the int 5, not the float 5.0).
        $this->assertSame(5.25, $decoded['price']);
    }

    public function testEjsEscapesHtmlSignificantCharacters(): void
    {
        // JSON_HEX_TAG etc. -- so the encoded string is safe to drop
        // straight into an inline <script> block without closing it early.
        $encoded = ejs('</script><script>alert(1)</script>');
        $this->assertStringNotContainsString('</script>', $encoded);
    }

    /* --- url() ------------------------------------------------------------ */

    public function testUrlJoinsBaseUrlAndPath(): void
    {
        $this->assertSame('/Restuarent_system/menu.php', url('menu.php'));
    }

    public function testUrlStripsALeadingSlashOnThePathSoItDoesNotDoubleUp(): void
    {
        $this->assertSame('/Restuarent_system/menu.php', url('/menu.php'));
    }

    public function testUrlWithNoArgumentReturnsJustTheBase(): void
    {
        $this->assertSame('/Restuarent_system/', url());
    }

    /* --- is_current() ------------------------------------------------- */

    public function testIsCurrentMatchesOnBasenameOnly(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/Restuarent_system/orders.php';
        $this->assertTrue(is_current('orders.php'));
        $this->assertTrue(is_current('/some/other/path/orders.php'));
        $this->assertFalse(is_current('menu.php'));
    }

    /* --- one_of() ------------------------------------------------------ */

    public function testOneOfReturnsTheValueWhenItIsInTheAllowedList(): void
    {
        $this->assertSame('Paid', one_of('Paid', ['Paid', 'Pending'], null));
    }

    public function testOneOfReturnsTheDefaultWhenTheValueIsNotAllowed(): void
    {
        // This is the exact guard that BUG-1 (order_type silently discarded)
        // needed: an unrecognised value must never be passed through.
        $this->assertNull(one_of('Take Away', ['Dine-In', 'Takeaway'], null));
        $this->assertSame('Unpaid', one_of('bogus', ['Paid', 'Unpaid'], 'Unpaid'));
    }

    public function testOneOfComparesStrictly(): void
    {
        // '0' and 0 and false must not be treated as interchangeable.
        $this->assertNull(one_of('0', [0, false], null));
    }

    /* --- status_colour() ------------------------------------------------ */

    public function testStatusColourMapsKnownStatuses(): void
    {
        $this->assertSame('success', status_colour('Completed'));
        $this->assertSame('danger', status_colour('Cancelled'));
        $this->assertSame('warning', status_colour('Preparing'));
    }

    public function testStatusColourFallsBackToLightForAnythingUnknown(): void
    {
        $this->assertSame('light', status_colour('Something made up'));
        $this->assertSame('light', status_colour(null));
    }

    /* --- time_ago() ------------------------------------------------------- */

    public function testTimeAgoJustNowForAVeryRecentTimestamp(): void
    {
        $this->assertSame('just now', time_ago(date('Y-m-d H:i:s', time() - 5)));
    }

    public function testTimeAgoMinutesForSomethingUnderAnHourOld(): void
    {
        $this->assertSame('5 min ago', time_ago(date('Y-m-d H:i:s', time() - 5 * 60)));
    }

    public function testTimeAgoHoursForSomethingUnderADayOld(): void
    {
        $this->assertSame('3 hr ago', time_ago(date('Y-m-d H:i:s', time() - 3 * 3600)));
    }

    public function testTimeAgoReturnsAnEmDashForNullOrEmpty(): void
    {
        $this->assertSame('—', time_ago(null));
        $this->assertSame('—', time_ago(''));
    }

    public function testTimeAgoReturnsAnEmDashForUnparseableInput(): void
    {
        $this->assertSame('—', time_ago('not a date'));
    }
}
