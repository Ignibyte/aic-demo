<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Queue\Domain\QueuedJob;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function count;

/**
 * The jobs in AIC's queue as a table (#35), a row per `QueuedJob` in the order `QueueRecords::queued()` gives them,
 * newest first: its name, what it is about, its state as a badge, its attempts, when it is due (a retrying job's next
 * attempt) and when a worker took it. Times show as AIC gives them, ISO 8601 in UTC. Each row is keyed by the job's id.
 */
final readonly class QueueTable
{
    /**
     * The table's id.
     */
    public const string ID = 'jobs-queue';

    /**
     * @param JobWords $jobWords The page's words.
     */
    public function __construct(
        private JobWords $jobWords = new JobWords(),
    ) {
    }

    /**
     * @param list<QueuedJob> $queuedJobs The jobs in the queue, newest first.
     *
     * @throws InvalidArgumentException When a row's key is malformed.
     *
     * @return DataTable The table; "No job is waiting." when there are none.
     */
    public function table(array $queuedJobs): DataTable
    {
        $rows = [];

        foreach ($queuedJobs as $queuedJob) {
            $rows[] = new KeyedComponent('job-' . $queuedJob->id, $this->row($queuedJob));
        }

        return new DataTable(self::ID, $this->jobWords->queued(count($rows)), 'No job is waiting.', 'content', self::columns(), $rows);
    }

    /**
     * @param QueuedJob $queuedJob A job in the queue.
     *
     * @return TableRow Its row.
     */
    private function row(QueuedJob $queuedJob): TableRow
    {
        return new TableRow([
            new TableCell($queuedJob->job, '', true, false, null),
            new TableCell($this->jobWords->about($queuedJob->job, $queuedJob->payload), '', false, false, null),
            new TableCell($this->jobWords->state($queuedJob->state), '', false, false, $this->jobWords->tone($queuedJob->state)),
            new TableCell((string) $queuedJob->attempts, '', false, true, null),
            new TableCell($queuedJob->dueAt, '', true, false, null),
            new TableCell($this->jobWords->started($queuedJob->reservedAt), '', true, false, null),
        ]);
    }

    /**
     * @return list<TableColumn> The column heads; none sorts.
     */
    private static function columns(): array
    {
        return [
            new TableColumn('Job', '', 'none', '', false),
            new TableColumn('About', '', 'none', '', false),
            new TableColumn('State', '', 'none', '', false),
            new TableColumn('Attempts', '', 'none', '', true),
            new TableColumn('Due', '', 'none', '', false),
            new TableColumn('Started', '', 'none', '', false),
        ];
    }
}
