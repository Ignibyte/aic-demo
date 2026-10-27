<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Customers\Entity\Customer;
use App\Customers\Entity\CustomerDetails;

/**
 * A row of `customers` read into a `Customer`, and a customer's details as the values a statement saves, for both
 * repositories: the list's and the one that writes a customer at a time.
 */
final readonly class CustomerRows
{
    /**
     * The columns a customer is read from, the day it was added as text.
     */
    public const string SELECTED = "id, name, email, phone, address, notes, to_char(created_at, 'YYYY-MM-DD') AS added_on";

    /**
     * The columns its details are saved to, in `values()`'s order.
     */
    public const string SAVED = 'name, email, phone, address, notes';

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `fetchAll()` list
     * has no type to it, as `VehicleRows` found at gate:23. No AIC ticket carries this yet.
     *
     * @param Row $row A row with every column of `SELECTED`.
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

        return new Customer($row->int('id'), $customerDetails, $row->string('added_on'));
    }

    /**
     * @param CustomerDetails $customerDetails A customer's details.
     *
     * @return list<string> Its values for the `?`s of a statement naming `SAVED`, in that order.
     */
    public function values(CustomerDetails $customerDetails): array
    {
        return [
            $customerDetails->name,
            $customerDetails->email,
            $customerDetails->phone,
            $customerDetails->address,
            $customerDetails->notes,
        ];
    }
}
