<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Purchase;

/**
 * The plugin type through which the module that keeps sales tells a customer's pages what the customer bought (#7).
 * Customers cannot require that module, which requires customers, so the module registers its implementation with
 * `->plugin(CustomerPurchases::class, …)` and `CustomerServices` takes every one with `#[Tagged]`. The generated
 * provider builds a plugin with no arguments, so the database comes with each call.
 */
interface CustomerPurchases
{
    /**
     * @param Database $database   The database.
     * @param int      $customerId A customer's id.
     *
     * @throws QueryException When the purchases cannot be read.
     *
     * @return list<Purchase> What the customer bought, newest first.
     */
    public function purchases(Database $database, int $customerId): array;
}
