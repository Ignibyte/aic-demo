<?php

declare(strict_types=1);

namespace App\Dashboard\Entity;

/**
 * One row of the dashboard's "Latest sales": the sale, its day, what was sold, to whom, by whom and for how much.
 */
final readonly class LatestSale
{
    /**
     * @param int    $id          The sale's id.
     * @param string $soldOn      Its day, `YYYY-MM-DD`.
     * @param string $vehicle     The vehicle's title (`2023 Toyota Camry SE`).
     * @param string $customer    The customer's name.
     * @param string $salesperson The salesperson's name.
     * @param int    $price       The price, in whole dollars.
     */
    public function __construct(
        public int $id,
        public string $soldOn,
        public string $vehicle,
        public string $customer,
        public string $salesperson,
        public int $price,
    ) {
    }
}
