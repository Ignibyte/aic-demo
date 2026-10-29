<?php

declare(strict_types=1);

namespace App\Reports\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The views the reports page reads (#21), the one place it writes SQL. Every day is text, `YYYY-MM-DD`, so a range
 * compares as text and a malformed day never reaches a cast: the calendar of good days and their months, the presets
 * the database works out from its `current_date`, and one row per sale, per vehicle and per lead, which AIC's totals
 * count and add up with the range as conditions. No date is worked out in PHP.
 */
final readonly class CreateReportRangeViews implements Migration
{
    /**
     * Every day a range may name, from 2000-01-01 to the end of next year, with its month.
     */
    private const string CALENDAR = "SELECT to_char(g.d, 'YYYY-MM-DD') AS day, to_char(g.d, 'YYYY-MM') AS month"
        . " FROM generate_series(date '2000-01-01', (date_trunc('year', current_date) + interval '2 years' - interval '1 day')::date,"
        . " interval '1 day') AS g(d)";

    /**
     * The presets, each with its first and last day.
     */
    private const string RANGES = "SELECT p.name, p.position, p.label, to_char(p.first_day, 'YYYY-MM-DD') AS from_day,"
        . " to_char(p.first_day + p.span - interval '1 day', 'YYYY-MM-DD') AS to_day FROM (VALUES"
        . " ('this-month', 1, 'This month', date_trunc('month', current_date)::date, interval '1 month'),"
        . " ('last-month', 2, 'Last month', (date_trunc('month', current_date) - interval '1 month')::date, interval '1 month'),"
        . " ('this-quarter', 3, 'This quarter', date_trunc('quarter', current_date)::date, interval '3 months'),"
        . " ('this-year', 4, 'This year', date_trunc('year', current_date)::date, interval '1 year'))"
        . ' AS p(name, position, label, first_day, span)';

    /**
     * Each sale: its day and month, its price and its salesperson, by the email they sign in with, which is unique and,
     * unlike an id, never a numeric array key.
     */
    private const string SALES = "SELECT s.id, to_char(s.sold_on, 'YYYY-MM-DD') AS sold_on, to_char(s.sold_on, 'YYYY-MM') AS month,"
        . ' s.price, u.email AS salesperson_email, u.name AS salesperson FROM sales s JOIN users u ON u.id = s.salesperson_id';

    /**
     * Each vehicle: its make, the day it came onto the lot, the day it left (its sale's; for one marked sold without a
     * sale, the day it last changed; else today) and its days on the lot between them.
     */
    private const string STOCK = "SELECT v.id, v.make, to_char(v.added_on, 'YYYY-MM-DD') AS added_on,"
        . " to_char(o.left_on, 'YYYY-MM-DD') AS left_on, (o.left_on - v.added_on)::integer AS days"
        . ' FROM vehicles v LEFT JOIN sales s ON s.vehicle_id = v.id CROSS JOIN LATERAL (SELECT CASE'
        . " WHEN s.sold_on IS NOT NULL THEN s.sold_on WHEN v.status = 'sold' THEN v.updated_at::date"
        . ' ELSE current_date END AS left_on) AS o';

    /**
     * Each lead: its kind, the day it came in, and whether its car was sold to a customer with the lead's email.
     */
    private const string LEADS = "SELECT l.id, l.kind, to_char(l.created_at::date, 'YYYY-MM-DD') AS came_on,"
        . ' EXISTS (SELECT 1 FROM sales s JOIN customers c ON c.id = s.customer_id'
        . ' WHERE s.vehicle_id = l.vehicle_id AND lower(c.email) = lower(l.email)) AS sold FROM leads l';

    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000005_create_report_range_views';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When a view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('report_calendar', self::CALENDAR);
        $schema->view('report_ranges', self::RANGES);
        $schema->view('report_sales', self::SALES);
        $schema->view('report_stock', self::STOCK);
        $schema->view('report_leads', self::LEADS);
    }
}
