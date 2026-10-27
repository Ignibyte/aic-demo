<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Dashboard\Entity\Figure;
use App\Dashboard\Entity\MonthSales;
use App\Dashboard\Entity\TileFigures;

/**
 * The dashboard's figures, read from the vehicles, the sales and the staff. Every query counts against the database's
 * `current_date`, so no date is worked out in PHP: "this month" is its calendar month, and "the last twelve months"
 * start on the first day of the month eleven before it.
 */
final readonly class DashboardRepository
{
    /**
     * The first day of the last twelve months.
     */
    private const string FIRST_MONTH = "(date_trunc('month', current_date) - interval '11 months')";

    /**
     * A sale in the database's current month.
     */
    private const string THIS_MONTH = "to_char(sold_on, 'YYYY-MM') = to_char(current_date, 'YYYY-MM')";

    /**
     * A sale in the month before it.
     */
    private const string LAST_MONTH = "to_char(sold_on, 'YYYY-MM') = to_char(current_date - interval '1 month', 'YYYY-MM')";

    /**
     * The stock, the vehicles not sold and their average days on the lot; with this month's and last month's sales and
     * revenue; in one row.
     */
    private const string TILES = "SELECT count(*) FILTER (WHERE status = 'in_stock')::integer AS in_stock,"
        . " count(*) FILTER (WHERE status = 'on_hold')::integer AS on_hold,"
        . " count(*) FILTER (WHERE status <> 'sold')::integer AS not_sold,"
        . " coalesce(round(avg(current_date - added_on) FILTER (WHERE status <> 'sold')), 0)::integer AS days,"
        . ' (SELECT count(*) FROM sales WHERE ' . self::THIS_MONTH . ')::integer AS sales,'
        . ' (SELECT count(*) FROM sales WHERE ' . self::LAST_MONTH . ')::integer AS last_sales,'
        . ' (SELECT coalesce(sum(price), 0) FROM sales WHERE ' . self::THIS_MONTH . ')::integer AS revenue,'
        . ' (SELECT coalesce(sum(price), 0) FROM sales WHERE ' . self::LAST_MONTH . ')::integer AS last_revenue'
        . ' FROM vehicles';

    /**
     * Each of the last twelve months, the oldest first, with its names and its count of sales, 0 for none.
     */
    private const string MONTHS = "SELECT to_char(m.first_day, 'Mon') AS label,"
        . ' CASE WHEN m.first_day = ' . self::FIRST_MONTH . " OR to_char(m.first_day, 'MM') = '01'"
        . " THEN to_char(m.first_day, 'YYYY') ELSE '' END AS note,"
        . " to_char(m.first_day, 'FMMonth YYYY') AS name, count(s.id)::integer AS sales"
        . ' FROM generate_series(' . self::FIRST_MONTH . ", date_trunc('month', current_date), interval '1 month') AS m(first_day)"
        . " LEFT JOIN sales s ON to_char(s.sold_on, 'YYYY-MM') = to_char(m.first_day, 'YYYY-MM')"
        . ' GROUP BY m.first_day ORDER BY m.first_day';

    /**
     * The vehicles not sold, per make, the most first.
     */
    private const string MAKES = "SELECT make AS label, count(*)::integer AS amount FROM vehicles WHERE status <> 'sold'"
        . ' GROUP BY make ORDER BY amount DESC, make';

    /**
     * Each salesperson's revenue over the last twelve months, the most first.
     */
    private const string SALESPEOPLE = 'SELECT u.name AS label, sum(s.price)::integer AS amount'
        . ' FROM sales s JOIN users u ON u.id = s.salesperson_id WHERE s.sold_on >= ' . self::FIRST_MONTH . '::date'
        . ' GROUP BY u.id, u.name ORDER BY amount DESC, u.name';

    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return TileFigures The stat tiles' figures.
     */
    public function tiles(): TileFigures
    {
        $row = $this->database->fetchOne(self::TILES, []);

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
     * @throws QueryException When the query fails.
     *
     * @return list<MonthSales> The last twelve months, the oldest first.
     */
    public function months(): array
    {
        $months = [];

        foreach ($this->database->fetchAll(self::MONTHS, []) as $row) {
            $months[] = $this->month($row);
        }

        return $months;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Figure> The vehicles not sold per make, the most first, then by make.
     */
    public function makes(): array
    {
        return $this->figures(self::MAKES);
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Figure> Each salesperson's revenue over the last twelve months in whole dollars, the most first, then
     *                      by name.
     */
    public function salespeople(): array
    {
        return $this->figures(self::SALESPEOPLE);
    }

    /**
     * @param string $sql A query of `label` and `amount`.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Figure> Its rows.
     */
    private function figures(string $sql): array
    {
        $figures = [];

        foreach ($this->database->fetchAll($sql, []) as $row) {
            $figures[] = $this->figure($row);
        }

        return $figures;
    }

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `fetchAll()` list
     * has no type to it, as `SaleRows` notes. No AIC ticket carries this yet.
     *
     * @param Row $row A row of `MONTHS`.
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
     * @param Row $row A row of `label` and `amount`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Figure The bar.
     */
    private function figure(Row $row): Figure
    {
        return new Figure($row->string('label'), $row->int('amount'));
    }
}
