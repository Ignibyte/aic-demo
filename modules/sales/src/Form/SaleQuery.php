<?php

declare(strict_types=1);

namespace App\Sales\Form;

/**
 * What the sales list was asked for, each value already checked: a value that was missing, malformed or not one the
 * list offers is its default here.
 */
final readonly class SaleQuery
{
    /**
     * @param string $month       The month asked for, `YYYY-MM`, one that has sales; empty for every month.
     * @param string $salesperson The login of the member of staff asked for; empty for everyone.
     * @param int    $page        The page asked for, from 1.
     */
    public function __construct(
        public string $month,
        public string $salesperson,
        public int $page,
    ) {
    }
}
