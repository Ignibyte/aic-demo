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
 * A vehicle's status by its id, for the modules that sell and hold vehicles (#7, #20): whether one can be sold, or its
 * status, its row locked for the transaction that sells or holds it, and its status changed when a sale is recorded or
 * voided or a hold placed or released. Inventory owns the `vehicles` table, so other modules change its rows only
 * through here; each call runs on the caller's transaction, through the entity store (#14).
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
        $status = $this->lockedStatus($vehicleId);

        return $status !== '' && $status !== VehicleStatus::Sold->value;
    }

    /**
     * Call it inside a transaction: the row stays locked until it ends. Holds (#20) lock a vehicle through here before
     * any of its holds, as a sale does, so every write takes the locks in the same order.
     *
     * @param int $vehicleId A vehicle's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string Its stored status, its row now locked; empty when it is gone.
     */
    public function lockedStatus(int $vehicleId): string
    {
        $row = $this->entityStore->query($this->vehicleTable->type())->where('id', $vehicleId)->forUpdate()->first();

        return $row instanceof Row
            ? $row->string('status')
            : '';
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
