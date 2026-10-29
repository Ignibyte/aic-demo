<?php

declare(strict_types=1);

namespace App\Reports\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `report_export_states` view (#21): each export with whether seven days have passed since it was asked for, by
 * the database's `now()`, so neither the download nor the prune works out a time in PHP.
 */
final readonly class CreateReportExportsView implements Migration
{
    /**
     * Each export, and whether it is past its seven days.
     */
    private const string STATES = 'SELECT id, user_id, report, from_day, to_day, status,'
        . " (created_at < now() - interval '7 days') AS expired FROM report_exports";

    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000007_create_report_exports_view';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view('report_export_states', self::STATES);
    }
}
