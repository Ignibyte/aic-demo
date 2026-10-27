<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Form\InventoryQuery;
use App\Inventory\Service\VehicleStatuses;
use UnexpectedValueException;

use function str_replace;

/**
 * The vehicles table: one row per VIN, which the table holds unique; the makes on the lot; and the vehicles a list's
 * query keeps, counted and a page at a time. Every value travels as a bound parameter, and the order's column comes
 * only from this class's own list.
 */
final readonly class VehicleRepository
{
    /**
     * The columns a vehicle is read from and saved to, in its constructor's order.
     */
    private const string COLUMNS = 'vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost,'
        . ' status, added_on';

    /**
     * Inserts a vehicle, or on its VIN gives the row every other value.
     */
    private const string UPSERT = 'INSERT INTO vehicles (' . self::COLUMNS . ', created_at, updated_at)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())'
        . ' ON CONFLICT (vin) DO UPDATE SET year = EXCLUDED.year, make = EXCLUDED.make, model = EXCLUDED.model,'
        . ' trim = EXCLUDED.trim, body_style = EXCLUDED.body_style, mileage = EXCLUDED.mileage,'
        . ' exterior_colour = EXCLUDED.exterior_colour, list_price = EXCLUDED.list_price, cost = EXCLUDED.cost,'
        . ' status = EXCLUDED.status, added_on = EXCLUDED.added_on, updated_at = now()';

    /**
     * The column each sort key orders by.
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
     * @param Database        $database        The database.
     * @param VehicleStatuses $vehicleStatuses Reads a stored status.
     */
    public function __construct(
        private Database $database,
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * Makes the vehicle, or gives the one with its VIN every other value.
     *
     * @param Vehicle $vehicle The vehicle.
     *
     * @throws QueryException When the statement fails.
     */
    public function save(Vehicle $vehicle): void
    {
        $this->database->execute(
            self::UPSERT,
            [
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
            ],
        );
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<string> Every make on the lot, in alphabetical order.
     */
    public function makes(): array
    {
        $makes = [];

        foreach ($this->database->fetchAll('SELECT DISTINCT make FROM vehicles ORDER BY make', []) as $row) {
            $makes[] = $this->make($row);
        }

        return $makes;
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @throws QueryException When the query fails.
     *
     * @return int How many vehicles it keeps.
     */
    public function count(InventoryQuery $inventoryQuery): int
    {
        $sql = 'SELECT count(*)::integer AS total FROM vehicles' . $this->where($inventoryQuery);

        return $this->database->fetchOne($sql, $this->values($inventoryQuery))->int('total');
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     * @param int            $limit          How many vehicles a page holds.
     * @param int            $offset         How many come before the page.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return list<Vehicle> The page's vehicles, in the query's order and then by VIN.
     */
    public function page(InventoryQuery $inventoryQuery, int $limit, int $offset): array
    {
        $direction = $inventoryQuery->descending
            ? ' DESC'
            : ' ASC';
        $column = self::ORDER[$inventoryQuery->sort] ?? 'added_on';
        $sql = 'SELECT ' . self::COLUMNS . ' FROM vehicles' . $this->where($inventoryQuery)
            . ' ORDER BY ' . $column . $direction . ', vin ASC LIMIT ? OFFSET ?';
        $vehicles = [];

        foreach ($this->database->fetchAll($sql, [...$this->values($inventoryQuery), $limit, $offset]) as $row) {
            $vehicles[] = $this->vehicle($row);
        }

        return $vehicles;
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @return string The `WHERE` clause its search and filters make, `?` for each value; `TRUE` alone for none.
     */
    private function where(InventoryQuery $inventoryQuery): string
    {
        $conditions = ' WHERE TRUE';

        if ($inventoryQuery->search !== '') {
            $conditions .= ' AND (make ILIKE ? OR model ILIKE ? OR vin ILIKE ?)';
        }

        if ($inventoryQuery->make !== '') {
            $conditions .= ' AND make = ?';
        }

        if ($inventoryQuery->status !== '') {
            $conditions .= ' AND status = ?';
        }

        if ($inventoryQuery->minPrice !== null) {
            $conditions .= ' AND list_price >= ?';
        }

        if ($inventoryQuery->maxPrice !== null) {
            $conditions .= ' AND list_price <= ?';
        }

        return $conditions;
    }

    /**
     * @param InventoryQuery $inventoryQuery What the list was asked for.
     *
     * @return list<string|int> The values for `where()`'s placeholders, in order.
     */
    private function values(InventoryQuery $inventoryQuery): array
    {
        $values = [];

        if ($inventoryQuery->search !== '') {
            $pattern = '%' . $this->literal($inventoryQuery->search) . '%';
            $values = [$pattern, $pattern, $pattern];
        }

        if ($inventoryQuery->make !== '') {
            $values[] = $inventoryQuery->make;
        }

        if ($inventoryQuery->status !== '') {
            $values[] = $inventoryQuery->status;
        }

        if ($inventoryQuery->minPrice !== null) {
            $values[] = $inventoryQuery->minPrice;
        }

        if ($inventoryQuery->maxPrice !== null) {
            $values[] = $inventoryQuery->maxPrice;
        }

        return $values;
    }

    /**
     * @param string $text Search text.
     *
     * @return string The text with `\`, `%` and `_` escaped, so `ILIKE` matches it as written.
     */
    private function literal(string $text): string
    {
        return str_replace('_', '\\_', str_replace('%', '\\%', str_replace('\\', '\\\\', $text)));
    }

    /**
     * Reads a row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `fetchAll()` list
     * has no type to it, and `->string()` there would be resolved against every class with that method, as
     * `StaffRepository::first()` found at gate:23. No AIC ticket carries this yet.
     *
     * @param Row $row A row of `makes()`'s query.
     *
     * @throws QueryException When the row has no make.
     *
     * @return string Its make.
     */
    private function make(Row $row): string
    {
        return $row->string('make');
    }

    /**
     * Reads a row through a native `Row` parameter, as `make()` does.
     *
     * @param Row $row A row with every column of `COLUMNS`.
     *
     * @throws QueryException           When a column is missing or of another type.
     * @throws UnexpectedValueException When its status is no status.
     *
     * @return Vehicle The vehicle.
     */
    private function vehicle(Row $row): Vehicle
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
}
