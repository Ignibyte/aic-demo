<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Inventory\Service\VehicleSales;
use App\Sales\Entity\Sale;
use App\Sales\Repository\SaleRecordRepository;
use InvalidArgumentException;
use Override;

use function rawurlencode;

/**
 * Inventory's `VehicleSales` plugin: a vehicle's recorded sale for its page, and the sale form that starts from it.
 * The generated provider builds it with no arguments, so it reads through the entity store each call hands it.
 */
final readonly class VehicleSaleLookup implements VehicleSales
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param string      $vin         A vehicle's VIN.
     *
     * @throws QueryException           When the sale cannot be read.
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return VehicleSale|null Its sale; null when it has none.
     */
    #[Override]
    public function sale(EntityStore $entityStore, string $vin): ?VehicleSale
    {
        $sale = (new SaleRecordRepository($entityStore, new VehicleStatusRepository($entityStore)))->forVehicle($vin);

        if (!$sale instanceof Sale) {
            return null;
        }

        return new VehicleSale($sale->soldOn, $sale->path(), $sale->customer, $sale->customerPath(), $sale->salesperson, $sale->price);
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @return string The sale form with the vehicle chosen, `/app/sales/new?vehicle={VIN}`.
     */
    #[Override]
    public function recordPath(string $vin): string
    {
        return Sale::PAGES . '/new?vehicle=' . rawurlencode($vin);
    }
}
