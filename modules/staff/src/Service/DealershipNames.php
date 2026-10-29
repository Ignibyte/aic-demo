<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use InvalidArgumentException;

/**
 * The plugin type through which the module that keeps the dealership's profile names the dealership on the staff pages
 * (#23). Staff cannot require that module, which requires staff, so the module registers its implementation with
 * `->plugin(DealershipNames::class, …)` and `StaffDealership` takes every one with `#[Tagged]`. The generated provider
 * builds a plugin with no arguments, so the entity store comes with each call.
 */
interface DealershipNames
{
    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @throws QueryException           When the name cannot be read.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return string The dealership's name as its profile has it now.
     */
    public function name(EntityStore $entityStore): string;
}
