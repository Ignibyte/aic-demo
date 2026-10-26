<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Theme\Components\BarChart\BarCharts;
use App\Theme\Components\ColumnChart\ColumnCharts;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\StatTiles\StatTile;
use App\Theme\Components\StatTiles\StatTiles;
use InvalidArgumentException;

/**
 * The style guide's dashboard parts with sample Ridgeway Motors figures: the month's tiles, sales by month as columns,
 * stock by make and revenue by salesperson as bars, each chart in a panel.
 */
final readonly class ThemeSampleCharts
{
    /**
     * @param BarCharts    $barCharts    Lays out the bar charts.
     * @param ColumnCharts $columnCharts Lays out the column chart.
     */
    public function __construct(
        private BarCharts $barCharts = new BarCharts(),
        private ColumnCharts $columnCharts = new ColumnCharts(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> The tiles, then the three chart panels.
     */
    public function items(): array
    {
        return [
            new KeyedComponent('tiles', $this->tiles()),
            new KeyedComponent('sales', $this->sales()),
            new KeyedComponent('makes', $this->makes()),
            new KeyedComponent('salespeople', $this->salespeople()),
        ];
    }

    /**
     * @return StatTiles This month on the lot.
     */
    private function tiles(): StatTiles
    {
        return new StatTiles('dashboard-tiles', 'This month on the lot', [
            new StatTile('tile-in-stock', 'Vehicles in stock', '4', '2 more on hold'),
            new StatTile('tile-sales', 'Sales this month', '11', '9 last month'),
            new StatTile('tile-revenue', 'Revenue this month', '$318,450', '$271,900 last month'),
            new StatTile('tile-days', 'Average days on the lot', '37 days', 'over the 6 vehicles not sold'),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel Sales by month, the last twelve.
     */
    private function sales(): Panel
    {
        $columnChart = $this->columnCharts->chart(
            'sales-chart',
            'Sales by month',
            '104 sales over the last twelve months; the most, 13, in June.',
            ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            ['2025', '', '2026', '', '', '', '', '', '', '', '', ''],
            [7, 6, 5, 8, 9, 10, 12, 13, 11, 6, 6, 11],
        );

        return new Panel('sales', 'Sales by month', 'The last twelve months, every salesperson', true, [
            new KeyedComponent('chart', $columnChart),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel Stock by make.
     */
    private function makes(): Panel
    {
        $barChart = $this->barCharts->chart(
            'makes-chart',
            'Inventory by make',
            'Six vehicles in stock or on hold, at most two of one make.',
            ['Ford', 'Toyota', 'Jeep', 'Chevrolet', 'Honda'],
            [2, 1, 1, 1, 1],
            '',
        );

        return new Panel('makes', 'Inventory by make', 'Vehicles in stock or on hold', false, [
            new KeyedComponent('chart', $barChart),
        ]);
    }

    /**
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Panel Revenue by salesperson.
     */
    private function salespeople(): Panel
    {
        $barChart = $this->barCharts->chart(
            'salespeople-chart',
            'Revenue by salesperson',
            'Dana Reyes sold the most over the last twelve months.',
            ['Dana Reyes', 'Sam Okafor', 'Lee Marsh'],
            [1_284_300, 968_150, 742_900],
            '$',
        );

        return new Panel('salespeople', 'Revenue by salesperson', 'The last twelve months, whole dollars', false, [
            new KeyedComponent('chart', $barChart),
        ]);
    }
}
