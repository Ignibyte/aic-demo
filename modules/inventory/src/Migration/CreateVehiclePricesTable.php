<?php

declare(strict_types=1);

namespace App\Inventory\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The list price changes of each vehicle (#31): `vehicle_prices`, a row each time a save changes a vehicle's list
 * price, the price it had and the price it took, on the database's day. Its rows go with their vehicle, and they are
 * read by vehicle, newest first, so `vehicle_id` is indexed.
 */
final readonly class CreateVehiclePricesTable implements Migration
{
    /**
     * @return string Its version, after the vehicles' location.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_04_000001_create_vehicle_prices';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('vehicle_prices')
            ->identity('id')
            ->bigInteger('vehicle_id')
            ->integer('from_price')
            ->integer('to_price')
            ->date('changed_on')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->index(['vehicle_id'])
            ->foreignKey('vehicle_id', 'vehicles', 'id', OnDelete::Cascade);
    }
}
