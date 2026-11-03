<?php

declare(strict_types=1);

namespace App\Theme\Components\FinanceCalculator;

/**
 * One row of the calculator's example payments (#26), shown with JavaScript off: a term and what it costs, as written.
 */
final readonly class FinanceExample
{
    /**
     * @param int    $months   The term, in months.
     * @param string $payment  The monthly payment, as written (`$320.02`).
     * @param string $interest The total interest over the term, as written.
     */
    public function __construct(
        public int $months,
        public string $payment,
        public string $interest,
    ) {
    }
}
