<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Form\InventoryQuery;
use App\Inventory\Service\VehicleRows;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * The vehicles table through the entity store (#14): one row per VIN, which the table holds unique; the makes on the
 * lot; and the vehicles a list's query keeps, counted and a page at a time. The entity API binds every value, and the
 * order's field comes only from this class's own list.
 */
final readonly class VehicleRepository
{
    /**
     * The field each sort key orders by.
     */
    private const array ORDER = [
        'added' => 'added_on',
        'make' => 'make',
        'mileage' => 'mileage',
        'model' => 'model',
        'price' => 'list_price',
        'status' => 'status',
        'year' => 'year',
    ];

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
    }

    /**
     * Makes the vehicle, or gives the one with its VIN every other value.
     *
     * @param Vehicle $vehicle The vehicle.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function save(Vehicle $vehicle): void
    {
        $this->entityStore->upsert($this->vehicleTable->type(), $this->vehicleRows->values($vehicle), 'vin');
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<string> Every make on the lot, in alphabetical order.
     */
    public function makes(): array
    {
        return $this->entityStore->query($this->vehicleTable->type())->totals()->values('make');
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int How many vehicles it keeps.
     */
    public function count(InventoryQuery $inventoryQuery): int
    {
        return $this->matching($inventoryQuery)->totals()->count();
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param int            $limit          How many vehicles a page holds, at least 1.
     * @param int            $offset         How many come before the page.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws InvalidArgumentException When the limit is below 1 or the offset negative.
     *
     * @return list<Vehicle> The page's vehicles, in the query's order and then by VIN.
     */
    public function page(InventoryQuery $inventoryQuery, int $limit, int $offset): array
    {
        $field = self::ORDER[$inventoryQuery->sort] ?? 'added_on';
        $entityQuery = $inventoryQuery->descending
            ? $this->matching($inventoryQuery)->orderByDescending($field)
            : $this->matching($inventoryQuery)->orderBy($field);
        $vehicles = [];

        foreach ($entityQuery->orderBy('vin')->limit($limit)->offset($offset)->rows() as $row) {
            $vehicles[] = $this->vehicleRows->vehicle($row);
        }

        return $vehicles;
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery The vehicles its search (in make, model and VIN, as typed) and filters keep.
     */
    private function matching(InventoryQuery $inventoryQuery): EntityQuery
    {
        $entityQuery = $this->entityStore->query($this->vehicleTable->type())
            ->search(['make', 'model', 'vin'], $inventoryQuery->search);

        if ($inventoryQuery->make !== '') {
            $entityQuery->where('make', $inventoryQuery->make);
        }

        if ($inventoryQuery->status !== '') {
            $entityQuery->where('status', $inventoryQuery->status);
        }

        if ($inventoryQuery->minPrice !== null) {
            $entityQuery->where('list_price', $inventoryQuery->minPrice, Comparison::GreaterOrEqual);
        }

        if ($inventoryQuery->maxPrice !== null) {
            $entityQuery->where('list_price', $inventoryQuery->maxPrice, Comparison::LessOrEqual);
        }

        return $entityQuery;
    }
}
