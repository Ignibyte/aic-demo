<?php

declare(strict_types=1);

namespace App\Inventory\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The vehicles on the lot: an identity key, the VIN (17 characters, one row per VIN), what the vehicle is, its
 * mileage and colour, its list price and cost in whole dollars, its status (VehicleStatus's values), the date it came
 * onto the lot, and when the row was made and last changed.
 */
final readonly class CreateVehiclesTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_27_000002_create_vehicles';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('vehicles')
            ->identity('id')
            ->string('vin', 17)
            ->integer('year')
            ->string('make', 40)
            ->string('model', 60)
            ->string('trim', 60)
            ->string('body_style', 30)
            ->integer('mileage')
            ->string('exterior_colour', 30)
            ->integer('list_price')
            ->integer('cost')
            ->string('status', 10)
            ->date('added_on')
            ->timestampTz('created_at')
            ->timestampTz('updated_at')
            ->primary(['id'])
            ->unique(['vin']);
    }
}
