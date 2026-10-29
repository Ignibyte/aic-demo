<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * One vehicle in the aged-stock list (#20).
 */
final readonly class AgedVehicle
{
    /**
     * @param string $title     Year, make, model and trim.
     * @param string $vin       Its VIN.
     * @param int    $days      Its days on the lot.
     * @param int    $listPrice Its list price, whole dollars.
     */
    public function __construct(
        public string $title,
        public string $vin,
        public int $days,
        public int $listPrice,
    ) {
    }
}
