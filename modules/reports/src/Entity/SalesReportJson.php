<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use JsonSerializable;
use Override;

/**
 * The sales report as `/api/reports/sales` answers it (#21): the range, its sales and their revenue in whole dollars,
 * and each month the range touches with its own, the figures the page's sales report shows.
 */
final readonly class SalesReportJson implements JsonSerializable
{
    /**
     * @param ReportRange        $reportRange The range.
     * @param list<ReportFigure> $months      Its months, the oldest first.
     */
    public function __construct(
        private ReportRange $reportRange,
        private array $months,
    ) {
    }

    /**
     * @return array<string, string|int|list<array<string, string|int>>> The range, the totals and the months.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $sales = 0;
        $revenue = 0;
        $months = [];

        foreach ($this->months as $month) {
            $sales += $month->count;
            $revenue += $month->amount;
            $months[] = ['month' => $month->key, 'sales' => $month->count, 'revenue' => $month->amount];
        }

        return [
            'from' => $this->reportRange->from,
            'months' => $months,
            'revenue' => $revenue,
            'sales' => $sales,
            'to' => $this->reportRange->until,
        ];
    }
}
