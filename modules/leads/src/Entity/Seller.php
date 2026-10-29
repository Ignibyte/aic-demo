<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * A salesperson leads go to (#18): their user id, which their stored notifications carry, their email, which the
 * forms and the list's filter name them by, and their name.
 */
final readonly class Seller
{
    /**
     * @param int    $id    Their row in `users`.
     * @param string $email Their email, their login.
     * @param string $name  Their name.
     */
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
    ) {
    }
}
