<?php

declare(strict_types=1);

namespace App\Finance\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use InvalidArgumentException;

/**
 * The plugin type through which the module that keeps the dealership's profile gives its default APR (#26), as staff's
 * `DealershipNames` gives its name: settings registers its implementation with `->plugin(FinanceRates::class, …)`, and
 * `DealershipRates` takes every one with `#[Tagged]`. The generated provider builds a plugin with no arguments, so the
 * entity store comes with each call.
 */
interface FinanceRates
{
    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @throws QueryException           When the APR cannot be read.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return int The dealership's default APR as its profile has it now, in basis points (690 is 6.90%).
     */
    public function apr(EntityStore $entityStore): int;
}
