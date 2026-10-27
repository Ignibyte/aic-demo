<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Entity\Figure;
use App\Dashboard\Entity\MonthSales;

use function count;
use function number_format;

/**
 * Each chart's summary in words, from the rows it draws: the chart's `<desc>` and caption, for anyone who cannot see
 * the bars.
 */
final readonly class ChartSummaries
{
    /**
     * @param DashboardWords $dashboardWords Writes counts and money.
     */
    public function __construct(
        private DashboardWords $dashboardWords = new DashboardWords(),
    ) {
    }

    /**
     * @param list<MonthSales> $months The last twelve months, the oldest first.
     *
     * @return string How many sales the months had, and the latest month with the most.
     */
    public function sales(array $months): string
    {
        $total = 0;
        $busiest = new MonthSales('', '', '', 0);

        foreach ($months as $month) {
            $total += $month->sales;
            $busiest = $this->busier($month, $busiest);
        }

        if ($total === 0) {
            return 'No sales over the last twelve months.';
        }

        return $this->dashboardWords->count($total, 'sale', 'sales') . ' over the last twelve months; the most, '
            . number_format($busiest->sales) . ', in ' . $busiest->name . '.';
    }

    /**
     * @param list<Figure> $makes The vehicles not sold per make, the most first.
     *
     * @return string How many vehicles across how many makes, and the make with the most.
     */
    public function makes(array $makes): string
    {
        $first = $makes[0] ?? null;

        if (!$first instanceof Figure) {
            return 'No vehicles in stock or on hold.';
        }

        return $this->dashboardWords->count($this->total($makes), 'vehicle', 'vehicles') . ' in stock or on hold across '
            . $this->dashboardWords->count(count($makes), 'make', 'makes') . '; ' . $first->label . ' has the most, '
            . number_format($first->amount) . '.';
    }

    /**
     * @param list<Figure> $salespeople Each salesperson's revenue over the last twelve months, the most first.
     *
     * @return string The revenue, from how many salespeople, and who sold the most.
     */
    public function salespeople(array $salespeople): string
    {
        $first = $salespeople[0] ?? null;

        if (!$first instanceof Figure) {
            return 'No sales over the last twelve months.';
        }

        return $this->dashboardWords->money($this->total($salespeople)) . ' over the last twelve months from '
            . $this->dashboardWords->count(count($salespeople), 'salesperson', 'salespeople') . '; ' . $first->label
            . ' sold the most, ' . $this->dashboardWords->money($first->amount) . '.';
    }

    /**
     * @param MonthSales $monthSales A month.
     * @param MonthSales $busiest    The busiest month before it.
     *
     * @return MonthSales The month when it had as many sales or more, so a tie goes to the later month.
     */
    private function busier(MonthSales $monthSales, MonthSales $busiest): MonthSales
    {
        return $monthSales->sales >= $busiest->sales
            ? $monthSales
            : $busiest;
    }

    /**
     * @param list<Figure> $figures Bars.
     *
     * @return int Their amounts, summed.
     */
    private function total(array $figures): int
    {
        $total = 0;

        foreach ($figures as $figure) {
            $total += $figure->amount;
        }

        return $total;
    }
}
