<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\VehicleSale;
use InvalidArgumentException;

/**
 * The plugin type through which the module that keeps sales tells a vehicle's page about its sale (#7). Inventory
 * cannot require that module, which requires inventory, so the module registers its implementation with
 * `->plugin(VehicleSales::class, …)` and `VehicleServices` takes every one with `#[Tagged]`. The generated provider
 * builds a plugin with no arguments, so the entity store comes with each call.
 */
interface VehicleSales
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param string      $vin         A vehicle's VIN.
     *
     * @throws QueryException           When the sale cannot be read.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return VehicleSale|null Its recorded sale; null when it has none.
     */
    public function sale(EntityStore $entityStore, string $vin): ?VehicleSale;

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @return string The path of the form that records its sale.
     */
    public function recordPath(string $vin): string;
}
