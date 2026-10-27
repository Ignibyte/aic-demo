<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * One vehicle on the lot, as the list shows it and the seed saves it.
 */
final readonly class Vehicle
{
    /**
     * @param string        $vin            Its VIN, 17 characters.
     * @param int           $year           Its model year.
     * @param string        $make           Its make.
     * @param string        $model          Its model.
     * @param string        $trim           Its trim.
     * @param string        $bodyStyle      Its body style.
     * @param int           $mileage        Its odometer, in miles.
     * @param string        $exteriorColour Its paint.
     * @param VehiclePrice  $price          What it is offered at and what the lot paid.
     * @param VehicleStatus $status         Where it stands.
     * @param string        $addedOn        The day it came onto the lot, `YYYY-MM-DD`.
     */
    public function __construct(
        public string $vin,
        public int $year,
        public string $make,
        public string $model,
        public string $trim,
        public string $bodyStyle,
        public int $mileage,
        public string $exteriorColour,
        public VehiclePrice $price,
        public VehicleStatus $status,
        public string $addedOn,
    ) {
    }
}
