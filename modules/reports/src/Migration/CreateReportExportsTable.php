<?php

declare(strict_types=1);

namespace App\Reports\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The reports' CSV exports (#21): an identity key, the member who asked (their exports go with them), the report, the
 * range's first and last day as text, `queued` or `ready`, and when it was asked for; indexed on the member, whose
 * exports the download looks among. The file itself is `var/exports/{id}.csv`.
 */
final readonly class CreateReportExportsTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000006_create_report_exports';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('report_exports')
            ->identity('id')
            ->bigInteger('user_id')
            ->string('report', 20)
            ->string('from_day', 10)
            ->string('to_day', 10)
            ->string('status', 10)
            ->timestampTz('created_at')
            ->primary(['id'])
            ->index(['user_id'])
            ->foreignKey('user_id', 'users', 'id', OnDelete::Cascade);
    }
}
