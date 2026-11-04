<?php

declare(strict_types=1);

namespace App\Theme\Components\CompareTable;

use AIC\Component\Contracts\Component;

/**
 * Up to three cars side by side (#33), from compare-table.phtml: a column per car, in the order the visitor chose
 * them, and a row per fact (its main photo, its title linked to its page, its year, make, model, trim, mileage, price
 * and monthly payment), under a caption saying what the payments assume. It scrolls sideways when it does not fit.
 */
final readonly class CompareTable implements Component
{
    /**
     * @param string            $id      The table's id.
     * @param string            $caption What the monthly payments assume.
     * @param list<ComparedCar> $cars    The cars, one column each.
     */
    public function __construct(
        public string $id,
        public string $caption,
        public array $cars,
    ) {
    }
}
