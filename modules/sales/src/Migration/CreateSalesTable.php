<?php

declare(strict_types=1);

namespace App\Sales\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Ridgeway Motors' sales: an identity key, the vehicle (sold once, so unique), the customer and the salesperson, each
 * a foreign key that keeps its row while a sale names it, the price in whole dollars, the day, and when the row was
 * made; the day and the customer are indexed for the list and the customer's page.
 */
final readonly class CreateSalesTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_27_000004_create_sales';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('sales')
            ->identity('id')
            ->bigInteger('vehicle_id')
            ->bigInteger('customer_id')
            ->bigInteger('salesperson_id')
            ->integer('price')
            ->date('sold_on')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->unique(['vehicle_id'])
            ->index(['sold_on'])
            ->index(['customer_id'])
            ->foreignKey('vehicle_id', 'vehicles', 'id', OnDelete::Restrict)
            ->foreignKey('customer_id', 'customers', 'id', OnDelete::Restrict)
            ->foreignKey('salesperson_id', 'users', 'id', OnDelete::Restrict);
    }
}
