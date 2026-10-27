<?php

declare(strict_types=1);

namespace App\Dashboard\Entity;

/**
 * The stat tiles' figures, counted against the database's today: the stock, this month's and last month's sales and
 * revenue in whole dollars, and the days the vehicles not sold have been on the lot.
 */
final readonly class TileFigures
{
    /**
     * @param int $inStock     Vehicles in stock.
     * @param int $onHold      Vehicles on hold.
     * @param int $sales       Sales this month.
     * @param int $lastSales   Sales last month.
     * @param int $revenue     This month's sale prices, summed.
     * @param int $lastRevenue Last month's sale prices, summed.
     * @param int $notSold     Vehicles not sold.
     * @param int $days        Their average days on the lot, rounded; 0 when there are none.
     */
    public function __construct(
        public int $inStock,
        public int $onHold,
        public int $sales,
        public int $lastSales,
        public int $revenue,
        public int $lastRevenue,
        public int $notSold,
        public int $days,
    ) {
    }
}
