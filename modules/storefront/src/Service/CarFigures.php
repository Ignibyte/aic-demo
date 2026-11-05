<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use function number_format;

/**
 * A car's price and mileage as the public pages write them, the same on its card and on its page, and its price as the
 * JSON and XML feeds write it (#37).
 */
final readonly class CarFigures
{
    /**
     * @param int $price A price, in whole dollars.
     *
     * @return string It with a dollar sign and thousands separated, such as `$28,995`.
     */
    public function price(int $price): string
    {
        return '$' . number_format($price);
    }

    /**
     * @param int $price A price, in whole dollars.
     *
     * @return string It as a decimal string, as the feeds give it, such as `28995.00`.
     */
    public function decimal(int $price): string
    {
        return $price . '.00';
    }

    /**
     * @param int $mileage A mileage.
     *
     * @return string It with thousands separated and its unit, such as `34,500 miles`.
     */
    public function mileage(int $mileage): string
    {
        return number_format($mileage) . ($mileage === 1 ? ' mile' : ' miles');
    }
}
