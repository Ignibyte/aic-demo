<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleDetails;
use App\Sales\Entity\SaleTable;
use App\Sales\Entity\SaleWrite;
use App\Sales\Service\SaleRows;
use InvalidArgumentException;
use Throwable;

/**
 * One sale at a time, through the entity store (#14): by its id, by its vehicle's VIN, a customer's, and the two
 * writes, each one `EntityStore::transaction()` that keeps the vehicle's status in step through inventory's
 * `VehicleStatusRepository`: recording a sale locks the vehicle and marks it sold, and voiding one puts it back in
 * stock. A transaction's closure is static and calls one method of a `SaleWriteRepository` it takes as a local, which
 * holds what it wrote in a `SaleWrite`, as AIC's own repositories write a closure.
 */
final readonly class SaleRecordRepository
{
    /**
     * @param EntityStore             $entityStore             The entity store.
     * @param VehicleStatusRepository $vehicleStatusRepository Locks and changes the vehicle's status.
     * @param SaleRows                $saleRows                The joined sales, and a row of them read into a sale.
     * @param SaleTable               $saleTable               The `sales` entity type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleStatusRepository $vehicleStatusRepository,
        private SaleRows $saleRows = new SaleRows(),
        private SaleTable $saleTable = new SaleTable(),
    ) {
    }

    /**
     * @param int $id A sale's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return Sale|null The sale with it; null when none has it.
     */
    public function find(int $id): ?Sale
    {
        return $this->first($this->saleRows->joined($this->entityStore)->where('id', $id));
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return Sale|null Its sale; null when it has none.
     */
    public function forVehicle(string $vin): ?Sale
    {
        return $this->first($this->saleRows->joined($this->entityStore)->where('v.vin', $vin));
    }

    /**
     * @param int $customerId A customer's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return list<Sale> What they bought, newest first.
     */
    public function forCustomer(int $customerId): array
    {
        $sales = [];

        foreach ($this->saleRows->newest($this->saleRows->joined($this->entityStore)->where('customer_id', $customerId))->rows() as $row) {
            $sales[] = $this->saleRows->sale($row);
        }

        return $sales;
    }

    /**
     * @param SaleDetails $saleDetails A checked sale.
     *
     * @throws Throwable When a statement fails; the transaction is rolled back first.
     *
     * @return int|null The new sale's id; null when its vehicle is gone, already sold, has a sale or is held for another
     *                  customer, and nothing changed.
     */
    public function record(SaleDetails $saleDetails): ?int
    {
        $saleWriteRepository = $this->saleWriteRepository();
        $saleWrite = new SaleWrite();
        $this->entityStore->transaction(static function () use ($saleWriteRepository, $saleDetails, $saleWrite): void {
            $saleWriteRepository->record($saleDetails, $saleWrite);
        });

        return $saleWrite->written() === 0
            ? null
            : $saleWrite->written();
    }

    /**
     * @param Sale $sale A recorded sale.
     *
     * @throws Throwable When a statement fails; the transaction is rolled back first.
     *
     * @return bool Whether it was voided and its vehicle put back in stock; false when it was gone already.
     */
    public function void(Sale $sale): bool
    {
        $saleWriteRepository = $this->saleWriteRepository();
        $saleWrite = new SaleWrite();
        $this->entityStore->transaction(static function () use ($saleWriteRepository, $sale, $saleWrite): void {
            $saleWriteRepository->void($sale, $saleWrite);
        });

        return $saleWrite->written() !== 0;
    }

    /**
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return SaleWriteRepository The writes over the store, the vehicles' statuses, their holds and the `sales` entity
     *                             type. The holds' repository is built here over the same store, so the sales plugins
     *                             that build this repository with no arguments of their own need none more.
     */
    private function saleWriteRepository(): SaleWriteRepository
    {
        $holdRepository = new HoldRepository($this->entityStore, $this->vehicleStatusRepository);

        return new SaleWriteRepository($this->entityStore, $this->vehicleStatusRepository, $holdRepository, $this->saleTable->type());
    }

    /**
     * @param EntityQuery $entityQuery A query of the joined sales.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the limit is refused, which 1 never is.
     *
     * @return Sale|null The first sale it keeps; null when it keeps none.
     */
    private function first(EntityQuery $entityQuery): ?Sale
    {
        $row = $entityQuery->limit(1)->first();

        return $row instanceof Row
            ? $this->saleRows->sale($row)
            : null;
    }
}
