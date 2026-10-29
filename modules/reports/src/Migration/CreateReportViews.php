<?php

declare(strict_types=1);

namespace App\Reports\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The reports' views (#20), the one place the reports module writes SQL. They read the tables of the modules it
 * requires and count against the database's `current_date`, as the dashboard's views do, so no date is worked out in
 * PHP: the vehicles not sold with their days on the lot, and one row of the day's figures for the digest.
 */
final readonly class CreateReportViews implements Migration
{
    /**
     * Each vehicle not sold, keyed by its id, with how many days it has been on the lot.
     */
    private const string AGED_STOCK = "SELECT id, vin, trim(year || ' ' || make || ' ' || model || ' ' || trim) AS title,"
        . " list_price, (current_date - added_on)::integer AS days FROM vehicles WHERE status <> 'sold'";

    /**
     * One row, keyed by the day it counts: the sales recorded for it and their revenue, the leads that came in, by kind,
     * and the vehicles that came onto the lot.
     */
    private const string DAY = 'SELECT current_date::text AS today,'
        . ' (SELECT count(*) FROM sales WHERE sold_on = current_date)::integer AS sales,'
        . ' (SELECT coalesce(sum(price), 0) FROM sales WHERE sold_on = current_date)::integer AS revenue,'
        . ' (SELECT count(*) FROM leads WHERE created_at::date = current_date)::integer AS leads,'
        . " (SELECT count(*) FROM leads WHERE created_at::date = current_date AND kind = 'inquiry')::integer AS inquiries,"
        . " (SELECT count(*) FROM leads WHERE created_at::date = current_date AND kind = 'test_drive')::integer AS test_drives,"
        . ' (SELECT count(*) FROM vehicles WHERE added_on = current_date)::integer AS new_stock';

    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000004_create_report_views';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When a view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('report_aged_stock', self::AGED_STOCK);
        $schema->view('report_day', self::DAY);
    }
}
