<?php

declare(strict_types=1);

namespace App\Reports\Form;

use App\Reports\Entity\ReportRange;

/**
 * What a request's range came to (#21): the range to report on, which is this month when the asked one is refused,
 * the days as they were asked, to show in the form again, and each refused day's error by its field.
 */
final readonly class RangeCheck
{
    /**
     * @param ReportRange           $reportRange The range to report on.
     * @param string                $from        The first day as asked, or the range's when none was.
     * @param string                $until       The last day as asked, or the range's when none was.
     * @param array<string, string> $errors      What is wrong with each field, by its name (`from`, `to`); empty when
     *                                           the range was taken.
     */
    public function __construct(
        public ReportRange $reportRange,
        public string $from,
        public string $until,
        public array $errors,
    ) {
    }

    /**
     * @return bool Whether the range asked for was refused.
     */
    public function refused(): bool
    {
        return $this->errors !== [];
    }
}
