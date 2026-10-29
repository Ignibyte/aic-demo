<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * A vehicle for sale as the public side sees it (#17): what it is, its mileage and its list price, with its row's id,
 * which its photos refer to, and its key, which its public slug ends with. It holds no VIN and no cost, so nothing that
 * reads it can show them.
 */
final readonly class ListedVehicle
{
    /**
     * @param int    $id             Its row's id.
     * @param string $key            The first 6 hex digits of the SHA-256 of its VIN.
     * @param int    $year           Its model year.
     * @param string $make           Its make.
     * @param string $model          Its model.
     * @param string $trim           Its trim.
     * @param string $bodyStyle      Its body style.
     * @param int    $mileage        Its odometer, in miles.
     * @param string $exteriorColour Its paint.
     * @param int    $price          What it is offered at, in whole dollars.
     */
    public function __construct(
        public int $id,
        public string $key,
        public int $year,
        public string $make,
        public string $model,
        public string $trim,
        public string $bodyStyle,
        public int $mileage,
        public string $exteriorColour,
        public int $price,
    ) {
    }

    /**
     * @return string What the public calls it: its year, make and model.
     */
    public function title(): string
    {
        return $this->year . ' ' . $this->make . ' ' . $this->model;
    }
}
