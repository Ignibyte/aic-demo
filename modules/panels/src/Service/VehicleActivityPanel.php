<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Entity\Application\EntityStore;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Leads\Repository\VehicleLeadRepository;
use App\Staff\Form\StaffSeals;
use LogicException;
use Override;
use RuntimeException;

/**
 * Inventory's `VehiclePanel` plugin for a vehicle's leads and holds (#31), each read through its own module's exported
 * repository. Its version joins the leads' (with their moves) and the holds'. The generated provider builds it with no
 * arguments.
 */
final readonly class VehicleActivityPanel implements VehiclePanel
{
    /**
     * @param PanelParts $panelParts Builds its part.
     */
    public function __construct(
        private PanelParts $panelParts = new PanelParts(),
    ) {
    }

    /**
     * @return string Its key and route segment.
     */
    #[Override]
    public function key(): string
    {
        return 'activity';
    }

    /**
     * @return string Its anchor.
     */
    #[Override]
    public function anchor(): string
    {
        return 'vehicle-activity';
    }

    /**
     * @return string Its heading.
     */
    #[Override]
    public function heading(): string
    {
        return 'Leads and holds';
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @throws RuntimeException When what it needs cannot be read.
     * @throws LogicException   When an entity type is malformed.
     *
     * @return string Its leads' version and its holds', `leads/holds`.
     */
    #[Override]
    public function version(EntityStore $entityStore, Vehicle $vehicle): string
    {
        $leads = (new VehicleLeadRepository($entityStore))->version($vehicle->vin, VehicleActivitySource::SHOWN);

        return $leads . '/' . $this->holds($entityStore)->version($vehicle->vin);
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @return array<string, VehicleActivitySource|VehicleHoldsSource> Its leads' table under `leads` and its holds'
     *                                                                 under `holds`.
     */
    #[Override]
    public function sources(EntityStore $entityStore, Vehicle $vehicle): array
    {
        // The leads' table first, then the holds': the panel shows its sources in this order.
        $sources = [];
        $sources['leads'] = new VehicleActivitySource(new VehicleLeadRepository($entityStore), $vehicle);
        $sources['holds'] = new VehicleHoldsSource($this->holds($entityStore), $vehicle);

        return $sources;
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param StaffSeals  $staffSeals  Seals its version.
     * @param Vehicle     $vehicle     The page's vehicle.
     * @param bool        $lazy        Whether to give its skeleton.
     *
     * @throws RuntimeException When its version cannot be read.
     * @throws LogicException   When its key, its anchor or an entity type is malformed, or its props cannot be sealed.
     *
     * @return KeyedComponent Its part.
     */
    #[Override]
    public function part(
        EntityStore $entityStore,
        StaffSeals $staffSeals,
        Vehicle $vehicle,
        bool $lazy,
    ): KeyedComponent {
        return $this->panelParts->part($this, $entityStore, $staffSeals, $vehicle, $lazy);
    }

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @return HoldRepository The holds, holds' repository.
     */
    private function holds(EntityStore $entityStore): HoldRepository
    {
        return new HoldRepository($entityStore, new VehicleStatusRepository($entityStore));
    }
}
