<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use UnexpectedValueException;

/**
 * A row of `vehicles` read into a `Vehicle`, and a `Vehicle` as the values the entity store saves by field, for both
 * repositories: the list's and the one that writes a vehicle at a time.
 */
final readonly class VehicleRows
{
    /**
     * @param VehicleStatuses $vehicleStatuses Reads a stored status.
     */
    public function __construct(
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `rows()` list has
     * no type to it, and `->string()` there would be resolved against every class with that method, as the staff
     * repository found at gate:23. No AIC ticket carries this yet.
     *
     * @param Row $row A row of the `vehicles` entity type.
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
     * @return array<string, string|int> Its values by field, every writable field of `VehicleTable`'s.
     */
    public function values(Vehicle $vehicle): array
    {
        return [
            'added_on' => $vehicle->addedOn,
            'body_style' => $vehicle->bodyStyle,
            'cost' => $vehicle->price->cost,
            'exterior_colour' => $vehicle->exteriorColour,
            'list_price' => $vehicle->price->list,
            'make' => $vehicle->make,
            'mileage' => $vehicle->mileage,
            'model' => $vehicle->model,
            'status' => $vehicle->status->value,
            'trim' => $vehicle->trim,
            'vin' => $vehicle->vin,
            'year' => $vehicle->year,
        ];
    }
}
