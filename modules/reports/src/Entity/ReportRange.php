<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * A checked date range (#21): its first and last day, each one the calendar holds, the first not after the last.
 */
final readonly class ReportRange
{
    /**
     * @param string $from  The first day, `YYYY-MM-DD`.
     * @param string $until The last day, `YYYY-MM-DD`.
     */
    public function __construct(
        public string $from,
        public string $until,
    ) {
    }

    /**
     * @return string The range as a query, `from=…&to=…`.
     */
    public function query(): string
    {
        return 'from=' . $this->from . '&to=' . $this->until;
    }
}
