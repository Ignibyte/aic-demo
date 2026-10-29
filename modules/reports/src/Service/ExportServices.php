<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Entity\Application\EntityStore;
use AIC\Notification\Application\Notifier;
use AIC\Queue\Application\PostgresQueue;
use App\Reports\Repository\ExportRepository;
use App\Reports\Repository\FigureRepository;
use App\Reports\Repository\RangeRepository;

/**
 * The reports module's second factory (#21): the export jobs' work, built here so the generated jobs make one call
 * apiece rather than each writing out the graph (PHPMD's coupling limit). It needs nothing only the web side has, so
 * the worker gets it too.
 */
final readonly class ExportServices
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param PostgresQueue $postgresQueue The queue.
     * @param Notifier      $notifier      Queues each delivery of a notification.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PostgresQueue $postgresQueue,
        private Notifier $notifier,
    ) {
    }

    /**
     * @return ExportWork Writes an export's file and tells its member; prunes the exports past their days.
     */
    public function exportWork(): ExportWork
    {
        return new ExportWork(
            new ExportRepository($this->entityStore, $this->postgresQueue),
            new FigureRepository($this->entityStore, new RangeRepository($this->entityStore)),
            new ExportFiles(),
            new ExportNotices($this->notifier),
        );
    }
}
