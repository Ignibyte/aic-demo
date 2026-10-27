<?php

declare(strict_types=1);

namespace App\Customers\Entity;

/**
 * What staff keep on a customer, as a checked form or the seed gives it: the name, the email (trimmed and in small
 * letters), and the phone, address and notes, each empty when not given.
 */
final readonly class CustomerDetails
{
    /**
     * @param string $name    The customer's name.
     * @param string $email   Their email, unique among customers.
     * @param string $phone   Their phone; empty for none.
     * @param string $address Their address; empty for none.
     * @param string $notes   What staff note about them; empty for none.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $address,
        public string $notes,
    ) {
    }
}
