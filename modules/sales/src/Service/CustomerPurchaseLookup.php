<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Customers\Entity\Purchase;
use App\Customers\Service\CustomerPurchases;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Sales\Repository\SaleRecordRepository;
use InvalidArgumentException;
use Override;

/**
 * Customers' `CustomerPurchases` plugin: what a customer bought, for their page and their delete. The generated
 * provider builds it with no arguments, so it reads through the entity store each call hands it.
 */
final readonly class CustomerPurchaseLookup implements CustomerPurchases
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param int         $customerId  A customer's id.
     *
     * @throws QueryException           When the sales cannot be read.
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return list<Purchase> What they bought, newest first.
     */
    #[Override]
    public function purchases(EntityStore $entityStore, int $customerId): array
    {
        $purchases = [];

        foreach ((new SaleRecordRepository($entityStore, new VehicleStatusRepository($entityStore)))->forCustomer($customerId) as $sale) {
            $purchases[] = new Purchase($sale->vin, $sale->vehicle, $sale->vehiclePath(), $sale->soldOn, $sale->path(), $sale->price);
        }

        return $purchases;
    }
}
