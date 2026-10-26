<?php

declare(strict_types=1);

namespace App\Theme\Components\ColumnChart;

use App\Theme\Components\BarChart\ChartScale;

use function count;
use function intdiv;
use function max;
use function number_format;

/**
 * Lays out a column chart (old Lotline's geometry): a 640 by 250 view box, a gridline per step of the scale, a column
 * per label with its value above it and its label and note below.
 */
final readonly class ColumnCharts
{
    /** The view box's width. */
    private const int WIDTH = 640;

    /** The view box's height. */
    private const int HEIGHT = 250;

    /** Where the plot starts, after the gridline values. */
    private const int LEFT = 44;

    /** The room above the tallest column. */
    private const int TOP = 18;

    /** The room under the plot, for the labels and notes. */
    private const int BOTTOM = 40;

    /**
     * @param string       $id      The chart's id.
     * @param string       $title   Its title.
     * @param string       $summary What it shows, in words.
     * @param list<string> $labels  Each column's label, left to right.
     * @param list<string> $notes   Each column's second line, in the same order.
     * @param list<int>    $values  Each column's value, in the same order.
     *
     * @return ColumnChart The chart.
     */
    public function chart(
        string $id,
        string $title,
        string $summary,
        array $labels,
        array $notes,
        array $values,
    ): ColumnChart {
        $chartScale = new ChartScale($values === [] ? 0 : max($values));
        $base = self::HEIGHT - self::BOTTOM;

        return new ColumnChart(
            $id,
            $title,
            $summary,
            '0 0 ' . self::WIDTH . ' ' . self::HEIGHT,
            self::LEFT,
            self::WIDTH,
            $base + 16,
            $base + 31,
            $this->gridlines($chartScale),
            $this->columns($chartScale, $labels, $notes, $values),
        );
    }

    /**
     * @param ChartScale $chartScale The value axis.
     *
     * @return list<ChartGridline> A gridline per step, from zero.
     */
    private function gridlines(ChartScale $chartScale): array
    {
        $gridlines = [];

        for ($tick = 0; $tick <= ChartScale::STEPS; $tick += 1) {
            $value = $chartScale->tick($tick);
            $top = self::HEIGHT - self::BOTTOM - $chartScale->length($value, self::HEIGHT - self::TOP - self::BOTTOM);
            $gridlines[] = new ChartGridline($top, number_format($value));
        }

        return $gridlines;
    }

    /**
     * @param ChartScale   $chartScale The value axis.
     * @param list<string> $labels     Each column's label.
     * @param list<string> $notes      Each column's second line.
     * @param list<int>    $values     Each column's value.
     *
     * @return list<ChartColumn> The columns, each three fifths of its slot.
     */
    private function columns(ChartScale $chartScale, array $labels, array $notes, array $values): array
    {
        $slot = intdiv(self::WIDTH - self::LEFT, max(1, count($labels)));
        $width = max(4, intdiv($slot * 3, 5));
        $columns = [];

        foreach ($labels as $index => $label) {
            $value = $values[$index] ?? 0;
            $height = $chartScale->length($value, self::HEIGHT - self::TOP - self::BOTTOM);
            $left = self::LEFT + $index * $slot + intdiv($slot - $width, 2);
            $columns[] = $this->column($left, $width, $height, $label, $notes[$index] ?? '', number_format($value));
        }

        return $columns;
    }

    /**
     * @param int    $left   Its left edge.
     * @param int    $width  Its width.
     * @param int    $height Its height, standing on the plot's base.
     * @param string $label  What it measures.
     * @param string $note   A second line under the label.
     * @param string $value  Its value, formatted.
     *
     * @return ChartColumn The column, its value 5 above it.
     */
    private function column(int $left, int $width, int $height, string $label, string $note, string $value): ChartColumn
    {
        $top = self::HEIGHT - self::BOTTOM - $height;

        return new ChartColumn($left, $top, $width, $height, $left + intdiv($width, 2), $top - 5, $label, $note, $value);
    }
}
