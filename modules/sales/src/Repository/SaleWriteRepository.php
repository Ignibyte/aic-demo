<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleDetails;
use App\Sales\Entity\SaleWrite;
use InvalidArgumentException;

/**
 * A sale's two writes as they run inside `EntityStore::transaction()` (#14): recording a sale and voiding one, each
 * holding what it wrote in a `SaleWrite`. `SaleRecordRepository` builds one per transaction and its static closure
 * calls one method on it, the shape the native compiler takes for a closure (framework/docs/native.md). A vehicle on
 * hold (#20) sells only to the hold's customer, asked under the vehicle's lock, and that sale ends the hold.
 */
final readonly class SaleWriteRepository
{
    /**
     * @param EntityStore             $entityStore             The entity store.
     * @param VehicleStatusRepository $vehicleStatusRepository Locks and changes the vehicle's status, inventory's.
     * @param HoldRepository          $holdRepository          The vehicle's hold, holds'.
     * @param EntityType              $entityType              The `sales` entity type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleStatusRepository $vehicleStatusRepository,
        private HoldRepository $holdRepository,
        private EntityType $entityType,
    ) {
    }

    /**
     * Locks the vehicle, gives up when it is gone or sold or held for another customer, inserts the sale unless its
     * vehicle has one, marks the vehicle sold and ends its hold, holding the new sale's id.
     *
     * @param SaleDetails $saleDetails A checked sale.
     * @param SaleWrite   $saleWrite   Holds the new sale's id.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     */
    public function record(SaleDetails $saleDetails, SaleWrite $saleWrite): void
    {
        if (!$this->vehicleStatusRepository->lockSellable($saleDetails->vehicleId)) {
            return;
        }

        if ($this->holdRepository->heldForAnother($saleDetails->vehicleId, $saleDetails->customerId)) {
            return;
        }

        $id = $this->entityStore->insertIfAbsent($this->entityType, [
            'customer_id' => $saleDetails->customerId,
            'price' => $saleDetails->price,
            'salesperson_id' => $saleDetails->salespersonId,
            'sold_on' => $saleDetails->soldOn,
            'vehicle_id' => $saleDetails->vehicleId,
        ], 'vehicle_id');

        if ($id === null) {
            return;
        }

        $this->vehicleStatusRepository->mark($saleDetails->vehicleId, VehicleStatus::Sold);
        $this->holdRepository->endForSale($saleDetails->vehicleId);
        $saleWrite->hold($id);
    }

    /**
     * Locks the sale, deletes it and puts its vehicle back in stock, holding the vehicle's id; nothing when the sale is
     * gone.
     *
     * @param Sale      $sale      A recorded sale.
     * @param SaleWrite $saleWrite Holds the voided sale's vehicle.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     */
    public function void(Sale $sale, SaleWrite $saleWrite): void
    {
        $row = $this->entityStore->query($this->entityType)->where('id', $sale->id)->forUpdate()->first();

        if (!$row instanceof Row) {
            return;
        }

        $vehicleId = $row->int('vehicle_id');
        $this->entityStore->query($this->entityType)->where('id', $sale->id)->changes()->delete();
        $this->vehicleStatusRepository->mark($vehicleId, VehicleStatus::InStock);
        $saleWrite->hold($vehicleId);
    }
}
