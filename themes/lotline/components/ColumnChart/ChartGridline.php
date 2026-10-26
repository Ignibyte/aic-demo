<?php

declare(strict_types=1);

namespace App\Theme\Components\ColumnChart;

/**
 * One gridline, placed by ColumnCharts: its height and its value's label.
 */
final readonly class ChartGridline
{
    /**
     * @param int    $top   Its height.
     * @param string $label The value it marks, formatted.
     */
    public function __construct(
        public int $top,
        public string $label,
    ) {
    }
}
