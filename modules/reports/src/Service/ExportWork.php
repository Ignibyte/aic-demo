<?php

declare(strict_types=1);

namespace App\Reports\Service;

use App\Reports\Entity\ReportExport;
use App\Reports\Repository\ExportRepository;
use App\Reports\Repository\FigureRepository;
use Throwable;

/**
 * What the two export jobs do (#21). Writing one reads the report's figures for its range, the same the page shows,
 * writes them as CSV to the export's file, then marks it ready and tells its member in one transaction; an export
 * that is gone (pruned, or its member removed) or ready already is left, so a second run tells no one twice. Pruning
 * removes every export past its seven days, its file first and then its row, so no file outlives its row.
 */
final readonly class ExportWork
{
    /**
     * @param ExportRepository $exportRepository The exports.
     * @param FigureRepository $figureRepository The reports' figures.
     * @param ExportFiles      $exportFiles      The exports' files.
     * @param ExportNotices    $exportNotices    Tells a member their export is ready.
     * @param ReportCsv        $reportCsv        Figures as CSV.
     */
    public function __construct(
        private ExportRepository $exportRepository,
        private FigureRepository $figureRepository,
        private ExportFiles $exportFiles,
        private ExportNotices $exportNotices,
        private ReportCsv $reportCsv = new ReportCsv(),
    ) {
    }

    /**
     * @param int $id An export's id.
     *
     * @throws Throwable When the export or its figures cannot be read, its file written or its member told.
     */
    public function write(int $id): void
    {
        $reportExport = $this->exportRepository->find($id);

        if (!$reportExport instanceof ReportExport || $reportExport->ready) {
            return;
        }

        $figures = $this->figureRepository->figures($reportExport->reportKind, $reportExport->reportRange);
        $this->exportFiles->write($reportExport->id, $this->reportCsv->document($reportExport->reportKind, $figures)->text());
        $this->exportRepository->complete($reportExport, $this->exportNotices);
    }

    /**
     * @throws Throwable When the exports cannot be read or one deleted.
     */
    public function prune(): void
    {
        foreach ($this->exportRepository->expired() as $id) {
            $this->exportFiles->remove($id);
            $this->exportRepository->delete($id);
        }
    }
}
