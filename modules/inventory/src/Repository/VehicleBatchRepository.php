<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use AIC\Storage\Application\PublicFiles;
use App\Inventory\Entity\BulkEdit;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Service\VehicleRows;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Service\VehiclePhotos;
use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * The vehicles a bulk edit acts on (#29), by their VINs, through the entity store: one transaction around the batch,
 * the batch's vehicles read `FOR UPDATE` inside it, so no other write changes them between the checks and the change,
 * and each change made to all of them by one statement. It also reads them back after the change, for the answer, and
 * reads a delete's photos before it and forgets their files after it, as a single delete does (#16).
 */
final readonly class VehicleBatchRepository
{
    /**
     * The vehicles' photos, whose files a delete forgets once it has committed.
     */
    private VehiclePhotos $vehiclePhotos;

    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param PublicFiles  $publicFiles  AIC's public files, where the photos are kept.
     * @param VehicleTable $vehicleTable The `vehicles` entity type.
     * @param VehicleRows  $vehicleRows  Reads a row into a vehicle.
     */
    public function __construct(
        private EntityStore $entityStore,
        PublicFiles $publicFiles,
        private VehicleTable $vehicleTable = new VehicleTable(),
        private VehicleRows $vehicleRows = new VehicleRows(),
    ) {
        $this->vehiclePhotos = new VehiclePhotos($entityStore, $publicFiles);
    }

    /**
     * @param Closure(): void $work The batch's reads, checks and writes, made together or not at all.
     *
     * @throws Throwable Whatever the work throws, after the transaction is rolled back.
     */
    public function transaction(Closure $work): void
    {
        $this->entityStore->transaction($work);
    }

    /**
     * @param list<string> $vins The batch's VINs, at least one.
     *
     * @throws RuntimeException         When the query fails (`QueryException`), or a stored status is no status.
     * @throws InvalidArgumentException When the list is empty, or the entity type is malformed.
     *
     * @return list<Vehicle> The vehicles that have them, by VIN, each row locked until the transaction ends.
     */
    public function locked(array $vins): array
    {
        $vehicles = [];

        // In VIN order, so two batches that share vehicles lock them in the same order and do not deadlock.
        $rows = $this->entityStore->query($this->vehicleTable->type())
            ->whereIn('vin', $vins)
            ->orderBy('vin')
            ->forUpdate()
            ->rows();

        foreach ($rows as $row) {
            $vehicles[] = $this->vehicleRows->vehicle($row);
        }

        return $vehicles;
    }

    /**
     * @param list<string> $vins The batch's VINs, at least one.
     *
     * @throws RuntimeException         When the query fails (`QueryException`), or a stored status is no status.
     * @throws InvalidArgumentException When the list is empty, or the entity type is malformed.
     *
     * @return list<Vehicle> The vehicles that have them, by VIN, as they stand now.
     */
    public function vehicles(array $vins): array
    {
        $vehicles = [];

        foreach ($this->entityStore->query($this->vehicleTable->type())->whereIn('vin', $vins)->rows() as $row) {
            $vehicles[] = $this->vehicleRows->vehicle($row);
        }

        return $vehicles;
    }

    /**
     * @param BulkEdit $bulkEdit A bulk edit checked for every one of its vehicles, confirmed when it deletes.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When it has no VIN, or the entity type is malformed.
     */
    public function apply(BulkEdit $bulkEdit): void
    {
        $entityQuery = $this->entityStore->query($this->vehicleTable->type())->whereIn('vin', $bulkEdit->vins);
        $vehicleStatus = $bulkEdit->vehicleStatus;
        $vehicleLocation = $bulkEdit->vehicleLocation;

        if ($vehicleStatus !== null) {
            $entityQuery->changes()->update(['status' => $vehicleStatus->value]);
        }

        if ($vehicleLocation !== null) {
            $entityQuery->changes()->update(['location' => $vehicleLocation->value]);
        }

        if (!$bulkEdit->deletes()) {
            return;
        }

        // A sold vehicle is kept, as a single delete keeps it; the checks refused one before this was reached.
        $entityQuery->where('status', VehicleStatus::Sold->value, Comparison::NotEquals)->changes()->delete();
    }

    /**
     * @param list<string> $vins The batch's VINs.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles or their photos cannot be read (`QueryException`).
     *
     * @return list<VehiclePhoto> Their vehicles' photos, read before a delete takes their rows by the foreign key.
     */
    public function photos(array $vins): array
    {
        $vehicleIds = $this->ids($vins);

        if ($vehicleIds === []) {
            return [];
        }

        $photos = [];

        foreach ($this->vehiclePhotos->photosOf($vehicleIds) as $vehiclePhotos) {
            foreach ($vehiclePhotos as $vehiclePhoto) {
                $photos[] = $vehiclePhoto;
            }
        }

        return $photos;
    }

    /**
     * @param list<VehiclePhoto> $vehiclePhotos The deleted vehicles' photos.
     *
     * @throws LogicException   When their entity type is malformed.
     * @throws RuntimeException When the photos holding a file's bytes cannot be counted.
     */
    public function forget(array $vehiclePhotos): void
    {
        $this->vehiclePhotos->forget($vehiclePhotos);
    }

    /**
     * @param list<string> $vins The batch's VINs.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<int> The ids of the vehicles that have them, which their photos refer to.
     */
    private function ids(array $vins): array
    {
        $vehicleIds = [];

        foreach ($this->entityStore->query($this->vehicleTable->type())->whereIn('vin', $vins)->rows() as $row) {
            $vehicleIds[] = $row->int('id');
        }

        return $vehicleIds;
    }
}
