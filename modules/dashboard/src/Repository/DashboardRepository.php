<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Customers\Entity\CustomerTable;
use App\Dashboard\Entity\Figure;
use App\Dashboard\Entity\MonthSales;
use App\Dashboard\Entity\TileFigures;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Repository\TodayRepository;
use App\Sales\Entity\Sale;
use App\Sales\Repository\SaleRepository;
use InvalidArgumentException;

use function implode;

/**
 * The dashboard's figures, read through the entity API (#14): the four reports from the views `CreateDashboardViews`
 * makes, each through a read-only entity type named here as a literal (gate:26), so the SQL that counts them lives only
 * in that migration; the sales recorded last, which the dashboard's "Latest sales" lists (#10), through sales' exported
 * repository; and what the live part shows now, its version, from the counts and latest changes of the tables it reads
 * and the database's today.
 */
final readonly class DashboardRepository
{
    /**
     * @param EntityStore     $entityStore     The entity store.
     * @param SaleRepository  $saleRepository  The sales, sales'.
     * @param TodayRepository $todayRepository The database's today, inventory's.
     * @param VehicleTable    $vehicleTable    The `vehicles` entity type, inventory's.
     * @param CustomerTable   $customerTable   The `customers` entity type, customers'.
     */
    public function __construct(
        private EntityStore $entityStore,
        private SaleRepository $saleRepository,
        private TodayRepository $todayRepository,
        private VehicleTable $vehicleTable = new VehicleTable(),
        private CustomerTable $customerTable = new CustomerTable(),
    ) {
    }

    /**
     * @throws QueryException           When the query fails, or the view gives no row, which it always gives.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return TileFigures The stat tiles' figures.
     */
    public function tiles(): TileFigures
    {
        $row = $this->entityStore->query(new EntityType('dashboard_tiles', 'today', $this->integers([
            'in_stock', 'on_hold', 'sales', 'last_sales', 'revenue', 'last_revenue', 'not_sold', 'days',
        ]), true))->first();

        if (!$row instanceof Row) {
            throw new QueryException('The dashboard_tiles view gave no row.');
        }

        return new TileFigures(
            $row->int('in_stock'),
            $row->int('on_hold'),
            $row->int('sales'),
            $row->int('last_sales'),
            $row->int('revenue'),
            $row->int('last_revenue'),
            $row->int('not_sold'),
            $row->int('days'),
        );
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<MonthSales> The last twelve months, the oldest first.
     */
    public function months(): array
    {
        $months = [];
        $entityType = new EntityType('dashboard_months', 'first_day', [
            new Field('label', FieldType::Text),
            new Field('note', FieldType::Text),
            new Field('name', FieldType::Text),
            new Field('sales', FieldType::Integer),
        ], true);

        foreach ($this->entityStore->query($entityType)->orderBy('first_day')->rows() as $row) {
            $months[] = $this->month($row);
        }

        return $months;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Figure> The vehicles not sold per make, the most first, then by make.
     */
    public function makes(): array
    {
        $figures = [];
        $entityType = new EntityType('dashboard_makes', 'make', $this->integers(['amount']), true);

        foreach ($this->entityStore->query($entityType)->orderByDescending('amount')->orderBy('make')->rows() as $row) {
            $figures[] = $this->figure($row, 'make');
        }

        return $figures;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Figure> Each salesperson's revenue over the last twelve months in whole dollars, the most first, then
     *                      by name.
     */
    public function salespeople(): array
    {
        $figures = [];
        $entityType = new EntityType('dashboard_salespeople', 'salesperson_id', [
            new Field('name', FieldType::Text),
            new Field('amount', FieldType::Integer),
        ], true);

        foreach ($this->entityStore->query($entityType)->orderByDescending('amount')->orderBy('name')->rows() as $row) {
            $figures[] = $this->figure($row, 'name');
        }

        return $figures;
    }

    /**
     * @param int $limit How many, at least 1.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the limit is below 1.
     *
     * @return list<Sale> The sales recorded last, the latest first.
     */
    public function latest(int $limit): array
    {
        return $this->saleRepository->latest($limit);
    }

    /**
     * @throws QueryException           When a query fails, or the today view gives no row.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return string What the tiles and the latest sales are now: the vehicles' count and latest change, the sales'
     *                count and latest record, the customers' latest change and the database's today. Staff names are
     *                left out: `users` keeps no time of change.
     */
    public function version(): string
    {
        $entityTotals = $this->entityStore->query($this->vehicleTable->type())->totals();
        $customers = $this->entityStore->query($this->customerTable->type())->totals();

        return implode('|', [
            (string) $entityTotals->count(),
            $entityTotals->max('updated_at') ?? '',
            $this->saleRepository->version(),
            $customers->max('updated_at') ?? '',
            $this->todayRepository->today(),
        ]);
    }

    /**
     * @param list<string> $names A view's whole-number columns.
     *
     * @throws InvalidArgumentException When a name is not an identifier.
     *
     * @return list<Field> Each as an Integer field.
     */
    private function integers(array $names): array
    {
        $fields = [];

        foreach ($names as $name) {
            $fields[] = new Field($name, FieldType::Integer);
        }

        return $fields;
    }

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `rows()` list has
     * no type to it, as `SaleRows` notes. No AIC ticket carries this yet.
     *
     * @param Row $row A row of `dashboard_months`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return MonthSales The month.
     */
    private function month(Row $row): MonthSales
    {
        return new MonthSales($row->string('label'), $row->string('note'), $row->string('name'), $row->int('sales'));
    }

    /**
     * Reads the row through a native `Row` parameter, as `month()` does.
     *
     * @param Row    $row   A row of `label` and `amount`.
     * @param string $label The column that labels the bar.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Figure The bar.
     */
    private function figure(Row $row, string $label): Figure
    {
        return new Figure($row->string($label), $row->int('amount'));
    }
}
