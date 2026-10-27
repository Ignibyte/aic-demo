<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * A vehicle's recorded sale as its page shows it: when it was sold, to whom, by whom and for how much, with the paths
 * to the sale and to the customer. The module that keeps sales gives it through a `VehicleSales` plugin.
 */
final readonly class VehicleSale
{
    /**
     * @param string $soldOn       The day it was sold, `YYYY-MM-DD`.
     * @param string $path         The sale's page.
     * @param string $customer     The customer's name.
     * @param string $customerPath The customer's page.
     * @param string $salesperson  The salesperson's name.
     * @param int    $price        The sale price, in whole dollars.
     */
    public function __construct(
        public string $soldOn,
        public string $path,
        public string $customer,
        public string $customerPath,
        public string $salesperson,
        public int $price,
    ) {
    }
}
