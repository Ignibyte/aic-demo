<?php

declare(strict_types=1);

namespace App\Customers\Entity;

/**
 * A vehicle a customer bought, as the customer's page lists it: the vehicle and its page, the day of the sale and its
 * page, and the price. The module that keeps sales gives it through a `CustomerPurchases` plugin.
 */
final readonly class Purchase
{
    /**
     * @param string $vin         The vehicle's VIN.
     * @param string $vehicle     What staff call the vehicle: its year, make, model and trim.
     * @param string $vehiclePath The vehicle's page.
     * @param string $soldOn      The day it was sold, `YYYY-MM-DD`.
     * @param string $salePath    The sale's page.
     * @param int    $price       The sale price, in whole dollars.
     */
    public function __construct(
        public string $vin,
        public string $vehicle,
        public string $vehiclePath,
        public string $soldOn,
        public string $salePath,
        public int $price,
    ) {
    }
}
