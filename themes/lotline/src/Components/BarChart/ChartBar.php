<?php

declare(strict_types=1);

namespace App\Theme\Components\BarChart;

/**
 * One bar, placed by BarCharts: its box, the baseline of its texts, where its value starts, its label and its value.
 */
final readonly class ChartBar
{
    /**
     * @param int    $left      Its left edge.
     * @param int    $top       Its top edge.
     * @param int    $width     Its length.
     * @param int    $height    Its thickness.
     * @param int    $baseline  The baseline of its label and value.
     * @param int    $valueEdge Where its value starts.
     * @param string $label     What it measures.
     * @param string $value     Its value, formatted.
     */
    public function __construct(
        public int $left,
        public int $top,
        public int $width,
        public int $height,
        public int $baseline,
        public int $valueEdge,
        public string $label,
        public string $value,
    ) {
    }
}
