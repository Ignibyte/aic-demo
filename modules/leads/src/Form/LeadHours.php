<?php

declare(strict_types=1);

namespace App\Leads\Form;

use App\Settings\Entity\OpeningHours;

use function checkdate;
use function intdiv;
use function preg_match;
use function substr;

/**
 * The dealership's opening hours, as a test drive is held to them (#18): on a day after today that the dealership is
 * open, starting from the opening to an hour before the closing. Since #22 the hours are the settings' profile
 * (`OpeningHours`), Monday to Saturday, 09:00 to 18:00 until an admin saves others, and the error texts are built from
 * them. Days and times compare as text, which sorts as they do; the weekday is worked out in integers (Zeller's
 * congruence), so no time zone enters it.
 */
final readonly class LeadHours
{
    /**
     * @param string       $day          The posted day.
     * @param string       $today        The database's today, `YYYY-MM-DD`.
     * @param OpeningHours $openingHours When the dealership is open.
     *
     * @return string Why the day is refused; empty when it is fine.
     */
    public function dayError(string $day, string $today, OpeningHours $openingHours): string
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

        if ($day <= $today) {
            return 'Choose a day from tomorrow on.';
        }

        // Zeller's 0 is Saturday and 1 Sunday; the ISO weekday counts from Monday as 1.
        $isoDay = ($this->weekday($year, $month, $date) + 5) % 7 + 1;

        return $openingHours->opensOn($isoDay)
            ? ''
            : 'We are closed on ' . $openingHours->dayName($isoDay) . 's. Choose ' . $openingHours->choice() . '.';
    }

    /**
     * @param string       $time         The posted time.
     * @param OpeningHours $openingHours When the dealership is open.
     *
     * @return string Why the time is refused; empty when it is fine.
     */
    public function timeError(string $time, OpeningHours $openingHours): string
    {
        $written = preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $time) === 1;
        $first = $openingHours->opensAt;
        $last = $openingHours->lastStart();

        return $written && $time >= $first && $time <= $last
            ? ''
            : 'Choose a time from ' . $first . ' to ' . $last . '. We are open ' . $first . ' to ' . $openingHours->closesAt . ', '
                . $openingHours->days() . '.';
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
