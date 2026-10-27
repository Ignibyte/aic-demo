<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Inventory\Entity\VehicleStatus;

/**
 * A vehicle's status by its id, for the module that sells vehicles (#7): whether one can be sold, its row locked for
 * the transaction that sells it, and its status changed when a sale is recorded or voided. Inventory owns the
 * `vehicles` table, so other modules change its rows only through here; each call runs on the caller's transaction.
 */
final readonly class VehicleStatusRepository
{
    /**
     * Locks a vehicle's row and reads its status.
     */
    private const string LOCK = 'SELECT status FROM vehicles WHERE id = ? FOR UPDATE';

    /**
     * Gives a vehicle a status.
     */
    private const string STATUS = 'UPDATE vehicles SET status = ?, updated_at = now() WHERE id = ?';

    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * Call it inside a transaction: the row stays locked until it ends, so two sales of one vehicle cannot pass
     * together.
     *
     * @param int $vehicleId A vehicle's id.
     *
     * @throws QueryException When the query fails.
     *
     * @return bool Whether the vehicle is there and not sold, its row now locked.
     */
    public function lockSellable(int $vehicleId): bool
    {
        foreach ($this->database->fetchAll(self::LOCK, [$vehicleId]) as $row) {
            return $this->status($row) !== VehicleStatus::Sold->value;
        }

        return false;
    }

    /**
     * @param int           $vehicleId     A vehicle's id.
     * @param VehicleStatus $vehicleStatus Its new status.
     *
     * @throws QueryException When the statement fails.
     */
    public function mark(int $vehicleId, VehicleStatus $vehicleStatus): void
    {
        $this->database->execute(self::STATUS, [$vehicleStatus->value, $vehicleId]);
    }

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `fetchAll()` list
     * has no type to it, as `VehicleRows` notes. No AIC ticket carries this yet.
     *
     * @param Row $row A row of `LOCK`.
     *
     * @throws QueryException When it has no status.
     *
     * @return string The stored status.
     */
    private function status(Row $row): string
    {
        return $row->string('status');
    }
}
