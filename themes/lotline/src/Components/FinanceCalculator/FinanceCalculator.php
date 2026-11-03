<?php

declare(strict_types=1);

namespace App\Theme\Components\FinanceCalculator;

use AIC\Component\Contracts\Component;

/**
 * A car's payment calculator, from finance-calculator.phtml (#26): the down payment (a range and a number kept in step),
 * the term, the APR and the trade-in, and the monthly payment, the amount financed and the total interest, all worked
 * out in the browser from Tessera's signals, with no request. The server gives the signals their defaults, as whole
 * numbers, and the first figures as written. With JavaScript off the calculator stays hidden and the table of example
 * payments shows instead.
 */
final readonly class FinanceCalculator implements Component
{
    /**
     * @param int                  $price          The car's price, in whole dollars: the range's max.
     * @param int                  $down           The default down payment, in whole dollars.
     * @param int                  $term           The default term, in months.
     * @param int                  $aprBasisPoints The dealership's default APR, in hundredths of a percent (690 is 6.90%).
     * @param int                  $warnPercent    The share of the price a month above which the payment is warned.
     * @param string               $payment        The monthly payment at the defaults, as written (`$320.02`).
     * @param string               $financed       The amount financed at the defaults, as written.
     * @param string               $interest       The total interest at the defaults, as written.
     * @param string               $caption        What the example payments assume, for the table's caption.
     * @param list<FinanceExample> $examples       The example payments, one per term.
     */
    public function __construct(
        public int $price,
        public int $down,
        public int $term,
        public int $aprBasisPoints,
        public int $warnPercent,
        public string $payment,
        public string $financed,
        public string $interest,
        public string $caption,
        public array $examples,
    ) {
    }
}
