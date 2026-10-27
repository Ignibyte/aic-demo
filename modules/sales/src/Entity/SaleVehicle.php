<?php

declare(strict_types=1);

namespace App\Sales\Entity;

/**
 * A vehicle a sale form names by its VIN, as the form needs it: its id, whether it is sold, its list price and the
 * day it came onto the lot.
 */
final readonly class SaleVehicle
{
    /**
     * @param int    $id        Its id.
     * @param bool   $sold      Whether its status is sold.
     * @param int    $listPrice Its list price, in whole dollars.
     * @param string $addedOn   The day it came onto the lot, `YYYY-MM-DD`.
     */
    public function __construct(
        public int $id,
        public bool $sold,
        public int $listPrice,
        public string $addedOn,
    ) {
    }
}
