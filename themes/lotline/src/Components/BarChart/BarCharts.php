<?php

declare(strict_types=1);

namespace App\Theme\Components\BarChart;

use function count;
use function intdiv;
use function max;
use function number_format;

/**
 * Lays out a bar chart (old Lotline's geometry): a 400-wide view box, labels to the left, a bar per row scaled to the
 * largest value, its value after it.
 */
final readonly class BarCharts
{
    /** The view box's width. */
    private const int WIDTH = 400;

    /** The width the labels take. */
    private const int LABELS = 122;

    /** The width left for the values after the longest bar. */
    private const int VALUES = 80;

    /** One row's height. */
    private const int ROW = 26;

    /** One bar's thickness. */
    private const int BAR = 16;

    /**
     * @param string       $id      The chart's id.
     * @param string       $title   Its title.
     * @param string       $summary What it shows, in words.
     * @param list<string> $labels  Each bar's label, top to bottom.
     * @param list<int>    $values  Each bar's value, in the same order.
     * @param string       $prefix  What each value starts with (`$`); empty for none.
     *
     * @return BarChart The chart.
     */
    public function chart(
        string $id,
        string $title,
        string $summary,
        array $labels,
        array $values,
        string $prefix,
    ): BarChart {
        $chartScale = new ChartScale($values === [] ? 0 : max($values));
        $bars = [];

        foreach ($labels as $index => $label) {
            $value = $values[$index] ?? 0;
            $bars[] = $this->bar($chartScale, $index, $label, $prefix . number_format($value), $value);
        }

        $viewBox = '0 0 ' . self::WIDTH . ' ' . max(self::ROW, count($labels) * self::ROW);

        return new BarChart($id, $title, $summary, $viewBox, self::LABELS - 8, $bars);
    }

    /**
     * @param ChartScale $chartScale The value axis.
     * @param int        $index      The bar's row, from 0.
     * @param string     $label      What it measures.
     * @param string     $text       Its value, formatted.
     * @param int        $value      Its value.
     *
     * @return ChartBar The bar in its row, at least 2 long, its value after it.
     */
    private function bar(ChartScale $chartScale, int $index, string $label, string $text, int $value): ChartBar
    {
        $top = $index * self::ROW + intdiv(self::ROW - self::BAR, 2);
        $width = max(2, $chartScale->length($value, self::WIDTH - self::LABELS - self::VALUES));

        $baseline = $top + intdiv(self::BAR, 2) + 4;

        return new ChartBar(self::LABELS, $top, $width, self::BAR, $baseline, self::LABELS + $width + 6, $label, $text);
    }
}
