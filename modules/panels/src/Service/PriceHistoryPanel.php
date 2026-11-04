<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Repository\TodayRepository;
use App\Inventory\Repository\VehiclePriceRepository;
use App\Staff\Form\StaffSeals;
use LogicException;
use Override;
use RuntimeException;

/**
 * Inventory's `VehiclePanel` plugin for a vehicle's price history (#31): its list price over time as a line, read from
 * the changes inventory's saves record. Its version is their count and the newest one's id, with the line's first and
 * last days. The generated provider
 * builds it with no arguments.
 */
final readonly class PriceHistoryPanel implements VehiclePanel
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
        return 'price-history';
    }

    /**
     * @return string Its anchor.
     */
    #[Override]
    public function anchor(): string
    {
        return 'vehicle-price-history';
    }

    /**
     * @return string Its heading.
     */
    #[Override]
    public function heading(): string
    {
        return 'Price history';
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @throws RuntimeException When what it needs cannot be read.
     * @throws LogicException   When an entity type is malformed.
     *
     * @return string Its changes' count and newest id, the day the vehicle came and the database's today: the line
     *                starts on the first and ends on the second, so an edit of the day it came or a new day redraws it.
     */
    #[Override]
    public function version(EntityStore $entityStore, Vehicle $vehicle): string
    {
        $changes = (new VehiclePriceRepository($entityStore))->version($vehicle->vin);

        return $changes . '.' . $vehicle->lot->addedOn . '.' . (new TodayRepository($entityStore))->today();
    }

    /**
     * @param EntityStore $entityStore The entity store.
     * @param Vehicle     $vehicle     The page's vehicle.
     *
     * @return array<string, PriceHistorySource> Its line, under the key `line`.
     */
    #[Override]
    public function sources(EntityStore $entityStore, Vehicle $vehicle): array
    {
        $priceHistorySource = new PriceHistorySource(new VehiclePriceRepository($entityStore), new TodayRepository($entityStore), $vehicle);

        return ['line' => $priceHistorySource];
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
