<?php

declare(strict_types=1);

namespace App\Theme\Components\ColumnChart;

/**
 * One column, placed by ColumnCharts: its box, its middle, where its value sits, its label, a note under it and its
 * value.
 */
final readonly class ChartColumn
{
    /**
     * @param int    $left   Its left edge.
     * @param int    $top    Its top edge.
     * @param int    $width  Its width.
     * @param int    $height Its height.
     * @param int    $middle Its middle, where its texts centre.
     * @param int    $above  The baseline of its value, above it.
     * @param string $label  What it measures.
     * @param string $note   A second line under the label; empty for none.
     * @param string $value  Its value, formatted.
     */
    public function __construct(
        public int $left,
        public int $top,
        public int $width,
        public int $height,
        public int $middle,
        public int $above,
        public string $label,
        public string $note,
        public string $value,
    ) {
    }
}
