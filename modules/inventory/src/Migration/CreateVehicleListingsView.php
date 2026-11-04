<?php

declare(strict_types=1);

namespace App\Inventory\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The cars in stock with their listing key (#33): `vehicle_listings`, every vehicle whose status is `in_stock`, with the
 * columns a `ListedVehicle` is read from and `listing_key`, the first 6 hex digits of the SHA-256 of its VIN, which is
 * the key a public slug ends in. The key is worked out here, in SQL, so the comparison can ask for up to three cars by
 * key in one read, and the VIN never leaves inventory. Its version follows the vehicles' and their price changes'.
 */
final readonly class CreateVehicleListingsView implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_04_000003_create_vehicle_listings';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     *
     * @throws InvalidArgumentException When the view's name or SELECT is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->view(
            'vehicle_listings',
            'SELECT id, vin, year, make, model, trim, body_style, mileage, exterior_colour, list_price, cost, status,'
            . " added_on, location, substr(encode(sha256(convert_to(vin, 'UTF8')), 'hex'), 1, 6) AS listing_key"
            . " FROM vehicles WHERE status = 'in_stock'",
        );
    }
}
