<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Dashboard\Entity\Figure;
use App\Dashboard\Repository\DashboardRepository;
use App\Theme\Components\BarChart\BarCharts;
use App\Theme\Components\ColumnChart\ColumnCharts;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;

/**
 * The dashboard's three charts, each in its panel: sales by month as columns, inventory by make and revenue by
 * salesperson as bars. The theme's builders lay them out as SVG; this hands them the labels and whole numbers Postgres
 * gives, and the summary of each.
 */
final readonly class DashboardCharts
{
    /**
     * @param DashboardRepository $dashboardRepository The figures.
     * @param ChartSummaries      $chartSummaries      Each chart's summary.
     * @param BarCharts           $barCharts           Lays out the bar charts.
     * @param ColumnCharts        $columnCharts        Lays out the column chart.
     */
    public function __construct(
        private DashboardRepository $dashboardRepository,
        private ChartSummaries $chartSummaries = new ChartSummaries(),
        private BarCharts $barCharts = new BarCharts(),
        private ColumnCharts $columnCharts = new ColumnCharts(),
    ) {
    }

    /**
     * @throws QueryException           When the figures cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel Sales by month, the last twelve, the oldest first.
     */
    public function sales(): Panel
    {
        $months = $this->dashboardRepository->months();
        $labels = [];
        $notes = [];
        $values = [];

        foreach ($months as $month) {
            $labels[] = $month->label;
            $notes[] = $month->note;
            $values[] = $month->sales;
        }

        $summary = $this->chartSummaries->sales($months);
        $columnChart = $this->columnCharts->chart('sales-chart', 'Sales by month', $summary, $labels, $notes, $values);

        return new Panel('sales', 'Sales by month', 'The last twelve months, every salesperson', true, [
            new KeyedComponent('chart', $columnChart),
        ]);
    }

    /**
     * @throws QueryException           When the figures cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel The vehicles not sold, per make.
     */
    public function makes(): Panel
    {
        $makes = $this->dashboardRepository->makes();
        $barChart = $this->barCharts->chart(
            'makes-chart',
            'Inventory by make',
            $this->chartSummaries->makes($makes),
            $this->labels($makes),
            $this->amounts($makes),
            '',
        );

        return new Panel('makes', 'Inventory by make', 'Vehicles in stock or on hold', false, [
            new KeyedComponent('chart', $barChart),
        ]);
    }

    /**
     * @throws QueryException           When the figures cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel Each salesperson's revenue over the last twelve months.
     */
    public function salespeople(): Panel
    {
        $salespeople = $this->dashboardRepository->salespeople();
        $barChart = $this->barCharts->chart(
            'salespeople-chart',
            'Revenue by salesperson',
            $this->chartSummaries->salespeople($salespeople),
            $this->labels($salespeople),
            $this->amounts($salespeople),
            '$',
        );

        return new Panel('salespeople', 'Revenue by salesperson', 'The last twelve months, whole dollars', false, [
            new KeyedComponent('chart', $barChart),
        ]);
    }

    /**
     * @param list<Figure> $figures Bars.
     *
     * @return list<string> Their labels, in order.
     */
    private function labels(array $figures): array
    {
        $labels = [];

        foreach ($figures as $figure) {
            $labels[] = $figure->label;
        }

        return $labels;
    }

    /**
     * @param list<Figure> $figures Bars.
     *
     * @return list<int> Their amounts, in order.
     */
    private function amounts(array $figures): array
    {
        $amounts = [];

        foreach ($figures as $figure) {
            $amounts[] = $figure->amount;
        }

        return $amounts;
    }
}
