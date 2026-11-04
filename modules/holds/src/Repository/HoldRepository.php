<?php

declare(strict_types=1);

namespace App\Holds\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Holds\Entity\Hold;
use App\Holds\Entity\HoldDetails;
use App\Holds\Entity\HoldStatus;
use App\Holds\Entity\HoldTable;
use App\Holds\Entity\HoldTrace;
use App\Holds\Entity\HoldWrite;
use App\Holds\Service\HoldRows;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Repository\VehicleStatusRepository;
use InvalidArgumentException;
use Throwable;

/**
 * The holds (#20), through the entity store. Placing one is one `EntityStore::transaction()` that locks the vehicle's
 * row first, as a sale does, so a hold and a sale of one vehicle cannot pass together; the vehicle's status changes
 * through inventory's `VehicleStatusRepository`. `HoldReleaseRepository` releases one on the same locks, through
 * `markReleased()`. Exported: `sales` asks whether a vehicle is held for another customer and ends
 * a hold when its vehicle is sold, both on its own transaction. The placement's closure is static and calls one public
 * method of this repository, `placeLocked()`, the shape the native compiler takes for a closure; nothing else calls it.
 * A vehicle's page lists its holds, newest first, with their version, which its leads and holds panel polls (#31).
 */
final readonly class HoldRepository
{
    /**
     * @param EntityStore             $entityStore             The entity store.
     * @param VehicleStatusRepository $vehicleStatusRepository Changes a vehicle's status, inventory's.
     * @param HoldTable               $holdTable               The holds' entity types.
     * @param HoldRows                $holdRows                Reads a view row into a hold.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleStatusRepository $vehicleStatusRepository,
        private HoldTable $holdTable = new HoldTable(),
        private HoldRows $holdRows = new HoldRows(),
    ) {
    }

    /**
     * @param HoldDetails $holdDetails A checked hold.
     *
     * @throws Throwable When a statement fails or a value does not fit its field; the transaction is rolled back first.
     *
     * @return int|null The new hold's id; null when the vehicle is gone or not in stock, and nothing changed.
     */
    public function place(HoldDetails $holdDetails): ?int
    {
        $holdRepository = $this;
        $holdWrite = new HoldWrite();
        $this->entityStore->transaction(static function () use ($holdRepository, $holdDetails, $holdWrite): void {
            $holdWrite->hold($holdRepository->placeLocked($holdDetails));
        });

        return $holdWrite->written() === 0
            ? null
            : $holdWrite->written();
    }

    /**
     * Locks the vehicle through inventory, gives up unless it is in stock, inserts the hold and marks the vehicle on hold. The lock holds
     * only inside a transaction; `place()` calls this inside its own.
     *
     * @param HoldDetails $holdDetails A checked hold.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return int The new hold's id; 0 when the vehicle is gone or not in stock.
     */
    public function placeLocked(HoldDetails $holdDetails): int
    {
        if ($this->vehicleStatusRepository->lockedStatus($holdDetails->vehicleId) !== VehicleStatus::InStock->value) {
            return 0;
        }

        $id = $this->entityStore->insert($this->holdTable->type(), [
            'customer_id' => $holdDetails->customerId,
            'note' => $holdDetails->note,
            'placed_by' => $holdDetails->placedBy === 0 ? null : $holdDetails->placedBy,
            'status' => HoldStatus::Active->value,
            'vehicle_id' => $holdDetails->vehicleId,
        ]);
        $this->vehicleStatusRepository->mark($holdDetails->vehicleId, VehicleStatus::OnHold);

        return $id;
    }

    /**
     * @param int $id A hold's id.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return Hold|null The hold with it; null when none has it.
     */
    public function find(int $id): ?Hold
    {
        $row = $this->entityStore->query($this->holdTable->states())->where('id', $id)->first();

        return $row instanceof Row
            ? $this->holdRows->hold($row)
            : null;
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Hold> The active holds, newest first.
     */
    public function active(): array
    {
        return $this->holds(false);
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Hold> The active holds whose 48 hours have passed, oldest first.
     */
    public function expired(): array
    {
        return $this->holds(true);
    }

    /**
     * Marks the hold released while it is active, on the caller's transaction, after the vehicle's row is locked
     * (inventory's `VehicleStatusRepository::lockedStatus()`), as `HoldReleaseRepository` does; the update locks the
     * hold's row until the transaction ends.
     *
     * @param Hold $hold An expired hold.
     *
     * @throws QueryException           When a statement fails.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return bool Whether it was still active and is now released.
     */
    public function markReleased(Hold $hold): bool
    {
        $entityChanges = $this->activeFor($hold->vehicleId)->where('id', $hold->id)->changes();

        return $entityChanges->update(['status' => HoldStatus::Released->value]) === 1;
    }

    /**
     * Call it after the vehicle's row is locked, inside the transaction that sells it.
     *
     * @param int $vehicleId  A vehicle's id.
     * @param int $customerId The customer a sale names.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return bool Whether the vehicle's active hold is for someone else; false when it has none.
     */
    public function heldForAnother(int $vehicleId, int $customerId): bool
    {
        $row = $this->activeFor($vehicleId)->first();

        return $row instanceof Row && $row->intOrNull('customer_id') !== $customerId;
    }

    /**
     * Ends the vehicle's active hold as sold, on the caller's transaction; nothing when it has none.
     *
     * @param int $vehicleId A vehicle just sold.
     *
     * @throws QueryException           When the statement fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     */
    public function endForSale(int $vehicleId): void
    {
        $this->activeFor($vehicleId)->changes()->update(['status' => HoldStatus::Sold->value]);
    }

    /**
     * @param string $vin   A vehicle's VIN.
     * @param int    $limit How many to read, at least 1.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed or the limit is below 1.
     *
     * @return list<HoldTrace> Its newest holds, whatever their status, at most `$limit`, newest first.
     */
    public function forVehicle(string $vin, int $limit): array
    {
        $traces = [];
        $rows = $this->entityStore->query($this->holdTable->states())
            ->where('vin', $vin)
            ->orderByDescending('id')
            ->limit($limit)
            ->rows();

        foreach ($rows as $row) {
            $traces[] = $this->holdRows->trace($row);
        }

        return $traces;
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When an entity type is malformed.
     *
     * @return string What its holds are now: their count, the newest `updated_at` among them and how many active ones
     *                have passed their time, so a hold placed, released, sold or expired changes it.
     */
    public function version(string $vin): string
    {
        $entityQuery = $this->entityStore->query($this->holdTable->type())
            ->with('vehicle_id', 'v')
            ->where('v.vin', $vin);
        $expired = $this->entityStore->query($this->holdTable->states())
            ->where('vin', $vin)
            ->where('status', HoldStatus::Active->value)
            ->where('expired', true);

        $updated = $entityQuery->totals()->max('updated_at') ?? '';

        return $entityQuery->totals()->count() . '.' . $updated . '.' . $expired->totals()->count();
    }

    /**
     * @param bool $expired Whether to keep only those whose time has passed.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<Hold> The active holds, newest first; those expired oldest first.
     */
    private function holds(bool $expired): array
    {
        $entityQuery = $this->entityStore->query($this->holdTable->states())->where('status', HoldStatus::Active->value);
        $entityQuery = $expired
            ? $entityQuery->where('expired', true)->orderBy('id')
            : $entityQuery->orderByDescending('id');
        $holds = [];

        foreach ($entityQuery->rows() as $row) {
            $holds[] = $this->holdRows->hold($row);
        }

        return $holds;
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery Its active holds.
     */
    private function activeFor(int $vehicleId): EntityQuery
    {
        return $this->entityStore->query($this->holdTable->type())
            ->where('vehicle_id', $vehicleId)
            ->where('status', HoldStatus::Active->value);
    }
}
