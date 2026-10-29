<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Component\Contracts\Component;
use App\Reports\Entity\ReportFigure;
use App\Reports\Entity\ReportKind;
use App\Theme\Components\BarChart\BarCharts;
use App\Theme\Components\ColumnChart\ColumnCharts;

use function count;
use function number_format;
use function str_ends_with;
use function substr;

/**
 * Each report's chart, from the theme (#21): sales by month as columns, each month's sales, its year under the first
 * month and every January; the other three as bars: each salesperson's revenue, each make's vehicles and each kind's
 * leads. The theme's builders lay them out as SVG; this hands them the labels and whole numbers, and a summary.
 */
final readonly class ReportCharts
{
    /**
     * @param BarCharts    $barCharts    Lays out a bar chart.
     * @param ColumnCharts $columnCharts Lays out a column chart.
     */
    public function __construct(
        private BarCharts $barCharts = new BarCharts(),
        private ColumnCharts $columnCharts = new ColumnCharts(),
    ) {
    }

    /**
     * @param ReportKind         $reportKind The report.
     * @param list<ReportFigure> $figures    Its lines.
     *
     * @return Component Its chart.
     */
    public function chart(ReportKind $reportKind, array $figures): Component
    {
        $id = 'report-' . $reportKind->value . '-chart';
        $title = $reportKind->title();

        if ($reportKind === ReportKind::Sales) {
            return $this->months($id, $title, $figures);
        }

        $labels = [];

        foreach ($figures as $figure) {
            $labels[] = $figure->label;
        }

        $values = $this->values($reportKind, $figures);
        $prefix = $reportKind === ReportKind::Salespeople
            ? '$'
            : '';

        return $this->barCharts->chart($id, $title, $this->summary($reportKind, $figures), $labels, $values, $prefix);
    }

    /**
     * @param ReportKind         $reportKind A bar chart's report.
     * @param list<ReportFigure> $figures    Its lines.
     *
     * @return list<int> Each bar's value: a salesperson's revenue, else the line's count.
     */
    private function values(ReportKind $reportKind, array $figures): array
    {
        $values = [];

        foreach ($figures as $figure) {
            $values[] = $reportKind === ReportKind::Salespeople
                ? $figure->amount
                : $figure->count;
        }

        return $values;
    }

    /**
     * @param string             $id      The chart's id.
     * @param string             $title   Its title.
     * @param list<ReportFigure> $figures The months, the oldest first.
     *
     * @return Component The columns: each month's sales.
     */
    private function months(string $id, string $title, array $figures): Component
    {
        $labels = [];
        $notes = [];
        $values = [];
        $sales = 0;

        foreach ($figures as $index => $figure) {
            $labels[] = substr($figure->label, 0, 3);
            $notes[] = $index === 0 || str_ends_with($figure->key, '-01')
                ? substr($figure->key, 0, 4)
                : '';
            $values[] = $figure->count;
            $sales += $figure->count;
        }

        $summary = number_format($sales) . ' sales over ' . number_format(count($figures)) . ' months';

        return $this->columnCharts->chart($id, $title, $summary, $labels, $notes, $values);
    }

    /**
     * @param ReportKind         $reportKind A bar chart's report.
     * @param list<ReportFigure> $figures    Its lines.
     *
     * @return string What the chart shows, in words.
     */
    private function summary(ReportKind $reportKind, array $figures): string
    {
        $count = 0;
        $amount = 0;

        foreach ($figures as $figure) {
            $count += $figure->count;
            $amount += $figure->amount;
        }

        if ($reportKind === ReportKind::Salespeople) {
            return '$' . number_format($amount) . ' of revenue across ' . number_format(count($figures)) . ' salespeople';
        }

        if ($reportKind === ReportKind::Inventory) {
            return number_format($count) . ' vehicles across ' . number_format(count($figures)) . ' makes';
        }

        return number_format($count) . ' leads, ' . number_format($amount) . ' of them became a sale';
    }
}
