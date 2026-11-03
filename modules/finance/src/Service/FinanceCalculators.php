<?php

declare(strict_types=1);

namespace App\Finance\Service;

use App\Theme\Components\FinanceCalculator\FinanceCalculator;
use App\Theme\Components\FinanceCalculator\FinanceExample;

use function intdiv;
use function max;
use function number_format;

/**
 * Builds a car's payment calculator (#26), the theme's `FinanceCalculator`, for storefront's car page and inventory's
 * vehicle page: its signals' defaults (the price, a down payment of 10% of it rounded down to $100, a 60-month term
 * and the dealership's APR), its first figures at those defaults, and the example payments over 36, 48 and 60 months
 * that show with JavaScript off. The browser works out every later figure itself.
 */
final readonly class FinanceCalculators
{
    /**
     * The term the calculator starts at, in months.
     */
    private const int TERM = 60;

    /**
     * The terms of the example payments, in months.
     */
    private const array EXAMPLE_TERMS = [36, 48, 60];

    /**
     * The share of the price a month, in percent, above which the payment is warned.
     */
    private const int WARN_PERCENT = 3;

    /**
     * @param Payments $payments The arithmetic and the dollars as written.
     */
    public function __construct(
        private Payments $payments = new Payments(),
    ) {
    }

    /**
     * @param int $price          The car's price, in whole dollars.
     * @param int $aprBasisPoints The dealership's default APR, in basis points.
     *
     * @return FinanceCalculator Its calculator.
     */
    public function calculator(int $price, int $aprBasisPoints): FinanceCalculator
    {
        $price = max(0, $price);
        $apr = max(0, $aprBasisPoints);
        $down = intdiv($price, 1_000) * 100;
        $financed = $price - $down;

        return new FinanceCalculator(
            $price,
            $down,
            self::TERM,
            $apr,
            self::WARN_PERCENT,
            $this->payment($financed, $apr, self::TERM),
            $this->payments->dollars($financed * 100),
            $this->interest($financed, $apr, self::TERM),
            $this->caption($down, $apr),
            $this->examples($financed, $apr),
        );
    }

    /**
     * @param int $financed       The amount financed at the default down payment, in whole dollars.
     * @param int $aprBasisPoints The APR, in basis points.
     *
     * @return list<FinanceExample> The example payments, one per example term.
     */
    private function examples(int $financed, int $aprBasisPoints): array
    {
        $examples = [];

        foreach (self::EXAMPLE_TERMS as $months) {
            $payment = $this->payment($financed, $aprBasisPoints, $months);
            $examples[] = new FinanceExample($months, $payment, $this->interest($financed, $aprBasisPoints, $months));
        }

        return $examples;
    }

    /**
     * @param int $down           The default down payment, in whole dollars.
     * @param int $aprBasisPoints The APR, in basis points.
     *
     * @return string What the example payments assume.
     */
    private function caption(int $down, int $aprBasisPoints): string
    {
        $rate = $this->payments->percent($aprBasisPoints);

        return 'With $' . number_format($down) . ' down and no trade-in, at ' . $rate . '% APR, before tax and fees.';
    }

    /**
     * @param int $financed       The amount financed, in whole dollars.
     * @param int $aprBasisPoints The APR, in basis points.
     * @param int $months         The term, in months.
     *
     * @return string The total interest over the term, as written.
     */
    private function interest(int $financed, int $aprBasisPoints, int $months): string
    {
        return $this->payments->dollars($this->payments->interest($financed, $aprBasisPoints, $months));
    }

    /**
     * @param int $financed       The amount financed, in whole dollars.
     * @param int $aprBasisPoints The APR, in basis points.
     * @param int $months         The term, in months.
     *
     * @return string The monthly payment, as written.
     */
    private function payment(int $financed, int $aprBasisPoints, int $months): string
    {
        return $this->payments->dollars($this->payments->cents($this->payments->monthly($financed, $aprBasisPoints, $months)));
    }
}
