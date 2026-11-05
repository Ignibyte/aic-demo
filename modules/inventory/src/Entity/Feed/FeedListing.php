<?php

declare(strict_types=1);

namespace App\Inventory\Entity\Feed;

use App\Inventory\Entity\ListedVehicle;

/**
 * A car for sale as the XML feed for listing sites gives it (#37): the public side's `ListedVehicle` and its VIN, which
 * listing sites match a car by. It holds no cost, status, location or date, so the feed cannot show them, and it is the
 * one shape in which a VIN leaves inventory for the public side.
 */
final readonly class FeedListing
{
    /**
     * @param ListedVehicle $vehicle The car, as the public pages see it.
     * @param string        $vin     Its VIN.
     */
    public function __construct(
        public ListedVehicle $vehicle,
        public string $vin,
    ) {
    }
}
