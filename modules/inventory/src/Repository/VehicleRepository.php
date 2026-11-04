<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Form\InventoryQuery;
use App\Inventory\Service\VehicleRows;
use InvalidArgumentException;
use UnexpectedValueException;

use function abs;
use function array_merge;
use function array_slice;
use function array_values;
use function ksort;
use function sprintf;

use const SORT_STRING;

/**
 * The vehicles table through the entity store (#14): one row per VIN, which the table holds unique; the makes on the
 * lot; and the vehicles a list's query keeps, counted and a page at a time. The entity API binds every value, and the
 * order's field comes only from this class's own list. For a vehicle's page (#31) it reads the vehicles like it: not
 * sold, of its make and body style, nearest its list price, with their version.
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
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return array<string, int> How many vehicles it keeps with each status, by the status's stored value (#29).
     */
    public function statuses(InventoryQuery $inventoryQuery): array
    {
        return $this->matching($inventoryQuery)->totals()->countBy('status');
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
     * The vehicles like one (#31): not sold, of its make and body style, itself left out, nearest its list price first
     * and then by VIN. Two reads of at most `$limit` each, the nearest at or above its price and the nearest below it,
     * joined by how far each is from it.
     *
     * @param Vehicle $vehicle A vehicle.
     * @param int     $limit   How many to give, at least 1.
     *
     * @throws QueryException           When a query fails.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws InvalidArgumentException When the entity type is malformed or the limit is below 1.
     *
     * @return list<Vehicle> At most `$limit` of them, nearest first.
     */
    public function similar(Vehicle $vehicle, int $limit): array
    {
        $price = $vehicle->price->list;
        $entityQuery = $this->like($vehicle)
            ->where('list_price', $price, Comparison::GreaterOrEqual)
            ->orderBy('list_price')
            ->orderBy('vin')
            ->limit($limit);
        $below = $this->like($vehicle)
            ->where('list_price', $price - 1, Comparison::LessOrEqual)
            ->orderByDescending('list_price')
            ->orderBy('vin')
            ->limit($limit);

        return $this->nearest($this->vehicles($entityQuery), $this->vehicles($below), $price, $limit);
    }

    /**
     * @param Vehicle $vehicle A vehicle.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string What the vehicles like it are now: their count, the newest `updated_at` among them and its own
     *                list price, `count.updated.price`; any change to one of them, or one more or fewer, changes it.
     */
    public function similarVersion(Vehicle $vehicle): string
    {
        $count = $this->like($vehicle)->totals()->count();
        $updated = $this->like($vehicle)->totals()->max('updated_at') ?? '';

        return $count . '.' . $updated . '.' . $vehicle->price->list;
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

    /**
     * @param Vehicle $vehicle A vehicle.
     *
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery The vehicles not sold, of its make and body style, but itself.
     */
    private function like(Vehicle $vehicle): EntityQuery
    {
        return $this->entityStore->query($this->vehicleTable->type())
            ->where('make', $vehicle->make)
            ->where('body_style', $vehicle->bodyStyle)
            ->where('status', VehicleStatus::Sold->value, Comparison::NotEquals)
            ->where('vin', $vehicle->vin, Comparison::NotEquals);
    }

    /**
     * @param EntityQuery $entityQuery A query of vehicles.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return list<Vehicle> Its vehicles, in its order.
     */
    private function vehicles(EntityQuery $entityQuery): array
    {
        $vehicles = [];

        foreach ($entityQuery->rows() as $row) {
            $vehicles[] = $this->vehicleRows->vehicle($row);
        }

        return $vehicles;
    }

    /**
     * @param list<Vehicle> $above The vehicles at or above the price.
     * @param list<Vehicle> $below The vehicles below it.
     * @param int           $price The price.
     * @param int           $limit How many to give.
     *
     * @return list<Vehicle> The two joined, nearest the price first and then by VIN, at most `$limit`: each is ranked
     *                       by its distance from the price, written with leading zeros, and its VIN, so a sort of the
     *                       ranks as text is that order.
     */
    private function nearest(array $above, array $below, int $price, int $limit): array
    {
        $ranked = [];

        foreach (array_merge($above, $below) as $vehicle) {
            $ranked[sprintf('%010d %s', abs($vehicle->price->list - $price), $vehicle->vin)] = $vehicle;
        }

        ksort($ranked, SORT_STRING);

        return array_slice(array_values($ranked), 0, $limit);
    }
}
