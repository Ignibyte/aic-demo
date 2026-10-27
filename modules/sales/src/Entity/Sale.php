<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use App\Inventory\Entity\Vehicle;

/**
 * A recorded sale as the sales pages show it: its identity, the vehicle sold (its VIN and what staff call it), the
 * customer, the salesperson, the price and the day; its page is found by its id.
 */
final readonly class Sale
{
    /**
     * Where the sales' pages are: the list, the form, and below it each sale's.
     */
    public const string PAGES = '/app/sales';

    /**
     * Where the customers' pages are.
     */
    private const string CUSTOMERS = '/app/customers';

    /**
     * @param int    $id          Its identity.
     * @param string $vin         The vehicle's VIN.
     * @param string $vehicle     What staff call the vehicle: its year, make, model and trim.
     * @param int    $customerId  The customer's id.
     * @param string $customer    The customer's name.
     * @param string $salesperson The salesperson's name.
     * @param int    $price       The sale price, in whole dollars.
     * @param string $soldOn      The day it was sold, `YYYY-MM-DD`.
     */
    public function __construct(
        public int $id,
        public string $vin,
        public string $vehicle,
        public int $customerId,
        public string $customer,
        public string $salesperson,
        public int $price,
        public string $soldOn,
    ) {
    }

    /**
     * @return string Its page's path, `/app/sales/{id}`.
     */
    public function path(): string
    {
        return self::PAGES . '/' . $this->id;
    }

    /**
     * @return string The vehicle's page, `/app/inventory/{VIN}`.
     */
    public function vehiclePath(): string
    {
        return Vehicle::PAGES . '/' . $this->vin;
    }

    /**
     * @return string The customer's page, `/app/customers/{id}`.
     */
    public function customerPath(): string
    {
        return self::CUSTOMERS . '/' . $this->customerId;
    }
}
