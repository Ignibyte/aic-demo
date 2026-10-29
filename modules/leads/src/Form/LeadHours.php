<?php

declare(strict_types=1);

namespace App\Leads\Form;

use function checkdate;
use function intdiv;
use function preg_match;
use function strcmp;
use function substr;

/**
 * Ridgeway Motors' opening hours, as a test drive is held to them (#18): open Monday to Saturday, 09:00 to 18:00, and a
 * drive starts by 17:00, on a day after today. Days and times compare as text with `strcmp()`, since elephc refuses `<`
 * and `>` between two strings (old Lotline's ticket 28; no AIC ticket carries it yet, as sales' `SaleChecks` notes); the
 * weekday is worked out in integers (Zeller's congruence), so no time zone enters it.
 */
final readonly class LeadHours
{
    /**
     * The earliest start, when the doors open.
     */
    public const string FIRST = '09:00';

    /**
     * The latest start, an hour before the doors close.
     */
    public const string LAST = '17:00';

    /**
     * Why a time is refused.
     */
    public const string TIME_REFUSED = 'Choose a time from 09:00 to 17:00. We are open 09:00 to 18:00, Monday to Saturday.';

    /**
     * @param string $day   The posted day.
     * @param string $today The database's today, `YYYY-MM-DD`.
     *
     * @return string Why the day is refused; empty when it is fine.
     */
    public function dayError(string $day, string $today): string
    {
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $day) !== 1) {
            return 'Choose a day, written YYYY-MM-DD.';
        }

        $year = (int) substr($day, 0, 4);
        $month = (int) substr($day, 5, 2);
        $date = (int) substr($day, 8, 2);

        if (!checkdate($month, $date, $year)) {
            return 'Choose a day that is on the calendar.';
        }

        if (strcmp($day, $today) <= 0) {
            return 'Choose a day from tomorrow on.';
        }

        return $this->weekday($year, $month, $date) === 1
            ? 'We are closed on Sundays. Choose a day from Monday to Saturday.'
            : '';
    }

    /**
     * @param string $time The posted time.
     *
     * @return string Why the time is refused; empty when it is fine.
     */
    public function timeError(string $time): string
    {
        $written = preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $time) === 1;

        return $written && strcmp($time, self::FIRST) >= 0 && strcmp($time, self::LAST) <= 0
            ? ''
            : self::TIME_REFUSED;
    }

    /**
     * @param int $year  A year.
     * @param int $month Its month, 1 to 12.
     * @param int $date  Its day of the month.
     *
     * @return int The weekday by Zeller's congruence: 0 for Saturday, 1 for Sunday, to 6 for Friday.
     */
    private function weekday(int $year, int $month, int $date): int
    {
        // January and February count as months 13 and 14 of the year before.
        $shifted = $month < 3
            ? $year - 1
            : $year;
        $counted = $month < 3
            ? $month + 12
            : $month;
        $century = intdiv($shifted, 100);
        $rest = $shifted % 100;

        return ($date + intdiv(13 * ($counted + 1), 5) + $rest + intdiv($rest, 4) + intdiv($century, 4) + 5 * $century) % 7;
    }
}
