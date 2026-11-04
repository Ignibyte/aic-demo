<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Repository\VehicleRepository;
use App\Staff\Form\StaffSeals;
use LogicException;
use Override;
use RuntimeException;

/**
 * Inventory's `VehiclePanel` plugin for the vehicles like one (#31): not sold, of its make and body style, nearest its
 * list price. Its version is their count, the newest change among them and its own list price. The generated provider
 * builds it with no arguments.
 */
final readonly class SimilarVehiclesPanel implements VehiclePanel
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
        return 'similar';
    }

    /**
     * @return string Its anchor.
     */
    #[Override]
    public function anchor(): string
    {
        return 'vehicle-similar';
    }

    /**
     * @return string Its heading.
     */
    #[Override]
    public function heading(): string
    {
        return 'Similar vehicles';
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @throws RuntimeException When what it needs cannot be read.
     * @throws LogicException   When an entity type is malformed.
     *
     * @return string What the vehicles like it are now.
     */
    #[Override]
    public function version(EntityStore $entityStore, Vehicle $vehicle): string
    {
        return (new VehicleRepository($entityStore))->similarVersion($vehicle);
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @return array<string, SimilarVehiclesSource> Its table, under the key `table`.
     */
    #[Override]
    public function sources(EntityStore $entityStore, Vehicle $vehicle): array
    {
        return ['table' => new SimilarVehiclesSource(new VehicleRepository($entityStore), $vehicle)];
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
}
