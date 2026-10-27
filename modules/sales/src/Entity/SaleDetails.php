<?php

declare(strict_types=1);

namespace App\Sales\Entity;

/**
 * What a sale form that passed its checks records: the vehicle, the customer and the salesperson by their ids, the
 * price and the day.
 */
final readonly class SaleDetails
{
    /**
     * @param int    $vehicleId     The vehicle's id.
     * @param int    $customerId    The customer's id.
     * @param int    $salespersonId The salesperson's id, a member of staff.
     * @param int    $price         The sale price, in whole dollars.
     * @param string $soldOn        The day it was sold, `YYYY-MM-DD`.
     */
    public function __construct(
        public int $vehicleId,
        public int $customerId,
        public int $salespersonId,
        public int $price,
        public string $soldOn,
    ) {
    }
}
