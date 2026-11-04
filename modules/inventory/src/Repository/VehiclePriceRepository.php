<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\PricePoint;
use App\Inventory\Entity\VehicleTable;
use InvalidArgumentException;

use function array_reverse;

/**
 * A vehicle's list price changes (#31), read by its VIN through the entity store: the newest ones, oldest first, for
 * the line the vehicle page draws, and their version, which the panel's poll compares. Exported, so the panels module
 * reads them; inventory's saves write them (`VehicleRecordRepository::updateLocked()`).
 */
final readonly class VehiclePriceRepository
{
    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param VehicleTable $vehicleTable The `vehicles` and `vehicle_prices` entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleTable $vehicleTable = new VehicleTable(),
    ) {
    }

    /**
     * @param string $vin   A vehicle's VIN.
     * @param int    $limit How many changes to read, at least 1.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed or the limit is below 1.
     *
     * @return list<PricePoint> Its newest changes, at most `$limit`, oldest first.
     */
    public function history(string $vin, int $limit): array
    {
        $points = [];
        $rows = $this->changes($vin)->orderByDescending('id')->limit($limit)->rows();

        foreach ($rows as $row) {
            $points[] = new PricePoint($row->string('changed_on'), $row->int('from_price'), $row->int('to_price'));
        }

        return array_reverse($points);
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return string What its changes are now: their count and the newest one's id, `count.id`.
     */
    public function version(string $vin): string
    {
        $count = $this->changes($vin)->totals()->count();
        $newest = $this->changes($vin)->totals()->max('id') ?? '0';

        return $count . '.' . $newest;
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery Its changes, joined to their vehicle by its VIN.
     */
    private function changes(string $vin): EntityQuery
    {
        return $this->entityStore->query($this->vehicleTable->prices())
            ->with('vehicle_id', 'v')
            ->where('v.vin', $vin);
    }
}
