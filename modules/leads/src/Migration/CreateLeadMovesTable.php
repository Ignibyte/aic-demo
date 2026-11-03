<?php

declare(strict_types=1);

namespace App\Leads\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use Override;

/**
 * The `lead_moves` table (#30): one row each time a lead changes stage, which the board's stream reads after the id it
 * last sent. A move goes with its lead, as a lead goes with its car; the member who made it, once removed, leaves it
 * with none.
 */
final readonly class CreateLeadMovesTable implements Migration
{
    /**
     * @return string Its version, after the leads' stage.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_03_000003_create_lead_moves';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $this->keys(
            $schema->create('lead_moves')
                ->identity('id')
                ->bigInteger('lead_id')
                ->string('from_stage', 20)
                ->string('to_stage', 20)
                ->bigInteger('user_id')
                ->nullable()
                ->timestampTz('created_at'),
        );
    }

    /**
     * @param TableBuilder $tableBuilder The table, with its columns.
     */
    private function keys(TableBuilder $tableBuilder): void
    {
        $tableBuilder->primary(['id'])
            ->index(['lead_id'])
            ->foreignKey('lead_id', 'leads', 'id', OnDelete::Cascade)
            ->foreignKey('user_id', 'users', 'id', OnDelete::SetNull);
    }
}
