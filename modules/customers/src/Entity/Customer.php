<?php

declare(strict_types=1);

namespace App\Customers\Entity;

/**
 * A customer as stored: the identity the table gave it, what staff keep on it and the day it was added; its page is
 * found by its id.
 */
final readonly class Customer
{
    /**
     * Where the customers' pages are: the list, and below it each customer's.
     */
    public const string PAGES = '/app/customers';

    /**
     * @param int             $id              Its identity.
     * @param CustomerDetails $customerDetails What staff keep on it.
     * @param string          $addedOn         The day it was added, `YYYY-MM-DD`.
     */
    public function __construct(
        public int $id,
        public CustomerDetails $customerDetails,
        public string $addedOn,
    ) {
    }

    /**
     * @return string Its page's path, `/app/customers/{id}`.
     */
    public function path(): string
    {
        return self::PAGES . '/' . $this->id;
    }
}
