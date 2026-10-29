<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * The car a lead is about (#18), as staff see it on the lead's page: its title, its VIN, which names its inventory
 * page, and its list price in whole dollars.
 */
final readonly class LeadCar
{
    /**
     * @param string $title     Year, make, model and trim.
     * @param string $vin       Its VIN.
     * @param int    $listPrice Its list price, whole dollars.
     */
    public function __construct(
        public string $title,
        public string $vin,
        public int $listPrice,
    ) {
    }
}
