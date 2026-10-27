<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Customers\Entity\Purchase;
use InvalidArgumentException;

/**
 * The plugin type through which the module that keeps sales tells a customer's pages what the customer bought (#7).
 * Customers cannot require that module, which requires customers, so the module registers its implementation with
 * `->plugin(CustomerPurchases::class, …)` and `CustomerServices` takes every one with `#[Tagged]`. The generated
 * provider builds a plugin with no arguments, so the entity store comes with each call.
 */
interface CustomerPurchases
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param int         $customerId  A customer's id.
     *
     * @throws QueryException           When the purchases cannot be read.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return list<Purchase> What the customer bought, newest first.
     */
    public function purchases(EntityStore $entityStore, int $customerId): array;
}
