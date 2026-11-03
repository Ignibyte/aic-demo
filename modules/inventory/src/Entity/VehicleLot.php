<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * A vehicle on the lot (#29): the day it came, and where on the lot it stands, which a vehicle stored before the
 * location's column came has none of until a bulk move gives it one. Exported, since the sales seed builds vehicles.
 */
final readonly class VehicleLot
{
    /**
     * @param string           $addedOn  The day it came onto the lot, `YYYY-MM-DD`.
     * @param ?VehicleLocation $location Where on the lot it stands; null when it has no location yet.
     */
    public function __construct(
        public string $addedOn,
        public ?VehicleLocation $location = null,
    ) {
    }

    /**
     * @return string Where it stands, as staff read it; a dash when it has no location yet.
     */
    public function place(): string
    {
        return $this->location instanceof VehicleLocation
            ? $this->location->label()
            : '—';
    }
}
