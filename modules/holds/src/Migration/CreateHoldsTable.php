<?php

declare(strict_types=1);

namespace App\Holds\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use Override;

/**
 * The holds (#20): a vehicle kept for a customer, who placed it and why, its status (`HoldStatus`'s values) and when
 * it was placed and last changed. A vehicle deleted takes its holds with it; a customer or a member of staff deleted
 * leaves the hold with none.
 */
final readonly class CreateHoldsTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000002_create_holds';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $this->keys($this->columns($schema->create('holds')));
    }

    /**
     * @param TableBuilder $tableBuilder The new table.
     *
     * @return TableBuilder It, with its columns.
     */
    private function columns(TableBuilder $tableBuilder): TableBuilder
    {
        return $tableBuilder->identity('id')
            ->bigInteger('vehicle_id')
            ->bigInteger('customer_id')
            ->nullable()
            ->bigInteger('placed_by')
            ->nullable()
            ->text('note')
            ->string('status', 10)
            ->timestampTz('created_at')
            ->timestampTz('updated_at');
    }

    /**
     * The keys, then the indexes: `vehicle_id` with `status` for a vehicle's active hold, which a sale, a placement and a
     * release each ask for, and `status` for the active holds.
     *
     * @param TableBuilder $tableBuilder The table, with its columns.
     */
    private function keys(TableBuilder $tableBuilder): void
    {
        $tableBuilder->primary(['id'])
            ->foreignKey('placed_by', 'users', 'id', OnDelete::SetNull)
            ->foreignKey('customer_id', 'customers', 'id', OnDelete::SetNull)
            ->foreignKey('vehicle_id', 'vehicles', 'id', OnDelete::Cascade)
            ->index(['vehicle_id', 'status'])
            ->index(['status']);
    }
}
