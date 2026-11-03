<?php

declare(strict_types=1);

namespace App\Reports\Repository;

use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Queue\Application\PostgresQueue;
use App\Reports\Entity\ExportTables;
use App\Reports\Entity\ExportWrite;
use App\Reports\Entity\ReportExport;
use App\Reports\Entity\ReportKind;
use App\Reports\Entity\ReportKinds;
use App\Reports\Entity\ReportRange;
use App\Reports\Service\ExportNotices;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * The CSV exports (#21), through the entity store. Asking for one is one `EntityStore::transaction()` that stores it
 * and queues its `report-export` job, so an export is never left without its job nor a job without its export; the
 * closure is static and calls `queueLocked()`: natively a closure does not bind `$this` (AIC #184), so it takes the
 * repository through `use`, as holds' does. Its file written,
 * an export is marked ready with its notification queued in one transaction too (`complete()`), so a retry of the job
 * neither tells the member twice nor leaves them untold. A member reads only
 * their own exports, and only within their seven days, which the `report_export_states` view tells.
 */
final readonly class ExportRepository
{
    /**
     * The job that writes an export's file.
     */
    public const string JOB = 'report-export';

    /**
     * An export whose file is not written yet.
     */
    private const string QUEUED = 'queued';

    /**
     * An export whose file is written.
     */
    private const string READY = 'ready';

    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param PostgresQueue $postgresQueue Queues the export's job.
     * @param ExportTables  $exportTables  The exports' types.
     * @param StaffTable    $staffTable    The users' type, staff's.
     * @param ReportKinds   $reportKinds   Reads a stored report.
     */
    public function __construct(
        private EntityStore $entityStore,
        private PostgresQueue $postgresQueue,
        private ExportTables $exportTables = new ExportTables(),
        private StaffTable $staffTable = new StaffTable(),
        private ReportKinds $reportKinds = new ReportKinds(),
    ) {
    }

    /**
     * @param string $email The email a member of staff signs in with.
     *
     * @throws RuntimeException         When the users cannot be read.
     * @throws InvalidArgumentException When the users' type is malformed.
     *
     * @return int Their id, the one user with that email; 0 when no member signs in so.
     */
    public function memberId(string $email): int
    {
        return (int) ($this->entityStore->query($this->staffTable->type())->where('email', $email)->totals()->max('id') ?? '0');
    }

    /**
     * @param int         $userId      The member who asks.
     * @param ReportKind  $reportKind  The report.
     * @param ReportRange $reportRange Its range.
     *
     * @throws Throwable When a statement fails or the job cannot be queued; the transaction is rolled back first.
     *
     * @return int The new export's id.
     */
    public function queue(int $userId, ReportKind $reportKind, ReportRange $reportRange): int
    {
        $exportRepository = $this;
        $exportWrite = new ExportWrite();
        $this->entityStore->transaction(
            static function () use ($exportRepository, $exportWrite, $userId, $reportKind, $reportRange): void {
                $exportWrite->export($exportRepository->queueLocked($userId, $reportKind, $reportRange));
            },
        );

        return $exportWrite->written();
    }

    /**
     * `queue()` calls this inside its transaction.
     *
     * @param int         $userId      The member who asks.
     * @param ReportKind  $reportKind  The report.
     * @param ReportRange $reportRange Its range.
     *
     * @throws RuntimeException         When a statement fails or the job cannot be queued.
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws JsonException            When the job's payload cannot be encoded.
     *
     * @return int The new export's id.
     */
    public function queueLocked(int $userId, ReportKind $reportKind, ReportRange $reportRange): int
    {
        $id = $this->entityStore->insert($this->exportTables->exports(), [
            'from_day' => $reportRange->from,
            'report' => $reportKind->value,
            'status' => self::QUEUED,
            'to_day' => $reportRange->until,
            'user_id' => $userId,
        ]);
        $this->postgresQueue->dispatch(self::JOB, ['export' => (string) $id], 0);

        return $id;
    }

    /**
     * @param int $id An export's id.
     *
     * @throws RuntimeException         When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return ReportExport|null The export, ready or not, past its days or not; null when it is gone.
     */
    public function find(int $id): ?ReportExport
    {
        $row = $this->entityStore->query($this->exportTables->states())->where('id', $id)->first();

        return $row instanceof Row
            ? $this->export($row)
            : null;
    }

    /**
     * @param int $id     An export's id.
     * @param int $userId The member who asks for it.
     *
     * @throws RuntimeException         When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return ReportExport|null The export when it is that member's and within its seven days; null otherwise.
     */
    public function own(int $id, int $userId): ?ReportExport
    {
        $row = $this->entityStore->query($this->exportTables->states())
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('expired', false)
            ->first();

        return $row instanceof Row
            ? $this->export($row)
            : null;
    }

    /**
     * @param ReportExport  $reportExport  An export whose file is written.
     * @param ExportNotices $exportNotices Queues its notification inside the transaction.
     *
     * @throws Throwable When a statement fails or the notification cannot be queued; the transaction is rolled back
     *                   first.
     */
    public function complete(ReportExport $reportExport, ExportNotices $exportNotices): void
    {
        $exportRepository = $this;
        $this->entityStore->transaction(static function () use ($exportRepository, $reportExport, $exportNotices): void {
            $exportRepository->completeLocked($reportExport, $exportNotices);
        });
    }

    /**
     * `complete()` calls this inside its transaction: marks the export ready and tells its member.
     *
     * @param ReportExport  $reportExport  An export whose file is written.
     * @param ExportNotices $exportNotices Queues its notification.
     *
     * @throws RuntimeException         When a statement fails, the export is gone, or the notification cannot be queued.
     * @throws InvalidArgumentException When an entity type is malformed.
     * @throws JsonException            When the notification's payload cannot be encoded.
     */
    public function completeLocked(ReportExport $reportExport, ExportNotices $exportNotices): void
    {
        $this->entityStore->update($this->exportTables->exports(), $reportExport->id, ['status' => self::READY]);
        $row = $this->entityStore->query($this->staffTable->type())->where('id', $reportExport->userId)->first();

        if (!($row instanceof Row)) {
            return;
        }

        $exportNotices->ready($reportExport, $row->string('email'));
    }

    /**
     * @throws RuntimeException         When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<int> The exports past their seven days, by id.
     */
    public function expired(): array
    {
        $ids = [];

        foreach ($this->entityStore->query($this->exportTables->states())->where('expired', true)->orderBy('id')->rows() as $row) {
            $ids[] = $row->int('id');
        }

        return $ids;
    }

    /**
     * @param int $id An export's id.
     *
     * @throws RuntimeException         When the statement fails, or the export is gone already.
     * @throws InvalidArgumentException When the entity type is malformed.
     */
    public function delete(int $id): void
    {
        $this->entityStore->delete($this->exportTables->exports(), $id);
    }

    /**
     * @param Row $row A row of `report_export_states`.
     *
     * @throws RuntimeException When a column is missing.
     *
     * @return ReportExport|null The export; null when its report is none the page has.
     */
    private function export(Row $row): ?ReportExport
    {
        $reportKind = $this->reportKinds->named($row->string('report'));

        if (!$reportKind instanceof ReportKind) {
            return null;
        }

        $reportRange = new ReportRange($row->string('from_day'), $row->string('to_day'));

        return new ReportExport($row->int('id'), $row->int('user_id'), $reportKind, $reportRange, $row->string('status') === self::READY);
    }
}
