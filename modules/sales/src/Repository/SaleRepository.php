<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleTotals;
use App\Sales\Entity\SeedSale;
use App\Sales\Form\SaleQuery;
use App\Sales\Service\SaleRows;

use function implode;

/**
 * The sales table for the list and the seed: the months that have sales, the sales a month and a salesperson keep
 * (totalled and a page at a time, newest first), the sales staff, and the seed's save by vehicle, which the table holds
 * unique. Every value travels as a bound parameter.
 */
final readonly class SaleRepository
{
    /**
     * Every month that has a sale, newest first.
     */
    private const string MONTHS = "SELECT DISTINCT to_char(sold_on, 'YYYY-MM') AS month FROM sales ORDER BY month DESC";

    /**
     * The count, revenue and gross profit of the sales a condition keeps.
     */
    private const string TOTALS = 'SELECT count(*)::integer AS sales, coalesce(sum(s.price), 0)::integer AS revenue,'
        . ' coalesce(sum(s.price - v.cost), 0)::integer AS gross';

    /**
     * The logins of the staff whose role sells.
     */
    private const string SELLERS = "SELECT email FROM users WHERE role IN ('salesperson', 'sales_manager') ORDER BY email";

    /**
     * Inserts a seed sale from its vehicle's VIN, its customer's email and its salesperson's login, dated its days
     * after the vehicle came; or, on the vehicle, gives its sale every other value. No row when one of them is missing.
     */
    private const string UPSERT = 'INSERT INTO sales (vehicle_id, customer_id, salesperson_id, price, sold_on, created_at)'
        . ' SELECT v.id, c.id, u.id, ?, v.added_on + ?::integer, now() FROM vehicles v, customers c, users u'
        . ' WHERE v.vin = ? AND c.email = ? AND u.email = ?'
        . ' ON CONFLICT (vehicle_id) DO UPDATE SET customer_id = EXCLUDED.customer_id,'
        . ' salesperson_id = EXCLUDED.salesperson_id, price = EXCLUDED.price, sold_on = EXCLUDED.sold_on';

    /**
     * @param Database $database The database.
     * @param SaleRows $saleRows Reads a row into a sale.
     */
    public function __construct(
        private Database $database,
        private SaleRows $saleRows = new SaleRows(),
    ) {
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<string> Every month that has a sale, `YYYY-MM`, newest first.
     */
    public function months(): array
    {
        $months = [];

        foreach ($this->database->fetchAll(self::MONTHS, []) as $row) {
            $months[] = $this->text($row, 'month');
        }

        return $months;
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     *
     * @throws QueryException When the query fails.
     *
     * @return SaleTotals How many sales it keeps, their revenue and their gross profit.
     */
    public function totals(SaleQuery $saleQuery): SaleTotals
    {
        $row = $this->database->fetchOne(self::TOTALS . SaleRows::FROM . $this->where($saleQuery), $this->values($saleQuery));

        return new SaleTotals($row->int('sales'), $row->int('revenue'), $row->int('gross'));
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     * @param int       $limit     How many sales a page holds.
     * @param int       $offset    How many come before the page.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Sale> The page's sales, newest first.
     */
    public function page(SaleQuery $saleQuery, int $limit, int $offset): array
    {
        $sql = 'SELECT ' . SaleRows::SELECTED . SaleRows::FROM . $this->where($saleQuery) . SaleRows::NEWEST . ' LIMIT ? OFFSET ?';
        $sales = [];

        foreach ($this->database->fetchAll($sql, [...$this->values($saleQuery), $limit, $offset]) as $row) {
            $sales[] = $this->saleRows->sale($row);
        }

        return $sales;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<string> The logins of the salespeople and sales managers, in order.
     */
    public function sellers(): array
    {
        $sellers = [];

        foreach ($this->database->fetchAll(self::SELLERS, []) as $row) {
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
     * @throws QueryException When the statement fails.
     *
     * @return bool Whether it was saved; false when its vehicle, customer or salesperson is missing.
     */
    public function save(SeedSale $seedSale): bool
    {
        $values = [$seedSale->price, $seedSale->daysOnLot, $seedSale->vin, $seedSale->customer, $seedSale->salesperson];

        return $this->database->execute(self::UPSERT, $values) === 1;
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     *
     * @return string Its condition, a `?` for each value; empty when it keeps every sale.
     */
    private function where(SaleQuery $saleQuery): string
    {
        $conditions = [];

        if ($saleQuery->month !== '') {
            $conditions[] = "to_char(s.sold_on, 'YYYY-MM') = ?";
        }

        if ($saleQuery->salesperson !== '') {
            $conditions[] = 'u.email = ?';
        }

        return $conditions === []
            ? ''
            : ' WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * @param SaleQuery $saleQuery What the list was asked for.
     *
     * @return list<string> The values for `where()`'s `?`s, in order.
     */
    private function values(SaleQuery $saleQuery): array
    {
        $values = [];

        if ($saleQuery->month !== '') {
            $values[] = $saleQuery->month;
        }

        if ($saleQuery->salesperson !== '') {
            $values[] = $saleQuery->salesperson;
        }

        return $values;
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
