<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleDetails;
use App\Sales\Service\SaleRows;

/**
 * One sale at a time: by its id, by its vehicle's VIN, a customer's, and the two writes, each one transaction that
 * keeps the vehicle's status in step through inventory's `VehicleStatusRepository`: recording a sale locks the vehicle
 * and marks it sold, and voiding one puts it back in stock.
 */
final readonly class SaleRecordRepository
{
    /**
     * Records a sale, unless its vehicle has one, and gives its id.
     */
    private const string INSERT = 'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at)'
        . ' VALUES (?, ?, ?, ?, ?, now()) ON CONFLICT (vehicle_id) DO NOTHING RETURNING id';

    /**
     * Deletes a sale and gives its vehicle.
     */
    private const string VOID = 'DELETE FROM sales WHERE id = ? RETURNING vehicle_id';

    /**
     * @param Database                $database                The database.
     * @param VehicleStatusRepository $vehicleStatusRepository Locks and changes the vehicle's status.
     * @param SaleRows                $saleRows                Reads a row into a sale.
     */
    public function __construct(
        private Database $database,
        private VehicleStatusRepository $vehicleStatusRepository,
        private SaleRows $saleRows = new SaleRows(),
    ) {
    }

    /**
     * @param int $id A sale's id.
     *
     * @throws QueryException When the query fails.
     *
     * @return Sale|null The sale with it; null when none has it.
     */
    public function find(int $id): ?Sale
    {
        return $this->first(' WHERE s.id = ? LIMIT 1', [$id]);
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws QueryException When the query fails.
     *
     * @return Sale|null Its sale; null when it has none.
     */
    public function forVehicle(string $vin): ?Sale
    {
        return $this->first(' WHERE v.vin = ? LIMIT 1', [$vin]);
    }

    /**
     * @param int $customerId A customer's id.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Sale> What they bought, newest first.
     */
    public function forCustomer(int $customerId): array
    {
        $sql = 'SELECT ' . SaleRows::SELECTED . SaleRows::FROM . ' WHERE s.customer_id = ?' . SaleRows::NEWEST;
        $sales = [];

        foreach ($this->database->fetchAll($sql, [$customerId]) as $row) {
            $sales[] = $this->saleRows->sale($row);
        }

        return $sales;
    }

    /**
     * @param SaleDetails $saleDetails A checked sale.
     *
     * @throws QueryException When a statement fails; the transaction is rolled back first.
     *
     * @return int|null The new sale's id; null when its vehicle is gone or already sold, and nothing changed.
     */
    public function record(SaleDetails $saleDetails): ?int
    {
        $this->database->beginTransaction();

        try {
            $id = $this->inserted($saleDetails);

            if ($id === null) {
                $this->database->rollBack();

                return null;
            }

            $this->vehicleStatusRepository->mark($saleDetails->vehicleId, VehicleStatus::Sold);
            $this->database->commit();

            return $id;
        } catch (QueryException $queryException) {
            $this->database->rollBack();

            throw $queryException;
        }
    }

    /**
     * @param Sale $sale A recorded sale.
     *
     * @throws QueryException When a statement fails; the transaction is rolled back first.
     *
     * @return bool Whether it was voided and its vehicle put back in stock; false when it was gone already.
     */
    public function void(Sale $sale): bool
    {
        $this->database->beginTransaction();

        try {
            $vehicleId = 0;

            foreach ($this->database->fetchAll(self::VOID, [$sale->id]) as $row) {
                $vehicleId = $this->number($row, 'vehicle_id');
            }

            if ($vehicleId === 0) {
                $this->database->rollBack();

                return false;
            }

            $this->vehicleStatusRepository->mark($vehicleId, VehicleStatus::InStock);
            $this->database->commit();

            return true;
        } catch (QueryException $queryException) {
            $this->database->rollBack();

            throw $queryException;
        }
    }

    /**
     * @param SaleDetails $saleDetails A checked sale, inside the transaction.
     *
     * @throws QueryException When a statement fails.
     *
     * @return int|null The new sale's id, its vehicle locked first; null when the vehicle is gone, sold, or has a sale.
     */
    private function inserted(SaleDetails $saleDetails): ?int
    {
        if (!$this->vehicleStatusRepository->lockSellable($saleDetails->vehicleId)) {
            return null;
        }

        $values = [
            $saleDetails->vehicleId,
            $saleDetails->customerId,
            $saleDetails->salespersonId,
            $saleDetails->price,
            $saleDetails->soldOn,
        ];

        foreach ($this->database->fetchAll(self::INSERT, $values) as $row) {
            return $this->number($row, 'id');
        }

        return null;
    }

    /**
     * @param string           $condition The condition and limit after the join.
     * @param list<int|string> $values    Its values.
     *
     * @throws QueryException When the query fails.
     *
     * @return Sale|null The first sale it keeps; null when it keeps none.
     */
    private function first(string $condition, array $values): ?Sale
    {
        foreach ($this->database->fetchAll('SELECT ' . SaleRows::SELECTED . SaleRows::FROM . $condition, $values) as $row) {
            return $this->saleRows->sale($row);
        }

        return null;
    }

    /**
     * Reads the row through a native `Row` parameter, as `SaleRows::sale()` does.
     *
     * @param Row    $row    A row.
     * @param string $column One of its integer columns.
     *
     * @throws QueryException When the column is missing or not an integer.
     *
     * @return int Its value.
     */
    private function number(Row $row, string $column): int
    {
        return $row->int($column);
    }
}
