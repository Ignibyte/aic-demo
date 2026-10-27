<?php

declare(strict_types=1);

namespace App\Customers\Form;

/**
 * What the customers list was asked for, each value already checked: a value that was missing or malformed is its
 * default here.
 */
final readonly class CustomerQuery
{
    /**
     * @param string $search The search text over name, email and phone, trimmed; empty for none.
     * @param int    $page   The page asked for, from 1.
     */
    public function __construct(
        public string $search,
        public int $page,
    ) {
    }
}
