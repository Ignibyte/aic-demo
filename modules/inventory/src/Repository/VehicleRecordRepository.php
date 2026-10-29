<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Service\VehicleRows;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * One vehicle at a time, by its VIN, through the entity store (#14): read, its id found, checked for a taken VIN, made,
 * changed and deleted; and the database's own today, which the vehicle form's checks and its first date read.
 */
final readonly class VehicleRecordRepository
{
    /**
     * The database's today, from the view inventory makes.
     */
    private TodayRepository $todayRepository;

    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param VehicleTable $vehicleTable The `vehicles` entity type.
     * @param VehicleRows  $vehicleRows  Reads a row into a vehicle, and a vehicle into its values.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleTable $vehicleTable = new VehicleTable(),
        private VehicleRows $vehicleRows = new VehicleRows(),
    ) {
        $this->todayRepository = new TodayRepository($entityStore);
    }

    /**
     * @param string $vin A VIN.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return Vehicle|null The vehicle with it; null when none has it.
     */
    public function find(string $vin): ?Vehicle
    {
        $row = $this->entityStore->query($this->vehicleTable->type())->where('vin', $vin)->limit(1)->first();

        return $row instanceof Row
            ? $this->vehicleRows->vehicle($row)
            : null;
    }

    /**
     * @param string $vin A VIN.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int The id of the vehicle with it, which its photos refer to; 0 when none has it.
     */
    public function vehicleId(string $vin): int
    {
        $row = $this->entityStore->query($this->vehicleTable->type())->where('vin', $vin)->limit(1)->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }

    /**
     * @param string $vin       A VIN.
     * @param string $exceptVin The VIN of the vehicle being changed, which may keep it; empty for a new vehicle.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether another vehicle has it.
     */
    public function taken(string $vin, string $exceptVin): bool
    {
        return $this->entityStore->query($this->vehicleTable->type())
            ->where('vin', $vin)
            ->where('vin', $exceptVin, Comparison::NotEquals)
            ->totals()
            ->count() > 0;
    }

    /**
     * @param Vehicle $vehicle A new vehicle.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return bool Whether it was made; false when its VIN was taken first.
     */
    public function create(Vehicle $vehicle): bool
    {
        return $this->entityStore->insertIfAbsent($this->vehicleTable->type(), $this->vehicleRows->values($vehicle), 'vin') !== null;
    }

    /**
     * @param string  $vin     The VIN the vehicle has now.
     * @param Vehicle $vehicle Every value it is to have.
     *
     * @throws QueryException           When the statement fails, such as on a VIN another vehicle took since the check,
     *                                  which `VehicleSaves` answers as that refusal.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return bool Whether it was changed; false when no vehicle has the VIN any more.
     */
    public function update(string $vin, Vehicle $vehicle): bool
    {
        return $this->entityStore->query($this->vehicleTable->type())
            ->where('vin', $vin)
            ->changes()
            ->update($this->vehicleRows->values($vehicle)) === 1;
    }

    /**
     * @param string $vin A VIN.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether the vehicle was deleted; false when none has the VIN or it is sold.
     */
    public function delete(string $vin): bool
    {
        return $this->entityStore->query($this->vehicleTable->type())
            ->where('vin', $vin)
            ->where('status', VehicleStatus::Sold->value, Comparison::NotEquals)
            ->changes()
            ->delete() === 1;
    }

    /**
     * @throws QueryException           When the query fails, or the today view gives no row.
     * @throws InvalidArgumentException When its entity type is malformed.
     *
     * @return string The database's today, `YYYY-MM-DD`.
     */
    public function today(): string
    {
        return $this->todayRepository->today();
    }
}
