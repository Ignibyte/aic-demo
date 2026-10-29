<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * A range the database works out from its today (#21): this month, last month, this quarter or this year.
 */
final readonly class RangePreset
{
    /**
     * @param string      $label       Its link's text.
     * @param ReportRange $reportRange Its first and last day.
     */
    public function __construct(
        public string $label,
        public ReportRange $reportRange,
    ) {
    }
}
