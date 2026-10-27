<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use UnexpectedValueException;

/**
 * A row of `vehicles` read into a `Vehicle`, and a `Vehicle` as the values a statement saves, for both repositories:
 * the list's and the one that writes a vehicle at a time.
 */
final readonly class VehicleRows
{
    /**
     * The columns a vehicle is read from and saved to, in its constructor's order.
     */
    public const string COLUMNS = 'vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost,'
        . ' status, added_on';

    /**
     * @param VehicleStatuses $vehicleStatuses Reads a stored status.
     */
    public function __construct(
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `fetchAll()` list
     * has no type to it, and `->string()` there would be resolved against every class with that method, as
     * `StaffRepository::first()` found at gate:23. No AIC ticket carries this yet.
     *
     * @param Row $row A row with every column of `COLUMNS`.
     *
     * @throws QueryException           When a column is missing or of another type.
     * @throws UnexpectedValueException When its status is no status.
     *
     * @return Vehicle The vehicle.
     */
    public function vehicle(Row $row): Vehicle
    {
        return new Vehicle(
            $row->string('vin'),
            $row->int('year'),
            $row->string('make'),
            $row->string('model'),
            $row->string('trim'),
            $row->string('body_style'),
            $row->int('mileage'),
            $row->string('exterior_colour'),
            new VehiclePrice($row->int('list_price'), $row->int('cost')),
            $this->vehicleStatuses->stored($row->string('status')),
            $row->string('added_on'),
        );
    }

    /**
     * @param Vehicle $vehicle A vehicle.
     *
     * @return list<string|int> Its values for the `?`s of a statement naming `COLUMNS`, in that order.
     */
    public function values(Vehicle $vehicle): array
    {
        return [
            $vehicle->vin,
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
            $vehicle->trim,
            $vehicle->bodyStyle,
            $vehicle->mileage,
            $vehicle->exteriorColour,
            $vehicle->price->list,
            $vehicle->price->cost,
            $vehicle->status->value,
            $vehicle->addedOn,
        ];
    }
}
