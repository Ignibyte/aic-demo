<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use App\Customers\Entity\CustomerTable;
use App\Inventory\Entity\VehicleTable;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleTable;
use App\Sales\Entity\SaleTotals;
use App\Sales\Entity\SeedSale;
use App\Sales\Form\SaleQuery;
use App\Sales\Service\SaleRows;
use App\Staff\Entity\StaffTable;
use InvalidArgumentException;

use function count;
use function in_array;
use function substr;

/**
 * The sales table for the list, the seed and the dashboard, through the entity store (#14): the months that have
 * sales, the sales a month and a salesperson keep (totalled and a page at a time, newest first), the sales staff, the
 * seed's save by vehicle, which the table holds unique, and the sales recorded last with what their version is, which
 * the dashboard reads, so it is exported.
 */
final readonly class SaleRepository
{
    /**
     * The roles that sell, as staff stores them.
     */
    private const array SELLING = ['salesperson', 'sales_manager'];

    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param SaleRows      $saleRows      The joined sales, and a row of them read into a sale.
     * @param SaleTable     $saleTable     The `sales` entity type.
     * @param VehicleTable  $vehicleTable  The `vehicles` entity type, inventory's.
     * @param CustomerTable $customerTable The `customers` entity type, customers'.
     * @param StaffTable    $staffTable    The `users` entity type, staff's.
     */
    public function __construct(
        private EntityStore $entityStore,
        private SaleRows $saleRows = new SaleRows(),
        private SaleTable $saleTable = new SaleTable(),
        private VehicleTable $vehicleTable = new VehicleTable(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffTable $staffTable = new StaffTable(),
    ) {
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<string> Every month that has a sale, `YYYY-MM`, newest first.
     */
    public function months(): array
    {
        $days = $this->entityStore->query($this->saleTable->type())->totals()->values('sold_on');
        $months = [];
        $last = '';

        for ($index = count($days) - 1; $index >= 0; $index -= 1) {
            $month = substr($days[$index] ?? '', 0, 7);

            if ($month === $last) {
                continue;
            }

            $months[] = $month;
            $last = $month;
        }

        return $months;
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return SaleTotals How many sales it keeps, their revenue and their gross profit: the revenue less the vehicles'
     *                    cost.
     */
    public function totals(SaleQuery $saleQuery): SaleTotals
    {
        $entityTotals = $this->matching($saleQuery)->totals();
        $revenue = (int) $entityTotals->sum('price');

        return new SaleTotals($entityTotals->count(), $revenue, $revenue - (int) $entityTotals->sum('v.cost'));
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     * @param int       $limit     How many sales a page holds, at least 1.
     * @param int       $offset    How many come before the page.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the limit is below 1 or the offset negative.
     *
     * @return list<Sale> The page's sales, newest first.
     */
    public function page(SaleQuery $saleQuery, int $limit, int $offset): array
    {
        return $this->sales($this->saleRows->newest($this->matching($saleQuery))->limit($limit)->offset($offset));
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
        return $this->sales($this->saleRows->joined($this->entityStore)->orderByDescending('id')->limit($limit));
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string What the sales are now: how many there are and when the latest was recorded, which changes with
     *                every sale recorded or voided.
     */
    public function version(): string
    {
        $entityTotals = $this->entityStore->query($this->saleTable->type())->totals();

        return $entityTotals->count() . '|' . ($entityTotals->max('created_at') ?? '');
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<string> The logins of the salespeople and sales managers, in order.
     */
    public function sellers(): array
    {
        $sellers = [];

        foreach ($this->entityStore->query($this->staffTable->type())->whereIn('role', self::SELLING)->orderBy('email')->rows() as $row) {
            $sellers[] = $this->text($row, 'email');
        }

        return $sellers;
    }

    /**
     * Records the seed sale, or gives the vehicle's sale its values. The seed saves its vehicles sold through
     * inventory's `VehicleRepository` first, so this changes no vehicle.
     *
     * @param SeedSale $seedSale A seed sale.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return bool Whether it was saved; false when its vehicle, customer or salesperson is missing.
     */
    public function save(SeedSale $seedSale): bool
    {
        $vehicleId = $this->idWhere($this->vehicleTable->type(), 'vin', $seedSale->vin);
        $customerId = $this->idWhere($this->customerTable->type(), 'email', $seedSale->customer);
        $salespersonId = $this->idWhere($this->staffTable->type(), 'email', $seedSale->salesperson);

        if (in_array(0, [$vehicleId, $customerId, $salespersonId], true)) {
            return false;
        }

        $this->entityStore->upsert($this->saleTable->type(), [
            'customer_id' => $customerId,
            'price' => $seedSale->price,
            'salesperson_id' => $salespersonId,
            'sold_on' => $seedSale->soldOn,
            'vehicle_id' => $vehicleId,
        ], 'vehicle_id');

        return true;
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     *
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return EntityQuery The joined sales its month (each of its days) and its salesperson (by login) keep.
     */
    private function matching(SaleQuery $saleQuery): EntityQuery
    {
        $entityQuery = $this->saleRows->joined($this->entityStore);

        if ($saleQuery->month !== '') {
            $entityQuery = $this->saleRows->inMonth($entityQuery, $saleQuery->month);
        }

        if ($saleQuery->salesperson !== '') {
            $entityQuery->where('u.email', $saleQuery->salesperson);
        }

        return $entityQuery;
    }

    /**
     * @param EntityQuery $entityQuery A query of the joined sales.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Sale> Its sales, in its order.
     */
    private function sales(EntityQuery $entityQuery): array
    {
        $sales = [];

        foreach ($entityQuery->rows() as $row) {
            $sales[] = $this->saleRows->sale($row);
        }

        return $sales;
    }

    /**
     * @param EntityType $entityType Another module's entity type.
     * @param string     $field      One of its fields the table holds unique.
     * @param string     $value      Its value.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type has no such field.
     *
     * @return int The id of the row that holds it; 0 when none does.
     */
    private function idWhere(EntityType $entityType, string $field, string $value): int
    {
        $row = $this->entityStore->query($entityType)->where($field, $value)->first();

        return $row instanceof Row
            ? $row->int('id')
            : 0;
    }

    /**
     * Reads the row through a native `Row` parameter, as `SaleRows::sale()` does.
     *
     * @param Row    $row    A row.
     * @param string $column One of its text columns.
     *
     * @throws QueryException When the column is missing or not text.
     *
     * @return string Its value.
     */
    private function text(Row $row, string $column): string
    {
        return $row->string($column);
    }
}
