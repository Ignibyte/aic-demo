<?php

declare(strict_types=1);

namespace App\Sales\Entity;

/**
 * One of the seed's invented sales: the vehicle by VIN, the customer by email, the salesperson by login, the price,
 * and the day it was sold, a few days after the vehicle came onto the lot.
 */
final readonly class SeedSale
{
    /**
     * @param string $vin         The vehicle's VIN.
     * @param string $customer    The customer's email.
     * @param string $salesperson The salesperson's login.
     * @param int    $price       The sale price, in whole dollars.
     * @param string $soldOn      The day it was sold, `YYYY-MM-DD`.
     */
    public function __construct(
        public string $vin,
        public string $customer,
        public string $salesperson,
        public int $price,
        public string $soldOn,
    ) {
    }
}
