<?php

declare(strict_types=1);

namespace App\Dashboard\Entity;

/**
 * One month's column of the sales chart: its short name, the year under it when it starts the chart or a year, its
 * full name for the summary, and how many sales it had.
 */
final readonly class MonthSales
{
    /**
     * @param string $label Its short name (`Jun`).
     * @param string $note  Its year (`2026`) on the chart's first month and on January; empty on the others.
     * @param string $name  Its full name (`June 2026`).
     * @param int    $sales How many sales it had.
     */
    public function __construct(
        public string $label,
        public string $note,
        public string $name,
        public int $sales,
    ) {
    }
}
