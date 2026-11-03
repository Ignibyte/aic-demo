<?php

declare(strict_types=1);

namespace App\Finance\Service;

use function intdiv;
use function max;
use function number_format;
use function round;

/**
 * The calculator's arithmetic on the server (#26), for its first figures and its example payments: the monthly payment
 * of an amortised loan, `financed * rate * growth / (growth - 1)` with `growth` the monthly rate's compound over the
 * term, the same formula the browser's signals use. An APR is in basis points (690 is 6.90%) and money in whole
 * dollars or cents, so no figure travels as a float.
 */
final readonly class Payments
{
    /**
     * The APR a dealership quotes until it sets its own: 6.90%.
     */
    public const int STANDARD_APR = 690;

    /**
     * @param int $financed       The amount financed, in whole dollars.
     * @param int $aprBasisPoints The APR, in basis points.
     * @param int $months         The term, in months, at least 1.
     *
     * @return float The monthly payment, in dollars, unrounded; 0 when nothing is financed.
     */
    public function monthly(int $financed, int $aprBasisPoints, int $months): float
    {
        if ($financed <= 0) {
            return 0.0;
        }

        if ($aprBasisPoints <= 0) {
            return (float) $financed / (float) $months;
        }

        $rate = (float) $aprBasisPoints / 120_000.0;
        $growth = (1.0 + $rate) ** (float) $months;

        return (float) $financed * $rate * $growth / ($growth - 1.0);
    }

    /**
     * @param int $financed       The amount financed, in whole dollars.
     * @param int $aprBasisPoints The APR, in basis points.
     * @param int $months         The term, in months, at least 1.
     *
     * @return int The total interest over the term, in cents.
     */
    public function interest(int $financed, int $aprBasisPoints, int $months): int
    {
        $total = $this->monthly($financed, $aprBasisPoints, $months) * (float) $months - (float) max(0, $financed);

        return max(0, $this->cents($total));
    }

    /**
     * @param float $dollars An amount in dollars.
     *
     * @return int The same amount in cents, rounded half away from zero.
     */
    public function cents(float $dollars): int
    {
        return (int) round($dollars * 100.0);
    }

    /**
     * @param int $cents An amount in cents, not below 0.
     *
     * @return string It written as US dollars with cents, such as `$1,234.56`.
     */
    public function dollars(int $cents): string
    {
        return '$' . number_format(intdiv($cents, 100)) . $this->hundredths($cents % 100);
    }

    /**
     * @param int $basisPoints A rate in basis points, not below 0.
     *
     * @return string It written as a percentage with two decimals and no sign, such as `6.90`.
     */
    public function percent(int $basisPoints): string
    {
        return intdiv($basisPoints, 100) . $this->hundredths($basisPoints % 100);
    }

    /**
     * @param int $hundredths A number from 0 to 99.
     *
     * @return string It as two decimals after a point, such as `.05`.
     */
    private function hundredths(int $hundredths): string
    {
        return ($hundredths < 10 ? '.0' : '.') . $hundredths;
    }
}
