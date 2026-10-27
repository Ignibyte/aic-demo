<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use function trim;

/**
 * One vehicle on the lot, as the list and its page show it, the seed and the form save it; its page is found by its
 * VIN.
 */
final readonly class Vehicle
{
    /**
     * Where the vehicles' pages are: the list, and below it each vehicle's.
     */
    public const string PAGES = '/app/inventory';

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

    /**
     * @return string What staff call it: its year, make, model and trim.
     */
    public function title(): string
    {
        return trim($this->year . ' ' . $this->make . ' ' . $this->model . ' ' . $this->trim);
    }

    /**
     * @return string Its page's path, `/app/inventory/{VIN}`.
     */
    public function path(): string
    {
        return self::PAGES . '/' . $this->vin;
    }
}
