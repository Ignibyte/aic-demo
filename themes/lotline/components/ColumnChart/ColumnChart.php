<?php

declare(strict_types=1);

namespace App\Theme\Components\ColumnChart;

use AIC\Component\Contracts\Component;

/**
 * A column chart, from column-chart.phtml: an SVG with a title and description, gridlines with their values, a column
 * per label with its value above and its label and note below, and the summary as a caption. ColumnCharts lays it out.
 */
final readonly class ColumnChart implements Component
{
    /**
     * @param string              $id        Its id.
     * @param string              $title     Its title.
     * @param string              $summary   What it shows, in words.
     * @param string              $viewBox   The SVG's view box.
     * @param int                 $left      Where the plot starts.
     * @param int                 $right     Where the plot ends.
     * @param int                 $labelLine The baseline of the labels.
     * @param int                 $noteLine  The baseline of the notes.
     * @param list<ChartGridline> $gridlines The gridlines, bottom to top.
     * @param list<ChartColumn>   $columns   The columns, left to right.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public string $viewBox,
        public int $left,
        public int $right,
        public int $labelLine,
        public int $noteLine,
        public array $gridlines,
        public array $columns,
    ) {
    }
}
