<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * One change of a vehicle's list price (#31), as `vehicle_prices` keeps it: the day it changed, the price it had and the
 * price it took, in whole dollars. Exported, so the vehicle page's price history panel draws its line from them.
 */
final readonly class PricePoint
{
    /**
     * @param string $changedOn The day it changed, `YYYY-MM-DD`, the database's.
     * @param int    $oldPrice  The list price it had.
     * @param int    $newPrice  The list price it took.
     */
    public function __construct(
        public string $changedOn,
        public int $oldPrice,
        public int $newPrice,
    ) {
    }
}
