<?php

declare(strict_types=1);

namespace App\Leads\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Leads\Entity\LeadMoveTable;
use App\Leads\Entity\LeadStage;
use App\Leads\Entity\LeadTable;
use App\Leads\Entity\StageMove;
use App\Leads\Service\LeadRows;
use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * The leads' moves on the board (#30): a lead's stage changed under a lock, with its `lead_moves` row, inside the entity
 * store's transaction so whatever else the move queues commits with it, and the moves the board's stream reads after
 * the last one it sent.
 */
final readonly class LeadMoveRepository
{
    /**
     * @param EntityStore   $entityStore   The tables.
     * @param LeadTable     $leadTable     The leads' type.
     * @param LeadMoveTable $leadMoveTable The moves' type.
     * @param LeadRows      $leadRows      Reads a lead's stage.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LeadTable $leadTable = new LeadTable(),
        private LeadMoveTable $leadMoveTable = new LeadMoveTable(),
        private LeadRows $leadRows = new LeadRows(),
    ) {
    }

    /**
     * @param Closure(): void $work The move and what it queues, made together.
     *
     * @throws Throwable Whatever the work throws, after the transaction is rolled back.
     */
    public function transaction(Closure $work): void
    {
        $this->entityStore->transaction($work);
    }

    /**
     * Moves a lead to a stage: it locks the lead's row, and when the lead is at another stage writes the stage and the
     * status that goes with it and records the move. The lock holds only inside a transaction; `StageMoves` calls this
     * inside `transaction()`, and nothing else may.
     *
     * @param int       $leadId    A lead's id.
     * @param LeadStage $leadStage The stage to move it to.
     * @param int       $userId    Who moves it; 0 for no user.
     *
     * @throws QueryException           When the tables cannot be read or written.
     * @throws EntityNotFoundException  When the lead went between the lock and the write.
     * @throws InvalidArgumentException When a type or a value is malformed.
     *
     * @return StageMove|null The move recorded; null when the lead is gone or at that stage already.
     */
    public function moveLocked(int $leadId, LeadStage $leadStage, int $userId): ?StageMove
    {
        $row = $this->entityStore->query($this->leadTable->type())->where('id', $leadId)->forUpdate()->first();

        if (!$row instanceof Row) {
            return null;
        }

        $from = $this->leadRows->stage($row);

        if ($from === $leadStage) {
            return null;
        }

        $this->entityStore->update($this->leadTable->type(), $leadId, ['stage' => $leadStage->value, 'status' => $leadStage->status()]);
        $id = $this->entityStore->insert($this->leadMoveTable->type(), [
            'from_stage' => $from->value,
            'lead_id' => $leadId,
            'to_stage' => $leadStage->value,
            'user_id' => $userId === 0 ? null : $userId,
        ]);

        return new StageMove($id, $from, $leadStage);
    }

    /**
     * @throws QueryException           When the moves cannot be read.
     * @throws InvalidArgumentException When the moves' type is malformed.
     *
     * @return int The newest move's id; 0 when there is none.
     */
    public function newest(): int
    {
        $newest = $this->entityStore->query($this->leadMoveTable->type())->totals()->max('id');

        if ($newest === null) {
            return 0;
        }

        return (int) $newest;
    }

    /**
     * @param int $id    A move's id; 0 for the start.
     * @param int $limit The most to read.
     *
     * @throws QueryException           When the moves cannot be read or a stored stage is no stage.
     * @throws InvalidArgumentException When the moves' type is malformed.
     *
     * @return list<StageMove> The moves after it, oldest first.
     */
    public function after(int $id, int $limit): array
    {
        $moves = [];
        $rows = $this->entityStore->query($this->leadMoveTable->type())
            ->where('id', $id + 1, Comparison::GreaterOrEqual)
            ->orderBy('id')
            ->limit($limit)
            ->rows();

        foreach ($rows as $row) {
            $moves[] = new StageMove(
                $row->int('id'),
                $this->stored($row->string('from_stage')),
                $this->stored($row->string('to_stage')),
            );
        }

        return $moves;
    }

    /**
     * @param string $value A stored stage.
     *
     * @throws QueryException When it is no stage.
     *
     * @return LeadStage The stage.
     */
    private function stored(string $value): LeadStage
    {
        $stage = LeadStage::New->stored($value);

        if (!$stage instanceof LeadStage) {
            throw new QueryException('lead_moves holds a stage that is none: "' . $value . '".');
        }

        return $stage;
    }
}
