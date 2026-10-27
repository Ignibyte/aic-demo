<?php

declare(strict_types=1);

namespace App\Dashboard\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The dashboard's reports as views (#14, AIC #191), the one place the dashboard writes SQL: the stat tiles' figures,
 * the last twelve months' sales, the vehicles not sold per make and each salesperson's revenue. They read the tables of
 * the modules the dashboard requires and count against the database's `current_date`, so no date is worked out in PHP:
 * "this month" is its calendar month, and "the last twelve months" start on the first day of the month eleven before
 * it. Each view has a column it holds unique, its entity type's key; the queries give the order.
 */
final readonly class CreateDashboardViews implements Migration
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
     * One row, keyed by the day it counts on: the stock, the vehicles not sold and their average days on the lot; with
     * this month's and last month's sales and revenue.
     */
    private const string TILES = "SELECT current_date AS today, count(*) FILTER (WHERE status = 'in_stock')::integer AS in_stock,"
        . " count(*) FILTER (WHERE status = 'on_hold')::integer AS on_hold,"
        . " count(*) FILTER (WHERE status <> 'sold')::integer AS not_sold,"
        . " coalesce(round(avg(current_date - added_on) FILTER (WHERE status <> 'sold')), 0)::integer AS days,"
        . ' (SELECT count(*) FROM sales WHERE ' . self::THIS_MONTH . ')::integer AS sales,'
        . ' (SELECT count(*) FROM sales WHERE ' . self::LAST_MONTH . ')::integer AS last_sales,'
        . ' (SELECT coalesce(sum(price), 0) FROM sales WHERE ' . self::THIS_MONTH . ')::integer AS revenue,'
        . ' (SELECT coalesce(sum(price), 0) FROM sales WHERE ' . self::LAST_MONTH . ')::integer AS last_revenue'
        . ' FROM vehicles';

    /**
     * Each of the last twelve months, keyed by its first day, with its names and its count of sales, 0 for none.
     */
    private const string MONTHS = "SELECT m.first_day::date AS first_day, to_char(m.first_day, 'Mon') AS label,"
        . ' CASE WHEN m.first_day = ' . self::FIRST_MONTH . " OR to_char(m.first_day, 'MM') = '01'"
        . " THEN to_char(m.first_day, 'YYYY') ELSE '' END AS note,"
        . " to_char(m.first_day, 'FMMonth YYYY') AS name, count(s.id)::integer AS sales"
        . ' FROM generate_series(' . self::FIRST_MONTH . ", date_trunc('month', current_date), interval '1 month') AS m(first_day)"
        . " LEFT JOIN sales s ON to_char(s.sold_on, 'YYYY-MM') = to_char(m.first_day, 'YYYY-MM')"
        . ' GROUP BY m.first_day';

    /**
     * The vehicles not sold, per make, keyed by the make.
     */
    private const string MAKES = "SELECT make, count(*)::integer AS amount FROM vehicles WHERE status <> 'sold' GROUP BY make";

    /**
     * Each salesperson's revenue over the last twelve months, keyed by their id.
     */
    private const string SALESPEOPLE = 'SELECT u.id AS salesperson_id, u.name, sum(s.price)::integer AS amount'
        . ' FROM sales s JOIN users u ON u.id = s.salesperson_id WHERE s.sold_on >= ' . self::FIRST_MONTH . '::date'
        . ' GROUP BY u.id, u.name';

    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_27_000006_create_dashboard_views';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When a view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('dashboard_tiles', self::TILES);
        $schema->view('dashboard_months', self::MONTHS);
        $schema->view('dashboard_makes', self::MAKES);
        $schema->view('dashboard_salespeople', self::SALESPEOPLE);
    }
}
