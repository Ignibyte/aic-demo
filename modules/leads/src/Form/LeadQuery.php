<?php

declare(strict_types=1);

namespace App\Leads\Form;

/**
 * What the leads list asks for (#18), each value checked already: a kind's value and a salesperson's email, each empty
 * for every one.
 */
final readonly class LeadQuery
{
    /**
     * @param string $kind   `inquiry` or `test_drive`; empty for both.
     * @param string $seller A salesperson's email; empty for every one.
     */
    public function __construct(
        public string $kind,
        public string $seller,
    ) {
    }
}
