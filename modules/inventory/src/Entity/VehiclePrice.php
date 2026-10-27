<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * What a vehicle costs, in whole dollars: what the lot offers it at, and what the lot paid, which the floor never sees.
 */
final readonly class VehiclePrice
{
    /**
     * @param int $list What it is offered at.
     * @param int $cost What the lot paid.
     */
    public function __construct(
        public int $list,
        public int $cost,
    ) {
    }
}
