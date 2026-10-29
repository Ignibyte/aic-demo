<?php

declare(strict_types=1);

namespace App\Leads\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use Override;

/**
 * The `leads` table (#18): one row per question or test drive a visitor sent about a car. A lead goes with its car, as
 * the car's photos do, since a car is deleted only before it is sold; a salesperson or a customer removed leaves the
 * lead with none. Staff read the open ones newest first.
 */
final readonly class CreateLeadsTable implements Migration
{
    /**
     * @return string Its version, after every table it refers to.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_28_000002_create_leads';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $this->keys($this->columns($schema->create('leads')));
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
            ->string('kind', 20)
            ->string('name', 100)
            ->string('email', 254)
            ->string('phone', 30)
            ->text('message')
            ->date('drive_on')
            ->nullable()
            ->string('drive_at', 5)
            ->nullable()
            ->bigInteger('salesperson_id')
            ->nullable()
            ->bigInteger('customer_id')
            ->nullable()
            ->string('status', 10)
            ->timestampTz('created_at');
    }

    /**
     * @param TableBuilder $tableBuilder The table, with its columns.
     */
    private function keys(TableBuilder $tableBuilder): void
    {
        $tableBuilder->primary(['id'])
            ->index(['status'])
            ->index(['vehicle_id'])
            ->foreignKey('vehicle_id', 'vehicles', 'id', OnDelete::Cascade)
            ->foreignKey('salesperson_id', 'users', 'id', OnDelete::SetNull)
            ->foreignKey('customer_id', 'customers', 'id', OnDelete::SetNull);
    }
}
