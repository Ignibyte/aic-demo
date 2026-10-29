<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Reports\Entity\ReportFigure;
use App\Reports\Entity\ReportKind;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function count;
use function number_format;

/**
 * Each report's figures as the theme's `DataTable` (#21): its header's three columns, the numbers set right, and a line
 * per figure, money in whole dollars; the lines the CSV holds.
 */
final readonly class FigureTables
{
    /**
     * @param ReportKind         $reportKind The report.
     * @param list<ReportFigure> $figures    Its lines.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The table, or its empty line.
     */
    public function table(ReportKind $reportKind, array $figures): DataTable
    {
        $columns = [];

        foreach ($reportKind->header() as $index => $label) {
            $columns[] = new TableColumn($label, '', 'none', '', $index > 0);
        }

        $rows = [];

        foreach ($figures as $index => $figure) {
            $rows[] = new KeyedComponent('line-' . ($index + 1), $this->row($reportKind, $figure));
        }

        $summary = count($figures) === 1
            ? '1 line'
            : number_format(count($figures)) . ' lines';

        return new DataTable('report-' . $reportKind->value . '-table', $summary, 'Nothing in this range.', 'content', $columns, $rows);
    }

    /**
     * @param ReportKind   $reportKind   The report.
     * @param ReportFigure $reportFigure One of its lines.
     *
     * @return TableRow Its label, its count and its amount.
     */
    private function row(ReportKind $reportKind, ReportFigure $reportFigure): TableRow
    {
        $amount = $reportKind->money()
            ? '$' . number_format($reportFigure->amount)
            : number_format($reportFigure->amount);

        return new TableRow([
            new TableCell($reportFigure->label, '', false, false, null),
            new TableCell(number_format($reportFigure->count), '', false, true, null),
            new TableCell($amount, '', false, true, null),
        ]);
    }
}
