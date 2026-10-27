<?php

declare(strict_types=1);

namespace App\Sales\Service;

/**
 * How long a month is, by plain arithmetic (#14): the sales list's month filter ends on its last day, and the seed
 * counts a sale's day from its vehicle's. PHP's date classes are not used, since elephc is not known to compile them;
 * no AIC ticket carries them, as `SeedVehicles` notes.
 */
final readonly class MonthLengths
{
    /**
     * The days of each month, January first, in a year that is not a leap year.
     */
    private const array DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    /**
     * @param int $year  A year.
     * @param int $month One of its months, 1 to 12.
     *
     * @return int How many days the month has: February 29 in a leap year.
     */
    public function days(int $year, int $month): int
    {
        $extra = $month === 2 && $year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0)
            ? 1
            : 0;

        return (self::DAYS[$month - 1] ?? 31) + $extra;
    }
}
