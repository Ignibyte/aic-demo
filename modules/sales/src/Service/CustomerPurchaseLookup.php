<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Purchase;
use App\Customers\Service\CustomerPurchases;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Sales\Repository\SaleRecordRepository;
use Override;

/**
 * Customers' `CustomerPurchases` plugin: what a customer bought, for their page and their delete. The generated
 * provider builds it with no arguments, so it reads through the database each call hands it.
 */
final readonly class CustomerPurchaseLookup implements CustomerPurchases
{
    /**
     * @param Database $database   The database.
     * @param int      $customerId A customer's id.
     *
     * @throws QueryException When the sales cannot be read.
     *
     * @return list<Purchase> What they bought, newest first.
     */
    #[Override]
    public function purchases(Database $database, int $customerId): array
    {
        $purchases = [];

        foreach ((new SaleRecordRepository($database, new VehicleStatusRepository($database)))->forCustomer($customerId) as $sale) {
            $purchases[] = new Purchase($sale->vin, $sale->vehicle, $sale->vehiclePath(), $sale->soldOn, $sale->path(), $sale->price);
        }

        return $purchases;
    }
}
