<?php

declare(strict_types=1);

namespace App\Theme\Components\BarChart;

use AIC\Component\Contracts\Component;

/**
 * A horizontal bar chart, from bar-chart.phtml: an SVG with a title and description, a bar per label with its value,
 * and the summary as a caption. BarCharts lays it out.
 */
final readonly class BarChart implements Component
{
    /**
     * @param string         $id        Its id.
     * @param string         $title     Its title.
     * @param string         $summary   What it shows, in words.
     * @param string         $viewBox   The SVG's view box.
     * @param int            $labelEdge Where the labels end.
     * @param list<ChartBar> $bars      The bars, top to bottom.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public string $viewBox,
        public int $labelEdge,
        public array $bars,
    ) {
    }
}
