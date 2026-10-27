<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use InvalidArgumentException;

/**
 * A vehicle's status by its id, for the module that sells vehicles (#7): whether one can be sold, its row locked for
 * the transaction that sells it, and its status changed when a sale is recorded or voided. Inventory owns the
 * `vehicles` table, so other modules change its rows only through here; each call runs on the caller's transaction,
 * through the entity store (#14).
 */
final readonly class VehicleStatusRepository
{
    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param VehicleTable $vehicleTable The `vehicles` entity type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleTable $vehicleTable = new VehicleTable(),
    ) {
    }

    /**
     * Call it inside a transaction: the row stays locked until it ends, so two sales of one vehicle cannot pass
     * together.
     *
     * @param int $vehicleId A vehicle's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether the vehicle is there and not sold, its row now locked.
     */
    public function lockSellable(int $vehicleId): bool
    {
        $row = $this->entityStore->query($this->vehicleTable->type())->where('id', $vehicleId)->forUpdate()->first();

        return $row instanceof Row && $row->string('status') !== VehicleStatus::Sold->value;
    }

    /**
     * @param int           $vehicleId     A vehicle's id.
     * @param VehicleStatus $vehicleStatus Its new status.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     */
    public function mark(int $vehicleId, VehicleStatus $vehicleStatus): void
    {
        $this->entityStore->query($this->vehicleTable->type())
            ->where('id', $vehicleId)
            ->changes()
            ->update(['status' => $vehicleStatus->value]);
    }
}
