<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * One line of a report (#21), whichever report it is: what it is about, a count and an amount, which the report's
 * header names (a month's sales and revenue, a make's vehicles and average days on the lot).
 */
final readonly class ReportFigure
{
    /**
     * @param string $key    What it is about, as the view groups it: a month `YYYY-MM`, a salesperson's login, a make or a
     *                       lead's kind.
     * @param string $label  The same in words.
     * @param int    $count  Its count.
     * @param int    $amount Its amount: whole dollars, days or a second count.
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public int $amount,
    ) {
    }
}
