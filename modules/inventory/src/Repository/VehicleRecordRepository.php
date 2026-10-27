<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Service\VehicleRows;
use UnexpectedValueException;

/**
 * One vehicle at a time, by its VIN: read, checked for a taken VIN, made, changed and deleted, each statement a fixed
 * text with its values bound; and the database's own today, which the vehicle form's checks and its first date read.
 */
final readonly class VehicleRecordRepository
{
    /**
     * Reads the vehicle with a VIN.
     */
    private const string FIND = 'SELECT ' . VehicleRows::COLUMNS . ' FROM vehicles WHERE vin = ? LIMIT 1';

    /**
     * Counts the vehicles but one that hold a VIN.
     */
    private const string TAKEN = 'SELECT count(*)::integer AS total FROM vehicles WHERE vin = ? AND vin <> ?';

    /**
     * Makes a vehicle, unless its VIN is taken.
     */
    private const string CREATE = 'INSERT INTO vehicles (' . VehicleRows::COLUMNS . ', created_at, updated_at)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now()) ON CONFLICT (vin) DO NOTHING';

    /**
     * Gives the vehicle with a VIN every value, its VIN too.
     */
    private const string UPDATE = 'UPDATE vehicles SET vin = ?, year = ?, make = ?, model = ?, trim = ?, body_style = ?,'
        . ' mileage = ?, exterior_colour = ?, list_price = ?, cost = ?, status = ?, added_on = ?, updated_at = now()'
        . ' WHERE vin = ?';

    /**
     * Deletes the vehicle with a VIN, unless it is sold.
     */
    private const string DELETE = "DELETE FROM vehicles WHERE vin = ? AND status <> 'sold'";

    /**
     * The database's date.
     */
    private const string TODAY = "SELECT to_char(current_date, 'YYYY-MM-DD') AS today";

    /**
     * @param Database    $database    The database.
     * @param VehicleRows $vehicleRows Reads a row into a vehicle, and a vehicle into its values.
     */
    public function __construct(
        private Database $database,
        private VehicleRows $vehicleRows = new VehicleRows(),
    ) {
    }

    /**
     * @param string $vin A VIN.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When its stored status is no status.
     *
     * @return Vehicle|null The vehicle with it; null when none has it.
     */
    public function find(string $vin): ?Vehicle
    {
        foreach ($this->database->fetchAll(self::FIND, [$vin]) as $row) {
            return $this->vehicleRows->vehicle($row);
        }

        return null;
    }

    /**
     * @param string $vin       A VIN.
     * @param string $exceptVin The VIN of the vehicle being changed, which may keep it; empty for a new vehicle.
     *
     * @throws QueryException When the query fails.
     *
     * @return bool Whether another vehicle has it.
     */
    public function taken(string $vin, string $exceptVin): bool
    {
        return $this->database->fetchOne(self::TAKEN, [$vin, $exceptVin])->int('total') > 0;
    }

    /**
     * @param Vehicle $vehicle A new vehicle.
     *
     * @throws QueryException When the statement fails.
     *
     * @return bool Whether it was made; false when its VIN was taken first.
     */
    public function create(Vehicle $vehicle): bool
    {
        return $this->database->execute(self::CREATE, $this->vehicleRows->values($vehicle)) === 1;
    }

    /**
     * @param string  $vin     The VIN the vehicle has now.
     * @param Vehicle $vehicle Every value it is to have.
     *
     * @throws QueryException When the statement fails, such as on a VIN another vehicle took since the check, which
     *                        `VehicleSaves` answers as that refusal.
     *
     * @return bool Whether it was changed; false when no vehicle has the VIN any more.
     */
    public function update(string $vin, Vehicle $vehicle): bool
    {
        return $this->database->execute(self::UPDATE, [...$this->vehicleRows->values($vehicle), $vin]) === 1;
    }

    /**
     * @param string $vin A VIN.
     *
     * @throws QueryException When the statement fails.
     *
     * @return bool Whether the vehicle was deleted; false when none has the VIN or it is sold.
     */
    public function delete(string $vin): bool
    {
        return $this->database->execute(self::DELETE, [$vin]) === 1;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return string The database's today, `YYYY-MM-DD`.
     */
    public function today(): string
    {
        return $this->database->fetchOne(self::TODAY, [])->string('today');
    }
}
