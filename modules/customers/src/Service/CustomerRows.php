<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Customers\Entity\Customer;
use App\Customers\Entity\CustomerDetails;

use function substr;

/**
 * A row of `customers` read into a `Customer`, and a customer's details as the values the entity store saves by field,
 * for both repositories: the list's and the one that writes a customer at a time.
 */
final readonly class CustomerRows
{
    /**
     * The day it was added is the date that leads `created_at`'s text, which Postgres writes `YYYY-MM-DD hh:mm:ss…` in
     * the session's time zone.
     *
     * @param Row $row A row of the `customers` entity type.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Customer The customer.
     */
    public function customer(Row $row): Customer
    {
        $customerDetails = new CustomerDetails(
            $row->string('name'),
            $row->string('email'),
            $row->string('phone'),
            $row->string('address'),
            $row->string('notes'),
        );

        return new Customer($row->int('id'), $customerDetails, substr($row->string('created_at'), 0, 10));
    }

    /**
     * @param CustomerDetails $customerDetails A customer's details.
     *
     * @return array<string, string> Its values by field, every writable field of `CustomerTable`'s.
     */
    public function values(CustomerDetails $customerDetails): array
    {
        return [
            'address' => $customerDetails->address,
            'email' => $customerDetails->email,
            'name' => $customerDetails->name,
            'notes' => $customerDetails->notes,
            'phone' => $customerDetails->phone,
        ];
    }
}
