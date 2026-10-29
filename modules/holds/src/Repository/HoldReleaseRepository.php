<?php

declare(strict_types=1);

namespace App\Holds\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Holds\Entity\Hold;
use App\Holds\Service\HoldNotices;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Repository\VehicleStatusRepository;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Releases a hold whose 48 hours have passed (#20), in one `EntityStore::transaction()`: it locks the vehicle, then the
 * hold while it is still active, as a sale and a placement lock them; marks the hold released; puts the vehicle back in
 * stock while it is still on hold, so a status staff changed by hand stands; and queues the notification inside the
 * same transaction, so a hold is released and told of once. The closure is static and calls `releaseLocked()`, the
 * shape the native compiler takes for a closure; nothing else calls it.
 */
final readonly class HoldReleaseRepository
{
    /**
     * @param EntityStore             $entityStore             The entity store.
     * @param HoldRepository          $holdRepository          Marks the hold released.
     * @param VehicleStatusRepository $vehicleStatusRepository Locks the vehicle and changes its status, inventory's.
     */
    public function __construct(
        private EntityStore $entityStore,
        private HoldRepository $holdRepository,
        private VehicleStatusRepository $vehicleStatusRepository,
    ) {
    }

    /**
     * Nothing changes for a hold that ended meanwhile.
     *
     * @param Hold        $hold        An expired hold.
     * @param HoldNotices $holdNotices Queues its notification inside the transaction.
     *
     * @throws Throwable When a statement fails or the notification cannot be queued; the transaction is rolled back
     *                   first.
     */
    public function release(Hold $hold, HoldNotices $holdNotices): void
    {
        $holdReleaseRepository = $this;
        $this->entityStore->transaction(static function () use ($holdReleaseRepository, $hold, $holdNotices): void {
            $holdReleaseRepository->releaseLocked($hold, $holdNotices);
        });
    }

    /**
     * `release()` calls this inside its transaction.
     *
     * @param Hold        $hold        An expired hold.
     * @param HoldNotices $holdNotices Queues its notification.
     *
     * @throws QueryException           When a statement fails or the notification cannot be queued.
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws JsonException            When the notification's payload cannot be encoded.
     */
    public function releaseLocked(Hold $hold, HoldNotices $holdNotices): void
    {
        $vehicleStatus = $this->vehicleStatusRepository->lockedStatus($hold->vehicleId);

        if (!$this->holdRepository->markReleased($hold)) {
            return;
        }

        if ($vehicleStatus === VehicleStatus::OnHold->value) {
            $this->vehicleStatusRepository->mark($hold->vehicleId, VehicleStatus::InStock);
        }

        $holdNotices->released($hold);
    }
}
