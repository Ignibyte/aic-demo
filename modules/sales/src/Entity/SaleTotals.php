<?php

declare(strict_types=1);

namespace App\Sales\Entity;

/**
 * The totals of the sales a list's filters keep: how many, the revenue (their prices) and the gross profit (each price
 * less its vehicle's cost), in whole dollars.
 */
final readonly class SaleTotals
{
    /**
     * @param int $count   How many sales.
     * @param int $revenue Their prices, summed.
     * @param int $gross   Their prices less their vehicles' costs, summed.
     */
    public function __construct(
        public int $count,
        public int $revenue,
        public int $gross,
    ) {
    }
}
