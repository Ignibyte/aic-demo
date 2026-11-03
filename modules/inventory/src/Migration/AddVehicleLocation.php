<?php

declare(strict_types=1);

namespace App\Inventory\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Where on the lot each vehicle stands (#29): `vehicles.location`, a `VehicleLocation` value. AIC adds a column to a
 * table that has rows only as nullable, so null is "no location yet", which every vehicle stored before has.
 */
final readonly class AddVehicleLocation implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_03_000001_add_vehicle_location';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->alter('vehicles')
            ->addColumn('location', $schema->types()->string(20))
            ->nullable();
    }
}
