<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Controller\Domain\CsvDocument;
use App\Reports\Entity\ReportFigure;
use App\Reports\Entity\ReportKind;

/**
 * A report's lines as CSV (#21), through AIC's `CsvDocument` (AIC #209), which quotes, doubles quotes and guards a
 * field a spreadsheet would run as a formula: the report's header, then a line per figure with its label and its two
 * numbers as plain digits, the same lines the page's table shows.
 */
final readonly class ReportCsv
{
    /**
     * @param ReportKind         $reportKind The report.
     * @param list<ReportFigure> $figures    Its lines.
     *
     * @return CsvDocument The CSV.
     */
    public function document(ReportKind $reportKind, array $figures): CsvDocument
    {
        $rows = [];

        foreach ($figures as $figure) {
            $rows[] = [$figure->label, (string) $figure->count, (string) $figure->amount];
        }

        return new CsvDocument($reportKind->header(), $rows);
    }
}
