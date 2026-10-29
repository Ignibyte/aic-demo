<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Reports\Entity\ReportKind;
use App\Reports\Entity\ReportRange;
use App\Reports\Form\ExportForms;
use App\Reports\Repository\FigureRepository;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;

/**
 * One report's panel on the reports page (#21): its chart, its table and its "Export CSV" button, from the figures
 * the CSV and the API read too. Sales by month spans the grid; the other three sit two to a row on a wide screen.
 */
final readonly class ReportCards
{
    /**
     * @param FigureRepository $figureRepository The figures.
     * @param ReportCharts     $reportCharts     Each report's chart.
     * @param FigureTables     $figureTables     Each report's table.
     * @param ExportForms      $exportForms      Each report's export form.
     */
    public function __construct(
        private FigureRepository $figureRepository,
        private ReportCharts $reportCharts = new ReportCharts(),
        private FigureTables $figureTables = new FigureTables(),
        private ExportForms $exportForms = new ExportForms(),
    ) {
    }

    /**
     * @param ReportKind  $reportKind  The report.
     * @param ReportRange $reportRange The range shown.
     * @param string      $token       The export form's token.
     *
     * @throws QueryException           When the figures cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return KeyedComponent The report's panel, keyed and identified `report-<report>`.
     */
    public function card(ReportKind $reportKind, ReportRange $reportRange, string $token): KeyedComponent
    {
        $figures = $this->figureRepository->figures($reportKind, $reportRange);
        $id = 'report-' . $reportKind->value;
        $range = $reportRange->from . ' to ' . $reportRange->until;
        $panel = new Panel($id, $reportKind->title(), $range, $reportKind === ReportKind::Sales, [
            new KeyedComponent('chart', $this->reportCharts->chart($reportKind, $figures)),
            new KeyedComponent('table', $this->figureTables->table($reportKind, $figures)),
            new KeyedComponent('export', $this->exportForms->form($reportKind, $reportRange, $token)),
        ]);

        return new KeyedComponent($id, $panel);
    }
}
