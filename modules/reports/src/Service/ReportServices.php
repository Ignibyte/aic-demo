<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Controller\Application\PageDocuments;
use AIC\Entity\Application\EntityStore;
use AIC\Queue\Application\PostgresQueue;
use App\Reports\Form\RangeChecks;
use App\Reports\Repository\ExportRepository;
use App\Reports\Repository\FigureRepository;
use App\Reports\Repository\RangeRepository;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;

/**
 * The reports module's factory (#21): who may read the reports, framed as the panel `reports`; the range's check; the
 * figures the API reads; the export's post, its line and its download; and the page's panels. The page, the export's
 * post and download, and the API share them, so the generated handlers make one call apiece rather than each writing
 * out the graph (PHPMD's coupling limit). It offers nothing the console side asks for: it needs the page documents,
 * which only the web side has.
 */
final readonly class ReportServices
{
    /**
     * @param RangeRepository $rangeRepository The calendar and the presets, which the range's check, the figures and the
     *                                         page share.
     * @param EntityStore     $entityStore     The entity store.
     * @param PostgresQueue   $postgresQueue   Queues an export's job.
     * @param PageDocuments   $pageDocuments   The theme's documents.
     */
    public function __construct(
        private RangeRepository $rangeRepository,
        private EntityStore $entityStore,
        private PostgresQueue $postgresQueue,
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @return ReportAccess The managers' check and its refusals.
     */
    public function reportAccess(): ReportAccess
    {
        $exportRepository = new ExportRepository($this->entityStore, $this->postgresQueue);

        return new ReportAccess(new StaffPanels(new StaffPages($this->pageDocuments), 'reports'), $exportRepository);
    }

    /**
     * @return RangeChecks The range a request asks for, checked against the calendar.
     */
    public function rangeChecks(): RangeChecks
    {
        return new RangeChecks($this->rangeRepository);
    }

    /**
     * @return FigureRepository The reports' figures, which the API reads.
     */
    public function figureRepository(): FigureRepository
    {
        return new FigureRepository($this->entityStore, $this->rangeRepository);
    }

    /**
     * @return ExportPosts The export's post.
     */
    public function exportPosts(): ExportPosts
    {
        return new ExportPosts(new ExportRepository($this->entityStore, $this->postgresQueue), $this->rangeChecks(), $this->reportAccess());
    }

    /**
     * @return ExportDesk The export's line on the page and its download.
     */
    public function exportDesk(): ExportDesk
    {
        return new ExportDesk(new ExportRepository($this->entityStore, $this->postgresQueue), $this->reportAccess(), new ExportFiles());
    }

    /**
     * @return ReportPanels The reports page's document.
     */
    public function reportPanels(): ReportPanels
    {
        $reportCards = new ReportCards($this->figureRepository());
        $staffPages = new StaffPages($this->pageDocuments);

        return new ReportPanels($this->rangeRepository, $this->rangeChecks(), $this->exportDesk(), $reportCards, $staffPages);
    }
}
